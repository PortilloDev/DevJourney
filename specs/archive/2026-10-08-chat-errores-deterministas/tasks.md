# Tareas — Contrato de error determinista en el chat

- [x] 1. Añadir `rag.timeouts.complete` y `rag.timeouts.stream` a `config/rag.php`
      y a `.env.example`, y usarlos en `OpenAiLlmClient` (R5)
- [x] 2. Añadir el reintento acotado a `complete()` y dejar `stream()` sin
      reintento (R3, R4) (depende de 1)
- [x] 3. Registrar el turno fallido en `RagService` con `refused_stage = 'error'`
      (R2)
- [x] 4. Emitir `incomplete` en el evento SSE final, distinguiendo el corte tras
      haber emitido texto del cierre normal (R1, R4) (depende de 3)
- [x] 5. Marcar el turno incompleto en el widget conservando el texto recibido
      (R1) (depende de 4)
- [x] 6. Comprobar y documentar en `README.md` que los tiempos de espera de nginx
      y PHP-FPM superan a los del cliente LLM (R5) (depende de 1)
- [x] 7. Tests de R1–R5, incluyendo el escenario negativo de R4 (R1, R2, R3, R4,
      R5) (depende de 2, 3, 4)
