# Diseño — Privacidad del registro del chat

## Contexto técnico

- `ai_usage.ip_address` y `ai_conversations.ip_address` son `string(64)` indexados
  y se escriben en claro desde `RagService::recordUsage()` y
  `ChatController::__invoke()`.
- La migración `2026_08_25_000001_encrypt_visit_personal_data` establece el
  precedente del proyecto para los datos personales de `visits`.
- Las cuotas viven en `App\Services\Rag\ChatQuota` sobre `RateLimiter`:
  `visitorKey()` es `ai:req:{ip}:{fecha}` y se compara con igualdad exacta;
  `RagService` consume la cuota con `ChatQuota::consume($ip)` justo antes de la
  llamada al modelo. El presupuesto de tokens consulta
  `AiUsage::query()->where('ip_address', $ip)` (suma SQL de
  `tokens_in + tokens_out`).
- `ChatController::recordLatency(?int $usageId, ...)` localiza el turno por su id
  (ya no por IP).
- `App\Models\Document` tiene `post_id`, `status` e `indexed_at`, y se indexa en
  `RagService::indexDocument()` con payload `source_type='document'`,
  `source_id='doc:{id}'`, `post_id`. `App\Jobs\IndexDocumentJob` hace el trabajo
  y `RagService::deleteDocument()` ya sabe borrar sus puntos.
- El filtro del chat por post es
  `should: [source_id = 'post:{id}', post_id = {id}]`, de modo que **todo**
  documento con ese `post_id` entra en el contexto.
- `ai_usage.prompt` es `longText` y guarda el JSON completo de los mensajes,
  contexto incluido. `ai_usage.sources` ya guarda título, url, encabezado y score
  de cada fragmento, pero no su identificador en el almacén.
- `RagService::pointId()` genera el identificador de cada punto de forma
  determinista a partir de tipo, clave e índice de chunk.

## Decisiones

- **D1.** La IP se almacena como `hash_hmac('sha256', $ip, config('app.key'))`
  truncado a 64 caracteres, no cifrada. Motivo: las cuotas necesitan igualdad
  exacta y una búsqueda indexada; un valor cifrado con IV aleatorio no permite
  ninguna de las dos cosas, y nunca hace falta recuperar la IP original.
- **D2.** La derivación vive en un único método (un helper de `RagService` o un
  pequeño `IpHasher`) y se aplica **una sola vez en el controlador**, justo tras
  obtener la IP: el valor derivado es el que fluye a `ChatQuota` (clave de cuota),
  a la fila de `ai_conversations` y a `RagService` (registro y consumo de cuota).
  Motivo: que no queden dos representaciones de la misma IP y que la cuota y el
  histórico se calculen sobre el mismo valor.
- **D3.** Las filas existentes se migran con una migración que recalcula el valor
  derivado de cada `ip_address`. Las que no se puedan derivar se dejan como están
  y la poda a 30 días las retira. Motivo: no perder el histórico reciente ni
  bloquear el despliegue.
- **D4.** `documents` gana una columna booleana `is_public` con valor por defecto
  `true`. Motivo: todos los documentos actuales se subieron para ser consultables
  desde el chat, así que un valor por defecto `false` cambiaría el comportamiento
  en producción sin que nadie lo pida; la columna existe para poder marcar
  excepciones.
- **D5.** Un documento marcado como no público se excluye en dos puntos: no se
  indexa, y al pasar a no público se borran sus puntos con
  `RagService::deleteDocument()`, que ya existe. No se añade ningún filtro extra
  en la recuperación. Motivo: lo que no está en el índice no se puede recuperar;
  filtrar en consulta dejaría el contenido almacenado en Qdrant.
- **D6.** `sources` gana el identificador del punto de cada fragmento, y el campo
  `prompt` pasa a guardar únicamente el mensaje de sistema y la pregunta, sin el
  bloque de contexto. El contexto se reconstruye desde los identificadores.
  Motivo: R4; hoy el mismo texto está tres veces (en el post, en el índice y en
  cada turno que lo usó).
- **D7.** El visor de conversaciones del panel muestra las fuentes con su
  identificador, de modo que el administrador sigue pudiendo auditar qué
  fragmentos se usaron. Motivo: no perder la capacidad de auditoría que motivó la
  spec archivada `chat-logs-conversacion`.

## Cambios de datos

- Migración: `documents.is_public` booleano, por defecto `true`, con índice si se
  usa en consultas de listado.
- Migración de datos: recálculo de `ai_usage.ip_address` y
  `ai_conversations.ip_address` al valor derivado.
- `ai_usage.sources` cambia de forma: cada entrada incluye `point_id` junto a
  `title`, `url`, `heading` y `score`. Las filas antiguas sin `point_id` se siguen
  leyendo.
- `ai_usage.prompt` deja de contener el bloque de contexto.

## Seguridad

- La derivación usa `APP_KEY` como sal, de modo que un volcado de la base de datos
  sin la clave no permite recuperar ni comparar direcciones IP con un diccionario.
- Rotar `APP_KEY` invalida la correspondencia: las cuotas del día en curso se
  reinician. Se documenta en `README.md` junto al resto de notas de despliegue.
- R3 cierra el único camino por el que contenido no destinado al público podía
  llegar a un visitante a través del chat.
- La pregunta del visitante se sigue almacenando en claro, por necesidad de
  auditoría, y sigue sujeta a la retención de 30 días.

## Preguntas abiertas

(ninguna)
