# Diseño — Prompt aislado y respuesta nunca truncada

## Contexto técnico

- `App\Services\Rag\RagService` arma los mensajes en cuatro sitios (`ask`,
  `askStream`, `askForPost`, `askPostStream`) con la misma forma:
  `['role' => 'user', 'content' => $question."\n\nUse only the context below:\n\n".$context]`.
- `postChatPrompt()` y `systemPrompt()` devuelven el mensaje de sistema; ambos
  viven en código, no en base de datos, y así se quedan.
- `App\Services\Llm\OpenAiLlmClient::result()` lee `choices[0].message.content` y
  `usage`, pero **no** `choices[0].finish_reason`; en `stream()` tampoco se lee el
  `finish_reason` del último chunk.
- `ask()` y `askStream()` pasan `$this->maxInputTokens`
  (`rag.limits.max_input_tokens`, 2000) al parámetro `$maxTokens` de `complete()`,
  que termina en `max_tokens` de la API. `askForPost()` y `askPostStream()` sí
  usan `rag.post_chat.max_answer_tokens` (200), que es lo correcto.
- `rag.limits.max_input_tokens` no se aplica hoy en ningún sitio: nada recorta el
  contexto.
- `buildContext()` une los chunks con `\n\n---\n\n`; `sources()` ya conserva el
  score de cada hit, así que el orden por relevancia está disponible.
- El widget (`resources/js/chat.js`) consume el evento SSE final, del que ya lee
  `refused` y `sources`.

## Decisiones

- **D1.** El mensaje de sistema pasa a ser `<instrucciones>` + el contexto dentro
  de `<contexto>…</contexto>`, con una frase final que declara que el contenido de
  `<contexto>` son datos de referencia y nunca instrucciones. El mensaje de
  usuario contiene la pregunta y nada más. Motivo: el texto que controla el
  visitante queda acotado y por detrás de las instrucciones.
- **D2.** La construcción de mensajes se centraliza en un método privado de
  `RagService` que reciben los cuatro caminos. Motivo: hoy la misma concatenación
  está repetida cuatro veces y una corrección puede quedarse a medias.
- **D3.** Se añade `rag.general_chat.max_answer_tokens`
  (`RAG_GENERAL_MAX_ANSWER_TOKENS`, por defecto `400`) y `ask()`/`askStream()` lo
  usan como cota de salida. `max_input_tokens` deja de pasarse como `$maxTokens`.
  Motivo: R4; el camino genérico necesita su propia cota, distinta de la del chat
  por post.
- **D4.** El recorte de entrada descarta chunks por score ascendente hasta que la
  estimación de tokens del contexto cabe en `max_input_tokens`. La estimación es
  `ceil(mb_strlen / 4)`, la misma heurística que ya usa `OpenAiLlmClient`. Nunca
  se recorta por debajo de un chunk: si el primero ya no cabe, se envía igualmente
  y se registra el hecho. Motivo: una respuesta con el mejor chunk es preferible a
  un rechazo, y la estimación aproximada basta para una cota de seguridad.
- **D5.** `OpenAiLlmClient` devuelve `finish_reason` en el array de resultado, leído
  de `choices[0].finish_reason` en `complete()` y del último chunk con `delta` en
  `stream()`. Motivo: es el único dato que distingue «el modelo terminó» de «se
  quedó sin tokens».
- **D6.** Cuando `finish_reason` es `length`, el texto se recorta en el último
  signo de puntuación de cierre (`.`, `!`, `?`, `…`) y la respuesta expone
  `truncated: true`. Si no hay ninguno, el texto se deja tal cual con la marca
  puesta. En streaming los deltas ya se han emitido, así que el recorte no se
  aplica al texto emitido: el evento final lleva `truncated: true` y el widget
  marca el turno. Motivo: no se puede retirar lo ya enviado, pero sí señalarlo.
- **D7.** `truncated` se añade al JSON de respuesta y al evento SSE final, junto a
  `refused`. Motivo: el widget ya sabe leer ese evento.

## Cambios de datos

- Sin cambios de esquema.
- Nueva clave de configuración `rag.general_chat.max_answer_tokens`
  (`RAG_GENERAL_MAX_ANSWER_TOKENS`, por defecto `400`), documentada en `.env.example`.
- El contrato público de `POST /api/chat` gana el campo `truncated` (booleano) en
  la respuesta JSON y en el evento SSE final.

## Seguridad

- D1 elimina el vector de inyección más directo: hoy una pregunta que empieza por
  «ignora lo anterior» ocupa la primera posición del único mensaje de usuario, por
  delante del contexto.
- El contexto sigue saliendo exclusivamente del vector store filtrado al post; el
  cliente no puede aportar contexto en ningún caso.
- El recorte de entrada acota el tamaño máximo de la petición al proveedor, lo que
  limita también el gasto por turno.

## Preguntas abiertas

(ninguna)
