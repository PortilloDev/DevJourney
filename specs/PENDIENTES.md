# Specs pendientes

Specs abiertas, en el orden de ejecución recomendado. Cada una es independiente y
se aplica con `sdd-apply` por separado; la columna de dependencias indica las que
conviene haber aplicado antes porque la siguiente se apoya en sus decisiones.

Todas nacen del análisis del chat RAG del 2026-10-08.

| # | Feature | Resumen | Depende de |
|---|---------|---------|------------|
| 1 | chat-relevancia-calibrada | Las siglas del dominio dejan de rechazarse y el término del título deja de anular el umbral de similitud. | — |
| 2 | chat-prompt-aislado | Contexto delimitado en el mensaje de sistema, pregunta sola en el de usuario, cota de salida propia y respuesta nunca cortada a media palabra. | — |
| 3 | chat-presupuesto-global | Techo diario del sitio, cuota que solo consumen las peticiones atendidas y contabilidad de tokens fiable en streaming. | — |
| 4 | chat-errores-deterministas | Turno incompleto señalado, turnos fallidos registrados, reintento acotado y tiempos de espera razonables. | — |
| 5 | chat-degradacion-automatica | Los interruptores surten efecto al momento, circuito que se abre solo y guardas para los errores de configuración silenciosos. | 4 |
| 6 | chat-privacidad-registro | IP no reversible, documentos con visibilidad y registro que no duplica el contexto. | 3 |
| 7 | chat-observabilidad-calidad | Valoración del visitante, latencia bien atribuida, coste en euros y las series del día en el panel. | 4 |
| 8 | chat-cache-respuestas | Respuestas y vectores reutilizados, con invalidación por revisión de post. | 7 |
| 9 | chat-evaluacion-calidad | Conjunto de casos y comando `ai:eval` con métricas de recuperación y de respuesta. | 1, 2 |
| 11 | chat-retos-relacionados | Relación entre posts y retos, sugerencia sin intervención del modelo y fin de la indexación de contenido inalcanzable. | — |

Las cuatro primeras son correcciones de comportamiento del chat tal como está hoy
y no dependen entre sí: pueden aplicarse en cualquier orden. De la quinta en
adelante, respetar la columna de dependencias.

Al archivar una spec, su entrada pasa a `ARCHIVE.md` y se retira de esta tabla.
