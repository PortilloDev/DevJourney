# Tareas — Evaluación de la calidad de las respuestas

- [x] 1. Definir el formato de caso y crear `tests/Eval/cases.json` con los casos
      iniciales de respuesta y de rechazo (R1, R3)
- [x] 2. Añadir `rag.eval.judge_model` y `rag.eval.min_score` a `config/rag.php`
      (R1, R5)
- [x] 3. Comando `ai:eval` que recorre los casos llamando a `askForPost()` sin IP
      y con `$useCache = false` (R1, R4, A1) (depende de 1)
- [x] 4. Métricas de recuperación (acierto y MRR) separadas de las de respuesta
      (R2, A3) (depende de 3)
- [x] 5. Evaluación de los casos de rechazo contra el mensaje predefinido (R3)
      (depende de 3)
- [x] 6. Informe en consola y en `storage/app/eval/`, con código de salida según
      `--min` sobre la tasa de acierto global (R1, A3) (depende de 4, 5)
- [x] 7. Añadir `?string $model` a `LlmClient::complete()`/`stream()` y
      propagarlo en los clientes; opción `--judge` con la puntuación de
      fundamentación por caso vía `rag.eval.judge_model` sobre el `context`
      recuperado (R5, A2, A3) (depende de 6)
- [x] 8. Documentar en `README.md` el coste de ejecutar la evaluación y cuándo
      lanzarla (R1) (depende de 6)
- [x] 9. Tests del comando con proveedor simulado, incluyendo el escenario
      negativo de R4 y que no se lee ni escribe la caché (R1, R2, R3, R4, A1, A3)
      (depende de 6)
