# Infraestructura — DevJourney

> **Documento vivo.** Describe cómo están montados los servicios, su
> configuración y para qué sirve cada uno. **Cualquier cambio de infra** (nuevo
> servicio, variable de entorno, puerto, volumen, cambio de proveedor, retención,
> scheduler…) **debe quedar reflejado aquí en el mismo commit** que lo introduce.
> Al final hay un changelog para dejar rastro.

Última actualización: 2026-10-23.

---

## 1. Visión general

DevJourney es un monolito **Laravel 11 / PHP 8.4** desplegado en un **único VPS**
con **Docker Compose**. La app sirve el sitio público (Blade + Tailwind + Alpine)
y el panel admin (**Filament v3** en `/admin`). El tráfico entra por **Traefik**
(HTTPS automático con Let's Encrypt).

```
                        ┌─────────────────────────────────────────────────┐
                        │                  Traefik  (80/443)              │
                        │         reverse proxy + TLS (Let's Encrypt)     │
                        └───────────────┬─────────────────────────────────┘
                                        │
                        ┌───────────────▼─────────────────────────────────┐
                        │                app  (nginx → php-fpm)           │
                        │   supervisor: php-fpm · nginx · horizon · cron  │
                        └───┬───────────┬──────────┬──────────┬───────────┘
                            │           │          │          │
                     ┌──────▼───┐ ┌─────▼────┐ ┌───▼──────┐ ┌─▼─────────┐
                     │  MySQL 8 │ │ Redis 7  │ │Meili     │ │  Qdrant   │
                     │  (datos) │ │(cache/cola/sesión)│(búsqueda)│(vectores RAG)│
                     └──────────┘ └──────────┘ └──────────┘ └───────────┘
```

Servicios (producción, `docker-compose.yml`):

| Servicio | Imagen | Para qué | Puerto expuesto |
|---|---|---|---|
| `traefik` | `traefik:v3.1` | Reverse proxy + HTTPS automático | `80`, `443` |
| `app` | build propio (`Dockerfile`) | nginx + php-fpm + colas + scheduler | `8000` (solo interno) |
| `mysql` | `mysql:8.0` | Base de datos | — (red interna) |
| `redis` | `redis:7-alpine` | Caché, sesión y colas | — |
| `meilisearch` | `getmeili/meilisearch:latest` | Búsqueda full-text (Scout) | — |
| `qdrant` | `qdrant/qdrant:latest` | Índice vectorial del chat RAG | — |
| `portainer` | `portainer/portainer-ce:latest` | Gestión visual de contenedores | vía Traefik `logs.<dominio>` |

En **desarrollo** (`docker-compose.dev.yml`) se usa el mismo conjunto **sin
Traefik** y con **Mailpit** para el correo, con puertos abiertos al host.

---

## 2. Ficheros de infraestructura

| Fichero | Rol |
|---|---|
| `Dockerfile` | Imagen de la app (multi-stage: assets → composer → runtime). |
| `docker-compose.yml` | Stack de **producción** (Traefik + todos los servicios). |
| `docker-compose.dev.yml` | Stack de **desarrollo** (puertos al host, Mailpit, bind-mount). |
| `docker-fpm-setup/` | Config de nginx, php-fpm y supervisor dentro del contenedor `app`. |
| `deploy.sh` | Script de despliegue de producción. |
| `Makefile` | Atajos de dev (`make up`, `make shell`, `make test`, `make pint`…). |
| `.env.example` | **Fuente de verdad** de las variables de entorno (sin secretos). |
| `config/rag.php`, `config/horizon.php`, `config/pulse.php` | Config de RAG y observabilidad. |

---

## 3. El contenedor `app`

Imagen construida con `Dockerfile` (multi-stage):

1. **`assets`** (node:20-alpine) → compila Vite/Tailwind/Alpine en `public/build`.
2. **`vendor`** (composer:2) → instala dependencias PHP sin dev.
3. **`app`** (php:8.4-fpm) → añade nginx + supervisor + extensiones.

Dentro corren **4 procesos bajo supervisor** (`docker-fpm-setup/supervisord.conf`):

| Programa | Comando | Propósito |
|---|---|---|
| `php-fpm` | `php-fpm -F` | Ejecuta PHP por socket unix (`/run/php/php-fpm.sock`). |
| `nginx` | `nginx -g "daemon off;"` | Sirve estáticos y proxya PHP (puerto `8000`). |
| `horizon` | `php artisan horizon` | Maestro de colas: provisiona los workers Redis. |
| `scheduler` | `php artisan schedule:work` | Ejecuta `schedule:run` cada minuto. |

Extensiones PHP: `pdo_mysql mbstring exif pcntl bcmath gd zip opcache intl redis`;
además `poppler-utils` (`pdftotext`) para indexar PDFs en el RAG.

**PHP:** `ARG PHP_VERSION` en el `Dockerfile` (actualmente `8.4.27`).
Mitigaciones temporales de CVEs (CVE-2026-109618 / -109619) en
`docker-fpm-setup/zz-cve-2026-109618.conf`.

**Colas:** antes `queue:work redis` con supervisor; desde la fase de
observabilidad lo sustituye **Horizon** (mismo Redis, workers gestionados por
Horizon). Timeout de worker `300s` < `REDIS_QUEUE_RETRY_AFTER=360`.

---

## 4. Servicios y persistencia

### 4.1 MySQL 8

- Base `devjourney`, usuario `devjourney` (contraseña vía `DB_PASSWORD`).
- Volumen `mysql_data:/var/lib/mysql`.
- Healthcheck por `mysqladmin ping`.

### 4.2 Redis 7

- Drivers de **caché, sesión y cola** (`CACHE_STORE`, `SESSION_DRIVER`,
  `QUEUE_CONNECTION` = `redis` en los contenedores).
- Actualmente **sin password y sin persistencia** (solo caché/colas). Pendiente
  de endurecer si se quiere fiabilidad de colas.

### 4.3 Meilisearch

- Búsqueda full-text vía Laravel Scout (`SCOUT_DRIVER=meilisearch`).
- Clave maestra en `MEILISEARCH_KEY`; volumen `meilisearch_data`.

### 4.4 Qdrant

- Índice vectorial del **chat RAG** (posts + documentos).
- Colección `content` (`QDRANT_COLLECTION`), distancia `Cosine`.
- Autenticación con `QDRANT_API_KEY` (y `QDRANT_READ_ONLY_API_KEY` opcional).
- Volumen `qdrant_data`.

### 4.5 Mailpit (solo desarrollo)

- Captura el correo de la app (`MAIL_MAILER=smtp`, `MAIL_HOST=mailpit`).
- UI en `http://localhost:8025`, SMTP en `1025`.

### 4.6 Traefik (solo producción)

- Entrypoints `web` (80) y `websecure` (443); redirect 80 → 443.
- Certificados Let's Encrypt por **HTTP challenge** (`letsencrypt/acme.json`).
- Rutas por etiquetas Docker (`labels`) en cada servicio:

| Router | Host | Servicio |
|---|---|---|
| `devjourney` | `iabackendjourney.com` | `app:8000` |
| `portainer` | `logs.iabackendjourney.com` | `portainer:9000` |
| `grafana` | `grafana.iabackendjourney.com` | `grafana:3000` (definido en `docker-compose.observability.yml`) |

### 4.7 Portainer

- Gestión visual de contenedores/volúmenes/logs (complementa, no sustituye, la
  observabilidad descrita en §7).
- Acceso al socket de Docker (`/var/run/docker.sock:ro`); volumen `portainer_data`.

### 4.8 Stack de observabilidad (`docker-compose.observability.yml`)

Fichero **independiente** que comparte la red `devjourney` con el stack principal
(red externa, ver §5). Solo Grafana queda expuesto vía Traefik; el resto vive en
la red interna.

| Servicio | Imagen | Para qué |
|---|---|---|
| `prometheus` | `prom/prometheus` | TSDB de métricas; retención 30d (`--storage.tsdb.retention.time`). |
| `grafana` | `grafana/grafana` | Dashboards + alertas (datasources Prometheus/Loki aprovisionados). |
| `loki` | `grafana/loki` | Logs agregados; retención 30d (`limits_config.retention_period`). |
| `promtail` | `grafana/promtail` | Recolecta los logs stdout de los contenedores vía Docker socket. |
| `node-exporter` | `prom/node-exporter` | CPU/RAM/disco/red del host. |
| `cadvisor` | `gcr.io/cadvisor/cadvisor` | Métricas por contenedor (corre `privileged` para `/dev/kmsg`). |
| `redis_exporter` | `oliver006/redis_exporter` | Métricas de Redis. |
| `mysqld_exporter` | `prom/mysqld-exporter` | Métricas de MySQL (usa `devjourney` + `DB_PASSWORD`). |

Configuración en `docker/observability/` (prometheus, loki, promtail, grafana
provisioning). Arranque: `docker compose -f docker-compose.observability.yml up -d`.

### Volúmenes (producción)

| Volumen | Contenido |
|---|---|
| `portainer_data`, `mysql_data`, `meilisearch_data`, `qdrant_data` | Estado de cada servicio. |
| `storage` | `storage/` de Laravel (incluye logs y exportaciones). |
| `letsencrypt` | Certificados TLS. |
| `prometheus_data`, `grafana_data`, `loki_data` | Datos del stack de observabilidad (definidos en `docker-compose.observability.yml`). |

---

## 5. Red y TLS

- Producción solo expone `80`/`443` de Traefik; el resto de servicios vive en la
  red interna de Compose.
- La red del stack principal se fija con `name: devjourney`
  (`networks.default` en `docker-compose.yml`); el stack de observabilidad la
  referencia como **red externa** (`networks.default.external` en
  `docker-compose.observability.yml`) para llegar a mysql/redis y que Traefik
  enrute a Grafana.
- `APP_URL` y `ASSET_URL` deben ser el **origen público** (`https://…`); Laravel
  fuerza el root URL y valida las URLs firmadas contra él.
- `TRUSTED_PROXIES` lista los proxies reales en producción (`*` solo en local).

---

## 6. Variables de entorno

Fuente de verdad: `.env.example` (los secretos van en `.env`, ignorado por git).
Claves agrupadas por dominio:

| Dominio | Claves principales |
|---|---|
| App | `APP_URL`, `ASSET_URL`, `APP_ENV`, `APP_DEBUG`, `APP_KEY` |
| Logs | `LOG_CHANNEL`, `LOG_STACK`, `LOG_DAILY_DAYS`, `LOG_RETENTION_DAYS`, `LOG_EXPORT_RETENTION_DAYS` |
| BD / cache / colas | `DB_*`, `REDIS_HOST/PORT`, `QUEUE_CONNECTION`, `REDIS_QUEUE_RETRY_AFTER` |
| Búsqueda | `SCOUT_DRIVER`, `MEILISEARCH_HOST`, `MEILISEARCH_KEY` |
| Admin | `FILAMENT_PATH`, `FILAMENT_ADMIN_EMAIL` |
| RAG | `LLM_PROVIDER`, `LLM_BASE_URL`, `LLM_API_KEY`, `LLM_CHAT_MODEL`, `EMBEDDING_*`, `QDRANT_*`, `RAG_*` |
| Observabilidad | `HORIZON_NAME`, `PULSE_ENABLED`, `PULSE_STORAGE_DRIVER`, `PULSE_STORAGE_KEEP`, `GF_SECURITY_ADMIN_PASSWORD` |
| Analítica | `ANALYTICS_RETENTION_DAYS` |
| Proxy/TLS | `TRUSTED_PROXIES`, `ACME_EMAIL` |

> **Importante:** el bloque `environment:` de los `docker-compose` **pisa** al
> `.env` dentro del contenedor (p. ej. `DB_HOST=mysql`, `CACHE_STORE=redis`).
> Cambiar esos valores en `.env` no surte efecto mientras estén definidos en
> compose.

---

## 7. Observabilidad y operación

### 7.1 Estado actual (en servicio)

- **Filament `/admin`**: logs de app (`AppLogResource`), uso del chat
  (`AiUsageResource`, `AiConversationResource`), tráfico y errores (widgets).
- **Portainer**: contenedores, logs de contenedor, volúmenes.
- **`/up`**: health endpoint de Laravel.
- Logs de app a BD (`app_logs`) y a stdout (recogidos por el driver de Docker).

### 7.2 Colas y rendimiento (implementado, pendiente de deploy)

- **Laravel Horizon** en `/horizon`: workers, throughput, jobs fallidos/reintentos.
- **Laravel Pulse** en `/pulse`: requests lentas, queries, excepciones, cache, colas.
- Ambos **gateados por la allowlist de admin** (`FILAMENT_ADMIN_EMAIL`); en local
  quedan abiertos.
- Scheduler: `horizon:snapshot` cada 5 min y `pulse:check` cada minuto.

### 7.3 Stack de métricas y logs (Grafana, implementado — pendiente de deploy)

- **Grafana** en `grafana.iabackendjourney.com` (login, `GF_SECURITY_ADMIN_PASSWORD`).
- **Prometheus + Loki** como backends; **Promtail** agrega los logs stdout de los
  contenedores a Loki; exporters de node, cAdvisor, Redis y MySQL.
- Definido en `docker-compose.observability.yml` (ver §4.8).

### 7.4 Planificado (sin implementar todavía)

- Spatie Health, Redis Insight y alertas de Grafana — detalle en §10.

### 7.5 Retención y limpieza de logs

| Mecanismo | Detalle |
|---|---|
| `ai:prune-conversations` (diario) | Borra conversaciones del chat > `RAG_RETENTION_DAYS`. |
| `logs:prune --export` (diario) | Exporta `app_logs` viejas a `storage/logs/exports/*.json.gz.enc` (cifrado) y las borra. |
| `analytics:prune` (diario) | Agrega `visits`/`visit_events` en `daily_traffic` y borra el crudo. |
| Rotación de Laravel | `LOG_STACK=daily,applog` + `LOG_DAILY_DAYS`. |
| Rotación de Docker | `logging` json-file `max-size: 10m`, `max-file: 3`. |

### 7.6 Scheduler

`routes/console.php` (corre vía `schedule:work` dentro del contenedor):

```
ai:prune-conversations  → diario
logs:prune --export     → diario
analytics:prune         → diario
horizon:snapshot        → cada 5 min
pulse:check             → cada minuto
```

---

## 8. Despliegue

`deploy.sh` hace:

```bash
git pull
docker compose build --pull app   # --pull obligatorio para traer la base actualizada
docker compose down
docker compose up -d
docker compose exec app php artisan migrate --force
docker compose exec app php artisan storage:link
docker compose exec app php artisan optimize:clear
docker compose exec app php artisan queue:restart
```

Checklist post-deploy (no olvidar):

- `php artisan optimize:clear` tras desplegar código (caché de páginas/activos).
- `php artisan rag:reindex` si cambió el origen o el payload vectorial.
- `APP_URL`/`ASSET_URL` apuntando al origen público.
- No `config:cache` en el build de imagen (el `.env` se monta en runtime).
- Si cambió `docker-compose`/variables → `docker compose up -d` (recrea) o
  `up -d --build` si cambió el `Dockerfile`.
- El stack de observabilidad es independiente:
  `docker compose -f docker-compose.observability.yml up -d` (comparte la red
  `devjourney`; `docker compose down` del principal puede avisar de que la red
  sigue en uso mientras haya contenedores de observabilidad corriendo).

---

## 9. Seguridad

- Panel admin y dashboards (`/admin`, `/horizon`, `/pulse`) limitados por
  `FILAMENT_ADMIN_EMAIL`.
- IPs del chat y analítica **hasheadas** o **cifradas en reposo** (`IpHasher`,
  casts `encrypted` en `Visit`).
- Exportaciones de logs **cifradas** con la `APP_KEY` y fuera del disco público.
- TLS vía Traefik/Let's Encrypt; `TRUSTED_PROXIES` configurado en producción.
- Secretos solo en `.env` (gitignored); `.env.example` sin valores reales.

---

## 10. Recomendaciones pendientes (no implementadas)

Lista consolidada de mejoras de infra identificadas y **aún no aplicadas**. Sirve
como backlog operativo: al implementar una, márcala aquí y añade la entrada al
changelog (§11).

| # | Recomendación | Detalle | Por qué |
|---|---|---|---|
| 1 | Fijar versiones de imagen | En `docker-compose.observability.yml` las imágenes usan `latest` (Prometheus, Grafana, Loki, Promtail, exporters). | Builds reproducibles y upgrades controlados. |
| 2 | Endurecer Redis | Añadir `requirepass` y persistencia (`appendonly yes`) al servicio `redis`. | Evita acceso no autenticado y pérdida de colas/caché tras un reinicio. |
| 3 | Usuario MySQL dedicado para `mysqld_exporter` | Crear un usuario con `PROCESS` + `REPLICATION CLIENT` (hoy usa el usuario de la app, `devjourney`). | Minimiza los privilegios del exporter. |
| 4 | cAdvisor sin `privileged` | Evaluar la variante con mounts acotados (pierde métricas de kernel) en lugar de `privileged: true`. | Reduce la superficie de seguridad del agente. |
| 5 | Spatie Laravel Health | Añadir el paquete con dashboard de healthchecks (BD, Redis, colas, disco). | Visibilidad del estado interno de la app, no solo de los contenedores. |
| 6 | Redis Insight (GUI) | Contenedor `redis/redisinsight` expuesto detrás de `basicauth` de Traefik. | Inspección manual de claves/memoria de Redis. |
| 7 | Alertas en Grafana | Contact points (email/Telegram) + reglas (cola atascada, 5xx, disco, CPU). | Notificación proactiva en lugar de revisión manual. |

---

## 11. Changelog

| Fecha | Cambio |
|---|---|
| 2026-10-23 | Creado el documento. |
| 2026-10-23 | Añadidos comandos de retención (`logs:prune`, `analytics:prune`) y rotación de logs (Laravel + Docker). |
| 2026-10-23 | Añadidos Laravel Horizon y Laravel Pulse; supervisor pasa de `queue:work` a `horizon`. |
| 2026-10-23 | Añadido `ext-pcntl` al stage `vendor` del `Dockerfile` (lo exige `laravel/horizon`; el build fallaba en `composer install`). |
| 2026-10-23 | Añadido stack de observabilidad: `docker-compose.observability.yml` (Prometheus, Grafana, Loki, Promtail, node-exporter, cAdvisor, redis/mysql exporters) + red fija `devjourney`. |
