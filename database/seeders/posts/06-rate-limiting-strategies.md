---
title: Rate limiting strategies that actually hold
category: Backend
level: B1
tags: redis, api
excerpt: Fixed windows, sliding windows, token buckets and the Redis details that decide whether your limiter is fair under load.
featured: false
---
## Why rate limiting is a product feature

Rate limiting is often treated as a security checkbox. In practice it is a product decision. It protects the database from a crawler, keeps one tenant from starving the others, and gives the API a predictable cost. It also defines what happens when a client is too fast: a clear 429 with a retry header is a much better experience than a timeout or a corrupted response.

The algorithm matters because different algorithms fail in different ways. A limiter that is correct on a single server can be wrong behind a load balancer. A limiter that is fair in the average case can be unfair at the boundary. I want to know the shape of the failure before I choose one.

## Fixed window

The simplest approach counts requests per fixed interval: 100 requests per minute, reset at the start of each minute. It is easy to implement with a Redis key per window.

```php
$key = "rate:{$userId}:".now()->format('YmdHi');
$count = Redis::incr($key);
if ($count === 1) {
    Redis::expire($key, 60);
}
```

The problem is the boundary. A client can send 100 requests at 12:00:59 and another 100 at 12:01:00, which is 200 requests in two seconds. The limit is technically respected, but the backend sees a burst twice as large as intended. For many APIs that is acceptable; for a fragile downstream service it is not.

## Sliding window log

A sliding window log stores the timestamp of every request and counts how many fall inside the last interval. It is precise and handles the boundary correctly.

```text
ZREMRANGEBYSCORE rate:user 0 now-60
ZCARD rate:user
ZADD rate:user now now
```

The cost is memory: one entry per request. For a limit of 100 per minute it is trivial. For a limit of 100,000 per minute it is not. The log approach is a good fit for expensive operations with low limits, such as password resets or payment attempts.

## Sliding window counter

A compromise uses two fixed windows and interpolates. If the current window is 30 seconds old and the previous window had 80 requests, the estimated count is 80 multiplied by the fraction of the previous window still inside the sliding interval. It uses two counters per key instead of a full log.

The estimate can be slightly wrong, but it is cheap and smooths the boundary well enough for most APIs. I use it when the limit is high and the memory of a full log is not justified.

## Token bucket

A token bucket holds up to `capacity` tokens and refills at a fixed rate. Each request consumes one token. If the bucket is empty, the request is rejected or queued. The bucket allows short bursts up to the capacity and enforces the average rate over time. That is often exactly what an API wants: a client can burst after being idle, but cannot sustain an unfair rate.

```text
tokens = min(capacity, tokens + elapsed * rate)
if tokens < 1: reject
tokens -= 1
```

The state is two numbers per key: the current tokens and the last refill time. The calculation is cheap and can be done atomically in Redis with a Lua script.

## Leaky bucket

A leaky bucket queues requests and releases them at a constant rate. It smooths traffic instead of allowing bursts. It is useful when the downstream service needs a steady rate, but it adds latency and requires a queue. For a public API, the token bucket is usually the better default; for an internal integration with a strict downstream, the leaky bucket can be the right choice.

## Doing it atomically in Redis

The naive implementation reads the counter, decides, and writes it back. Two concurrent requests can both read the same value and both be allowed. The fix is a Lua script, which Redis executes atomically.

```lua
local key = KEYS[1]
local capacity = tonumber(ARGV[1])
local rate = tonumber(ARGV[2])
local now = tonumber(ARGV[3])

local bucket = redis.call('HMGET', key, 'tokens', 'ts')
local tokens = tonumber(bucket[1]) or capacity
local ts = tonumber(bucket[2]) or now

tokens = math.min(capacity, tokens + (now - ts) * rate)

if tokens < 1 then
  return 0
end

redis.call('HMSET', key, 'tokens', tokens - 1, 'ts', now)
redis.call('EXPIRE', key, 3600)
return 1
```

The script returns whether the request is allowed. The application adds the headers: `X-RateLimit-Limit`, `X-RateLimit-Remaining`, and on rejection `Retry-After`. Clients can then back off intelligently instead of hammering the endpoint.

## What to key on

Per IP is the easiest, but it punishes users behind a corporate NAT. Per user is fairer but requires authentication. Per API key is the right unit for integrations. In practice I combine them: a generous per-IP limit for unauthenticated traffic and a stricter per-user or per-key limit for authenticated traffic. I also add a global limit to protect the service from a distributed attack that stays under every individual limit.

## Choosing the limits

The algorithm is only half the work. The limit itself should come from capacity, not from a round number. I measure the p95 latency and the error rate of the downstream service, find the throughput at which it stays healthy, and set the limit below that. Then I load-test the limiter itself: what happens when Redis is slow, when many keys are hot, when the window resets. A limiter that is correct but adds 50 ms to every request is a new bottleneck. I also log rejections with the key and the route so I can tell a noisy client from an attack.

## Conclusion

Rate limiting is a small algorithm with large operational consequences. Fixed windows are simple but bursty at the boundary. Sliding logs are precise but memory-hungry. Token buckets allow useful bursts and are cheap to keep in Redis. Whatever the choice, make the check atomic, return clear headers, and decide explicitly what happens when Redis is unavailable. A limiter that fails open protects availability; one that fails closed protects the downstream. That is a product decision, not a library default.
