# Specs archivadas

Registro histórico de specs cerradas. Cada spec archivada es inmutable; cualquier
cambio futuro de comportamiento requiere una spec nueva que la referencie.

| Fecha | Feature | Resumen | Requisitos |
|-------|---------|---------|------------|
| 2026-09-26 | chat-por-post | Chat por artículo (acotado al post y sus documentos), con rechazo rápido de preguntas no relacionadas sin llamar al LLM y respuestas concisas. | R1, R2, R3, R4, R5, R6, R7, R8 |
| 2026-09-26 | chat-upload-401 | Fix: la subida de documentos en el admin devolvía 401 (firma de Livewire); middleware que normaliza el Host al origen canónico. | R1, R2 |
| 2026-09-26 | chat-por-post-mejoras | Mejoras del chat por post: fuentes con rutas relativas, historial de conversación en la sesión y botón de copiar mensaje. | R1, R2, R3 |
| 2026-09-26 | chat-configuracion-admin | Configuración del chat desde el panel: bloquear la API (403 + widget oculto) y ocultar el widget sin desactivar la API, vía SiteSettings booleanos. | R1, R2, R3, R4 |
| 2026-09-26 | chat-logs-conversacion | Logs de conversación del chat: registro detallado por turno (prompt, respuesta, retrieval, IP, post), visor de conversaciones en el panel, borrado manual y retención automática. | R1, R2, R3, R4, R5 |
