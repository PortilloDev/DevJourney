---
feature: chat-memoria-conversacion
estado: archivada
creada: 2026-10-08
aprobada: 2026-10-09
---

# Memoria de la conversación en el chat

## Por qué

El chat aparenta ser una conversación y no lo es: cada pregunta viaja al modelo
sola, con su contexto y sin ningún turno anterior. La interfaz muestra un hilo, la
base de datos agrupa los turnos por conversación y el panel tiene un visor de
conversaciones, pero el modelo nunca ve lo que se dijo antes. El efecto práctico
es que las preguntas de seguimiento —la forma natural de usar un chat— fallan dos
veces: el modelo no sabe a qué se refiere «eso» y, antes incluso de llegar ahí, la
comprobación de relevancia las rechaza porque una pregunta de seguimiento no
comparte vocabulario con el artículo. Además el identificador de conversación lo
genera el navegador en cada carga, así que recargar la página pierde el hilo.

## Requisitos

### R1 — Preguntas de seguimiento convertidas en autónomas

El sistema DEBE reformular la pregunta de seguimiento como una pregunta autónoma, usando los turnos anteriores de la conversación, antes de resolver su relevancia y su recuperación.

**Escenario R1-E1:** DADO una conversación cuyo turno anterior trata sobre puertos y adaptadores / CUANDO el visitante pregunta «¿y eso cómo se prueba?» / ENTONCES la relevancia y la recuperación se resuelven sobre una pregunta que menciona puertos y adaptadores, y el visitante recibe una respuesta en lugar del mensaje predefinido

### R2 — La primera pregunta no se reformula

El sistema NO DEBE invocar al proveedor para reformular la pregunta cuando la conversación no tiene turnos anteriores.

**Escenario R2-E1:** DADO una conversación sin turnos anteriores / CUANDO el visitante envía su primera pregunta / ENTONCES no se realiza ninguna petición de reformulación al proveedor y la pregunta se procesa tal cual

### R3 — Historial acotado

El sistema DEBE incluir en la petición al modelo como máximo el número de turnos anteriores configurado.

**Escenario R3-E1:** DADO una conversación con más turnos anteriores que el máximo configurado / CUANDO se construye la petición al modelo / ENTONCES contiene exactamente los últimos turnos permitidos por la configuración

### R4 — Degradación si la reformulación falla

El sistema DEBE continuar con la pregunta original cuando la reformulación falla.

**Escenario R4-E1:** DADO un proveedor que falla al reformular / CUANDO se procesa una pregunta de seguimiento / ENTONCES el sistema continúa con la pregunta original en lugar de devolver un error

### R5 — El hilo sobrevive a recargar la página

El sistema DEBE conservar el hilo visible y su conversación al recargar la página del post.

**Escenario R5-E1:** DADO una conversación con turnos en la página de un post / CUANDO el visitante recarga la página / ENTONCES el hilo sigue visible y la siguiente pregunta se registra en la misma conversación

### R6 — Conversaciones ajenas inaccesibles

El sistema NO DEBE añadir turnos a una conversación cuyo identificador no emitió para ese visitante.

**Escenario R6-E1:** DADO el identificador de una conversación ajena, sin la credencial emitida por el servidor / CUANDO se envía una pregunta con él / ENTONCES el turno se registra en una conversación nueva y la conversación ajena no se modifica

## Fuera de alcance

- Resumen rodante para conversaciones largas: con el máximo de turnos previsto no
  hace falta.
- Memoria entre sesiones o entre artículos distintos.
- Identificar al visitante mediante registro o login.
- Reutilizar respuestas de turnos con historial: la caché sigue limitada a la
  primera pregunta de cada conversación, según decidió `chat-cache-respuestas`.

## Enmiendas

### A1 — 2026-10-09
- Motivo: D5 condicionaba la ubicación del contexto a `chat-prompt-aislado`,
  spec todavía no aplicada; eso hacía que la implementación no coincidiera
  literalmente con la decisión.
- Cambio: D5 pasa a decidir solo lo propio de esta spec: el historial viaja como
  pares `user`/`assistant` entre el mensaje de sistema y la pregunta actual, con
  máximo `rag.conversation.max_turns`. La ubicación del contexto queda fuera de
  esta spec y se mantiene como en el código vigente (contexto en el mensaje de
  usuario) hasta que `chat-prompt-aislado` la mueva al mensaje de sistema.
- Afecta a: D5 (no afecta a requisitos ni a tareas)
- Aprobada por el usuario: sí
