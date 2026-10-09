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
| 2026-10-08 | chat-cache-respuestas | Caché de respuestas del chat por post: reutilización de respuesta y vector por pregunta normalizada, con invalidación al editar el post y turnos de caché registrados sin tokens. | R1, R2, R3, R4, R5 |
| 2026-10-08 | chat-degradacion-automatica | Degradación automática del chat: circuito que abre tras fallos consecutivos del proveedor (con estado en el panel), fallo ruidoso si falta Qdrant fuera de pruebas, detección de desajuste de dimensiones del índice y modelo de embeddings en el payload. | R2, R3, R4, R5, R6 |
| 2026-10-08 | chat-errores-deterministas | Contrato de error determinista en el chat: turnos fallidos registrados, streams cortados marcados como incompletos, reintento único de fallos transitorios sin reintentar streams y timeouts de proveedor configurables (30 s). | R1, R2, R3, R4, R5 |
| 2026-10-08 | chat-evaluacion-calidad | Evaluación de la calidad de las respuestas del chat: comando `ai:eval` con conjunto de casos versionado, métricas de recuperación (acierto y MRR) y respuesta medidas por separado, casos de rechazo, informe en consola/JSON y juez opcional de fundamentación. | R1, R2, R3, R4, R5 |
| 2026-10-09 | chat-memoria-conversacion | Memoria de la conversación en el chat: reformulación de preguntas de seguimiento en autónomas, historial acotado en la petición al modelo, credencial de conversación emitida por el servidor e hilo persistido en sessionStorage. | R1, R2, R3, R4, R5, R6 |
