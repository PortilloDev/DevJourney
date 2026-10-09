# Tareas — Degradación automática y guardas de configuración

- [x] 1. Exigir Qdrant fuera del entorno de pruebas al resolver `VectorStore` (R4)
- [x] 2. Comparar el tamaño de vector de la colección existente con el configurado
      en `ensureCollection()` y abortar si difieren (R5)
- [x] 3. Añadir `embedding_model` al payload de contenidos y de documentos (R6)
- [x] 4. Añadir `rag.circuit.threshold` y `rag.circuit.cooldown_seconds` a
      `config/rag.php` y a `.env.example` (R2)
- [x] 5. Implementar el decorador de circuito sobre `LlmClient` y registrarlo en
      `AppServiceProvider` (R2) (depende de 4; apertura estricta, A2)
- [x] 6. Mostrar el estado del circuito en `AiUsageOverviewWidget` (R3)
      (depende de 5)
- [x] 7. Tests de R2–R6, incluyendo el escenario negativo de R4 y los dos
      escenarios de R2 (R2, R3, R4, R5, R6) (depende de 1, 2, 3, 5, 6)
