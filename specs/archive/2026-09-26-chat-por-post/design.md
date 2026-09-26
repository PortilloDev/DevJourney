# Diseño — Chat contextual por post

## Contexto técnico

El proyecto ya tiene una pila RAG funcional y reutilizable:

- `App\Services\Rag\RagService` — `ask()`, `askStream()`, `retrieve()` (embed de la
  pregunta → búsqueda vectorial → guardrail `min_similarity` → prompt → LLM) y
  `indexContent()`/`indexDocument()`.
- `App\Http\Controllers\Api\ChatController` — `POST /api/chat`, con límites por IP
  y día, validación y respuesta SSE en streaming.
- `resources/js/chat.js` — componente Alpine `chatWidget` que consume el SSE.
- `App\Services\Vector\{VectorStore,QdrantVectorStore,InMemoryVectorStore}`,
  `App\Services\Embedding\EmbeddingService`, `App\Services\Llm\LlmClient`.
- `config/rag.php`, `App\Models\AiUsage` (uso por IP).
- El widget vive hoy en `resources/views/public/home.blade.php` (línea 111) y su
  destino es `resources/views/public/posts/show.blade.php` (controller
  `PostController::show`).

Payload de indexado: los posts se indexan con `source_type='post'`,
`source_id='post:{id}'`, `title`, `url`; los documentos con `source_type='document'`,
`source_id='doc:{id}'`, `post_id`.

**Gap detectado:** `QdrantVectorStore::search()` acepta el parámetro `$filter`
pero **no lo envía** en el cuerpo de la petición a Qdrant, así que hoy filtrar no
tiene efecto en producción (sí funciona en `InMemoryVectorStore`, que lo aplica
en PHP).

## Decisiones

- **D1.** El alcance al post se implementa con el **filtro por payload de Qdrant**,
  combinando con `should` (OR) el cuerpo del post (`source_id = 'post:{id}'`) y sus
  documentos vinculados (`post_id = {id}`); nunca se post-filtra en PHP. Motivo:
  evita traer chunks ajenos y hace determinista «solo ese post y sus documentos».
- **D2.** Se reutiliza `RagService` añadiendo un camino de recuperación acotado a
  un post; no se crea un segundo motor RAG. Motivo: una única fuente de la lógica
  de recuperación.
- **D3.** El guardrail semántico es el `min_similarity` existente aplicado **solo a
  los chunks del post**: si no hay hits o el mejor score queda por debajo del
  umbral, se devuelve la respuesta predefinida **sin** invocar al LLM. Motivo:
  cumple R3, R4 y R5 con lo que ya existe.
- **D4.** El mensaje predefinido se define **en código** (constante del servicio),
  no en base de datos. Motivo: requisito «predefinida por código».
- **D5.** Concisión: un **system prompt específico** del chat de post más una
  **cota de tokens de salida** en `config/rag.php`
  (`rag.post_chat.max_answer_tokens`, por defecto ~200 tokens). Motivo: garantizar
  el tamaño objetivo de R6.
- **D6.** `POST /api/chat` pasa a **requerir el post** (slug) y el widget lo envía;
  se retira el modo global. Motivo: el chat público es ahora por-post.
- **D7.** Se reutilizan los **límites por IP/día** y el registro `AiUsage` sin
  cambios de esquema. Motivo: no duplicar el control de consumo.
- **D8.** Guardrail en **dos etapas**: (1) comprobación **local** de relevancia
  (solapamiento léxico normalizado entre la pregunta y el texto del post, más
  coincidencia con términos distintivos del **título**); si es **concluyente**, se
  decide sin llamadas externas; (2) si no es concluyente, se usa el **embedding de
  la pregunta** (una sola llamada, sin historial) y el `min_similarity`. Umbrales:
  la pregunta se considera **relacionada** si comparte un término del título o su
  solapamiento es `>= 0.5`; se rechaza sin llamadas si el solapamiento es `<= 0.15`
  y no comparte término del título; en medio → respaldo con embeddings y
  `min_similarity` (por defecto `0.30`). Motivo: minimizar llamadas externas y
  tokens, cumpliendo R8, sin rechazar preguntas relacionadas (incluidas las
  formuladas en otro idioma que reutilizan el término técnico). Enmienda A2.
- **D9 (retirada por enmienda A1).** El indexado NO es al vuelo: se apoya en el
  hook de indexado al publicar ya existente en `AppServiceProvider` +
  `IndexContentJob` (ver enmienda A1 en `spec.md`). Para poder testear y para no
  depender de claves en el arranque, la comprobación de configuración se mueve al
  propio job (si no hay claves, no hace nada).

## Cambios de datos

- `ensureCollection()` en `QdrantVectorStore` crea **payload index** para
  `source_id` y `post_id` (campos usados en filtros).
- Sin cambios de esquema en MySQL (ver D7).
- El alta de contenido dispara el indexado al publicar (hook existente en
  `AppServiceProvider` + `IndexContentJob`). Los posts ya publicados antes de esta
  feature se reindexan re-guardándolos o con un comando de backfill (fuera del
  alcance de esta spec).

Filtro de recuperación (forma Qdrant, OR):

```json
{ "should": [
  { "key": "source_id", "match": { "value": "post:{id}" } },
  { "key": "post_id",   "match": { "value": "{id}" } }
] }
```

## Seguridad

- El endpoint valida que el `post` recibido exista y esté **publicado** (404/422 en
  caso contrario); no se acepta ningún filtro arbitrario del cliente.
- Se mantienen la validación de longitud de la pregunta, los límites por IP/día y
  el registro de uso.
- El indexado solo procesa **contenido publicado** y accesible públicamente.

## Preguntas abiertas

(ninguna)
