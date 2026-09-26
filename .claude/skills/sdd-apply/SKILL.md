---
name: sdd-apply
description: >-
  Implementa, tarea a tarea y guiado por tests, las tareas de una spec SDD
  aprobada en specs/<feature>/, y la deja en estado implementada. Úsala cuando
  el usuario pida "implementa la spec X", "aplica la spec", "haz las tareas de
  la spec X", "implement spec X", "apply the spec", "work on the SDD tasks". NO
  la uses para crear specs nuevas (usa sdd-propose) ni para archivar una spec ya
  implementada (usa sdd-archive). Tampoco la uses si la spec no está en estado
  aprobada o en-curso.
---

# sdd-apply — Implementar una spec aprobada

## Objetivo

Ejecutar las tareas de una spec aprobada en orden, con tests que trazan cada
escenario, respetando `design.md` al pie de la letra, y dejar la spec en estado
`implementada` cuando todas las tareas estén marcadas y la suite completa en
verde.

## Entrada

El **nombre de la feature**. Si el usuario no lo indica, lista las specs en
`specs/` cuyo `estado` sea `aprobada` o `en-curso` y pregunta cuál aplicar.

## Precondiciones

Comprueba todo antes de tocar código. Si algo falla, DETENTE e informa.

- `specs/<feature>/spec.md`, `design.md` y `tasks.md` existen.
- `spec.md` está en estado **`aprobada`** o **`en-curso`**.
- La checklist de validación (al final de esta skill) se cumple.
- `## Preguntas abiertas` de `design.md` está **vacía**.

## Descubrimiento del entorno (hazlo antes de escribir código)

El stack, las convenciones y el comando de tests NO están fijados en esta skill:
descúbrelos en el repositorio. Lee, en este orden, los que existan:

1. `AGENTS.md` (y los `AGENTS.md` anidados de los directorios implicados).
2. `CLAUDE.md`.
3. `README.md` / `docs/`.
4. Configuración de build y tests: `Makefile`, `composer.json`, `package.json`,
   `pyproject.toml`, `go.mod`, y la CI (`.github/workflows/` o equivalente).

Usa los comandos y convenciones tal cual aparezcan; no los reinterpretes. Si no
localizas la forma de ejecutar los tests, DETENTE y pregunta al usuario.
**Nunca inventes comandos ni asumas un stack.**

## Pasos

1. **Lee** los tres archivos de la spec y el contexto del repositorio necesario
   (código relacionado, tests existentes y el comando de tests descubierto).
2. **Presenta un resumen de comprensión y espera confirmación explícita.** Depende
   del estado:
   - Si el estado es `aprobada`: requisitos con tus propias palabras, decisiones
     de diseño que condicionan la implementación, plan de tareas y riesgos o
     ambigüedades detectados.
   - Si el estado es `en-curso` (reanudación): tareas ya completadas y la
     siguiente pendiente.
   No continúes hasta que el usuario confirme. El silencio o una respuesta
   ambigua **no** cuentan como confirmación.
3. **Cambia `estado` a `en-curso`** en `spec.md`.
4. **Para cada tarea sin marcar, en orden**, repite:
   1. **Tests primero.** Escribe los tests de los escenarios de sus requisitos
      que aún no existan, incluyendo el identificador del escenario (`R2-E1`) en
      el nombre del test o en un comentario. Comprueba que **fallan por el motivo
      esperado**.
   2. **Implementa lo mínimo** necesario para que pasen, respetando `design.md`.
   3. **Ejecuta los tests afectados** y confirma que están en verde.
   4. **Marca la tarea** en `tasks.md` (`- [x] n. …`).
   No hagas commit por tarea: esta configuración no lo requiere.
5. **Detente** ante cualquiera de estos casos (ver "Puntos de parada").
6. **Al completar todas las tareas**, ejecuta la **suite completa**. Si está en
   verde, cambia `estado` a `implementada`, presenta un informe (tareas
   completadas, tests por escenario, incidencias) y sugiere ejecutar
   `sdd-archive`.

## Desviaciones y enmiendas

La spec es la **fuente de verdad**. Es desviación cualquier cambio de
comportamiento, alcance o diseño, o cualquier modificación de archivos no
justificada por una tarea. Ante una desviación:

1. DETENTE y propón una **enmienda** con el formato de `spec.md`:
   ```markdown
   ### A<n> — <AAAA-MM-DD>
   - Motivo: <por qué hace falta>
   - Cambio: <qué cambia>
   - Afecta a: R2, D3, tareas 4–5
   - Aprobada por el usuario: sí
   ```
2. Espera la aprobación explícita. Si se aprueba, actualiza `spec.md`,
   `design.md` y `tasks.md` de forma coherente **en el mismo paso** y continúa.
3. Si no se aprueba, ajusta la implementación para ceñirte a la spec original.

## Prohibido

- Marcar una tarea sin que sus tests pasen.
- Modificar o eliminar tests existentes para que pasen, salvo que la tarea lo
  requiera explícitamente.
- Ampliar el alcance aunque parezca una mejora.
- Contradecir o sustituir decisiones de `design.md` sin una enmienda aprobada.
- Asignar un estado distinto de `en-curso` o `implementada`.

## Puntos de parada

Detente (termina el turno con una pregunta explícita y sin más cambios) cuando:

- Algo incumple las precondiciones.
- Hace falta una desviación: propón la enmienda y espera aprobación.
- Un test no pasa tras **dos intentos razonables** sin desviarte de la spec:
  informa del problema y de las opciones.
- Una tarea es ambigua: pregunta.

## Salida esperada

- Código y tests que implementan las tareas, con trazas `R<n>-E<m>`.
- `tasks.md` con todas las tareas marcadas.
- `spec.md` en estado `implementada` (o `en-curso` si no se terminó).
- Un informe final con tareas completadas, tests por escenario e incidencias.

## Checklist de validación

- [ ] Frontmatter completo (`feature`, `estado`, `creada`, `aprobada`) y estado
      válido.
- [ ] `## Por qué`, `## Requisitos`, `## Fuera de alcance` y `## Enmiendas`
      presentes y en ese orden en `spec.md`.
- [ ] `## Contexto técnico`, `## Decisiones`, `## Cambios de datos`,
      `## Seguridad` y `## Preguntas abiertas` presentes en `design.md`.
- [ ] Cada requisito usa exactamente una palabra clave.
- [ ] Cada requisito tiene al menos un escenario `R<n>-E<m>` con DADO, CUANDO y
      ENTONCES.
- [ ] Cada `NO DEBE` tiene un escenario negativo.
- [ ] Cada requisito `DEBE`, `DEBERÍA` y `NO DEBE` no retirado está cubierto por
      al menos una tarea.
- [ ] Cada tarea referencia al menos un requisito existente y no retirado.
- [ ] El orden de las tareas respeta sus dependencias.
