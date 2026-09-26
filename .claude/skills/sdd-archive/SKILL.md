---
name: sdd-archive
description: >-
  Verifica que una spec SDD implementada cumple su spec y su diseño, la archiva
  en specs/archive/ y registra el cierre en specs/ARCHIVE.md. Úsala cuando el
  usuario pida "archiva la spec X", "cierra la spec", "verifica y archiva",
  "archive spec X", "close the spec", "finish the SDD flow". NO la uses si la
  spec no está en estado implementada (usa sdd-apply para terminarla) ni para
  crear specs nuevas (usa sdd-propose). Tampoco la uses como sustituto de la
  suite de tests: la verificación exige ejecutarla.
---

# sdd-archive — Verificar y archivar una spec

## Objetivo

Comprobar que una spec en estado `implementada` se corresponde con lo construido,
y si todo pasa, archivarla y dejar constancia en `specs/ARCHIVE.md`. Si algo
falla, no archivar: informar y proponer la corrección.

## Entrada

El **nombre de la feature**. Si el usuario no lo indica, lista las specs en
estado `implementada` y pregunta cuál archivar.

## Precondiciones

- `specs/<feature>/spec.md` existe y su `estado` es **`implementada`**.
- Si el estado es otro, DETENTE e indica qué skill corresponde.

## Descubrimiento del entorno (hazlo antes de verificar)

El comando de tests y el stack NO están fijados en esta skill: descúbrelos en el
repositorio. Lee, en este orden, los que existan:

1. `AGENTS.md` (y los `AGENTS.md` anidados de los directorios implicados).
2. `CLAUDE.md`.
3. `README.md` / `docs/`.
4. Configuración de build y tests: `Makefile`, `composer.json`, `package.json`,
   `pyproject.toml`, `go.mod`, y la CI (`.github/workflows/` o equivalente).

Usa el comando de tests tal cual aparezca. Si no lo localizas, DETENTE y pregunta
al usuario. **Nunca inventes comandos.**

## Verificación (debe pasar todo)

1. **Tareas completas:** todas las tareas de `tasks.md` están marcadas (`- [x]`).
2. **Matriz de trazabilidad:** cada escenario de los requisitos no retirados
   tiene al menos un test identificable. Se excluyen los `PUEDE` sin tarea.
   Construye y presenta una matriz como esta:

   | Requisito | Escenario | Test |
   |-----------|-----------|------|
   | R1 | R1-E1 | `<test que lo cubre>` |
   | R1 | R1-E2 | `<test que lo cubre>` |
   | R2 | R2-E1 | `<test que lo cubre>` |
   | R3 | R3-E1 (negativo) | `<test que lo cubre>` |

3. **Suite en verde:** ejecuta **la suite completa** con el comando descubierto.
   No basta con suponerlo ni con ejecutar solo los tests de la feature.
4. **Conformidad con el diseño:** para cada decisión `D<n>` de `design.md`,
   comprueba en el código que se cumple. Lista los incumplimientos, si los hay.
5. **Enmiendas aprobadas:** todas las enmiendas de `## Enmiendas` constan como
   `Aprobada por el usuario: sí`.

## Si algo falla

1. Presenta un **informe con los fallos** (qué comprobación, qué evidencia) y una
   **propuesta**: tareas nuevas vía enmienda.
2. **No archives y no cambies el estado.**
3. Si el usuario aprueba la enmienda, el estado vuelve a **`en-curso`** y el
   trabajo continúa con `sdd-apply`.

## Si todo pasa

1. **Muestra el informe** (incluida la matriz de trazabilidad) y **pide
   confirmación explícita** para archivar. El silencio o una respuesta ambigua
   **no** son confirmación.
2. **Mueve la carpeta** a `specs/archive/<AAAA-MM-DD>-<nombre-feature>/` y cambia
   `estado` a **`archivada`**.
3. **Añade una entrada** a `specs/ARCHIVE.md` (créalo si no existe) con este
   formato:
   ```markdown
   | Fecha | Feature | Resumen (1 línea) | Requisitos |
   |-------|---------|-------------------|------------|
   | <AAAA-MM-DD> | <nombre-feature> | <resumen> | R1, R2, R3 |
   ```

## Fuente de verdad tras archivar

El **código y sus tests trazados** son la fuente de verdad. La spec archivada es
un **registro histórico** y no se modifica. Cualquier cambio futuro de ese
comportamiento requiere una **spec nueva** que referencie la archivada.

## Puntos de parada

Detente (termina el turno con una pregunta explícita y sin más cambios) cuando:

- El estado no es `implementada`.
- No localizas el comando de tests.
- Alguna comprobación de la verificación falla (presenta informe y propuesta).
- Vas a archivar y el usuario no ha confirmado explícitamente.

## Salida esperada

- Informe de verificación con la matriz de trazabilidad.
- `specs/archive/<AAAA-MM-DD>-<nombre-feature>/` con `estado: archivada`.
- Entrada añadida en `specs/ARCHIVE.md`.
- (Si falla) informe de fallos y propuesta de enmienda, sin archivar.

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
