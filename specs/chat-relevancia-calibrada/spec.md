---
feature: chat-relevancia-calibrada
estado: borrador
creada: 2026-10-08
aprobada:
---

# Relevancia del chat correctamente calibrada

## Por qué

La comprobación léxica descarta los tokens de menos de cuatro caracteres, así que
en un blog de backend elimina justo los términos más distintivos del dominio
(DDD, CQRS, API, SQL, JWT): preguntas legítimas se rechazan antes de llegar al
modelo. En el sentido contrario, compartir un término con el título del post
desactiva por completo el umbral de similitud, de modo que cualquier pregunta que
incluya una palabra del título alcanza al LLM y el prompt de sistema queda como
única defensa. La puerta más barata del sistema está mal calibrada en las dos
direcciones.

## Requisitos

### R1 — Siglas del dominio en la comprobación léxica

El sistema DEBE tener en cuenta los términos de dos y tres caracteres al calcular el solapamiento léxico entre la pregunta y el post.

**Escenario R1-E1:** DADO un post publicado cuyo cuerpo trata sobre DDD / CUANDO el visitante pregunta «¿para qué sirve DDD?» / ENTONCES la pregunta no se rechaza en la comprobación local y el sistema continúa con la recuperación

**Escenario R1-E2:** DADO un post publicado que no menciona CQRS / CUANDO el visitante pregunta «¿qué es CQRS?» / ENTONCES la pregunta se rechaza en la comprobación local y se devuelve el mensaje predefinido

### R2 — El término del título no desactiva el umbral semántico

El sistema NO DEBE omitir la comprobación de similitud mínima por el hecho de que la pregunta comparta un término con el título del post.

**Escenario R2-E1:** DADO un post cuyo título contiene «hexagonal» y cuyos chunks no superan ningún umbral para la pregunta / CUANDO el visitante pregunta por un tema ajeno al post usando la palabra «hexagonal» / ENTONCES se devuelve el mensaje predefinido y no se realiza ninguna petición al endpoint de chat completions

### R3 — El término del título sigue rescatando preguntas relacionadas

El sistema DEBE aplicar un umbral de similitud más permisivo, en lugar del estricto, cuando la pregunta comparte un término distintivo con el título del post.

**Escenario R3-E1:** DADO un post en inglés cuyo título contiene un término técnico y una pregunta en español que reutiliza ese término, cuyo mejor score queda entre el umbral permisivo y el estricto / CUANDO se resuelve la relevancia / ENTONCES la pregunta se considera relacionada y se responde con el contexto recuperado

### R4 — Un único origen para los umbrales

El sistema DEBE tomar todos los umbrales de relevancia exclusivamente de la configuración, sin valores por defecto duplicados en el código del servicio.

**Escenario R4-E1:** DADO un umbral de similitud mínima configurado con un valor distinto del valor por defecto / CUANDO se resuelve la relevancia de una pregunta cuyo mejor score queda por debajo de ese valor / ENTONCES la pregunta se rechaza, demostrando que se usa el valor configurado

## Fuera de alcance

- Sustituir la etapa léxica por Meilisearch o por un centroide vectorial por post.
- Cambiar el proveedor o el modelo de embeddings.
- El orden y la delimitación del prompt (spec `chat-prompt-aislado`).
- La memoria de la conversación y las preguntas de seguimiento (spec `chat-memoria-conversacion`).

## Enmiendas

(ninguna)
