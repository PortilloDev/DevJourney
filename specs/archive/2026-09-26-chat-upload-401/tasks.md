# Tareas — Fix: subida de documentos da 401

- [x] 1. Crear el middleware `SetRequestHost` que ajusta el puerto del `Host`
      cuando el nombre de host coincide con `APP_URL` (R2) (enmienda A1)
- [x] 2. Registrar el middleware al inicio del stack global en `bootstrap/app.php`
      (R2) (depende de 1)
- [x] 3. Test: con un `Host` entrante distinto, `$request->url()` usa `APP_URL` y
      una URL firmada generada por la app valida (R2) (depende de 1, 2)
- [x] 4. Verificación manual: subir un PDF en «Reference documents» sin 401
      (R1) (depende de 2)
