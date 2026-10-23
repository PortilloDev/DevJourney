# Tareas — Prompt aislado y respuesta nunca truncada

- [x] 1. Añadir `general_chat.max_answer_tokens` a `config/rag.php` y a
      `.env.example`, y usarlo como cota de salida en `ask()` y `askStream()` (R4)
- [x] 2. Centralizar la construcción de mensajes en `RagService`: contexto
      delimitado en el mensaje de sistema y pregunta sola en el de usuario, en los
      cuatro caminos (R1, R2)
- [x] 3. Recortar el contexto por score ascendente hasta respetar
      `max_input_tokens` (R5) (depende de 2)
- [x] 4. Leer `finish_reason` en `complete()` y en `stream()` y devolverlo en el
      resultado del cliente LLM (R3)
- [x] 5. Recortar a la última frase completa y exponer `truncated` en el JSON y en
      el evento SSE final (R3) (depende de 4)
- [x] 6. Marcar el turno truncado en el widget de chat (R3) (depende de 5)
- [x] 7. Tests de R1–R5, incluyendo el escenario negativo de R2 y los dos
      escenarios de R3 (R1, R2, R3, R4, R5) (depende de 2, 3, 5)
