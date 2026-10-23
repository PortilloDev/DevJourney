---
title: Queues, retries and dead-letter queues
category: Backend
level: B2
tags: queues, redis
excerpt: At-least-once delivery is not a bug to fix but a contract to design for: idempotency, timeouts, backoff and a place for failures to land.
featured: false
---
## Moving work off the request

A queue lets a request finish before the slow work is done: sending an email, generating a PDF, calling a third-party API, reindexing content. The request enqueues a job and returns. A worker picks it up later. The user sees a fast response and the system absorbs spikes without blocking PHP-FPM workers.

The first question is not which broker to use. It is what happens when a job runs twice, when it fails forever, and when the queue backs up. Those three cases decide whether the queue is a reliable part of the system or a source of silent data loss.

## At-least-once delivery

Most queues, including Redis and database queues, offer at-least-once delivery. A job is removed from the queue after it is acknowledged, and if the worker crashes before the acknowledgment, the job becomes visible again. That means a job can run more than once. Exactly-once delivery is not a property you get for free; it is a property you build with idempotent handlers and unique constraints.

This is not a flaw to work around. It is the contract. The handler must be safe to run twice. For an email, that can mean a deduplication key. For a database write, a unique index. For a payment, an idempotency key. The queue guarantees delivery; the handler guarantees the effect.

## Visibility timeout and retry_after

The queue needs to know when a worker has died. It uses a visibility timeout: after a job is reserved, it stays invisible for a period. If the worker does not acknowledge within that period, the job is released for another worker. In Laravel's Redis queue this is the `retry_after` setting.

The value must be larger than the maximum time a job can run. If a job takes 120 seconds and `retry_after` is 90, the queue releases it while the first worker is still running, and the job runs twice concurrently. That is how duplicate emails and double charges happen. The worker's `--timeout` must be lower than `retry_after`; the gap is the safety margin.

## Retries and backoff

A transient failure, such as a timeout from a third-party API, deserves a retry. A permanent failure, such as a validation error, does not. The job should distinguish them. Laravel lets a job define `tries`, `backoff` and `maxExceptions`.

```php
final class SendWelcomeEmail implements ShouldQueue
{
    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [10, 30, 60, 120];

    public function handle(Mailer $mailer): void
    {
        $mailer->send(new WelcomeEmail($this->user));
    }
}
```

The backoff grows so a failing provider is not hammered. The number of tries is finite so a poison job does not run forever. For jobs that call a flaky API, a circuit breaker in front of the client is a better answer than an infinite retry loop: fail fast, recover when the provider is healthy.

## Dead-letter queues

After the last retry, the job must go somewhere visible. Laravel stores failed jobs in the `failed_jobs` table by default. A dead-letter queue is the same idea: a separate queue for jobs that could not be processed, so they can be inspected, fixed and replayed.

The worst outcome is a job that disappears. If the queue is Redis and the server loses its data, the failed job may be gone. The database-backed failed jobs table is more durable; a dedicated DLQ with persistence and alerting is better still. I want a dashboard or a command that answers: how many jobs failed in the last hour, and why?

Replaying a failed job must be safe. If the job is idempotent, replay is easy. If it is not, replaying after a partial success can duplicate the effect. The dead-letter process should include a human decision or a safe replay script, not a blind `retry all`.

## Monitoring the queue

Queue depth is a leading indicator. A growing queue means the workers cannot keep up, or a job is stuck. I monitor the number of pending jobs, the age of the oldest job, the failure rate and the processing time. The age is more useful than the count: a queue with 10,000 fast jobs is fine; a queue with 10 jobs that are two hours old is not.

The worker itself needs monitoring. If the supervisor restarts it repeatedly, the logs will show why. If it is alive but not consuming, the connection or the queue name is wrong. A worker that is silent is not necessarily healthy.

## When not to use a queue

A queue is not a magic fix for a slow endpoint. If the work must happen before the response, a queue only moves the latency. If the work is small and fast, the overhead of serialization, a broker round trip and a worker may be worse than doing it inline. And if the result is needed immediately, a synchronous call is simpler.

I use queues for work that can be eventual: notifications, indexing, exports, webhooks, cleanup. I keep the request path for work that determines the response.

## Queue priorities and isolation

Not every job is equally urgent. A password reset email should not wait behind a thousand reindexing jobs. I split queues by urgency: a `default` queue for user-facing work and an `indexing` queue for heavy background work, each with its own worker pool. The queue name is part of the job contract; changing it later means updating the dispatcher and the worker. Isolation also limits the blast radius: a stuck indexing job cannot starve password resets.

## Conclusion

A queue is a contract: at-least-once delivery, a timeout, a retry policy and a place for failures. Design the handler to be idempotent, set `retry_after` above the worker timeout, use backoff, and make failed jobs visible and replayable. Monitor age, not just depth. Do that and the queue absorbs load without hiding errors; skip it and the queue becomes a place where work quietly disappears.
