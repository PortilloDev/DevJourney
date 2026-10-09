# Diseño — Retos relacionados con cada post

## Contexto técnico

- `App\Models\Challenge` tiene `scopePublished()` y `getRouteKeyName()`, pero
  ninguna relación con `Post`. `App\Models\Post` solo declara `category()`.
- La tabla `taggables` da etiquetas polimórficas a ambos modelos: es el único
  vínculo que existe hoy entre un post y un reto.
- `RagService::indexContent()` trata `Post`, `Challenge` y `Project`;
  `sourceType()` y `modelUrl()` resuelven su tipo y su ruta. Los retos se indexan
  con `source_type = 'challenge'`.
- `RagService::postFilter()` filtra con `should: [source_id = 'post:{id}',
  post_id = {id}]`, de modo que ningún chunk de reto es recuperable desde el chat.
  `RagService::ask()`, el único camino sin filtro, no lo usa ninguna ruta.
- El evento SSE final ya transporta `sources`, y el widget ya pinta esas fuentes
  como chips con enlace: la pieza de interfaz para R3 existe.
- `resources/views/public/posts/show.blade.php` es donde vive el widget y donde
  encaja la sección de R2.

## Decisiones

- **D1.** La relación es una tabla pivote `challenge_post` con clave primaria
  compuesta y borrado en cascada, gestionada desde el panel con un relation
  manager de Filament sobre el post. Motivo: un post puede llevar varios retos y
  un reto puede aparecer en varios posts; una columna `post_id` en `challenges` no
  lo permitiría.
- **D2.** Los retos relacionados se resuelven **en el servidor** a partir de la
  relación, nunca del texto de la respuesta. El modelo no recibe la lista de retos
  ni se le pide que los mencione, y el prompt de sistema no cambia. Motivo: R4; en
  cuanto el modelo escribe títulos de retos en prosa libre, antes o después
  inventa uno con su enlace roto, y evitarlo exige validaciones que esta solución
  no necesita.
- **D3.** Los retos acompañan a la respuesta en un campo `related` del evento SSE
  final y del JSON, con título y URL generadas por `route()`, igual que ya se hace
  con `sources`. Solo se envían en respuestas no rechazadas. Motivo: R3 y R3-E2,
  reutilizando el contrato y el componente que ya existen.
- **D4.** El selector de candidatos del panel propone primero los retos publicados
  que comparten etiquetas con el post, sin limitar la selección a ellos. Motivo:
  R5; las etiquetas son una buena primera aproximación pero el criterio final es
  del administrador.
- **D5.** Se añade `rag.indexing.source_types`
  (`RAG_INDEXING_SOURCE_TYPES`, por defecto `post,document`) y `IndexContentJob`
  no hace nada para un tipo que no esté en la lista. Motivo: R6; deja de pagarse
  por embeddings inalcanzables sin cerrar la puerta a un futuro chat global, que
  solo tendría que añadir el tipo a la lista.
- **D6.** Los puntos de retos ya indexados se retiran con un comando de limpieza
  puntual que recorre los retos publicados y llama a `deleteContent()`, que ya
  existe. Motivo: liberar el índice de contenido que nadie consulta, sin una
  migración de datos.

## Cambios de datos

- Migración: tabla pivote `challenge_post` con `post_id` y `challenge_id`, índice
  único sobre el par y borrado en cascada.
- Nueva clave de configuración `rag.indexing.source_types`, documentada en
  `.env.example`.
- Se retiran del almacén vectorial los puntos con `source_type = 'challenge'`.
- El contrato de `POST /api/chat` gana el campo `related` en la respuesta JSON y
  en el evento SSE final.

## Seguridad

- Solo se muestran retos **publicados**: la consulta aplica `scopePublished()`,
  igual que el resto del sitio.
- Los enlaces los genera `route()` a partir del identificador del reto, de modo
  que no existe ningún camino por el que una URL llegue al visitante desde texto
  generado.
- La relación solo se edita desde el panel, protegido por la lista blanca de
  correo de Filament.

## Preguntas abiertas

(ninguna)
