# Tareas — Caché de respuestas del chat

- [x] 1. Añadir `rag.cache.answer_ttl` y `rag.cache.embedding_ttl` a
      `config/rag.php` y a `.env.example` (R1, R5)
- [x] 2. Migración de `ai_usage` con `cache_hit` (R4)
- [x] 3. Contador de revisión por post, incrementado en el hook `saving` del
      post (R3) (depende de 1)
- [x] 4. Consultar y almacenar la respuesta en `askForPost()`, antes de resolver
      la relevancia y solo para turnos no rechazados (R1, R3) (depende de 3)
- [x] 5. Emitir la respuesta almacenada por el canal de streaming en
      `askPostStream()` (R2) (depende de 4)
- [x] 6. Registrar el turno con `cache_hit` y cero tokens (R4)
      (depende de 2, 4)
- [x] 7. Decorador de `EmbeddingService` con caché del vector por pregunta y
      modelo (R5) (depende de 1)
- [x] 8. Tests de R1–R5, incluyendo el escenario negativo de R3 (R1, R2, R3, R4,
      R5) (depende de 4, 5, 6, 7)
