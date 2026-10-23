---
title: Designing a URL shortener end to end
category: System Design
level: B2
tags: interviews, redis
excerpt: A whiteboard-friendly walkthrough of short codes, redirection latency, analytics and the failure modes that interviewers love to probe.
featured: true
---
## Start with the requirements

A URL shortener looks simple: take a long URL, return a short one, redirect when someone visits it. The interview is not about the happy path. It is about the constraints you choose and the consequences of those choices. I start by writing them down.

Functional requirements: create a short URL, redirect to the original, support optional custom aliases, expire links after a date, and collect click analytics. Non-functional requirements: redirects must be fast, the service must be highly available, and code generation must not collide.

I also ask about scale. A reasonable assumption for an interview is 100 million new links per month and a read-to-write ratio of 100:1. That means roughly 40 writes per second and 4,000 reads per second at peak. The read path dominates, which shapes the design.

## The code

The short code is the key design decision. There are three common options.

The first is a global auto-increment counter encoded in base62. The counter gives uniqueness for free, and base62 keeps the code short: 62^7 is about 3.5 trillion values, enough for decades. The downside is that the counter is a single point of coordination, and sequential codes leak information about volume.

The second is a random code with a collision check. Generate seven random base62 characters, check if the code exists, and retry if it does. With a large space, collisions are rare, but the check adds a read on the write path and the retry loop must be bounded.

The third is a distributed id such as Snowflake, then base62-encode it. Snowflake ids are roughly time-ordered, unique across nodes, and do not require a central counter. The code exposes a timestamp, which is usually acceptable.

In an interview I pick one and justify it. I usually choose Snowflake plus base62 because it avoids a central bottleneck and keeps the write path simple. If the interviewer cares about unguessable codes, I switch to random codes and note the collision check.

## Storage

The core mapping is a key-value lookup: short code to long URL. A relational database can store it, but the read path is a point lookup by primary key, which is exactly what a key-value store or a relational table with a primary-key index does well. I use a relational database as the source of truth for durability and a Redis cache for the hot links.

The schema is small: `code`, `url`, `created_at`, `expires_at`, `owner_id`. The code is the primary key. Expiration is either lazy (check `expires_at` on read) or active (a background job deletes expired rows). Lazy expiration is simpler; active cleanup keeps the table small.

## The redirect path

The redirect is the hot path, so it should do as little as possible.

1. Receive `GET /{code}`.
2. Look up the code in Redis.
3. On a miss, read from the database and populate the cache with a TTL.
4. If the link is expired or missing, return 404 or 410.
5. Return a 301 or 302 redirect.

301 is permanent and can be cached by browsers and CDNs, which reduces load. 302 is temporary and keeps analytics accurate because the browser asks again. Most shorteners use 302 or 307 for analytics. I mention the trade-off: 301 is faster and cheaper, 302 gives you data.

The cache TTL should be short enough that deleting a link takes effect reasonably quickly, but long enough to absorb traffic. A few minutes is a good starting point. For very hot links, the cache hit rate approaches one, and the database becomes a cold store.

## Analytics

Analytics must not slow down the redirect. The redirect should enqueue an event asynchronously — a message with the code, timestamp, referrer, user agent and a coarse location. A consumer aggregates these events into click counts per link per day. The write path stays a cache lookup and a redirect.

The queue introduces its own problems: duplicates, ordering and backpressure. Click counts can tolerate duplicates if they are rare, or they can be deduplicated with an event id. I prefer at-least-once delivery plus idempotent aggregation by `(code, day, event_id)` when accuracy matters.

## Custom aliases and abuse

Custom aliases introduce a uniqueness check and a namespace conflict with generated codes. The simplest solution is to reserve generated codes in the same table and check both. Abuse is a real product concern: short links are used for phishing and spam. You need a blocklist, rate limiting per account, and a way to disable a link quickly. I mention this even if the interviewer does not ask, because it shows the design is not only about throughput.

## Scaling and failure modes

Reads scale horizontally behind a load balancer. The cache absorbs most of the traffic; the database handles misses. Writes are lower volume, so a single primary database can handle them for a long time. If the write volume grows, shard by code prefix or by owner.

The interesting failure modes are cache stampedes and hot keys. When a very popular link expires from the cache, thousands of requests can hit the database at once. A short lock or a probabilistic early refresh prevents the stampede. A hot key can be replicated across cache nodes or served from a CDN edge.

If Redis is down, the system should degrade to the database rather than fail. If the database is down, cached links can still redirect, but new links cannot be created. I make the degradation explicit: the redirect path is more important than the creation path.

## What the interviewer is listening for

They are not listening for a perfect diagram. They are listening for trade-offs: why base62, why 302, why a cache, what happens when the cache dies, how you know the system is healthy. A short answer that names the constraint and the consequence is stronger than a long answer that lists technologies.

## Conclusion

The URL shortener is a study in read-heavy design. The code generation must be unique, the redirect must be fast, the analytics must be asynchronous, and the failure modes must degrade gracefully. Get those four right and the rest is operational detail.
