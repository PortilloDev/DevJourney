# Diseño — Logs de conversación del chat

## Contexto técnico

- `App\Models\AiUsage` — ya registra por turno: `ip_address`, `user_id`,
  `question`, `model`, `tokens_in`, `tokens_out`, `context_chunks`, `latency_ms`,
  `sources`. Se usa además para el cupo diario del `ChatController`.
- `App\Services\Rag\RagService` — `askForPost()` / `askPostStream()` y
  `recordUsage()`; hoy **no** guarda el prompt ni la respuesta.
- Widget: `resources/views/components/chat-widget.blade.php` + `resources/js/chat.js`.
- Filament: `App\Filament\Resources\AiUsageResource` (lista de turnos).

## Decisiones

- **D1.** `AiUsage` pasa a ser el **turno enriquecido**: se añaden `conversation_id`
  (FK), `post_id`, `prompt` (texto del prompt completo), `answer` (texto), `refused`
  (bool), `refused_stage` (local|embedding), `provider` y `embedding_model`. El campo
  `sources` pasa a guardar el **retrieval** (`título, url, score, heading`). Se
  mantiene como base del cupo diario. Motivo: una sola tabla por turno, sin
  duplicar inserts.
- **D2.** Nueva tabla `ai_conversations` (id, `session_id`, `ip_address`, `post_id`,
  `started_at`, `last_activity_at`, `turns_count`, `tokens_total`) para agrupar.
  Motivo: R2.
- **D3.** El widget genera un `conversation_id` (uuid) por carga de página y lo
  envía en cada pregunta; el controller lo propaga a `RagService`. Motivo: agrupar
  la sesión de conversación (decisión del usuario).
- **D4.** `RagService` construye el prompt como variable y registra prompt, answer,
  retrieval, `refused` y etapa del guardrail. Motivo: R1.
- **D5.** Campos de observabilidad extra: `provider`, `embedding_model`, `top_k`,
  `refused_stage`. Motivo: análisis (petición del usuario).
- **D6.** Visor Filament: `AiConversationResource` (lista con filtros por fecha,
  post e IP) y vista de detalle con la relación de turnos. Motivo: R3.
- **D7.** Borrado manual: acciones en el panel “borrar por rango de fechas” y
  “borrar todas”, con confirmación. Motivo: R4.
- **D8.** Retención automática: comando `ai:prune-conversations` programado a diario;
  `rag.retention_days` (por defecto 30). Motivo: R5.

## Cambios de datos

- Migración: columnas nuevas en `ai_usage` (`conversation_id`, `post_id`, `prompt`,
  `answer`, `refused`, `refused_stage`, `provider`, `embedding_model`, `top_k`).
- Nueva tabla `ai_conversations`.
- `rag.retention_days` en `config/rag.php`.

## Seguridad

- El visor vive en el panel autenticado.
- `prompt` incluye el contexto y la pregunta del visitante (posible PII): retención
  limitada + borrado manual.

## Preguntas abiertas

(ninguna)
