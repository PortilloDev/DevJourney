# Tareas — Señales de calidad y coste del chat

- [ ] 1. Migración de `ai_usage` con `rating` y `cost_eur` (R1, R4)
- [ ] 2. Devolver el modelo creado desde `recordUsage()` y exponer `usage_id` en
      el resultado de los cuatro caminos de `RagService` (R3)
- [ ] 3. Registrar la latencia por identificador de turno en el controlador (R3)
      (depende de 2)
- [ ] 4. Propagar `usage_id` al JSON y al evento SSE final (R1) (depende de 2)
- [ ] 5. Añadir `rag.pricing` a `config/rag.php` y calcular `cost_eur` al
      registrar el turno (R4) (depende de 1)
- [ ] 6. Ruta y controlador de `POST /api/chat/feedback` con la comprobación de
      pertenencia y la limitación por frecuencia (R1, R2) (depende de 1, 4)
- [ ] 7. Botones de valoración en el widget de chat (R1) (depende de 4, 6)
- [ ] 8. Ampliar `AiUsageOverviewWidget` con las seis cifras del día y mostrar
      valoración y coste en `AiUsageResource` (R5) (depende de 5, 6)
- [ ] 9. Tests de R1–R5, incluyendo el escenario negativo de R2 y los dos
      escenarios de R4 (R1, R2, R3, R4, R5) (depende de 3, 5, 6)
