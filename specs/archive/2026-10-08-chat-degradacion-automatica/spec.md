---
feature: chat-degradacion-automatica
estado: archivada
creada: 2026-10-08
aprobada: 2026-10-08
---

# Degradación automática y guardas de configuración

## Por qué

Los interruptores del chat ya surten efecto —cambiar un ajuste invalida la caché
de página completa, así que el widget desaparece en la siguiente carga—, pero la
degradación sigue siendo manual: si el proveedor cae, alguien tiene que darse
cuenta y mover el interruptor a mano. Al mismo tiempo, dos errores de
configuración degradan el chat **en silencio**: desactivar Qdrant hace que el
almacén caiga al de memoria, vacío en cada petición, de modo que todas las
preguntas se rechazan sin error ni traza; y cambiar el número de dimensiones del
modelo de embeddings deja la colección antigua en pie, con vectores de dos modelos
conviviendo y scores que ya no significan nada.

## Requisitos

### R1 — Ocultar el chat surte efecto de inmediato (retirado por enmienda A1)

El sistema DEBE dejar de mostrar el widget en las páginas públicas en cuanto se activa el ajuste correspondiente, sin esperar a un cambio de contenido.

**Escenario R1-E1:** DADO una página de post servida desde la caché de página completa con el widget visible / CUANDO el administrador activa el ajuste de ocultar el chat / ENTONCES la siguiente carga de esa página se sirve sin el widget

### R2 — Apertura automática del circuito

El sistema DEBE dejar de invocar al proveedor durante un periodo de enfriamiento cuando se acumulan fallos consecutivos por encima del umbral configurado.

**Escenario R2-E1:** DADO un proveedor que ha fallado más veces seguidas que el umbral configurado / CUANDO llega una pregunta válida / ENTONCES el visitante recibe el mensaje de servicio no disponible y no se realiza ninguna petición al proveedor

**Escenario R2-E2:** DADO un circuito abierto / CUANDO transcurre el periodo de enfriamiento y llega una pregunta válida / ENTONCES se vuelve a invocar al proveedor

### R3 — Estado del circuito visible

El sistema DEBERÍA mostrar en el panel de administración si el circuito está abierto y desde cuándo.

**Escenario R3-E1:** DADO un circuito abierto / CUANDO el administrador abre el panel / ENTONCES ve que el circuito está abierto y el instante en que se abrió

### R4 — El almacén vacío no se usa en silencio

El sistema NO DEBE resolver el almacén vectorial en memoria fuera del entorno de pruebas.

**Escenario R4-E1:** DADO un entorno distinto de pruebas con Qdrant desactivado en la configuración / CUANDO se resuelve el almacén vectorial / ENTONCES se lanza un error de configuración explícito en lugar de devolver un almacén vacío

### R5 — Desajuste de dimensiones detectado

El sistema DEBE abortar la indexación con un error explícito cuando la colección existente tiene un tamaño de vector distinto del configurado.

**Escenario R5-E1:** DADO una colección creada con un tamaño de vector y una configuración con otro distinto / CUANDO se lanza la indexación de un contenido / ENTONCES se aborta con un mensaje que nombra ambos valores y no se escribe ningún punto en la colección

### R6 — Modelo de embeddings registrado en el índice

El sistema DEBE guardar el modelo de embeddings utilizado en el payload de cada punto indexado.

**Escenario R6-E1:** DADO un contenido que se indexa / CUANDO se consulta uno de sus puntos en el almacén / ENTONCES su payload incluye el nombre del modelo de embeddings con el que se generó el vector

## Fuera de alcance

- Gestionar los interruptores con Laravel Pennant o un servicio externo de flags.
- Despliegues por porcentaje o activación por usuario.
- Reindexar automáticamente al detectar un desajuste de dimensiones: esta spec
  solo exige que el desajuste se detecte y se comunique.
- El reintento de llamadas puntuales (spec `chat-errores-deterministas`).

## Enmiendas

### A1 — 2026-10-08

- Motivo: el comportamiento de R1 ya existe en el código. `SiteSetting::booted()`
  llama a `flush()` en `saved`/`deleted`, y `AppServiceProvider::boot()` incluye
  `SiteSetting` en el bucle que llama a `PageCacheService::bump()`, de modo que
  cambiar un ajuste (p. ej. `chat_hidden`) ya invalida la caché de página completa.
  La premisa del `## Por qué` era inexacta y la tarea prevista era redundante.
- Cambio: se retira R1 y su tarea; se corrige el `## Por qué`; D1 pasa a documentar
  el mecanismo existente sin cambios de código.
- Afecta a: R1, D1, tarea 1 (eliminada).
- Aprobada por el usuario: sí

### A2 — 2026-10-08

- Motivo: el cuerpo de R2 («por encima del umbral») y su escenario R2-E1
  («tantas veces como indica el umbral») se contradecían. Se fija el criterio
  estricto: el circuito abre solo cuando los fallos consecutivos **superan** el
  umbral.
- Cambio: se reformula R2-E1 («más veces seguidas que el umbral»); D2 aclara que
  la apertura se evalúa como `failures > threshold`. Sin cambios en los valores
  por defecto del umbral.
- Afecta a: R2 (R2-E1), D2, tarea 5.
- Aprobada por el usuario: sí
