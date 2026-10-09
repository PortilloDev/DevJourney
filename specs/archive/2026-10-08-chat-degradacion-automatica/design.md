# Diseño — Degradación automática y guardas de configuración

## Contexto técnico

- `App\Services\SiteSettingService` guarda los ajustes en `site_settings` con
  `Cache::rememberForever` y los olvida en `flush()`, que ya llama `set()`.
  Expone `chatApiBlocked()`, `chatHidden()` y `chatVisible()`. Además,
  `SiteSetting::booted()` llama a `flush()` en `saved`/`deleted`, y
  `AppServiceProvider::boot()` incluye `SiteSetting` en el bucle que llama a
  `PageCacheService::bump()`, así que cambiar un ajuste ya invalida la caché
  pública (base de la enmienda A1).
- `App\Http\Middleware\CachePublicPages` sirve HTML completo desde caché para las
  GET públicas y se invalida mediante `App\Services\PageCacheService::bump()`,
  que incrementa la revisión `page_cache.revision` usada al construir la clave.
  Hoy solo lo llaman los cambios de contenido.
- `resources/views/public/posts/show.blade.php` decide mostrar el widget con
  `filled(config('rag.api_key')) && $settings->chatVisible()`.
- `App\Providers\AppServiceProvider` resuelve `VectorStore` con
  `config('rag.qdrant.enabled') ? new QdrantVectorStore : new InMemoryVectorStore`,
  sin distinguir entorno.
- `QdrantVectorStore::ensureCollection()` crea la colección solo si no existe, y
  después crea los índices de payload de `source_id` y `post_id`. No compara el
  tamaño de vector de una colección ya existente con el configurado.
- `RagService::basePayload()` escribe `source_type`, `source_id`, `title` y `url`;
  `observability()` ya guarda `embedding_model` en `ai_usage`, pero no en el
  payload del punto.
- El panel tiene `AiUsageOverviewWidget`, donde encaja el estado del circuito.

## Decisiones

- **D1.** (retirada por la enmienda A1) La invalidación de la caché pública al
  cambiar un ajuste ya existe: `SiteSetting` está en el bucle de bump de
  `AppServiceProvider` y su hook `saved` llama a `flush()`. No se modifica
  `SiteSettingService::flush()`: R1 se retira por estar ya satisfecho, y añadir el
  bump ahí produciría un bump duplicado.
- **D2.** El circuito es un contador de fallos consecutivos en caché, clave
  `ai:circuit:failures`, más una marca de apertura `ai:circuit:opened_at` con TTL
  igual al periodo de enfriamiento. Configuración:
  `rag.circuit.threshold` (`RAG_CIRCUIT_THRESHOLD`, por defecto `5`) y
  `rag.circuit.cooldown_seconds` (`RAG_CIRCUIT_COOLDOWN`, por defecto `300`). La
  apertura se evalúa de forma estricta: el circuito abre cuando los fallos
  consecutivos **superan** el umbral (`failures > threshold`, enmienda A2).
  Motivo: no hace falta una máquina de estados completa; con Redis compartido
  entre procesos basta para el volumen de este sitio.
- **D3.** El circuito se envuelve alrededor del cliente LLM mediante un decorador
  que implementa `LlmClient` y se registra en `AppServiceProvider`. Un turno
  correcto pone el contador a cero; una excepción lo incrementa. Con el circuito
  abierto, el decorador lanza `RuntimeException` sin llamar al cliente real, que
  el controlador ya traduce a 503 `service_error`. Motivo: respeta la interfaz
  existente y no toca `RagService`.
- **D4.** El circuito cuenta los fallos del proveedor, no los rechazos por
  relevancia ni los errores de validación. Motivo: mide disponibilidad, no uso.
- **D5.** La resolución de `VectorStore` lanza una excepción de configuración
  cuando Qdrant está desactivado y el entorno no es `testing`. `InMemoryVectorStore`
  queda reservado a las pruebas. Motivo: R4; el modo degradado actual es
  indistinguible de «el chat funciona pero nada está indexado».
- **D6.** `ensureCollection()` consulta la colección existente y compara el tamaño
  de vector declarado con `embedding->dimensions()`; si difieren, lanza una
  excepción que nombra ambos valores. La comprobación va antes de cualquier
  `upsert`. Motivo: R5; es el único momento en que el desajuste es detectable
  antes de corromper el índice.
- **D7.** `basePayload()` y el payload de documentos incluyen
  `embedding_model` con el valor de `config('rag.embeddings.model')`. Motivo: R6;
  permite detectar puntos generados con un modelo distinto y decidir si toca
  reindexar.
- **D8.** El estado del circuito se muestra en `AiUsageOverviewWidget` como una
  estadística más. Motivo: no crear una página de panel nueva para un dato.

## Cambios de datos

- Sin cambios de esquema en MySQL.
- Nuevas claves de configuración `rag.circuit.threshold` y
  `rag.circuit.cooldown_seconds`, documentadas en `.env.example`.
- Nuevas claves de caché `ai:circuit:failures` y `ai:circuit:opened_at`.
- El payload de los puntos del almacén vectorial gana el campo `embedding_model`.
  Los puntos ya indexados no lo tienen: se rellenan al reindexar, y su ausencia
  no rompe la recuperación.

## Seguridad

- D5 convierte un fallo silencioso en un fallo ruidoso en el arranque, que es el
  comportamiento correcto para un error de configuración de producción.
- El estado del circuito solo es visible en el panel, protegido por la lista
  blanca de correo de Filament; el visitante sigue recibiendo el mismo mensaje
  genérico de servicio no disponible.
- El circuito abierto reduce el gasto durante una caída del proveedor, además de
  la latencia.

## Preguntas abiertas

(ninguna)
