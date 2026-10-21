# Diseño — Señales de calidad y coste del chat

## Contexto técnico

- `App\Models\AiUsage` guarda un turno por fila con `conversation_id`, `post_id`,
  `question`, `prompt`, `answer`, `refused`, `refused_stage`, `model`, `provider`,
  `embedding_model`, `top_k`, `tokens_in`, `tokens_out`, `context_chunks`,
  `latency_ms` y `sources`. `UPDATED_AT` está desactivado.
- `ChatController::recordLatency()` busca con
  `AiUsage::query()->where('ip_address', $ip)->latest('created_at')->first()` y
  actualiza esa fila. `created_at` tiene precisión de segundo, así que el turno
  elegido no es necesariamente el que acaba de terminar.
- `RagService::recordUsage()` crea la fila y no devuelve nada; el identificador
  del turno no sale del servicio.
- `App\Models\AiConversation` tiene `session_id` único, que es el identificador que
  el widget genera y envía en cada petición.
- El panel ya tiene `AiUsageResource`, `AiConversationResource` y
  `AiUsageOverviewWidget`, donde encajan las cifras nuevas.
- `resources/js/chat.js` ya lee el evento SSE final (`refused`, `sources`) y tiene
  botones por mensaje, así que añadir dos más no cambia su estructura.

## Decisiones

- **D1.** `recordUsage()` devuelve el modelo creado y los cuatro caminos de
  `RagService` lo exponen en el resultado como `usage_id`. El controlador
  actualiza la latencia por identificador. Motivo: R3; es el dato que falta para
  no tener que adivinar qué fila corresponde al turno.
- **D2.** `usage_id` viaja al cliente en el JSON de respuesta y en el evento SSE
  final, junto a `sources`. Motivo: el widget necesita saber qué turno valora.
- **D3.** La valoración se envía a un endpoint propio,
  `POST /api/chat/feedback`, con `conversation` (el `session_id` que el widget ya
  maneja), `usage_id` y el valor. Motivo: la valoración llega después de la
  respuesta y no cabe en el ciclo del endpoint de chat.
- **D4.** El endpoint solo acepta la valoración si el turno pertenece a la
  conversación indicada y se registró en la última hora. Lleva `throttle:30,1`.
  Motivo: R2; sin esa comprobación cualquiera podría valorar turnos ajenos
  recorriendo identificadores.
- **D5.** La valoración se guarda en `ai_usage.rating`, entero con signo y
  nulo por defecto: `1` útil, `-1` no útil. Se sobrescribe. Motivo: R1-E2; una
  tabla aparte no aporta nada para un dato por turno.
- **D6.** Los precios viven en `config/rag.php` bajo `pricing`, indexados por
  nombre de modelo, con el precio por millón de tokens de entrada y de salida en
  euros. Motivo: cambian a menudo y no deben estar en el código.
- **D7.** El coste se calcula al registrar el turno y se guarda en
  `ai_usage.cost_eur`, decimal con seis decimales. Un modelo sin precios
  configurados deja el campo nulo. Motivo: R4; calcularlo al vuelo en el panel
  obligaría a recorrer precios históricos que ya habrán cambiado.
- **D8.** Las cifras del panel se calculan con agregaciones sobre `ai_usage` del
  día, sin tabla de resumen. Motivo: el volumen de este sitio no lo justifica.
- **D9.** «Turnos atendidos con cero fragmentos» se obtiene de
  `refused = false AND context_chunks = 0 AND cache_hit = false`. Excluir los
  aciertos de caché es imprescindible: `cachedResult()` y
  `streamCachedResult()` registran `context_chunks = 0` de forma legítima, así
  que sin esa condición la cifra incluiría todos los aciertos de caché y no
  bajaría a cero casi nunca, dejando la señal inútil. Con la exclusión, un turno
  con cero fragmentos señala un fallo de recuperación. Motivo: es la
  comprobación de salud más barata que permiten los datos ya registrados.

## Cambios de datos

- Migración sobre `ai_usage`: `rating` (`tinyInteger`, nulo) y `cost_eur`
  (`decimal(10,6)`, nulo).
- Nueva clave de configuración `rag.pricing`, con los precios por modelo en euros
  por millón de tokens, documentada en `.env.example` solo si se parametriza por
  entorno; por defecto vive en `config/rag.php`.
- El contrato de `POST /api/chat` gana el campo `usage_id` en la respuesta JSON y
  en el evento SSE final.
- Nueva ruta `POST /api/chat/feedback`.

## Seguridad

- El endpoint de valoración no acepta un identificador de turno suelto: exige que
  el turno pertenezca a la conversación indicada y sea reciente, y está limitado
  por frecuencia.
- `usage_id` es un entero autoincremental y por sí solo no revela nada, pero la
  comprobación de pertenencia de D4 es la que impide enumerar turnos ajenos.
- La valoración es anónima: no se guarda ningún dato nuevo del visitante.
- El coste y las series del día solo son visibles en el panel.

## Preguntas abiertas

(ninguna)
