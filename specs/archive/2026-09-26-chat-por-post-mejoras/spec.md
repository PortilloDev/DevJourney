---
feature: chat-por-post-mejoras
estado: archivada
creada: 2026-09-26
aprobada: 2026-09-26
---

# Chat por post — mejoras (historial, copiar y enlaces relativos)

> Continúa a la spec archivada `specs/archive/2026-09-26-chat-por-post/`.

## Por qué

Tras cerrar el chat por artículo quedan tres fricciones: (1) los enlaces de las
fuentes se guardan **absolutos** en el índice con el host del momento de indexar,
así que en desarrollo apuntan a `http://localhost` sin el puerto; (2) el widget
solo muestra la última respuesta, sin seguir la conversación; y (3) no se puede
copiar un mensaje.

## Requisitos

### R1 — Enlaces de fuentes relativos

El sistema DEBE referenciar las fuentes de una respuesta con rutas relativas al origen actual, no con URLs absolutas.

**Escenario R1-E1:** DADO una respuesta con fuentes / CUANDO el usuario abre una fuente / ENTONCES navega a la ruta correcta en el host y puerto actuales.

### R2 — Historial de conversación en la sesión

El sistema DEBE conservar los turnos anteriores de la conversación mostrada mientras no se recargue la página.

**Escenario R2-E1:** DADO que ya hay un turno (pregunta y respuesta) / CUANDO el usuario hace otra pregunta sin recargar / ENTONCES el widget muestra ambos turnos en orden.

### R3 — Copiar un mensaje

El sistema DEBE ofrecer, en cada mensaje, un botón para copiar su texto al portapapeles.

**Escenario R3-E1:** DADO un mensaje visible / CUANDO el usuario pulsa el botón de copiar / ENTONCES el texto de ese mensaje queda en el portapapeles.

## Fuera de alcance

- Persistir el historial entre recargas o entre dispositivos (solo en memoria de la página).
- Hilos o conversaciones múltiples.

## Enmiendas

(ninguna)
