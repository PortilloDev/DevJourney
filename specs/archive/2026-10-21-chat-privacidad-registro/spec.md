---
feature: chat-privacidad-registro
estado: archivada
creada: 2026-10-08
aprobada: 2026-10-21
---

# Privacidad del registro del chat

## Por qué

El proyecto ya cifra los datos personales de las visitas, pero el chat guarda la
dirección IP del visitante en claro en dos tablas, que es una inconsistencia con
el estándar que el propio proyecto se fijó y un dato personal a efectos de RGPD.
Además, cada turno almacena una copia completa del prompt, que duplica un
contexto ya registrado en las fuentes, y los documentos vinculados a un post
entran en el contexto del chat sin ningún control de visibilidad: cualquier PDF
que se suba queda consultable por cualquier visitante de ese post.

## Requisitos

### R1 — Dirección IP no reversible

El sistema DEBE almacenar la dirección IP del visitante de forma no reversible en el histórico de uso y en el de conversaciones.

**Escenario R1-E1:** DADO un turno atendido / CUANDO se consulta la fila registrada / ENTONCES el valor almacenado no coincide con la dirección IP del visitante

**Escenario R1-E2:** DADO dos turnos del mismo visitante / CUANDO se consultan sus filas / ENTONCES ambas contienen el mismo valor derivado

### R2 — Las cuotas siguen funcionando sobre el valor derivado

El sistema DEBE aplicar las cuotas por visitante usando el valor derivado de su dirección IP.

**Escenario R2-E1:** DADO un visitante con turnos registrados hoy / CUANDO se comprueba su presupuesto diario de tokens / ENTONCES se contabilizan los tokens de esos turnos anteriores

### R3 — Documentos no públicos fuera del chat

El sistema NO DEBE usar como contexto los fragmentos de un documento marcado como no público.

**Escenario R3-E1:** DADO un documento vinculado a un post y marcado como no público / CUANDO un visitante pregunta en ese post por información que solo aparece en ese documento / ENTONCES la pregunta se trata como fuera de contexto y la respuesta no usa el contenido del documento

**Escenario R3-E2:** DADO un documento indexado que pasa a marcarse como no público / CUANDO se guarda el cambio / ENTONCES sus fragmentos dejan de estar disponibles en el almacén vectorial

### R4 — El registro no duplica el contexto

El sistema DEBERÍA registrar el contexto de cada turno por referencia a sus fragmentos, en lugar de almacenar una segunda copia literal del texto en el prompt.

**Escenario R4-E1:** DADO un turno atendido / CUANDO se consulta la fila registrada / ENTONCES las fuentes incluyen el identificador de cada fragmento usado y el prompt almacenado no contiene el texto completo de los fragmentos

## Fuera de alcance

- Cifrar la pregunta o la respuesta del visitante.
- Cambiar el periodo de retención de 30 días ni el comando de poda.
- Un portal de ejercicio de derechos RGPD para el visitante.
- Identificar a los visitantes mediante registro o login.

## Enmiendas

(ninguna)
