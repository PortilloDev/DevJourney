# Tareas — Configuración del chat desde el panel

- [x] 1. Sembrar los `SiteSetting` `chat_api_blocked` y `chat_hidden`
      (boolean, grupo `chat`) (R1)
- [x] 2. Helpers de lectura `chatApiBlocked()` y `chatHidden()` en
      `SiteSettingService` (R1) (depende de 1)
- [x] 3. `ChatController`: responder 403 si `chat_api_blocked` (R2) (depende de 2)
- [x] 4. Widget: mostrarse solo si hay claves y no está bloqueado ni oculto
      (R3, R4) (depende de 2)
- [x] 5. Tests de R1 (ajustes), R2 (403), R3 (oculto al bloquear) y R4
      (oculto sin bloquear) (R1, R2, R3, R4) (depende de 2, 3, 4)
