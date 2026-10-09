# Diseño — Memoria de la conversación en el chat

## Contexto técnico

- `RagService::askForPost()` y `askPostStream()` construyen siempre dos mensajes:
  el de sistema y el de la pregunta. No existe ningún punto donde entren turnos
  anteriores.
- `resolvePostContext()` decide la relevancia a partir del texto de la pregunta;
  una pregunta de seguimiento («¿y eso cómo se prueba?») conserva un único token
  de contenido y cae del lado del rechazo local.
- `App\Models\AiConversation::turns()` ya devuelve los turnos ordenados por fecha,
  con `question` y `answer`, que es exactamente el historial necesario.
- `ChatController` resuelve la conversación con
  `AiConversation::firstOrCreate(['session_id' => $sessionId])` sobre un valor que
  llega del cliente: hoy cualquiera puede escribir en una conversación ajena si
  acierta el identificador.
- `resources/js/chat.js` genera el identificador con `crypto.randomUUID()` en cada
  carga y mantiene `messages` solo en memoria del componente.
- `config/rag.php` separa ya el endpoint y el modelo de chat de los de embeddings,
  de modo que añadir un tercer modelo encaja sin cambiar la forma del fichero.

## Decisiones

- **D1.** La reformulación es una llamada propia al modelo configurado en
  `rag.rewrite.model`, que por defecto toma `rag.chat_model`, con temperatura 0 y
  una cota de 60 tokens de salida. Recibe los últimos turnos y la pregunta, y
  devuelve una única pregunta autónoma. Motivo: es el patrón estándar de RAG
  conversacional y el único que resuelve el problema completo; añadir historial sin
  reformular dejaría un chat que entiende el seguimiento pero lo rechaza antes de
  responderlo.
- **D2.** La reformulación solo ocurre si la conversación tiene turnos anteriores.
  Motivo: R2; la primera pregunta ya es autónoma y pagar una llamada por ella no
  aporta nada.
- **D3.** La relevancia, la recuperación y la caché se resuelven sobre la pregunta
  **reformulada**. El histórico guarda las dos: `question` conserva lo que escribió
  el visitante y una columna nueva guarda la reformulación. Motivo: auditar sin
  perder el texto original.
- **D4.** Si la reformulación falla o devuelve algo vacío, se continúa con la
  pregunta original. Motivo: R4; la memoria es una mejora, no una dependencia
  dura.
- **D5.** El historial va a la petición final como pares de mensajes `user` y
  `assistant` entre el mensaje de sistema y la pregunta actual. El máximo es
  `rag.conversation.max_turns` (`RAG_CONVERSATION_MAX_TURNS`, por defecto `3`).
  La ubicación del contexto queda **fuera** de esta spec: mientras
  `chat-prompt-aislado` no se aplique, el contexto sigue en el mensaje de usuario,
  como en el código vigente. Motivo: R3; tres turnos cubren el seguimiento real
  sin que la entrada crezca sin control.
- **D6.** Los tokens de la reformulación se suman a los tokens de entrada del
  turno. Motivo: el presupuesto diario debe reflejar el coste real; una columna
  aparte complicaría las agregaciones del panel sin aportar.
- **D7.** El identificador de conversación lo emite el **servidor** en el primer
  turno y viaja al cliente acompañado de una credencial firmada con
  `hash_hmac` sobre `APP_KEY`. El cliente la devuelve en cada petición; una
  credencial ausente o inválida abre una conversación nueva en lugar de fallar.
  Motivo: R6, sin introducir sesiones ni autenticación en un endpoint sin estado.
- **D8.** El widget guarda el hilo y la credencial en `sessionStorage`, con clave
  por slug de post. Motivo: R5; persiste al recargar y desaparece al cerrar la
  pestaña, que es el alcance que corresponde a una conversación anónima.
- **D9.** La caché de respuestas solo se consulta y se escribe cuando la
  conversación no tiene turnos anteriores, tal como decidió
  `chat-cache-respuestas` en su D4. Motivo: una respuesta que depende del hilo no
  es reutilizable por otro visitante, y la repetición se concentra en la primera
  pregunta.

## Cambios de datos

- Migración sobre `ai_usage`: `rewritten_question` (`text`, nulo).
- Nuevas claves de configuración `rag.rewrite.model`,
  `rag.rewrite.max_tokens` y `rag.conversation.max_turns`, documentadas en
  `.env.example`.
- El contrato de `POST /api/chat` gana el campo `conversation_token` en la
  respuesta y lo acepta en la petición.

## Seguridad

- D7 cierra el camino por el que un visitante podía escribir turnos en la
  conversación de otro acertando un UUID.
- El historial que entra en la petición procede exclusivamente de turnos ya
  registrados de esa misma conversación, nunca del cuerpo de la petición.
- La reformulación mantiene el aislamiento del prompt decidido en
  `chat-prompt-aislado`: el texto del visitante y el historial van en el mensaje de
  usuario, y las instrucciones en el de sistema.
- El hilo se guarda en `sessionStorage`, no en `localStorage`, para que no
  sobreviva al cierre de la pestaña.

## Preguntas abiertas

(ninguna)
