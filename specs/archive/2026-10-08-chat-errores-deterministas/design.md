# Diseño — Contrato de error determinista en el chat

## Contexto técnico

- `ChatController::jsonAnswer()` captura `RuntimeException` → 503 `service_error`
  y `Throwable` → 503 `service_error`, con el detalle solo en el log
  (`Log::warning` / `Log::error`).
- `ChatController::streamAnswer()` captura lo mismo dentro del callback de
  `response()->stream()`, emite un evento SSE `{error, message}` y después
  `data: [DONE]`. El estado HTTP ya se envió como 200.
- `RagService::askForPost()` y `askPostStream()` llaman a `recordUsage()`
  **después** de `$this->llm->complete()` / `stream()`, así que una excepción del
  cliente impide el registro. `recordUsage()` ya acepta un parámetro `extra` y
  `observability()` ya rellena `refused_stage`, que hoy toma los valores `local` y
  `embedding`.
- `OpenAiLlmClient::complete()` usa `timeout(90)` y `stream()` `timeout(120)`; no
  hay `retry()` en ninguno de los dos.
- `resources/js/chat.js` trata el evento con `error` poniendo `this.error`, pero no
  toca el mensaje ya pintado en `this.messages[index]`.
- La vista del panel `AiConversationResource` muestra los turnos registrados; un
  turno que no se registra no aparece en ninguna parte.

## Decisiones

- **D1.** El evento SSE final gana el campo `incomplete` (booleano). Se emite
  `true` cuando se captura una excepción después de haber enviado al menos un
  delta, y `false` en el cierre normal. Motivo: el estado HTTP ya no se puede
  cambiar, pero el contrato del stream sí puede decir la verdad.
- **D2.** El widget marca el turno incompleto con una nota bajo la respuesta y
  conserva el texto recibido en lugar de descartarlo. Motivo: el visitante ya lo
  ha leído; borrarlo confunde más que avisarle.
- **D3.** `refused_stage` admite un valor nuevo, `error`. Un turno fallido se
  registra con ese valor, la pregunta, el post, el proveedor y cero tokens.
  Motivo: reutiliza la columna y el visor del panel que ya existen, sin migración.
- **D4.** El registro del turno fallido se hace en `RagService`, envolviendo la
  llamada al cliente LLM en `try`/`finally`, y no en el controlador. Motivo:
  `RagService` es quien tiene el contexto de recuperación y ya es el único sitio
  que escribe en `ai_usage`.
- **D5.** `complete()` usa `Http::retry(2, 200, throw: false)` limitado a errores
  de conexión y a los estados 429, 500, 502, 503 y 504. `stream()` **no** reintenta.
  Motivo: R3 y R4; una vez emitidos bytes al visitante, repetir la llamada
  duplicaría texto.
- **D6.** Los tiempos de espera pasan a configuración:
  `rag.timeouts.complete` (`RAG_TIMEOUT_COMPLETE`, por defecto `30`) y
  `rag.timeouts.stream` (`RAG_TIMEOUT_STREAM`, por defecto `30`), en segundos.
  Motivo: R5, y poder ajustarlos sin desplegar código.
- **D7.** En `design.md` se deja anotado que los tiempos de espera del servidor web
  (`fastcgi_read_timeout` de nginx y `request_terminate_timeout` de PHP-FPM) deben
  ser mayores que los de D6; si cortan antes, el stream se trunca sin evento de
  error y el widget queda cargando. No se modifica la configuración del servidor
  en esta spec: se comprueba y se documenta en el `README.md`.

## Cambios de datos

- Sin cambios de esquema: `ai_usage.refused_stage` es `string(20)` y admite el
  valor `error`.
- Nuevas claves de configuración `rag.timeouts.complete` y `rag.timeouts.stream`,
  documentadas en `.env.example`.
- El contrato del evento SSE final gana el campo `incomplete` (booleano).

## Seguridad

- Los mensajes de error visibles siguen siendo genéricos; el detalle de la
  excepción permanece en el log, como hasta ahora.
- Registrar los turnos fallidos aumenta lo que se guarda de los visitantes: la
  pregunta de un turno fallido se almacena igual que la de uno atendido, y queda
  sujeta a la misma retención de 30 días y a lo que decida la spec
  `chat-privacidad-registro`.
- Acortar los tiempos de espera reduce la superficie de agotamiento de workers de
  PHP-FPM ante un proveedor lento.

## Preguntas abiertas

(ninguna)
