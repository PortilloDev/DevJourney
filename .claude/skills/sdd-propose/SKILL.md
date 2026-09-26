---
name: sdd-propose
description: >-
  Crea una nueva spec SDD (spec.md, design.md, tasks.md) en specs/<feature>/ y
  la lleva hasta su aprobación explícita, sin escribir código. Úsala cuando el
  usuario pida "crea una spec", "nueva spec", "tengo una idea/nueva feature",
  "quiero proponer una funcionalidad", "propose a spec", "new feature",
  "write a spec", "start SDD". NO la uses para implementar tareas de una spec ya
  aprobada (usa sdd-apply), ni para archivar una spec implementada (usa
  sdd-archive), ni para cambios triviales sin comportamiento observable (bugs de
  una línea, typos, refactors sin cambio funcional).
---

# sdd-propose — Crear y aprobar una spec

## Objetivo

A partir de una idea, producir una spec completa (el QUÉ, el CÓMO y las tareas)
en estado `borrador` y, tras la aprobación explícita del usuario y sin preguntas
abiertas, dejarla en estado `aprobada`. Esta skill no escribe ni modifica código
de producción.

## Entrada

Una descripción de la feature: una frase, un ticket, un documento o una
conversación previa. Si el usuario no aporta ninguna descripción, DETENTE y
pídesela.

## Precondiciones

- Si `specs/<nombre-feature>/` ya existe, DETENTE y pregunta: ¿usar otro nombre
  o modificar la existente mediante una enmienda? No crees una carpeta duplicada.
- Si la descripción no corresponde a una feature con comportamiento observable,
  DETENTE y pregunta si realmente se quiere una spec (puede ser una corrección
  trivial).

## Descubrimiento del entorno (hazlo antes de escribir)

El stack, las convenciones y el comando de tests NO están fijados en esta skill:
descúbrelos en el repositorio. Lee, en este orden, los que existan:

1. `AGENTS.md` (y los `AGENTS.md` anidados de los directorios implicados).
2. `CLAUDE.md`.
3. `README.md` / `docs/`.
4. Configuración de build y tests: `Makefile`, `composer.json`, `package.json`,
   `pyproject.toml`, `go.mod`, y la CI (`.github/workflows/` o equivalente).

Usa los comandos y convenciones tal cual aparezcan; no los reinterpretes. Si tras
esta búsqueda no localizas el stack o la forma de ejecutar los tests, DETENTE y
pregunta al usuario. **Nunca inventes comandos ni asumas un stack.**

## Pasos

1. **Explora el código relacionado en modo solo lectura** para que el diseño
   parta de lo que ya existe: modelos/entidades, servicios, rutas/endpoints,
   migraciones, tests cercanos y cualquier pieza que la feature pueda reutilizar.
   No modifiques nada.
2. **Reúne lo que falta.** Formula al usuario, en un **único mensaje**, un
   **máximo de 5 preguntas** sobre lo que no puedas deducir del código ni de la
   descripción. Si algo se puede decidir razonablemente a partir del código, no
   lo preguntes: decídelo y documéntalo en `design.md`.
3. **Propón el nombre de la feature en kebab-case** (p. ej.
   `limite-transferencias-diarias`). Confírmalo si hay cualquier ambigüedad.
4. **Genera los tres archivos** copiando las plantillas de `templates/`
   (`spec.md`, `design.md`, `tasks.md`) en `specs/<nombre-feature>/`:
   - `spec.md` con estado `borrador`, `creada` = fecha de hoy y `aprobada` vacío.
   - Rellena el QUÉ en `spec.md`, el CÓMO en `design.md` y las tareas en `tasks.md`
     siguiendo las reglas de formato de más abajo.
5. **Valida** la spec con la checklist del final y corrige lo que falle. Si un
   requisito no tiene escenario verificable, redacta tú una propuesta y pide al
   usuario que la confirme o reformule (no marques la spec como válida sin
   escenario).
6. **Presenta un resumen** con: requisitos (por identificador y una línea),
   decisiones de diseño, preguntas abiertas y número de tareas. **Pide la
   aprobación explícita.**
7. **Cierra según la respuesta:**
   - Si el usuario aprueba y `## Preguntas abiertas` está vacía → cambia
     `estado` a `aprobada` y rellena `aprobada` con la fecha de hoy.
   - Si el usuario pide cambios → itera desde el paso 4 (mantén `borrador`).
   - Si quedan preguntas abiertas → NO puedes aprobar; resuélvelas primero.

## Reglas de formato (obligatorias)

### `spec.md` (el QUÉ)

Secciones obligatorias, en este orden: `## Por qué`, `## Requisitos`,
`## Fuera de alcance`, `## Enmiendas`.

Cada requisito:

```markdown
### R1 — <título corto>

El sistema DEBE <comportamiento observable>.

**Escenario R1-E1:** DADO <situación inicial> / CUANDO <una sola acción> / ENTONCES <resultado observable>
```

- Palabras clave **siempre en MAYÚSCULAS**: `DEBE` (obligatorio), `DEBERÍA` (se
  implementa salvo justificación en `design.md`), `PUEDE` (opcional; solo si hay
  tarea), `NO DEBE` (prohibición).
- Cada requisito usa **exactamente una** palabra clave.
- Todo requisito, de cualquier tipo, tiene al menos un escenario con
  identificador `R<n>-E<m>` con DADO, CUANDO y ENTONCES.
- Cada `NO DEBE` requiere al menos un escenario negativo que demuestre que el
  comportamiento prohibido no ocurre.
- Los escenarios describen **comportamiento observable**, no implementación.
- Los identificadores **nunca se renumeran**. Un requisito eliminado se conserva
  marcado como `(retirado por enmienda A<n>)`.

### `design.md` (el CÓMO)

Secciones: `## Contexto técnico`, `## Decisiones`, `## Cambios de datos`,
`## Seguridad`, `## Preguntas abiertas`.

- Cada decisión se identifica `D1`, `D2`… e incluye la decisión y su motivo.
- Las decisiones son **cerradas**: `sdd-apply` no puede contradecirlas.
- Mientras `## Preguntas abiertas` no esté vacía, la spec no puede pasar a
  `aprobada`.
- Anexos (diagramas, contratos OpenAPI) solo si `design.md` los referencia.

### `tasks.md`

- Checklist Markdown de tareas pequeñas, numeradas y ordenadas por dependencia.
- Cada tarea debe poder completarse y verificarse de forma independiente.
- Cada tarea referencia los requisitos que implementa y, si procede, sus
  dependencias: `- [ ] 3. Implementar la vinculación de cuentas (R2) (depende de 1)`.
- Todo requisito `DEBE`, `DEBERÍA` o `NO DEBE` no retirado está cubierto por al
  menos una tarea. Los `PUEDE` solo si hay una tarea.

## Puntos de parada

Detente (termina el turno con una pregunta explícita y sin más cambios) cuando:

- No hay descripción de feature.
- `specs/<nombre>/` ya existe.
- Tras descubrir el entorno, no localizas el stack o el comando de tests.
- Necesitas datos que solo el usuario puede aportar.
- Un requisito no tiene escenario verificable.
- Vas a aprobar y aún hay preguntas abiertas o el usuario no ha aprobado
  explícitamente. El silencio o una respuesta ambigua **no** son aprobación.

## Salida esperada

```
specs/<nombre-feature>/spec.md      (estado: borrador → aprobada)
specs/<nombre-feature>/design.md
specs/<nombre-feature>/tasks.md
```

Un mensaje final con el resumen de requisitos, decisiones, preguntas abiertas y
número de tareas; y el estado final de la spec.

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
- [ ] `design.md` se apoya en piezas existentes del repositorio (no inventa
      capas).
