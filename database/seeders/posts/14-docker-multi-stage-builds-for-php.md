---
title: Docker multi-stage builds for PHP without the bloat
category: DevOps
level: B1
tags: docker, php
excerpt: One Dockerfile, three stages, a small runtime image and a build that caches the expensive parts. The setup I use for Laravel services.
featured: false
---
## The problem with one big image

The naive PHP Dockerfile installs Node, Composer, the build tools and the application in a single stage. The resulting image contains npm, the node_modules cache, the Composer cache, the source of every dev dependency and a shell full of compilers. It is hundreds of megabytes larger than it needs to be, and every build starts from scratch because the layers are tangled.

A multi-stage build separates the build environment from the runtime. The build stages can be as heavy as they need to be; the final stage copies only the artifacts the application needs to run. The result is smaller, faster to pull and less exposed to attack because the compilers and package managers are not there.

## Stage one: frontend assets

The first stage uses a Node image to compile Vite and Tailwind.

```dockerfile
FROM node:20-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY resources ./resources
COPY vite.config.js tailwind.config.js postcss.config.js ./
RUN npm run build
```

The `npm ci` layer is cached as long as the lockfile does not change. Copying only the files needed for the build keeps the layer cache useful. The output is `public/build`, which the runtime stage will copy.

## Stage two: PHP dependencies

The second stage uses the official Composer image to install production dependencies.

```dockerfile
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --no-interaction
```

`--no-dev` keeps PHPUnit, Pint and PHPStan out of the runtime. `--no-scripts` and `--no-autoloader` defer the parts that need the full source tree; the runtime stage finishes them once all files are present. This avoids running Artisan commands during the dependency install, which would need a database or an environment that does not exist yet.

## Stage three: runtime

The final stage starts from `php:8.4-fpm`, installs the system packages and PHP extensions, and copies the application plus the two artifacts.

```dockerfile
FROM php:8.4-fpm AS app

RUN apt-get update && apt-get install -y \
        nginx supervisor libicu-dev libzip-dev \
    && docker-php-ext-install pdo_mysql mbstring intl zip opcache \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html
COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build

RUN composer dump-autoload --no-dev --optimize \
    && chown -R www-data:www-data storage bootstrap/cache

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
```

The `composer dump-autoload` runs after the source is present, so the optimized classmap includes the application classes. The `chown` gives the web user write access to `storage` and `bootstrap/cache`. The image does not contain Node, npm, the Composer cache or the dev dependencies.

## Layer caching

Docker caches layers in order. If a layer's inputs do not change, the layer is reused. The order of `COPY` instructions matters: copy the lockfiles first, install dependencies, then copy the source. That way a change to a controller does not invalidate the Composer install layer.

The same logic applies to the frontend: copy `package.json` and the lockfile, run `npm ci`, then copy the resources. A change to a Blade file does not reinstall Node modules.

## Running as the right user

PHP-FPM runs as `www-data`; the queue workers and the scheduler should too. Running them as root creates files owned by root in `storage`, and the web process can no longer write to them. The supervisor programs should declare `user=www-data`, and the image should set ownership once at build time.

## Health checks and signals

A container is healthy when the application can serve traffic, not when the process exists. A health check can hit `/up` or a dedicated endpoint that checks the database and Redis. Docker or the orchestrator can restart the container when the check fails repeatedly.

The main process must handle `SIGTERM` so the container stops gracefully. PHP-FPM handles it; supervisor forwards it to its programs. The queue worker finishes the current job before exiting, which is why `stopwaitsecs` should be long enough for a job to complete.

## What not to put in the image

Do not bake the `.env` into the image. The environment differs between staging and production, and a baked file is a secret leak waiting to happen. Mount it at runtime or inject environment variables. Do not run `config:cache` at build time for the same reason: the cache would capture the build environment, not the runtime one.

Do not include tests, fixtures or local tooling in the runtime image. They add size and attack surface. The CI pipeline can run them in a separate stage or a separate job.

## The build context

The build context is everything Docker sends to the daemon. A repository with `node_modules`, `vendor` and a large `storage` directory can send hundreds of megabytes before the first instruction runs. A `.dockerignore` that excludes `node_modules`, `vendor`, `.git`, `storage/logs`, `storage/framework` and the local `.env` makes builds faster and prevents accidental secrets from entering the image. The same file is part of the build's reproducibility: two developers with different local caches produce the same image.

## Image size is a security feature

A smaller image has fewer packages, fewer CVEs and a smaller attack surface. The runtime does not need Git, npm, the Composer cache or the test suite. Removing the build tools also removes the ability to compile a malicious extension if the application is compromised. Size is not vanity; it is a direct reduction of what an attacker can use.

## Conclusion

A multi-stage build is not about being clever; it is about putting each tool where it belongs. Node compiles assets, Composer installs dependencies, and the runtime contains only PHP, the application and the artifacts. Cache the expensive layers, run as the web user, expose a health check and keep secrets out of the image. The result is a smaller, faster and safer deployment.
