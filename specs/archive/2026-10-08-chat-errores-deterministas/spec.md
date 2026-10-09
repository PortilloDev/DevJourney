---
feature: chat-errores-deterministas
estado: archivada
creada: 2026-10-08
aprobada: 2026-10-08
---

# Contrato de error determinista en el chat

## Por qué

Los códigos de error del endpoint son estables y los mensajes no filtran nada,
pero falta el caso más probable en producción: que el proveedor muera **a mitad
del stream**. Hoy el visitante se queda con media respuesta en pantalla,
indistinguible de una completa, porque el estado HTTP ya fue 200 y el widget solo
guarda el error en una variable aparte. Además un turno que falla no deja fila en
`ai_usage` —la excepción sube antes del registro—, así que se pierden justo los
turnos que más interesa auditar, y los tiempos de espera de 90 y 120 segundos
retienen un worker de PHP-FPM mucho más de lo que nadie espera.

## Requisitos

### R1 — Turno incompleto señalado

El sistema DEBE señalar como incompleto el turno cuyo stream se interrumpe antes de terminar.

**Escenario R1-E1:** DADO un turno en streaming que ya ha emitido texto / CUANDO el proveedor corta la conexión antes del final / ENTONCES el evento final del stream indica que el turno quedó incompleto y el widget marca visualmente esa respuesta

### R2 — Todo turno deja rastro

El sistema DEBE registrar un turno en el histórico de uso también cuando la llamada al proveedor falla.

**Escenario R2-E1:** DADO un proveedor que devuelve un error / CUANDO se procesa una pregunta válida / ENTONCES se registra un turno con la etapa de fallo anotada, la pregunta y el post, y el visitante recibe el mensaje de servicio no disponible

### R3 — Reintento de los fallos transitorios

El sistema DEBE reintentar una vez la llamada no interactiva al proveedor cuando falla con un error transitorio.

**Escenario R3-E1:** DADO un proveedor que responde 500 a la primera petición y correctamente a la segunda / CUANDO se procesa una pregunta por el camino sin streaming / ENTONCES el visitante recibe la respuesta correcta

### R4 — No reintentar lo ya emitido

El sistema NO DEBE reintentar una respuesta en streaming una vez ha emitido texto al visitante.

**Escenario R4-E1:** DADO un turno en streaming que ya ha emitido texto / CUANDO el proveedor falla / ENTONCES no se realiza ninguna petición adicional al endpoint de chat completions y el turno se cierra como incompleto

### R5 — Tiempos de espera acotados

El sistema DEBE abortar la llamada al proveedor cuando supera el tiempo de espera configurado, no superior a treinta segundos por defecto.

**Escenario R5-E1:** DADO un proveedor que no responde / CUANDO se procesa una pregunta / ENTONCES la petición se aborta al cumplirse el tiempo de espera configurado y el visitante recibe el mensaje de servicio no disponible

## Fuera de alcance

- Abrir el circuito automáticamente tras varios fallos (spec `chat-degradacion-automatica`).
- Alertas o notificaciones ante una subida de la tasa de error.
- Cambiar el proveedor o añadir un proveedor de respaldo.
- La marca de respuesta truncada por agotar la cota de tokens (spec `chat-prompt-aislado`).

## Enmiendas

(ninguna)
