# Diseño — Relevancia del chat correctamente calibrada

## Contexto técnico

La resolución de relevancia vive entera en `App\Services\Rag\RagService`:

- `resolvePostContext()` — orquesta las dos etapas: comprobación local y, si no
  es concluyente, embeddings más `min_similarity`.
- `contentTokens()` — normaliza con `Str::ascii`, parte por caracteres no
  alfanuméricos, descarta tokens de **menos de 4 caracteres** y aplica una lista
  de stopwords mixta español/inglés.
- `lexicalOverlap()` — fracción de los tokens de contenido de la pregunta que
  aparecen en el texto del post.
- `sharesTitleTerm` — hoy pone `clearlyRelated = true`, lo que hace que la
  comprobación `bestScore($hits) < $this->minSimilarity` **no se evalúe**.

Umbrales actuales: `rag.post_chat.unrelated_threshold` (0.15),
`rag.post_chat.related_threshold` (0.5) y `rag.retrieval.min_similarity`, que
`config/rag.php` declara como 0.30 mientras el constructor de `RagService` pasa
0.35 como valor por defecto de `config()`.

El comportamiento de rescate por término del título procede de la enmienda A2 de
la spec archivada `chat-por-post`: resolvía preguntas relacionadas formuladas en
otro idioma cuyo score quedaba justo por debajo del umbral. Esa necesidad sigue
siendo válida y esta spec la conserva; lo que cambia es el mecanismo.

## Decisiones

- **D1.** La longitud mínima de token en `contentTokens()` baja de 4 a 2
  caracteres. Motivo: las siglas de dos y tres letras son los términos **más**
  distintivos del dominio del blog; filtrarlas por longitud es descartar
  exactamente la señal que se quiere medir.
- **D2.** Para compensar el ruido que introduce D1, la lista de stopwords se
  amplía con las palabras funcionales cortas de español e inglés que antes
  caían por longitud: `de`, `la`, `el`, `los`, `las`, `un`, `una`, `al`, `del`,
  `en`, `es`, `se`, `su`, `por`, `con`, `sin`, `mas`, `muy`, `qué`→`que`, `cual`,
  `the`, `of`, `to`, `in`, `is`, `it`, `on`, `at`, `by`, `or`, `as`, `an`, `be`,
  `do`, `if`, `my`, `we`. La lista pasa a una constante de clase documentada.
  Motivo: mantener el solapamiento como medida de términos de contenido.
- **D3.** `sharesTitleTerm` deja de ser un bypass y pasa a **seleccionar el
  umbral**: si la pregunta comparte un término distintivo del título o su
  solapamiento alcanza `related_threshold`, se aplica
  `rag.retrieval.related_min_similarity`; en caso contrario se aplica
  `rag.retrieval.min_similarity`. La comprobación de score se ejecuta **siempre**.
  Motivo: conserva el rescate de la enmienda A2 sin dejar abierto un camino por el
  que una pregunta ajena llega al modelo.
- **D4.** Se añade `rag.retrieval.related_min_similarity`, con variable de entorno
  `RAG_RELATED_MIN_SIMILARITY` y valor por defecto `0.20`. Motivo: un umbral
  permisivo pero real; 0.20 queda por debajo de los scores de los casos
  multiidioma que motivaron A2 y por encima del ruido.
- **D5.** `RagService` lee la configuración sin segundo valor por defecto:
  `(float) config('rag.retrieval.min_similarity')`. Los valores por defecto viven
  solo en `config/rag.php`. Motivo: hoy hay dos fuentes de verdad (0.30 y 0.35) y
  cuál gana depende de si la clave existe.
- **D6.** La etapa local conserva su forma actual: si la pregunta tiene tokens de
  contenido, su solapamiento es menor o igual a `unrelated_threshold` y no
  comparte término del título, se rechaza sin ninguna llamada externa. Motivo: es
  el acierto del diseño vigente y R1 solo cambia qué cuenta como token.

## Cambios de datos

- Sin cambios de esquema.
- Nueva clave de configuración `rag.retrieval.related_min_similarity`
  (`RAG_RELATED_MIN_SIMILARITY`, por defecto `0.20`), documentada en `.env.example`.

## Seguridad

- R2 cierra el camino por el que una pregunta fuera de alcance alcanzaba al modelo
  incluyendo una palabra del título. Tras esta spec, el prompt de sistema deja de
  ser la única defensa frente a ese caso.
- No se añaden entradas controladas por el cliente: los umbrales son de
  configuración del servidor.

## Preguntas abiertas

(ninguna)
