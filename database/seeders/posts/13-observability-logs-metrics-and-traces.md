---
title: Observability: logs, metrics and traces
category: DevOps
level: B2
tags: observability, devops
excerpt: Three signals, one question: what is the system doing right now, and why? Structured logs, useful metrics and traces that connect the dots.
featured: false
---
## You cannot fix what you cannot see

Observability is often confused with monitoring. Monitoring answers known questions: is the CPU high, is the disk full, is the endpoint returning 500s. Observability is the ability to ask new questions about a system you did not anticipate. When a customer says "the chat was slow at 14:32", can you reconstruct what happened without deploying new code?

The three pillars are logs, metrics and traces. They answer different questions. Logs tell you what happened in a specific event. Metrics tell you how the system behaves over time. Traces tell you where time went across services. A mature system correlates all three; a system with only one of them is guessing.

## Structured logs

A log line like `User 42 failed to place order` is readable by a human and almost useless to a machine. A structured log carries fields: `event=order.failed`, `user_id=42`, `order_id=abc`, `reason=payment_declined`, `duration_ms=320`. The message is still there, but the fields are queryable.

I log at the boundaries: incoming request, outgoing provider call, job start and finish, domain-level failures. I do not log every function. Noise hides signal. I include a correlation id in every line so a request can be followed across the web process, the queue worker and the provider call.

Laravel's default logger supports context arrays. A middleware can generate a request id and share it. When the job is dispatched, the id travels in the payload. The worker restores it into the log context. That one id turns a pile of lines into a story.

## Metrics

Metrics are numbers aggregated over time. They are cheap to store and cheap to query, which makes them ideal for dashboards and alerts. I use two families: request-level metrics and resource-level metrics.

For request-level, the RED method is enough for most services: rate, errors and duration. How many requests per second, how many failed, and how long did they take. I break them down by route and status code. A p95 latency that doubles is a signal even if the average looks fine.

For resources, the USE method: utilization, saturation and errors. CPU, memory, connection pool, queue depth, Redis memory. These tell you whether the system is close to a limit before users feel it.

A metric without a dimension is often useless. "Errors are up" is not actionable. "Errors on `POST /api/chat` are up because the provider returned 503" is. I add the smallest set of labels that lets me answer the next question, and I avoid high-cardinality labels such as user id or request id, which explode the time series.

## Traces

A trace follows one request across its components. Each step is a span with a start time, a duration and attributes. A trace shows that the request spent 20 ms in the controller, 1.8 seconds waiting for the LLM and 30 ms writing the log. Without traces, you know the request was slow; with traces, you know where.

In a monolith, traces are still useful for database calls, HTTP clients and queue dispatches. In a distributed system they are essential. OpenTelemetry is the common standard; the exact backend matters less than the instrumentation. I propagate the trace context through HTTP headers and job payloads, just like the correlation id.

## Alerts that people trust

An alert should be actionable and rare. If it fires every day and everyone ignores it, it is noise. I alert on symptoms that users feel: error rate above a threshold, p95 latency above a budget, queue age above a limit, certificate expiry. I do not alert on every CPU spike; CPU is a cause, not a symptom.

Each alert needs a runbook: what it means, what to check first, how to mitigate, who owns it. The runbook is part of the alert, not a wiki page nobody opens at 3 a.m. A good alert tells the on-call engineer where to look, not just that something is wrong.

## Dashboards

A dashboard is a conversation starter, not a wall of charts. I keep one overview per service: traffic, errors, latency, saturation and queue health. Then a few focused dashboards for the database, the provider and the workers. If a dashboard has fifty panels, nobody reads it.

I also keep a dashboard for the business: chats per day, refusals, token spend, indexing failures. Technical health and product health are different questions, and both matter. A service that is technically healthy but refusing every question is not healthy.

## Sampling and cost

Logs and traces are not free. High-volume services need sampling: keep all error traces, sample a percentage of successful ones, and aggregate logs rather than storing every line forever. I sample at the edge and keep the trace id in the logs so a sampled trace can still be found. Retention is a product decision too: thirty days of searchable logs is often enough, while metrics can live for a year at a lower resolution.

## The first hour of an incident

When an alert fires, I want three answers quickly: what changed, who is affected, and what is the blast radius. A deploy marker on the dashboard answers the first. Error rates by route and tenant answer the second. Queue age and dependency health answer the third. If those answers require a laptop and a query, the observability is not ready. The dashboard should be readable on a phone at 3 a.m.

## Conclusion

Observability is a practice, not a product. Structured logs with a correlation id, RED and USE metrics with useful dimensions, traces that cross process boundaries, and alerts tied to user symptoms. The goal is to answer "what happened" without guessing. Start with the request path and the queue, because that is where most incidents live. Then add the business signals that tell you whether the system is doing its job.
