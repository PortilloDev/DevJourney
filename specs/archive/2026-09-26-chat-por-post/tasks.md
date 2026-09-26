# Tareas — Chat contextual por post

- [x] 1. Implementar el filtro por payload en `QdrantVectorStore::search()` (enviar
      `filter` a Qdrant) y crear el payload index de `source_id` y `post_id` en
      `ensureCollection()` (R3)
- [x] 2. Añadir a `RagService` la recuperación acotada al post y sus documentos
      vinculados (filtro `should`) y el system prompt + cota de concisión
      (R3, R6) (depende de 1)
- [x] 3. Asegurar el indexado automático al publicar (mover la comprobación de
      configuración al `IndexContentJob`) (R7)
- [x] 4. Guardrail en dos etapas: comprobación local primero y embeddings como
      respaldo; respuesta predefinida en código sin invocar al LLM (R4, R5, R8)
      (depende de 2)
- [x] 5. Hacer post-scoped el endpoint `POST /api/chat`: acepta el post, valida que
      exista y esté publicado, y delega con el filtro (R3) (depende de 2)
- [x] 6. Mover el widget `<x-chat-widget>` a la página del post y retirarlo de la
      home (R1, R2)
- [x] 7. Actualizar `resources/js/chat.js` para enviar el slug del post y ajustar
      los textos del widget (R1) (depende de 5, 6)
- [x] 8. Tests de R1–R8 incluyendo los escenarios negativos (R1, R2, R3, R4, R5, R6,
      R7, R8) (depende de 2, 3, 4, 5)
- [x] 9. Refinar el guardrail: peso a los términos del título y umbral semántico
      0.30 (R3, R8) (enmienda A2)
