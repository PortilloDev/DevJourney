# Tareas — Presupuesto global y cuotas que cuentan bien

- [ ] 1. Añadir `limits.global_requests_per_day` a `config/rag.php` y a
      `.env.example` (R1)
- [ ] 2. Reordenar el controlador: validar post y pregunta antes de consumir
      cuota (R2)
- [ ] 3. Sustituir `Cache::add` + `Cache::increment` por `RateLimiter::attempt()`
      en la cuota por IP (R5) (depende de 2)
- [ ] 4. Añadir el contador global diario y la respuesta 429
      `daily_global_limit` (R1) (depende de 1, 3)
- [ ] 5. Calcular el presupuesto diario de tokens con agregación en SQL (R4)
- [ ] 6. Estimar siempre los tokens cuando el proveedor no devuelve `usage`, en
      `complete()` y en `stream()` (R3)
- [ ] 7. Añadir `Retry-After` a todas las respuestas 429 del endpoint (R6)
      (depende de 3, 4)
- [ ] 8. Tests de R1–R6, incluyendo el escenario negativo de R2 y el de
      concurrencia de R5 (R1, R2, R3, R4, R5, R6) (depende de 4, 5, 6, 7)
