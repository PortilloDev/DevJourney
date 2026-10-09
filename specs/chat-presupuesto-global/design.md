# Diseño — Presupuesto global y cuotas que cuentan bien

## Contexto técnico

`App\Http\Controllers\Api\ChatController` aplica hoy, en este orden:

1. `SiteSettingService::chatApiBlocked()` → 403.
2. `requestLimitReached($ip)` → `Cache::add` + `Cache::increment` sobre
   `ai:req:{ip}:{fecha}` contra `rag.limits.requests_per_day` (20).
3. `tokenBudgetReached($ip)` → `AiUsage::query()->where(...)->get()->sum(...)`
   contra `rag.limits.tokens_per_day` (20000).
4. Resolución del post publicado → 404, y validación de la pregunta → 422.

El middleware `throttle:10,1` de `routes/api.php` cubre el límite por minuto.

Dos consecuencias del orden actual: el contador del paso 2 se incrementa antes de
que los pasos del 4 validen nada, y `Cache::add` seguido de `Cache::increment` no
es una operación atómica, de modo que dos peticiones simultáneas pueden pasar
ambas el límite.

`App\Services\Llm\OpenAiLlmClient::stream()` pide
`stream_options.include_usage`, que OpenAI respeta pero no todos los proveedores
compatibles. Su cálculo actual es
`(int) ($usage['completion_tokens'] ?? ($tokensIn === 0 ? 0 : $this->estimateTokens($content)))`:
si no llega `usage`, `$tokensIn` vale 0 y la estimación nunca se usa, así que el
turno se registra con cero tokens. `estimateTokens()` ya existe
(`ceil(mb_strlen / 4)`).

Redis es el store de caché en producción (`AGENTS.md`), así que un contador
compartido entre procesos está disponible sin infraestructura nueva.

## Decisiones

- **D1.** El techo global es un contador diario en caché, clave
  `ai:global:{fecha}` con TTL de 24 horas, comparado contra
  `rag.limits.global_requests_per_day` (`RAG_GLOBAL_REQUESTS_PER_DAY`, por defecto
  `500`). Motivo: acota el gasto máximo del día con la infraestructura que ya hay.
- **D2.** El contador global se incrementa en el mismo punto que la cuota por
  visitante: cuando la petición va a generar una respuesta del modelo. Motivo: el
  techo mide gasto, y lo que gasta es la llamada al modelo.
- **D3.** Orden nuevo del controlador: flags → resolución del post → validación de
  la pregunta → cuota por IP → techo global → presupuesto de tokens → delegación
  en `RagService`. Motivo: R2; una petición malformada no debe costarle cuota a
  nadie.
- **D4.** Una pregunta rechazada por la comprobación **local** de relevancia no
  consume cuota de peticiones, porque no cuesta ninguna llamada externa. El
  rechazo en la etapa de **embeddings** sí ha gastado una llamada, y ese consumo se
  refleja en el presupuesto de tokens, no en el de peticiones. Motivo: cada
  contador mide lo que de verdad cuesta.
- **D5.** `Illuminate\Support\Facades\RateLimiter::attempt()` sustituye a
  `Cache::add` + `Cache::increment` para la cuota por IP y para el techo global.
  Motivo: resuelve R5 con la misma cantidad de código y es la herramienta que
  Laravel ya trae.
- **D6.** `tokenBudgetReached()` pasa a
  `->sum(DB::raw('tokens_in + tokens_out'))`. Motivo: R4.
- **D7.** `OpenAiLlmClient` estima siempre que falte `usage`: los tokens de salida
  a partir del contenido acumulado y los de entrada a partir de la suma de los
  mensajes enviados, con `estimateTokens()`. La condición `$tokensIn === 0`
  desaparece. El turno registra el origen de la cifra en el campo `provider` ya
  existente, sin columna nueva. Motivo: R3; un presupuesto que a veces no cuenta
  no es un presupuesto.
- **D8.** `Retry-After` se calcula con los segundos que devuelve
  `RateLimiter::availableIn()` para los límites por minuto, y con los segundos
  hasta la medianoche del huso de la aplicación para las cuotas diarias. Motivo:
  R6, con el dato correcto para cada ventana.
- **D9.** El mensaje del 429 global es el mismo mensaje de degradación que ya
  existe para las cuotas, con código `daily_global_limit`. Motivo: no filtrar al
  visitante el estado interno del presupuesto del sitio.

## Cambios de datos

- Sin cambios de esquema.
- Nueva clave de configuración `rag.limits.global_requests_per_day`
  (`RAG_GLOBAL_REQUESTS_PER_DAY`, por defecto `500`), documentada en `.env.example`.
- Nueva clave de caché `ai:global:{fecha}`, con TTL de 24 horas.

## Seguridad

- El techo global acota el gasto máximo diario del sitio frente a un uso
  automatizado desde muchas IP, que es el escenario que las cuotas por IP no
  cubren.
- Se asume y se documenta que un visitante decidido puede agotar el techo global
  para todos los demás: el mitigante posterior es identificar a los visitantes,
  que queda fuera del alcance de esta spec.
- El código y el mensaje del 429 no revelan el valor del techo ni cuánto queda.

## Preguntas abiertas

(ninguna)
