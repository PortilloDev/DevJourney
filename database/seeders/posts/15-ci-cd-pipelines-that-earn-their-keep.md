---
title: CI/CD pipelines that earn their keep
category: DevOps
level: B1
tags: devops, testing
excerpt: A pipeline is a product with users: the team. Fast feedback, a real gate, immutable artifacts and a deploy you can undo.
featured: false
---
## The pipeline is for the team

A CI/CD pipeline is often described as automation: run the tests, build the image, deploy. That framing misses the point. The pipeline is a product for the team, and its users care about two things: how fast they learn that something is broken, and how safely they can ship. Every step that does not improve one of those is a tax.

I want the pipeline to fail for the same reasons a reviewer would reject a change: broken style, a type error, a failing test, a migration that does not run. If the pipeline is slower or noisier than a human review, people will bypass it. The best pipeline is the one the team trusts.

## Fast feedback first

The order of the jobs should follow the cost of failure. Linting and static analysis are fast and catch a large class of mistakes. They run first. Unit tests are next. Integration tests and the build come later. If the lint fails, there is no reason to spend ten minutes building an image.

A typical Laravel pipeline has three jobs: lint, test, build-and-deploy. The lint job installs Composer dependencies and runs Pint and PHPStan. The test job runs the suite against a disposable database. The deploy job builds the image and ships it only if the previous jobs passed.

```yaml
lint:
  steps:
    - run: composer install --no-progress --prefer-dist
    - run: vendor/bin/pint --test
    - run: vendor/bin/phpstan analyse --no-progress
```

The `--test` flag on Pint is important: in CI it checks, it does not rewrite. A pipeline that fixes style silently hides the fact that the developer did not run the formatter. I want the failure to be visible locally too, which is why the same command exists in the Makefile.

## A real test gate

The test job should run the same suite the developers run locally, on a clean environment. For Laravel, that means a disposable MySQL or SQLite database, the same PHP version as production, and the same extensions. A test that passes on SQLite but fails on MySQL is a test that will fail in production.

Coverage is a useful signal, not a goal. A minimum threshold prevents a slow slide into untested code, but chasing a number produces tests that execute lines without asserting behaviour. I prefer a modest threshold plus a few high-value tests around the domain and the critical paths. The number is a floor, not a target.

## Build once, deploy the artifact

The pipeline should build the image once and deploy exactly that image to every environment. Building separately for staging and production creates two artifacts that can differ. The image is tagged with the commit sha, and the deploy step references that tag. If a rollback is needed, the previous tag is still there.

The deploy step is not just `docker compose up`. It runs migrations, clears caches, links storage and restarts the queue workers. Those steps belong in the deploy script, not in a human's memory. The script should be idempotent: running it twice should not break anything.

## Secrets and permissions

Secrets do not belong in the repository or in the image. They come from the CI secret store or the runtime environment. The deploy job needs the minimum permissions to push the image and restart the service. A long-lived root credential is convenient until it leaks.

The application's `.env` is mounted at runtime, not baked into the image. The pipeline should never print it. A debug step that dumps the environment is a common accident; I add a check that masks secrets and fails if a known pattern appears in the logs.

## Rollback is part of the design

A deploy without a rollback plan is a bet. The previous image tag should be deployable with one command. The database migrations are the hard part: a migration that drops a column cannot be undone by rolling back the image. For risky changes, I use an expand-and-contract pattern: add the new column, deploy code that writes both, backfill, then remove the old column in a later deploy.

The pipeline should record what was deployed, when, and by whom. That information is invaluable during an incident. A deploy marker on the metrics dashboard turns "it broke at 14:32" into "it broke after the 14:30 deploy".

## What not to automate

Not every step needs to be in the pipeline. Manual approval for production is a reasonable control in a small team. A long end-to-end suite that takes an hour and flakes is worse than a shorter suite that runs on every pull request. Security scans and dependency updates can run on a schedule rather than on every commit.

The pipeline should be as strict as necessary and as fast as possible. If a step is slow and rarely catches anything, move it to a nightly job. If a step is fast and catches real problems, keep it in the critical path.

## Caching and concurrency

CI minutes are a budget. Caching the Composer and npm directories between runs can cut the pipeline in half. Cancelling superseded runs on the same branch avoids testing a commit that is already obsolete. A concurrency group per branch keeps the feedback focused on the latest change and saves the runners for work that still matters. The pipeline should be cheap enough that running it on every pull request is never a debate.

## Conclusion

A good CI/CD pipeline gives fast, trustworthy feedback and ships an immutable artifact through a repeatable deploy. Lint and static analysis first, tests on a clean environment, one image per commit, a deploy script that migrates and restarts workers, secrets outside the repository, and a rollback path that works. The pipeline is not a badge; it is the mechanism that lets a small team ship often without breaking things.
