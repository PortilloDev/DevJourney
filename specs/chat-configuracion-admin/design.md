# Diseño — Configuración del chat desde el panel

## Contexto técnico

- `App\Services\SiteSettingService` — almacén key/value cacheado (`get`, `set`,
  `flush`); `App\Models\SiteSetting`; `App\Filament\Resources\SiteSettingResource`
  (editor genérico, ya permite tipo `boolean`).
- `App\Http\Controllers\Api\ChatController` — `POST /api/chat`.
- Widget: `resources/views/components/chat-widget.blade.php`
  (`@props(['enabled' => true, 'post' => null])`, hoy `enabled = filled(config('rag.api_key'))`).

## Decisiones

- **D1.** Dos claves en `SiteSetting`: `chat_api_blocked` y `chat_hidden`, tipo
  `boolean`, grupo `chat`. Motivo: reutilizar el almacén cacheado y el editor ya
  existentes (decisión del usuario).
- **D2.** `SiteSettingService` expone helpers de lectura `chatApiBlocked()` y
  `chatHidden()` (default `false`). Motivo: evitar dispersar la clave por el código.
- **D3.** `ChatController` responde `403` con `{ "error": "chat_blocked" }` cuando
  `chat_api_blocked` está activo, y **antes** de cualquier llamada al LLM o al
  vector store. Motivo: requisito del usuario (403).
- **D4.** El widget se renderiza solo si hay claves del LLM **y** `!chat_api_blocked`
  **y** `!chat_hidden`; el `enabled` se calcula en la vista a partir de los settings.
  Motivo: bloqueo y ocultación son independientes (R3/R4).

## Cambios de datos

- Se **siembran** los dos `SiteSetting` (`chat_api_blocked`, `chat_hidden`) en el
  seeder para que aparezcan en el panel.

## Seguridad

- El bloqueo aplica a API y web, evitando consumo del LLM y del proveedor de
  embeddings.

## Preguntas abiertas

(ninguna)
