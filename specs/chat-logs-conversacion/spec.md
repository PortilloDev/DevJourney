---
feature: chat-logs-conversacion
estado: aprobada
creada: 2026-09-26
aprobada: 2026-09-26
---

# Logs de conversación del chat

## Por qué

No hay visibilidad de lo que el chat envía al LLM, de lo que responde, ni de qué
documentos/fragmentos usa para responder. Se necesita un registro de
observabilidad y un visor en el panel para auditar y analizar cada conversación.

## Requisitos

### R1 — Registro detallado de cada turno

El sistema DEBE registrar, por cada turno del chat, la pregunta, el prompt completo enviado al LLM, la respuesta, los fragmentos recuperados (título, url, score y heading), el modelo, los tokens de entrada y salida, la latencia, si fue rechazado, la IP y el post.

**Escenario R1-E1:** DADO una consulta al chat / CUANDO se responde o se rechaza / ENTONCES queda un turno con todos esos datos.

### R2 — Agrupación por conversación

El sistema DEBE agrupar los turnos por conversación usando un identificador de sesión de página que envía el widget.

**Escenario R2-E1:** DADO el widget genera un identificador de conversación / CUANDO el visitante hace varias preguntas sin recargar / ENTONCES los turnos se agrupan bajo esa conversación.

### R3 — Visor de conversaciones en el admin

El panel DEBE permitir listar las conversaciones (con filtros por fecha, post e IP) y ver el detalle del flujo de cada una (prompt, respuesta, retrieval y uso).

**Escenario R3-E1:** DADO conversaciones registradas / CUANDO el administrador abre el visor / ENTONCES ve la lista filtrable y el detalle del flujo de cada conversación.

### R4 — Borrado manual desde el panel

El panel DEBE permitir borrar conversaciones indicando un rango de fechas o todas.

**Escenario R4-E1:** DADO conversaciones de varias fechas / CUANDO el administrador borra por un rango / ENTONCES solo desaparecen las conversaciones de ese rango.

### R5 — Retención automática

El sistema DEBE eliminar automáticamente las conversaciones más antiguas que un periodo configurable.

**Escenario R5-E1:** DADO conversaciones con antigüedad superior al periodo configurado / CUANDO se ejecuta la limpieza / ENTONCES se eliminan.

## Fuera de alcance

- Exportación a fichero o API externa.
- Cuadros de mando agregados y gráficas de analítica (posible spec futura).

## Enmiendas

(ninguna)
