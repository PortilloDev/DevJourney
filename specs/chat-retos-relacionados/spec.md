---
feature: chat-retos-relacionados
estado: borrador
creada: 2026-10-08
aprobada:
---

# Retos relacionados con cada post

## Por qué

No existe ninguna relación entre posts y retos: `Challenge` no tiene clave hacia
`Post` y el único puente es la tabla polimórfica de etiquetas. Al mismo tiempo los
retos **sí** se indexan en el almacén vectorial y nadie los consulta jamás, porque
el chat filtra estrictamente al post y a sus documentos y ninguna ruta usa el
camino de recuperación sin filtro: se están pagando embeddings de contenido
inalcanzable. Lo que falta para recomendar un reto al final de una respuesta no es
inteligencia, es una relación.

## Requisitos

### R1 — Vínculo entre posts y retos

El sistema DEBE permitir vincular retos publicados a un post desde el panel de administración.

**Escenario R1-E1:** DADO un post y un reto publicados / CUANDO el administrador los vincula desde el panel / ENTONCES la relación queda guardada y es consultable desde el post y desde el reto

### R2 — Retos relacionados en la página del post

El sistema DEBE mostrar los retos vinculados en la página del post, con independencia del chat.

**Escenario R2-E1:** DADO un post publicado con retos vinculados / CUANDO un visitante abre su página / ENTONCES ve los retos vinculados con su título y su enlace, sin necesidad de usar el chat

### R3 — Retos sugeridos junto a la respuesta

El sistema DEBE mostrar los retos vinculados al post junto a una respuesta no rechazada del chat.

**Escenario R3-E1:** DADO un post con retos vinculados / CUANDO el visitante recibe una respuesta no rechazada / ENTONCES se muestran los retos vinculados con enlaces generados por la aplicación

**Escenario R3-E2:** DADO un post con retos vinculados / CUANDO la pregunta se rechaza por no estar relacionada / ENTONCES no se muestra ningún reto

### R4 — Sin títulos ni enlaces inventados

El sistema NO DEBE mostrar ningún reto cuyo título o enlace proceda del texto generado por el modelo.

**Escenario R4-E1:** DADO una respuesta del modelo que menciona en su texto un reto que no existe / CUANDO se entrega al visitante / ENTONCES los retos mostrados son únicamente los vinculados en la base de datos y ninguno procede de ese texto

### R5 — Candidatos por etiquetas compartidas

El sistema DEBERÍA proponer al administrador, como candidatos a vincular, los retos publicados que comparten etiquetas con el post.

**Escenario R5-E1:** DADO un post y un reto publicados que comparten al menos una etiqueta / CUANDO el administrador edita los retos vinculados del post / ENTONCES el reto aparece entre los candidatos propuestos

### R6 — No indexar contenido inalcanzable

El sistema NO DEBE generar embeddings de tipos de contenido que ningún camino de recuperación puede alcanzar.

**Escenario R6-E1:** DADO un reto que pasa a publicado y una configuración en la que los retos no son recuperables / CUANDO se procesa su indexación / ENTONCES no se solicita ningún embedding para su contenido ni se escribe ningún punto en el almacén

## Fuera de alcance

- Que el modelo elija los retos mediante salida estructurada o llamadas a
  herramientas: esta spec resuelve la recomendación sin intervención del modelo.
- Recomendar retos por vecindad vectorial.
- Un chat global sobre todo el contenido del sitio.
- Vincular proyectos o documentos a un post por esta misma vía.

## Enmiendas

(ninguna)
