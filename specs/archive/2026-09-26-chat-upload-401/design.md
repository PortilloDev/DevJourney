# Diseño — Fix: subida de documentos da 401

## Contexto técnico

- La app corre en Docker. El contenedor recibe la petición con `Host: localhost`
  **sin puerto** (el navegador está en `:8002`), por lo que `$request->url()`
  resuelve a `http://localhost/...`.
- `URL::forceRootUrl(config('app.url'))` (ya aplicado en `AppServiceProvider`)
  corrige la **generación** de URLs, pero la **validación** de firmas
  (`Illuminate\Routing\Middleware\ValidateSignature` → `URL::hasValidSignature`)
  usa `$request->url()`, que sigue viendo el Host sin puerto.
- Por eso las URLs firmadas temporales de Livewire (`livewire.upload-file`,
  usadas por el `FileUpload` de Filament) fallan la validación → `401`.

## Decisiones

- **D1.** Middleware global `SetRequestHost` que fija el **Host** de la petición a
  `APP_URL` (host y puerto) antes del pipeline. Motivo: alinea generación y
  validación de URLs firmadas; arregla el upload y cualquier URL firmada.
- **D2.** Solo actúa si `APP_URL` está definido y no se está en consola. Motivo:
  no afectar a comandos/CLI.
- **D3.** Se registra al inicio del stack global en `bootstrap/app.php`. Motivo:
  debe aplicarse antes de resolver la ruta y validar firmas.
- **D4.** El middleware solo ajusta el `Host` cuando el **nombre de host** de la
  petición coincide con el de `APP_URL` (mismo sitio, distinto puerto): en ese caso
  fija `host[:puerto]` de `APP_URL`. Si el host difiere, **no lo toca**. Motivo: un
  `APP_URL` mal configurado no debe secuestrar el Host y tumbar el sitio
  (enmienda A1); de paso evita *host header injection* porque nunca adopta un host
  arbitrario distinto del canónico.

## Cambios de datos

- Ninguno.

## Seguridad

- Fijar el `Host` al valor canónico (`APP_URL`) evita depender del Host del cliente,
  lo que reduce el riesgo de *host header injection* y de firma de URLs con hosts
  arbitrarios.

## Preguntas abiertas

(ninguna)
