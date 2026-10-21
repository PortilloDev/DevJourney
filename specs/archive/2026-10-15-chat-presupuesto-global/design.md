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

`RagService` recibe la IP en sus cuatro caminos y es quien decide, tras la
comprobación local de relevancia, si va a llamar al modelo; por eso es el punto
natural donde se consume la cuota de forma atómica, y no el controlador. El
controlador conserva una comprobación sin consumir para devolver el 429 antes de
empezar a responder.

## Decisiones

- **D1.** El techo global es un contador diario en caché, clave
  `ai:global:{fecha}` con TTL de 24 horas, comparado contra
  `rag.limits.global_requests_per_day` (`RAG_GLOBAL_REQUESTS_PER_DAY`, por defecto
  `500`). Motivo: acota el gasto máximo del día con la infraestructura que ya hay.
- **D2.** El contador global se incrementa en el mismo punto que la cuota por
  visitante: justo antes de la llamada al modelo de chat, dentro de `RagService`.
  Motivo: el techo mide gasto, y lo que gasta es la llamada al modelo; así un
  rechazo local, que no llama a nadie, no consume.
- **D3.** Orden nuevo del controlador: flags → resolución del post → validación de
  la pregunta → comprobación *sin consumir* de la cuota por IP y del techo global
  (para poder devolver el 429 antes de empezar a responder) → presupuesto de
  tokens → delegación en `RagService`. El consumo real ocurre dentro de
  `RagService`, que lo hace de forma atómica antes de invocar al modelo y comunica
  el agotamiento para que el controlador lo traduzca a 429. Motivo: R2 y R5; una
  petición malformada o rechazada localmente no debe costarle cuota a nadie, pero
  dos peticiones simultáneas no pueden pasar ambas.
- **D4.** Una pregunta rechazada por la comprobación **local** de relevancia no
  consume cuota de peticiones porque no llega a la llamada al modelo. El rechazo
  en la etapa de **embeddings** sí gasta una llamada de embeddings, pero tampoco
  consume cuota de peticiones, porque la cuota acota las llamadas de chat. Motivo:
  cada contador mide lo que de verdad cuesta.
- **D5.** Un servicio `ChatQuota` encapsula la cuota por IP y el techo global
  sobre `Illuminate\Support\Facades\RateLimiter::attempt()` (atómico) y expone,
  además, una comprobación sin consumir para el 429 temprano del controlador.
  Motivo: resuelve R5 con la herramienta que Laravel ya trae y esconde la mecánica
  del contador tras una pieza testeable.
- **D6.** `tokenBudgetReached()` pasa a
  `->sum(DB::raw('tokens_in + tokens_out'))`. Motivo: R4.
- **D7.** `OpenAiLlmClient` estima siempre que falte `usage`: los tokens de salida
  a partir del contenido acumulado y los de entrada a partir de la suma de los
  mensajes enviados, con `estimateTokens()`. La condición `$tokensIn === 0`
  desaparece. Motivo: R3; un presupuesto que a veces no cuenta no es un
  presupuesto.
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
