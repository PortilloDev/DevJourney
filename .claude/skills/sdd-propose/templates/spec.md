---
feature: <nombre-feature>
estado: borrador
creada: <AAAA-MM-DD>
aprobada:
---

# <Título de la feature>

## Por qué

<Problema u oportunidad que motiva la feature, en 1–3 frases. Por qué ahora.>

## Requisitos

<!--
Un requisito por sección ### R<n> — <título>.
Cada requisito usa EXACTAMENTE una palabra clave en MAYÚSCULAS:
DEBE | DEBERÍA | PUEDE | NO DEBE.
Cada requisito tiene al menos un escenario R<n>-E<m> con DADO / CUANDO / ENTONCES.
Los escenarios describen comportamiento observable, no implementación.
Los identificadores nunca se renumeran.
-->

### R1 — <título corto>

El sistema DEBE <comportamiento observable>.

**Escenario R1-E1:** DADO <situación inicial> / CUANDO <una sola acción> / ENTONCES <resultado observable>

### R2 — <título corto>

El sistema DEBERÍA <comportamiento observable salvo justificación en design.md>.

**Escenario R2-E1:** DADO <situación inicial> / CUANDO <una sola acción> / ENTONCES <resultado observable>

### R3 — <título corto>

El sistema NO DEBE <comportamiento prohibido>.

**Escenario R3-E1:** DADO <situación inicial> / CUANDO <una sola acción> / ENTONCES <se demuestra que el comportamiento prohibido no ocurre>

### R4 — <título corto>

El sistema PUEDE <comportamiento opcional; solo se implementa si tasks.md tiene una tarea para él>.

**Escenario R4-E1:** DADO <situación inicial> / CUANDO <una sola acción> / ENTONCES <resultado observable>

## Fuera de alcance

- <lo que explícitamente no se aborda en esta spec>

## Enmiendas

(ninguna)

<!--
Si se aprueba una enmienda, sustituye "(ninguna)" por entradas con este formato:

### A1 — <AAAA-MM-DD>
- Motivo: <por qué hace falta>
- Cambio: <qué cambia>
- Afecta a: R2, D3, tareas 4–5
- Aprobada por el usuario: sí

Un requisito retirado se conserva marcado como "(retirado por enmienda A<n>)".
-->
