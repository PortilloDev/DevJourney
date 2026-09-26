---
feature: chat-por-post
estado: archivada
creada: 2026-09-26
aprobada: 2026-09-26
---

# Chat contextual por post

## Por qué

La home tiene hoy un chat global que responde sobre todo el contenido del sitio y
no debe estar ahí. Cada post genera dudas concretas: un chat acotado al post, que
responda únicamente sobre ese post (y sus documentos vinculados), ayuda al lector
sin invitar a preguntas fuera de contexto ni gastar tokens del LLM en consultas
irrelevantes.

## Requisitos

### R1 — Chat en la página del post

El sistema DEBE mostrar un chat en la página de detalle de cada post publicado.

**Escenario R1-E1:** DADO un post publicado / CUANDO un visitante abre `/journal/{slug}` / ENTONCES se renderiza el widget de chat asociado a ese post.

### R2 — El chat no aparece fuera del post

El sistema NO DEBE mostrar el chat en la página de inicio ni en páginas distintas al detalle de un post.

**Escenario R2-E1:** DADO un visitante / CUANDO abre la home `/` / ENTONCES no se renderiza ningún widget de chat.

### R3 — Respuestas acotadas al post

El sistema DEBE construir la respuesta usando exclusivamente el contenido del post en cuya página se pregunta y los documentos vinculados a ese post.

**Escenario R3-E1:** DADO el post A (con un documento vinculado) y el post B indexados / CUANDO se pregunta en la página del post A por información que solo existe en el post B / ENTONCES la pregunta se trata como fuera de contexto y la respuesta no usa contenido del post B.

### R4 — Respuesta predefinida para preguntas no relacionadas

El sistema DEBE devolver un mensaje predefinido en código cuando la pregunta no esté relacionada con el post.

**Escenario R4-E1:** DADO un post sobre arquitectura / CUANDO el visitante pregunta por un tema ajeno al post / ENTONCES recibe el mensaje predefinido de pregunta no válida.

### R5 — Sin llamada al LLM para preguntas no relacionadas

El sistema NO DEBE invocar la API del LLM cuando la pregunta no está relacionada con el post.

**Escenario R5-E1:** DADO un post y una pregunta no relacionada / CUANDO se procesa la pregunta / ENTONCES no se realiza ninguna petición al endpoint de chat completions.

### R6 — Respuestas concisas

El sistema DEBE producir respuestas concisas, de longitud aproximada a dos o tres «tweets», mediante un prompt de sistema específico y una cota de tokens de salida.

**Escenario R6-E1:** DADO una pregunta relacionada con el post / CUANDO el sistema consulta al LLM / ENTONCES la petición incluye un prompt de sistema que exige concisión y un `max_tokens` de salida no superior a la cota configurada.

### R7 — Indexado automático al publicar

El sistema DEBE indexar un post en el vector store cuando pasa al estado publicado, sin requerir acciones adicionales.

**Escenario R7-E1:** DADO un post en borrador / CUANDO pasa al estado publicado / ENTONCES sus chunks quedan disponibles en el vector store para el chat de su página.

### R8 — Relevancia sin llamadas innecesarias

El sistema DEBERÍA determinar si la pregunta está relacionada con el post mediante una comprobación local, sin invocar al proveedor de embeddings, cuando esa comprobación sea concluyente.

**Escenario R8-E1:** DADO una pregunta claramente ajena al post / CUANDO la comprobación local la clasifica como no relacionada / ENTONCES no se invoca al proveedor de embeddings y se devuelve la respuesta predefinida.

**Escenario R8-E2:** DADO una pregunta ambigua / CUANDO la comprobación local no permite decidir / ENTONCES se usa el proveedor de embeddings para decidir la relevancia.

## Fuera de alcance

- Chat sobre challenges o projects (solo posts en esta spec).
- Chat global sobre todo el sitio (se retira).
- Cambiar de proveedor o de modelo de embeddings/LLM.
- Interfaz de administración del chat.

## Enmiendas

### A1 — 2026-09-26
- Motivo: evitar la latencia añadida en la primera pregunta y aprovechar el hook de indexado que ya existe al publicar.
- Cambio: R7 pasa de «indexado al vuelo en la primera pregunta» a «indexado automático al publicar»; se retira la decisión D9 (indexado al vuelo) y la tarea 3 correspondiente.
- Afecta a: R7, D9, tarea 3
- Aprobada por el usuario: sí

### A2 — 2026-09-26
- Motivo: el guardrail rechazaba preguntas **relacionadas** cuyo score quedaba justo por debajo del umbral (p. ej. 0.333 < 0.35) y con mezcla de idiomas (pregunta en español, artículo en inglés).
- Cambio: el guardrail da peso a los términos distintivos del **título** (una pregunta que comparte un término del título se considera relacionada) y se baja el umbral semántico por defecto `min_similarity` a 0.30.
- Afecta a: D8, tarea 4 (guardrail), tarea 8 (tests)
- Aprobada por el usuario: sí
