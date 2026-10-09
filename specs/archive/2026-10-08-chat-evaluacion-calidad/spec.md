---
feature: chat-evaluacion-calidad
estado: archivada
creada: 2026-10-08
aprobada: 2026-10-08
---

# Evaluación de la calidad de las respuestas

## Por qué

Los veintitrés tests de feature del chat verifican el comportamiento del endpoint
—interruptores, límites, rechazos, formato— pero ninguno mide si la respuesta es
correcta. Hoy no hay nada que avise de que un cambio de prompt, de `top_k`, de
umbral o de modelo ha empeorado las respuestas: la única forma de enterarse es
leerlas a mano. Las specs de relevancia y de prompt cambian precisamente esas
piezas, así que sin una medida de referencia se trabaja a ciegas.

## Requisitos

### R1 — Comando de evaluación con informe

El sistema DEBE ofrecer un comando que ejecute el conjunto de casos de evaluación y publique un informe con las métricas obtenidas.

**Escenario R1-E1:** DADO un conjunto de casos de evaluación versionado en el repositorio / CUANDO se ejecuta el comando de evaluación / ENTONCES se publica un informe con el acierto de recuperación, el MRR y la proporción de respuestas correctas

**Escenario R1-E2:** DADO un mínimo exigido configurado y un resultado por debajo de ese mínimo / CUANDO termina el comando / ENTONCES devuelve un código de salida distinto de cero

### R2 — Recuperación y respuesta medidas por separado

El sistema DEBE distinguir en el informe los fallos de recuperación de los fallos de respuesta.

**Escenario R2-E1:** DADO un caso cuyo fragmento esperado no aparece entre los recuperados / CUANDO se evalúa / ENTONCES el caso se contabiliza como fallo de recuperación y no como fallo de respuesta

### R3 — Casos de rechazo

El sistema DEBE incluir en la evaluación casos de preguntas fuera de alcance y considerarlos superados solo si el sistema las rechaza.

**Escenario R3-E1:** DADO un caso marcado como fuera de alcance / CUANDO el sistema responde con contenido en lugar del mensaje predefinido / ENTONCES el caso se contabiliza como fallado

### R4 — La evaluación no contamina el histórico

El sistema NO DEBE registrar los turnos de una ejecución de evaluación en el histórico de uso del chat.

**Escenario R4-E1:** DADO el histórico de uso con un número conocido de turnos / CUANDO termina una ejecución completa de la evaluación / ENTONCES el histórico conserva exactamente ese mismo número de turnos

### R5 — Juez automático bajo demanda

El sistema PUEDE puntuar la fundamentación de cada respuesta con un modelo evaluador cuando se solicita explícitamente al ejecutar el comando.

**Escenario R5-E1:** DADO la opción de juez activada en la invocación / CUANDO termina la evaluación / ENTONCES el informe incluye una puntuación de fundamentación por caso

## Fuera de alcance

- Ejecutar la evaluación en la integración continua: gasta cuota real del
  proveedor y la decisión de cuándo correrla queda fuera de esta spec.
- Generar automáticamente casos de evaluación a partir del contenido publicado.
- Usar las valoraciones de los visitantes como casos (requiere la spec
  `chat-observabilidad-calidad` y un criterio de selección propio).
- Comparar varios modelos o varias versiones de prompt en una misma ejecución.

## Enmiendas

### A1 — 2026-10-08

- Motivo: con la caché de respuestas activa, una segunda ejecución del mismo caso
  servía la respuesta almacenada y no medía los cambios de prompt, `top_k`,
  umbral o modelo; la evaluación mediría datos obsoletos hasta el TTL o la
  edición del post, justo lo que la spec quiere detectar.
- Cambio: `askForPost()` incorpora un flag `$useCache` (por defecto `true`);
  `ai:eval` lo invoca con `false`, de modo que ni lee ni escribe la caché de
  respuestas.
- Afecta a: R1, R2, D5, tareas 3 y 9
- Aprobada por el usuario: sí

### A2 — 2026-10-08

- Motivo: D7 exige que el juez use `rag.eval.judge_model`, pero `LlmClient` no
  acepta modelo y `OpenAiLlmClient` lo fija desde `rag.chat_model`.
- Cambio: `LlmClient::complete()` y `stream()` aceptan un `?string $model = null`
  que sobreescribe el modelo configurado; `OpenAiLlmClient` usa
  `$model ?? config('rag.chat_model')` y `CircuitBreakerLlmClient` lo propaga.
  El juez llama con `rag.eval.judge_model`.
- Afecta a: R5, D7, tarea 7
- Aprobada por el usuario: sí

### A3 — 2026-10-08

- Motivo: D7 exige que el juez reciba el contexto recuperado, pero
  `askForPost()` no lo devolvía (solo `sources` y `context_chunks`); además el
  diseño no fijaba contra qué métrica compara `--min`/`rag.eval.min_score`.
- Cambio: `askForPost()` devuelve además la clave `context` (el texto recuperado
  que se envía al modelo) y el juez la usa para puntuar la fundamentación. El
  umbral `--min`/`rag.eval.min_score` se compara con la tasa de acierto global
  (casos correctos / total; una respuesta cuenta como correcta solo si recuperó
  la fuente esperada y contiene todos los `must_include`; un rechazo, si fue
  rechazado).
- Afecta a: R1, R2, R5, D6, D7, tareas 4, 6, 7 y 9
- Aprobada por el usuario: sí
