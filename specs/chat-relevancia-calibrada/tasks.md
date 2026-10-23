# Tareas — Relevancia del chat correctamente calibrada

- [x] 1. Bajar a 2 la longitud mínima de token en `contentTokens()` y ampliar la
      lista de stopwords a una constante de clase documentada (R1)
- [x] 2. Añadir `retrieval.related_min_similarity` a `config/rag.php` y a
      `.env.example`, y leer todos los umbrales sin valor por defecto duplicado en
      `RagService` (R4)
- [x] 3. Sustituir el bypass de `sharesTitleTerm` por la selección entre umbral
      permisivo y estricto, evaluando el score siempre (R2, R3) (depende de 2)
- [x] 4. Tests de R1–R4, incluyendo el escenario negativo de R2 que verifica que no
      se llama al endpoint de chat completions (R1, R2, R3, R4) (depende de 1, 3)
