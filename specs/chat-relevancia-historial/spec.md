---
feature: chat-relevancia-historial
estado: implementada
creada: 2026-10-23
aprobada: 2026-10-23
---

# Relevancia por cobertura e historial limpio

## Por qué

El rescate por término del título deja pasar preguntas mixtas que no son del
post: «cqrs tortilla patata» comparte «cqrs» con el título, supera el umbral
permisivo y llega al modelo de chat, que responde «I do not know». Ese turno queda
como `answered` y entra en el historial, de modo que los turnos siguientes
arrastran una respuesta vacía y el modelo tiende a repetir «No lo sé». Además, el
historial se acota por número de turnos pero no por tokens, y el prompt completo
no se guarda: el panel muestra solo sistema + pregunta, así que no se puede
auditar qué contexto e historial recibió realmente el modelo.

## Requisitos

### R1 — Rechazo local de preguntas mixtas por cobertura

El sistema DEBE rechazar localmente, sin llamar al modelo de chat, las preguntas
que comparten un término con el título pero cuya cobertura de contenido contra el
post es insuficiente.

**Escenario R1-E1:** DADO un post sobre CQRS / CUANDO el visitante pregunta «cqrs tortilla patata» / ENTONCES se devuelve el mensaje predefinido, no se solicitan embeddings y no se llama al modelo de chat

**Escenario R1-E2:** DADO un post sobre CQRS / CUANDO el visitante pregunta «hacer una tortilla con cqrs» / ENTONCES se devuelve el mensaje predefinido y no se llama al modelo de chat

**Escenario R1-E3:** DADO un post sobre CQRS / CUANDO el visitante pregunta «when is it good practice use CQRS?» / ENTONCES la pregunta no se rechaza localmente y se responde

**Escenario R1-E4:** DADO un post sobre CQRS etiquetado con DDD / CUANDO el visitante pregunta «se puede usar cqrs con ddd» / ENTONCES la pregunta no se rechaza localmente

### R2 — Historial sin turnos no respondidos

El sistema NO DEBE incluir en el historial enviado al modelo los turnos rechazados
ni los que no obtuvieron respuesta.

**Escenario R2-E1:** DADO una conversación con un turno rechazado y otro respondido / CUANDO se construye la petición del siguiente turno / ENTONCES el turno rechazado no aparece en el historial

### R3 — Respuesta ausente señalada por el modelo

El sistema DEBE detectar cuando el modelo declara que el contexto no contiene la
respuesta, marcar el turno como rechazado y entregar el mensaje predefinido.

**Escenario R3-E1:** DADO un modelo que devuelve exactamente `NO_ANSWER` / CUANDO se entrega la respuesta JSON / ENTONCES el visitante recibe el mensaje predefinido y el turno queda marcado como rechazado

**Escenario R3-E2:** DADO un modelo que devuelve exactamente `NO_ANSWER` en streaming / CUANDO se entrega la respuesta SSE / ENTONCES el visitante recibe el mensaje predefinido, el evento final indica rechazo y el texto `NO_ANSWER` no se muestra

### R4 — Presupuesto de tokens del historial

El sistema DEBE acotar el historial por tokens y descontarlo del presupuesto de
entrada antes de recortar el contexto.

**Escenario R4-E1:** DADO una conversación con historial largo y un contexto recuperado / CUANDO se construye la petición al modelo / ENTONCES el historial se recorta desde el turno más antiguo y el total no supera el máximo de tokens de entrada

### R5 — Prompt completo para auditoría

El sistema DEBE guardar el prompt completo enviado al modelo (sistema con
contexto, historial y pregunta) y mostrarlo en el panel.

**Escenario R5-E1:** DADO un turno respondido / CUANDO se consulta su registro / ENTONCES el prompt guardado contiene el contexto recuperado

**Escenario R5-E2:** DADO un turno respondido dentro de una conversación con historial / CUANDO se consulta su registro / ENTONCES el prompt guardado contiene los turnos anteriores enviados al modelo

## Fuera de alcance

- El layout del chat, el auto-scroll y el botón flotante móvil: se implementan
  como cambio directo, no como parte de esta spec.
- Cambiar el idioma de respuesta: el asistente responde siempre en inglés.
- Recuperación entre posts distintos o un chat global.
- Cambiar el proveedor, el modelo de chat o el de embeddings.

## Enmiendas

### A1 — 2026-10-23
- Motivo: R5 necesita el contexto completo para auditar relevancia e historial;
  la spec archivada `chat-privacidad-registro` (R4/D6) guardaba solo sistema +
  pregunta para no duplicar el contexto.
- Cambio: `ai_usage.prompt` guarda los mensajes completos cuando
  `rag.log_full_prompt` está activo; el visor del panel los muestra. La decisión
  de privacidad anterior queda supersedida para este campo.
- Afecta a: R5, D5, tarea 5.
- Aprobada por el usuario: sí
