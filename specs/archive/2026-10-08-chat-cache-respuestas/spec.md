---
feature: chat-cache-respuestas
estado: archivada
creada: 2026-10-08
aprobada: 2026-10-08
---

# Caché de respuestas del chat

## Por qué

En un blog las preguntas se repiten mucho más de lo que parece: el chat de un
artículo recibe una y otra vez las mismas tres o cuatro dudas. Hoy no hay nada
cacheado —la caché solo guarda el contador de peticiones—, así que cada
repetición paga una llamada de embeddings, una búsqueda vectorial, una llamada al
modelo y varios segundos de espera para producir el mismo texto que ya se produjo
ayer.

## Requisitos

### R1 — Respuesta reutilizada

El sistema DEBE devolver la respuesta almacenada, sin invocar al proveedor, cuando la misma pregunta normalizada se repite sobre el mismo post dentro del periodo de validez.

**Escenario R1-E1:** DADO una pregunta ya respondida en un post / CUANDO otro visitante formula la misma pregunta con distinta capitalización y espaciado / ENTONCES recibe la misma respuesta y no se realiza ninguna petición al endpoint de embeddings ni al de chat completions

### R2 — La respuesta almacenada se entrega igual que una generada

El sistema DEBE entregar la respuesta almacenada por el mismo canal que una generada, incluido el streaming.

**Escenario R2-E1:** DADO una respuesta almacenada para una pregunta / CUANDO el visitante la formula con streaming activo / ENTONCES recibe el texto en eventos de stream y un evento final con sus fuentes

### R3 — Invalidación al cambiar el post

El sistema NO DEBE devolver una respuesta almacenada que se generó antes de la última modificación del post.

**Escenario R3-E1:** DADO una respuesta almacenada para un post / CUANDO el post se edita y se guarda, y a continuación se repite la misma pregunta / ENTONCES se genera una respuesta nueva invocando al proveedor

### R4 — Turnos servidos desde la caché distinguibles

El sistema DEBE registrar el turno servido desde la caché indicando ese origen y sin consumo de tokens.

**Escenario R4-E1:** DADO una respuesta servida desde la caché / CUANDO se consulta la fila del turno / ENTONCES indica que se sirvió desde la caché y registra cero tokens de entrada y de salida

### R5 — Vector de la pregunta reutilizado

El sistema DEBERÍA reutilizar el vector de una pregunta ya embebida recientemente en lugar de volver a solicitarlo al proveedor.

**Escenario R5-E1:** DADO una pregunta cuyo vector se obtuvo recientemente / CUANDO esa misma pregunta se procesa en otro post / ENTONCES no se realiza una nueva petición al endpoint de embeddings

## Fuera de alcance

- La caché de prompt del proveedor, que no aplica al tamaño de prefijo actual.
- Cachear respuestas de conversaciones con historial (lo aborda la spec
  `chat-memoria-conversacion`, que debe respetar lo decidido aquí).
- Precalentar la caché con preguntas frecuentes.
- Cachear las respuestas de rechazo, que ya no cuestan nada.

## Enmiendas

(ninguna)
