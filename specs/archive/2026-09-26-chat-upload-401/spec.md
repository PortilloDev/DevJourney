---
feature: chat-upload-401
estado: archivada
creada: 2026-09-26
aprobada: 2026-09-26
---

# Fix: subida de documentos da 401 (firma de Livewire)

## Por qué

Al subir un documento desde el panel, la carga temporal de Livewire
(`POST /livewire/upload-file`) responde **401 Unauthorized**. La app corre en
Docker y el contenedor recibe la petición con `Host: localhost` **sin puerto**;
Livewire firma la URL temporal con `APP_URL` (`http://localhost:8002`), pero la
validación de la firma usa la URL que ve el servidor (`http://localhost/...`), así
que la firma no coincide y la subida se rechaza.

## Requisitos

### R1 — La subida de documentos funciona

El sistema DEBE permitir subir un documento desde el panel sin error `401`.

**Escenario R1-E1:** DADO un administrador autenticado en desarrollo (navegador en `:8002`) / CUANDO sube un PDF en «Reference documents» / ENTONCES la carga temporal y el guardado se completan sin 401.

### R2 — Validación de URLs firmadas estable

El sistema DEBE validar correctamente las URLs firmadas que genera, con independencia del puerto con el que el navegador acceda.

**Escenario R2-E1:** DADO `APP_URL` con puerto (`http://localhost:8002`) y una petición entrante con `Host` sin puerto / CUANDO se valida una URL firmada generada por la app / ENTONCES la firma valida.

## Fuera de alcance

- Soporte multi-dominio o de varios orígenes canónicos.
- Cambiar el mecanismo de carga de Livewire.

## Enmiendas

### A1 — 2026-09-26
- Motivo: evitar que un `APP_URL` mal configurado en producción propague un `Host` incorrecto y rompa el sitio.
- Cambio: el middleware solo ajusta el `Host` cuando el nombre de host de la petición **coincide** con el de `APP_URL` (corrige el puerto); si difiere, no lo toca. Se relaja D4 (ya no ignora el host entrante, solo corrige el puerto del mismo host).
- Afecta a: D4, tarea 1
- Aprobada por el usuario: sí
