---
title: Caching patterns with Redis that survive production
category: Backend
level: B2
tags: redis, caching
excerpt: Cache-aside is easy; invalidation, stampedes and hot keys are where the real decisions live. A practical tour of the patterns I trust.
featured: false
---
## Cache is a copy, not a source

A cache is a copy of data that lives somewhere else. That definition sounds obvious, but it settles most arguments. If the cache disagrees with the source of truth, the source wins. The cache can be deleted at any time and the system should still work, just slower. If deleting the cache breaks the system, it is not a cache; it is a database with a different name.

Redis is my default because it is fast, has useful data structures and supports atomic operations. The pattern matters more than the server. I want a clear answer to three questions: what is cached, how long it lives, and how it is invalidated.

## Cache-aside

The most common pattern is cache-aside. The application reads from the cache. On a miss, it reads from the database, writes the value to the cache with a TTL, and returns it. On a write, it updates the database and deletes the cache key.

```php
$key = "post:{$id}:html";
$html = Redis::get($key);

if ($html === null) {
    $html = $post->body_html;
    Redis::setex($key, 3600, $html);
}

return $html;
```

The TTL bounds staleness even if invalidation fails. That is the safety net. The delete on write keeps the window small. I prefer deleting to updating the cache on write: an update can race with a concurrent read and leave an old value behind, while a delete lets the next read repopulate from the source.

## Read-through and write-through

Read-through moves the cache-aside logic into the cache layer: the application asks the cache, and the cache loads from the database on a miss. It reduces duplication but hides the database call inside a library, which can make latency and failures harder to see.

Write-through writes to the cache and the database together. It keeps the cache warm but doubles the write cost and creates a consistency question if one of the two writes fails. Write-behind writes to the cache and flushes to the database asynchronously; it is fast but risks data loss if the cache dies before the flush. I rarely use write-behind for business data; it is more suitable for counters and analytics where a small loss is acceptable.

## Invalidation

There are two kinds of invalidation: time-based and event-based. Time-based is a TTL. It is simple and robust, but it means the data can be stale for up to the TTL. Event-based is a delete or update triggered by a change. It is precise but requires every write path to remember to invalidate.

I use both. The TTL is the maximum staleness I can tolerate. The event is the attempt to be fresher. If an event is missed, the TTL repairs it. For a content site, a short TTL and a delete on save is usually enough. For a price or inventory, I avoid caching the authoritative value at all; I cache a derived view with a short TTL and accept that the checkout revalidates.

A common bug is invalidating the wrong key. If the cache key includes the user, the locale or a version, the delete must use the same key. I centralize key generation in one function and use it for both read and invalidate. That sounds trivial until a key changes and half the deletes miss.

## Stampedes

A cache stampede happens when a popular key expires and many requests hit the database at once. The database sees a spike exactly when the cache was supposed to protect it. The classic fixes are a lock, a probabilistic early refresh, or a stale-while-revalidate policy.

With a lock, one request recomputes the value while the others wait or serve a stale copy. With early refresh, each request has a small chance of refreshing the key before it expires, spreading the work. With stale-while-revalidate, the cache serves the old value for a short grace period while one request refreshes in the background. The right choice depends on how bad a stale value is. For a blog page, stale for a few seconds is fine. For a bank balance, it is not.

## Hot keys

A single key can be so popular that it saturates one Redis node. Sharding the key with a random suffix and reading from one of the shards distributes the load, at the cost of more keys to manage. Another option is a local in-process cache in front of Redis, with a very short TTL, for data that tolerates a little staleness. I use local caches for configuration and feature flags, not for per-user data.

## What not to cache

Do not cache data that must be strongly consistent with a write the user just made. Do not cache secrets or personal data without encryption and a clear retention policy. Do not cache a query that is already fast; the cache adds a failure mode and a consistency problem for no gain. And do not cache the result of a write in a way that hides a failed transaction.

I also avoid caching paginated results with a page number, because inserting a row shifts the pages and the cache serves duplicates. Cursor-based pagination is friendlier to caching.

## Operating the cache

A cache is infrastructure, so it needs monitoring: hit rate, evictions, memory usage, latency and errors. A hit rate that suddenly drops usually means a key change or a deploy. A Redis instance at its memory limit starts evicting keys, which turns a cache into a random source of misses. Set a maxmemory policy and alert before it is reached.

When Redis is down, the application should degrade to the database, not fail. That means the cache client must have a short timeout and a fallback. A cache outage should be slower, not fatal. I test that path by disabling Redis in staging and watching the latency.

## Conclusion

Caching is a trade between freshness, latency and complexity. Cache-aside with a TTL and a delete on write is the default. Invalidation must use the same key as the read. Stampedes and hot keys need explicit handling. And the system must survive the cache disappearing. If those four things are true, the cache is an asset; if not, it is a second source of truth waiting to disagree with the first.
