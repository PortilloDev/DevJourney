# Tareas — Memoria de la conversación en el chat

- [x] 1. Añadir `rag.rewrite.model`, `rag.rewrite.max_tokens` y
      `rag.conversation.max_turns` a `config/rag.php` y a `.env.example` (R1, R3)
- [x] 2. Migración de `ai_usage` con `rewritten_question` (R1)
- [x] 3. Reformulador de preguntas de seguimiento, con degradación a la pregunta
      original si falla y sin invocarse en el primer turno (R1, R2, R4)
      (depende de 1)
- [x] 4. Resolver relevancia, recuperación y caché sobre la pregunta reformulada y
      registrar ambas versiones (R1) (depende de 2, 3)
- [x] 5. Incluir los últimos turnos como mensajes en la petición al modelo,
      respetando el máximo configurado (R3) (depende de 4)
- [x] 6. Emitir el identificador de conversación en el servidor con credencial
      firmada y validarla en cada petición (R6)
- [x] 7. Persistir el hilo y la credencial en `sessionStorage` por slug de post en
      el widget (R5) (depende de 6)
- [x] 8. Tests de R1–R6, incluyendo los escenarios negativos de R2 y R6 (R1, R2,
      R3, R4, R5, R6) (depende de 4, 5, 6, 7)
