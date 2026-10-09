# Diseño — Caché de respuestas del chat

## Contexto técnico

- Redis es el store de caché en producción, compartido entre procesos.
- `RagService::askForPost()` y `askPostStream()` son los dos únicos caminos que
  usa el chat público; ambos devuelven `answer` (o lo emiten por deltas),
  `sources`, `tokens_in`, `tokens_out`, `context_chunks` y `model`.
- `RagService::recordUsage()` ya acepta un array `extra` que se fusiona con los
  campos de `AiUsage`, de modo que añadir un campo no exige tocar su firma.
- `App\Models\Post` tiene un hook `saving` que prerenderiza el Markdown, y
  `App\Services\PageCacheService` ya establece el patrón de invalidación por
  número de revisión en caché.
- `ChatController::streamAnswer()` emite deltas con `sendSse()` y cierra con un
  evento final; cualquier texto puede emitirse por ese canal.
- La normalización de texto que ya usa el proyecto es `Str::lower(Str::ascii(...))`
  en `RagService::contentTokens()`.

## Decisiones

- **D1.** Clave de caché `chat:ans:{postRevision}:{postId}:{sha1(preguntaNormalizada)}`.
  La normalización es minúsculas, `Str::ascii`, colapso de espacios y recorte, sin
  tocar el orden de las palabras. Motivo: capta las repeticiones reales
  (capitalización, tildes, espacios) sin fundir preguntas distintas.
- **D2.** `postRevision` es un contador por post en caché,
  `chat:rev:post:{id}`, incrementado desde el hook `saving` del post. Al
  incrementarse, todas las claves anteriores dejan de alcanzarse. Motivo: R3, con
  el mismo patrón que `PageCacheService` y sin recorrer claves de Redis.
- **D3.** Periodo de validez de siete días, configurable en
  `rag.cache.answer_ttl` (`RAG_CACHE_ANSWER_TTL`, en segundos). Motivo: acota el
  crecimiento sin perder el efecto, que se concentra en los primeros días tras
  publicar.
- **D4.** Solo se almacenan respuestas **no rechazadas** y de turnos **sin
  historial**. Motivo: un rechazo ya es gratis, y una respuesta que dependa del
  hilo no es reutilizable por otro visitante. Esta restricción es la que permite
  que la spec `chat-memoria-conversacion` añada historial sin invalidar la caché.
- **D5.** La consulta a la caché ocurre antes de resolver la relevancia, de modo
  que un acierto no gasta tampoco la llamada de embeddings. Se asume y se
  documenta que un acierto no vuelve a pasar por las puertas: la respuesta ya se
  consideró relacionada cuando se generó, y un cambio de umbrales solo surte
  efecto en las respuestas nuevas o tras una edición del post.
- **D6.** Un acierto en streaming se emite troceando el texto almacenado en
  fragmentos de unos 24 caracteres, con el mismo evento final de siempre. Motivo:
  R2; que el visitante no distinga una respuesta cacheada de una generada.
- **D7.** El valor almacenado es un array con `answer`, `sources` y `model`. No se
  guardan tokens: un acierto no consume ninguno. Motivo: R4.
- **D8.** El turno se registra igualmente, con `cache_hit = true`, cero tokens y
  las fuentes almacenadas. Motivo: el histórico y las series del panel deben
  seguir contando todas las preguntas, y el porcentaje de aciertos de caché es en
  sí mismo una métrica útil.
- **D9.** El vector de la pregunta se cachea por separado en
  `chat:vec:{sha1(preguntaNormalizada)}` con el mismo periodo de validez, dentro
  de un decorador de `EmbeddingService` registrado en `AppServiceProvider`. La
  clave incluye el modelo de embeddings configurado. Motivo: R5; sirve también
  cuando la respuesta no se puede reutilizar, y el decorador deja
  `OpenAiEmbeddingService` intacto.

## Cambios de datos

- Migración sobre `ai_usage`: `cache_hit` booleano, por defecto `false`.
- Nuevas claves de configuración `rag.cache.answer_ttl` y
  `rag.cache.embedding_ttl`, documentadas en `.env.example`.
- Nuevas claves de caché `chat:ans:*`, `chat:rev:post:*` y `chat:vec:*`.

## Seguridad

- La clave incluye el identificador del post, de modo que una respuesta nunca
  puede servirse en el contexto de otro artículo.
- La caché solo almacena respuestas ya entregadas públicamente; no guarda nada del
  visitante que la originó.
- Las respuestas de documentos marcados como no públicos quedan fuera por
  construcción si la spec `chat-privacidad-registro` se aplica antes: al
  despublicar un documento se incrementa la revisión del post asociado.

## Preguntas abiertas

(ninguna)
