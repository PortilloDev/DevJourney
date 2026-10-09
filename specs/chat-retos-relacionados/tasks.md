# Tareas — Retos relacionados con cada post

- [ ] 1. Migración de la tabla pivote `challenge_post` y relaciones en `Post` y
      `Challenge` (R1)
- [ ] 2. Relation manager de Filament para vincular retos desde el post (R1)
      (depende de 1)
- [ ] 3. Proponer como candidatos los retos publicados que comparten etiquetas con
      el post (R5) (depende de 2)
- [ ] 4. Sección de retos relacionados en la página del post (R2) (depende de 1)
- [ ] 5. Añadir `related` al JSON y al evento SSE final, solo en respuestas no
      rechazadas (R3, R4) (depende de 1)
- [ ] 6. Pintar los retos relacionados junto a la respuesta en el widget (R3)
      (depende de 5)
- [ ] 7. Añadir `rag.indexing.source_types` a `config/rag.php` y a `.env.example`,
      y respetarlo en `IndexContentJob` (R6)
- [ ] 8. Comando de limpieza de los puntos de retos ya indexados (R6)
      (depende de 7)
- [ ] 9. Tests de R1–R6, incluyendo los escenarios negativos de R4 y R6 (R1, R2,
      R3, R4, R5, R6) (depende de 4, 5, 6, 7)
