---
feature: chat-configuracion-admin
estado: archivada
creada: 2026-09-26
aprobada: 2026-09-26
---

# Configuración del chat desde el panel

## Por qué

El chat por artículo está siempre disponible si hay claves del LLM. Se necesita
poder **bloquear la API** (p. ej. por coste o abuso) y **ocultar el widget** sin
desactivar la API, ambas cosas configurables desde el panel sin tocar código.

## Requisitos

### R1 — Ajustes booleanos editables en el admin

El sistema DEBE exponer dos ajustes booleanos editables desde el panel mediante `SiteSetting`: `chat_api_blocked` y `chat_hidden`.

**Escenario R1-E1:** DADO un administrador / CUANDO abre Site Settings / ENTONCES encuentra y puede editar `chat_api_blocked` y `chat_hidden`.

### R2 — Bloqueo de la API

El sistema DEBE responder `403` y no procesar la consulta cuando `chat_api_blocked` está activo.

**Escenario R2-E1:** DADO `chat_api_blocked` activo / CUANDO se llama `POST /api/chat` / ENTONCES responde 403 y no se consulta al LLM ni al vector store.

### R3 — Bloquear oculta el widget

El sistema NO DEBE mostrar el widget del chat cuando `chat_api_blocked` está activo.

**Escenario R3-E1:** DADO `chat_api_blocked` activo / CUANDO se abre la página de un post / ENTONCES no se renderiza el widget.

### R4 — Ocultar sin bloquear

Con `chat_hidden` activo y `chat_api_blocked` inactivo, el sistema DEBE ocultar el widget en la web manteniendo `POST /api/chat` operativo.

**Escenario R4-E1:** DADO `chat_hidden` activo y `chat_api_blocked` inactivo / CUANDO se abre el post / ENTONCES no se renderiza el widget, y CUANDO se llama `POST /api/chat` / ENTONCES responde con normalidad.

## Fuera de alcance

- Ajustes por post concreto.
- Mensaje al visitante explicando el motivo del bloqueo.
- Cuotas o límites configurables desde el panel.

## Enmiendas

(ninguna)
