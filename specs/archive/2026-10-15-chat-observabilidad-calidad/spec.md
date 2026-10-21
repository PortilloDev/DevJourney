---
feature: chat-observabilidad-calidad
estado: archivada
creada: 2026-10-08
aprobada: 2026-10-15
---

# Señales de calidad y coste del chat

## Por qué

El registro por turno es completo —pregunta, prompt, respuesta, fuentes con su
score, etapa de rechazo— pero nadie mira esos datos como serie, no hay ninguna
señal sobre si las respuestas son buenas, y el coste solo se conoce en tokens,
que no son dinero. Encima la latencia se atribuye mal: se escribe en la última
fila de esa IP, de modo que dos turnos en el mismo segundo se intercambian el
dato. Falta la señal más barata de todas, que es preguntarle al visitante si la
respuesta le sirvió.

## Requisitos

### R1 — Valoración del visitante

El sistema DEBE permitir que el visitante valore cada respuesta como útil o no útil, y registrar esa valoración en el turno correspondiente.

**Escenario R1-E1:** DADO una respuesta entregada en el chat de un post / CUANDO el visitante la marca como no útil / ENTONCES la valoración queda registrada en ese turno

**Escenario R1-E2:** DADO un turno ya valorado por ese visitante / CUANDO vuelve a valorarlo con el valor contrario / ENTONCES el turno conserva una única valoración, la última

### R2 — Valoraciones solo sobre turnos propios

El sistema NO DEBE aceptar una valoración sobre un turno que no pertenece a la conversación del visitante.

**Escenario R2-E1:** DADO un identificador de turno de otra conversación / CUANDO se envía una valoración para él / ENTONCES se rechaza y el turno mantiene su valoración anterior

### R3 — Latencia del turno que la produjo

El sistema DEBE registrar la latencia en el mismo turno que la generó.

**Escenario R3-E1:** DADO dos turnos del mismo visitante atendidos dentro del mismo segundo / CUANDO ambos terminan / ENTONCES cada turno tiene registrada su propia latencia

### R4 — Coste estimado por turno

El sistema DEBE registrar el coste estimado de cada turno a partir de los tokens consumidos y de los precios configurados para su modelo.

**Escenario R4-E1:** DADO precios configurados para el modelo en uso y un turno con tokens registrados / CUANDO se consulta la fila del turno / ENTONCES incluye el coste estimado correspondiente a esos tokens

**Escenario R4-E2:** DADO un modelo sin precios configurados / CUANDO se registra un turno / ENTONCES el turno se registra sin coste y el resto de campos no se ven afectados

### R5 — Series del día en el panel

El sistema DEBERÍA mostrar en el panel, para el día en curso, la tasa de rechazo separada por etapa, la tasa de error, el percentil 95 de latencia, el coste acumulado, la proporción de valoraciones negativas y el número de turnos atendidos con cero fragmentos de contexto.

**Escenario R5-E1:** DADO turnos registrados hoy con distintos desenlaces / CUANDO el administrador abre el panel / ENTONCES ve esas seis cifras referidas al día en curso

## Fuera de alcance

- Un conjunto de evaluación y su comando (spec `chat-evaluacion-calidad`).
- Alertas, notificaciones o integración con un sistema externo de monitorización.
- Trazas distribuidas u OpenTelemetry.
- Herramientas externas de observabilidad de LLM como Langfuse o Phoenix.

## Enmiendas

(ninguna)
