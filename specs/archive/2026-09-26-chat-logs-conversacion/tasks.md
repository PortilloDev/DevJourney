# Tareas — Logs de conversación del chat

- [x] 1. Migración: tabla `ai_conversations` y columnas nuevas en `ai_usage`
      (R1, R2)
- [x] 2. Widget + `chat.js`: generar un `conversation_id` por página y enviarlo
      (R2)
- [x] 3. `RagService`: construir/registrar prompt, answer, retrieval, `refused`,
      etapa y datos de observabilidad (R1) (depende de 1)
- [x] 4. `ChatController`: aceptar `conversation_id` y agrupar el turno (R2)
      (depende de 1, 2)
- [x] 5. Comando `ai:prune-conversations` + programación diaria y
      `rag.retention_days` (R5) (depende de 1)
- [x] 6. Filament: visor `AiConversationResource` (lista con filtros + detalle del
      flujo) (R3) (depende de 1)
- [x] 7. Filament: acciones de borrado por rango de fechas y todas (R4)
      (depende de 6)
- [x] 8. Tests de R1–R5 (R1, R2, R3, R4, R5) (depende de 3, 4, 5, 6, 7)
