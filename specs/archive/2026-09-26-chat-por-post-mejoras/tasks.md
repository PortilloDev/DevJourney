# Tareas — Chat por post — mejoras

- [x] 1. Guardar el `url` del payload como ruta relativa (`route(..., absolute: false)`)
      en `RagService::modelUrl()` y en `indexDocument()` (R1)
- [x] 2. Reindexar el contenido existente para actualizar los payloads a rutas
      relativas (R1) (depende de 1)
- [x] 3. `chat.js`: mantener un array `messages` con los turnos y copiar el mensaje
      con `navigator.clipboard` (R2, R3)
- [x] 4. Rediseñar el widget para renderizar el historial y el botón de copiar por
      mensaje (R2, R3) (depende de 3)
- [x] 5. Tests de R1 (payload relativo), R2 (historial) y R3 (botón de copiar)
      (R1, R2, R3) (depende de 1, 4)
