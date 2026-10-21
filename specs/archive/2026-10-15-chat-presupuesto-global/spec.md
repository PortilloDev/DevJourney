---
feature: chat-presupuesto-global
estado: archivada
creada: 2026-10-08
aprobada: 2026-10-15
---

# Presupuesto global y cuotas que cuentan bien

## Por qué

Las tres capas de límite actuales se anclan a la IP, que es gratis de rotar, y
ninguna pone techo al conjunto del sitio: veinte preguntas por IP multiplicadas
por cien IPs son dos mil llamadas al modelo en un día sin que nada las frene. El
riesgo de coste real no es el visitante individual. Además la cuota se consume
antes de validar la petición, de modo que un slug equivocado gasta una pregunta;
la suma diaria de tokens trae a memoria todas las filas del día para sumar dos
enteros; el contador de peticiones no es atómico; y en streaming el presupuesto de
tokens puede no incrementarse nunca si el proveedor no devuelve `usage`, que es
justo el camino que usa el widget en producción.

## Requisitos

### R1 — Techo diario del sitio

El sistema DEBE rechazar las peticiones de chat cuando el número de llamadas al modelo de chat del día alcance el máximo global configurado.

**Escenario R1-E1:** DADO que se alcanzó el máximo global del día / CUANDO cualquier visitante envía una pregunta válida / ENTONCES recibe una respuesta 429 con el código `daily_global_limit` y no se realiza ninguna petición al endpoint de chat completions

**Escenario R1-E2:** DADO un día sin consumo previo / CUANDO un visitante envía una pregunta válida / ENTONCES se atiende con normalidad y el contador global refleja una llamada al modelo

### R2 — Solo las peticiones atendidas consumen cuota

El sistema NO DEBE descontar cuota diaria de peticiones por una petición rechazada por validación o por la comprobación local de relevancia.

**Escenario R2-E1:** DADO un visitante con la cuota diaria sin estrenar / CUANDO envía una petición con un slug inexistente y a continuación una pregunta válida / ENTONCES la pregunta válida se atiende y el contador diario del visitante refleja una única petición

### R3 — Contabilidad de tokens también sin `usage`

El sistema DEBE registrar un consumo de tokens mayor que cero en todo turno atendido, aunque el proveedor no devuelva `usage`.

**Escenario R3-E1:** DADO un proveedor que no envía `usage` en el stream / CUANDO se atiende un turno / ENTONCES el turno registrado tiene tokens de entrada y de salida mayores que cero y el presupuesto diario del visitante se incrementa

### R4 — Presupuesto de tokens calculado en la base de datos

El sistema DEBE calcular el consumo diario de tokens mediante una agregación en la base de datos.

**Escenario R4-E1:** DADO varios turnos registrados hoy para una misma IP / CUANDO se comprueba el presupuesto de tokens / ENTONCES el presupuesto es la suma de los tokens de entrada y de salida de esos turnos

### R5 — Conteo atómico de peticiones

El sistema DEBE incrementar el contador diario de peticiones de forma atómica.

**Escenario R5-E1:** DADO un visitante al que le queda una petición de cuota / CUANDO se procesan dos peticiones concurrentes suyas / ENTONCES solo una de ellas se atiende y la otra recibe 429

### R6 — Cabecera de reintento

El sistema DEBE incluir la cabecera `Retry-After` en toda respuesta 429 del endpoint de chat.

**Escenario R6-E1:** DADO una cuota diaria agotada / CUANDO el visitante envía una pregunta / ENTONCES la respuesta 429 incluye `Retry-After` con los segundos que faltan para el reinicio de la cuota

## Fuera de alcance

- Identificar a los visitantes mediante registro o login social.
- Cloudflare Turnstile o cualquier otra verificación antibot.
- Cambiar de modelo o de proveedor para abaratar el turno.
- La caché de respuestas, que reduce el consumo por otra vía (spec `chat-cache-respuestas`).

## Enmiendas

(ninguna)
