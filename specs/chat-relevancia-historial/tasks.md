# Tareas — Relevancia por cobertura e historial limpio

- [x] 1. Añadir `retrieval.confident_similarity`, `conversation.max_tokens` y
      `log_full_prompt` a `config/rag.php` y a `.env.example` (R1, R4, R5)
- [x] 2. Implementar la puerta de cobertura por título con sus listas de términos
      genéricos y léxico de dominio, y tests de los casos tortilla, primera
      pregunta, CQRS/DDD y R3 (R1) (depende de 1)
- [x] 3. Añadir `NO_ANSWER` al prompt, detectarlo en JSON y SSE, marcar el turno
      como rechazado y excluirlo del historial (R2, R3) (depende de 1)
- [x] 4. Acotar el historial por tokens y descontarlo del presupuesto de entrada
      del contexto (R4) (depende de 1, 3)
- [x] 5. Guardar el prompt completo y mostrarlo en el visor, actualizando
      `ChatPrivacyTest` a la nueva decisión (R5) (depende de 1)
- [x] 6. Ejecutar suite completa, Pint y PHPStan (R1, R2, R3, R4, R5)
      (depende de 2, 3, 4, 5)
