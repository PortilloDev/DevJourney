# Tareas — Presupuesto global y cuotas que cuentan bien

- [x] 1. Añadir `limits.global_requests_per_day` a `config/rag.php` y a
      `.env.example` (R1)
- [x] 2. Crear el servicio `ChatQuota` sobre `RateLimiter` con comprobación sin
      consumir y consumo atómico de la cuota por IP y del techo global (R1, R5)
- [x] 3. Reordenar el controlador: resolver el post y validar la pregunta antes
      de comprobar la cuota, usando la comprobación sin consumir para el 429
      temprano (R2) (depende de 2)
- [x] 4. Consumir la cuota de forma atómica justo antes de cada llamada al modelo
      de chat en `RagService` y propagar el agotamiento
      (`daily_request_limit` / `daily_global_limit`) (R1, R2, R5) (depende de 2)
- [x] 5. Calcular el presupuesto diario de tokens con agregación en SQL (R4)
- [x] 6. Estimar siempre los tokens cuando el proveedor no devuelve `usage`, en
      `complete()` y en `stream()` (R3)
- [x] 7. Añadir `Retry-After` a todas las respuestas 429 del endpoint (R6)
      (depende de 3, 4)
- [x] 8. Tests de R1–R6, incluyendo el escenario negativo de R2 y el de
      concurrencia de R5 (R1, R2, R3, R4, R5, R6) (depende de 3, 4, 5, 6, 7)
