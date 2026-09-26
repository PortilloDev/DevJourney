# Diseño — Chat por post — mejoras

## Contexto técnico

Piezas existentes (v1 archivada): `App\Services\Rag\RagService` (`indexContent`,
`indexChunks`, `modelUrl`), `resources/views/components/chat-widget.blade.php`,
`resources/js/chat.js`, `App\Http\Controllers\Api\ChatController`. Los payloads de
Qdrant guardan `url` con `route(...)` **absoluto** en el momento de indexar.

## Decisiones

- **D1.** El payload `url` pasa a ser una **ruta relativa** (`route(..., absolute: false)`,
  p. ej. `/journal/slug`). Motivo: la fuente se resuelve contra el origen actual, así
  que funciona en cualquier host/puerto sin reindexar por entorno (R1).
- **D2.** El historial vive **solo en memoria** (estado de Alpine), sin persistencia.
  Motivo: requisito del usuario y evita datos por-usuario en HTML cacheado.
- **D3.** El botón de copiar usa `navigator.clipboard.writeText` con feedback visual
  breve; se implementa como acción Alpine en `chat.js`.
- **D4.** El contenido ya indexado debe **reindexarse** una vez para que sus payloads
  pasen a rutas relativas. Motivo: los payloads antiguos conservan la URL absoluta.

## Cambios de datos

- Sin cambios de esquema.
- Los payloads de Qdrant cambian de forma (URL absoluta → ruta relativa): requiere
  reindexar el contenido existente (posts, challenges, projects y documentos).

## Seguridad

- Las rutas relativas apuntan al mismo origen, sin riesgo añadido.
- Sin datos sensibles; el historial no se persiste.

## Preguntas abiertas

(ninguna)
