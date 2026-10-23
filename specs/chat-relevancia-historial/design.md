# Diseño — Relevancia por cobertura e historial limpio

## Contexto técnico

- `RagService::resolvePostContext()` hace dos etapas: rechazo léxico local
  (`unrelated_threshold`) y, si no es concluyente, embeddings con
  `related_min_similarity` o `min_similarity` según `sharesTitleTerm`.
- `contentTokens()` filtra tokens de menos de 2 caracteres y una lista de
  stopwords; `lexicalOverlap()` mide la fracción de tokens de la pregunta que
  aparecen en el texto del post.
- `conversationHistory()` devuelve los últimos `rag.conversation.max_turns`
  turnos con respuesta, sin filtrar por `refused` ni por respuestas vacías.
- `trimToInputBudget()` recorta chunks por score hasta `rag.limits.max_input_tokens`,
  pero no descuenta el sistema, el historial ni la pregunta.
- `storedPrompt()` guarda solo sistema + pregunta. El panel lo etiqueta como
  «Prompt sent to the LLM», lo que oculta el contexto y el historial reales.
- `askForPost()` y `askPostStream()` son los dos caminos públicos del chat por
  post; ambos comparten `postMessages()` y `resolvePostContext()`.

## Decisiones

- **D1.** La puerta de cobertura solo se aplica cuando `sharesTitleTerm` es
  verdadero, que es el camino que hoy permite el rescate. Se calcula una
  cobertura de tokens de contenido contra el vocabulario del post (título,
  extracto, cuerpo y etiquetas) más un léxico de dominio, ignorando términos
  genéricos. Dos etapas: antes de embeddings, cobertura ≤ 0.34 rechaza sin
  llamadas; después de embeddings, cobertura ≤ 0.5 y mejor score <
  `rag.retrieval.confident_similarity` rechaza sin modelo de chat. Motivo: R1;
  corta el caso mixto sin romper el rescate multilingüe de R3 de
  `chat-relevancia-calibrada`.
- **D2.** El prompt pide al modelo responder exactamente `NO_ANSWER` cuando el
  contexto no contiene la respuesta. El servicio lo detecta, marca el turno como
  rechazado (`refused_stage='model'`), entrega `UNRELATED_MESSAGE` y no lo cachea.
  En streaming se bufferizan los primeros caracteres para no pintar el sentinel;
  si al final el contenido es `NO_ANSWER`, se emite el mensaje predefinido.
  Motivo: R3; convierte una respuesta vacía en un rechazo consistente.
- **D3.** El historial excluye `refused=true`, `UNRELATED_MESSAGE` y respuestas
  equivalentes a «no lo sé» (incluidas las antiguas). Se acota por
  `rag.conversation.max_tokens`, descartando primero los turnos más antiguos.
  Motivo: R2 y R4; evita que un rechazo contamine los turnos siguientes.
- **D4.** El presupuesto del contexto se calcula como
  `max_input_tokens - sistema - historial - pregunta`, con un mínimo de un chunk.
  `resolvePostContext()` recibe ese presupuesto y `trimToInputBudget()` lo usa.
  Motivo: R4; el límite debe cubrir toda la entrada, no solo los chunks.
- **D5.** `ai_usage.prompt` guarda los mensajes completos enviados al modelo
  cuando `rag.log_full_prompt` está activo (por defecto `true`). Si se desactiva,
  se guarda el formato anterior (sistema + pregunta). En rechazos locales se
  guarda sistema + historial + pregunta, sin contexto, porque la recuperación no
  llegó a ejecutarse. Motivo: R5; es la única forma de auditar el contexto y el
  historial reales. Supersede R4/D6 de `chat-privacidad-registro` para este campo.
- **D6.** El visor de conversaciones muestra el prompt como JSON formateado (o
  transcripción) y mantiene `sources` para la trazabilidad de los fragmentos.
  Motivo: R5; el administrador puede ver exactamente qué recibió el modelo.

## Cambios de datos

- Sin cambios de esquema: `ai_usage.prompt` ya es `longText`.
- Nuevas claves de configuración:
  - `rag.retrieval.confident_similarity` (`RAG_CONFIDENT_SIMILARITY`, `0.65`).
  - `rag.conversation.max_tokens` (`RAG_CONVERSATION_MAX_TOKENS`, `600`).
  - `rag.log_full_prompt` (`RAG_LOG_FULL_PROMPT`, `true`).
- El contenido de `ai_usage.prompt` pasa de sistema + pregunta a los mensajes
  completos cuando el flag está activo.

## Seguridad

- Guardar el prompt completo duplica el contexto en `ai_usage`. Se acepta porque
  el acceso al panel está limitado por la lista blanca de Filament, la retención
  es de 30 días y el flag `RAG_LOG_FULL_PROMPT` permite desactivarlo.
- La pregunta y la respuesta ya se guardaban en claro; el cambio no amplía el
  tipo de dato personal almacenado, solo el contexto técnico del turno.
- Los rechazos locales guardan el prompt sin contexto para no duplicar chunks que
  no se llegaron a usar.

## Preguntas abiertas

(ninguna)
