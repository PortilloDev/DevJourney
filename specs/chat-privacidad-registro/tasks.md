# Tareas — Privacidad del registro del chat

- [ ] 1. Derivar la IP con HMAC en un único método y usarlo al registrar el uso,
      al crear la conversación y en la clave de caché de la cuota (R1, R2)
- [ ] 2. Migración de datos que recalcula el valor derivado de las filas
      existentes (R1) (depende de 1)
- [ ] 3. Migración y campo en el formulario de Filament para
      `documents.is_public` (R3)
- [ ] 4. No indexar los documentos no públicos y borrar sus puntos al marcarlos
      como tales (R3) (depende de 3)
- [ ] 5. Añadir `point_id` a las fuentes registradas y retirar el bloque de
      contexto del prompt almacenado (R4)
- [ ] 6. Mostrar el identificador de fragmento en el visor de conversaciones del
      panel (R4) (depende de 5)
- [ ] 7. Documentar en `README.md` el efecto de rotar `APP_KEY` sobre las cuotas
      del día (R1) (depende de 1)
- [ ] 8. Tests de R1–R4, incluyendo los dos escenarios negativos de R3 (R1, R2,
      R3, R4) (depende de 1, 4, 5)
