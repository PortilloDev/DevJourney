# Diseño — Evaluación de la calidad de las respuestas

## Contexto técnico

- `RagService::askForPost()` devuelve `answer`, `refused`, `sources` (con título,
  url, encabezado y score de cada fragmento), `tokens_in`, `tokens_out`,
  `context_chunks`, `model` y —desde A3— `context` (el texto recuperado que se
  envía al modelo). Es todo lo que hace falta para medir sin instrumentar
  nada nuevo.
- `RagService::recordUsage()` ya empieza con `if ($ip === null) return;`, de modo
  que invocar el servicio sin IP no escribe en `ai_usage`.
- `RagService::UNRELATED_MESSAGE` es una constante de clase: comparar con ella es
  la forma exacta de detectar un rechazo.
- `App\Console\Commands\PruneConversations` establece el patrón de comando del
  proyecto, registrado en `routes/console.php`.
- Los tests corren sobre SQLite en memoria y con el almacén vectorial en memoria;
  la evaluación, en cambio, se ejecuta contra el entorno real para medir lo que de
  verdad ocurre en producción.
- `config/rag.php` es el sitio donde viven los parámetros ajustables del chat.

## Decisiones

- **D1.** Los casos viven en `tests/Eval/cases.json`, versionados en el
  repositorio. Cada caso tiene `post_slug`, `question`, `expects` (`answer` o
  `refusal`), `must_include` (lista de términos que la respuesta correcta debe
  contener) y `expected_source` (título o encabezado del fragmento que debería
  recuperarse). Motivo: un fichero plano es revisable en un PR y no necesita
  esquema de base de datos.
- **D2.** La corrección de la respuesta se mide con `must_include`, no con
  comparación literal ni con similitud semántica. Motivo: la respuesta varía de
  una ejecución a otra; lo que debe permanecer es que mencione los hechos
  correctos. Es un criterio imperfecto y barato, y como tal se documenta.
- **D3.** El acierto de recuperación comprueba si `expected_source` aparece entre
  las fuentes devueltas; el MRR usa la posición en la que aparece. Un caso que
  falla la recuperación no cuenta como fallo de respuesta. Motivo: R2; son dos
  arreglos distintos y mezclarlos impide saber cuál toca.
- **D4.** El comando es `php artisan ai:eval`, con las opciones `--judge` para
  activar el juez y `--min=` para el umbral de corte del código de salida.
  Motivo: R1-E2 y R5, siguiendo el patrón de comandos del proyecto.
- **D5.** El comando llama a `RagService::askForPost()` **sin IP** y con
  `$useCache = false`, de modo que `recordUsage()` no escribe nada y la caché de
  respuestas ni se lee ni se escribe. Motivo: R4 y A1; el flag opcional evita
  añadir una bandera global o un modo especial al servicio.
- **D6.** El informe se imprime como tabla en consola y se guarda en
  `storage/app/eval/{timestamp}.json` con el resultado por caso y el resumen. El
  código de salida compara `--min`/`rag.eval.min_score` con la tasa de acierto
  global (A3). Motivo: comparar dos ejecuciones es el uso principal y la consola
  no se guarda.
- **D7.** El juez es una llamada a `LlmClient::complete()` con el modelo
  `rag.eval.judge_model`, que por defecto toma `rag.chat_model`; `complete()` y
  `stream()` aceptan un `?string $model = null` que sobreescribe el configurado
  (A2). Recibe la pregunta, el `context` recuperado (A3) y la respuesta, y
  devuelve una puntuación de fundamentación de 0 a 2. Motivo: R5; un modelo
  barato basta para una tendencia, y se documenta que el juez también se equivoca
  y no es una verdad.
- **D8.** El conjunto arranca con al menos veinte casos de respuesta y diez de
  rechazo, construidos sobre posts ya publicados. Motivo: por debajo de esa
  cantidad el ruido supera a la señal; por encima, el coste de mantenerlo supera a
  lo que aporta en un blog personal.

## Cambios de datos

- Sin cambios de esquema.
- Nuevo fichero versionado `tests/Eval/cases.json`.
- Nuevas claves de configuración `rag.eval.judge_model` y `rag.eval.min_score`.
- Nuevo directorio de salida `storage/app/eval/`, ignorado por git.

## Seguridad

- El comando consume cuota real del proveedor: se documenta en `README.md` que no
  debe lanzarse de forma automática sin control, y `--min` existe para poder
  ejecutarlo en una comprobación manual previa a un despliegue.
- Los casos de evaluación solo contienen contenido ya publicado.
- Al no registrar turnos, la evaluación no distorsiona las cuotas, el coste
  registrado ni las series del panel.

## Preguntas abiertas

(ninguna)
