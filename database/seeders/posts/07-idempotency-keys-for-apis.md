---
title: Idempotency keys for APIs that retry
category: Backend
level: B2
tags: api, redis
excerpt: How to make a POST safe to retry without duplicating payments, orders or emails — and why a unique index is not enough.
featured: false
---
## The duplicate that costs money

A client sends a request to charge a card. The server processes it, but the response is lost because the network drops or the client times out. The client retries. Without protection, the customer is charged twice. This is not an edge case; it is the normal behaviour of distributed systems. Retries are necessary, so the server must distinguish a retry from a new intent.

The usual answer is an idempotency key. The client generates a unique value for the operation and sends it in a header, typically `Idempotency-Key`. The server stores the key and the outcome. If the same key arrives again, the server returns the stored outcome instead of executing the operation again.

## What to store

The record needs more than the key. It needs a fingerprint of the request, the response, and a status. The fingerprint prevents a client from reusing a key for a different request; if the body changes, the server should reject the key rather than return an unrelated response.

A practical schema:

```text
idempotency_key   primary key
request_hash      sha256 of method + path + body
status            in_progress | completed | failed
response_code     int
response_body     json
created_at        timestamp
expires_at        timestamp
```

The `request_hash` is a safety check. If someone sends the same key with a different amount, returning the old response would be worse than an error. The server returns a 422 or 409 explaining the key was reused with a different payload.

## The race condition

Two identical requests can arrive at the same time. Both check the store, both find nothing, both execute the charge. The fix is an atomic insert or lock before doing the work.

With a relational database, insert the key with a unique constraint and status `in_progress` inside a transaction. The winner proceeds; the loser sees a duplicate-key error and waits or returns a conflict. With Redis, `SET key value NX` gives the same primitive. The important part is that only one request owns the key at a time.

If the request is still in progress when the retry arrives, the server can return 409 with a `Retry-After`, or wait briefly for the first request to finish. Waiting is friendlier but ties up a connection. I usually return 409 for long operations and wait for short ones.

## Once the operation completes

When the work finishes, update the record with the response and mark it `completed` in the same transaction as the business change, if possible. If the response is written after the charge and the process crashes in between, the next retry sees `in_progress` and cannot know whether the charge happened. This is the classic dual-write problem.

There are two honest solutions. The first is to make the business operation itself idempotent, so executing it twice is harmless. The second is to write the idempotency record in the same transaction as the business state, which requires them to share a database. If they cannot share a database, use an outbox or a reconciliation job. I prefer the shared transaction when possible; it is simpler to reason about.

## Expiration

Keys cannot be stored forever. A TTL of 24 hours is common; some providers keep them for 24 hours, others longer. The TTL must be longer than the client's retry window, or a late retry can repeat the operation. If the client retries after the TTL, the operation runs again, and that is expected. Document the window.

Cleanup is a background job or a Redis TTL. With a relational table, an index on `expires_at` and a periodic delete keeps it small. Do not delete keys immediately after completion; the whole point is to catch retries.

## What counts as idempotent

`GET`, `PUT` and `DELETE` are idempotent by definition: repeating them has the same effect. `POST` is not, which is why payment and order creation endpoints need the key. A `PATCH` can be idempotent if it sets absolute values, but it is not if it increments a counter. The HTTP method is a hint, not a guarantee; the server must implement the semantics.

Be careful with side effects that are not part of the response. Sending an email, publishing to a queue or calling a third-party API are all effects that a retry can duplicate. The idempotency key should cover the whole operation, not just the database write. If the email is sent by a worker after the response, the worker needs its own deduplication.

## A minimal Laravel shape

```php
final class ChargeCustomer
{
    public function execute(string $key, ChargeRequest $request): ChargeResponse
    {
        return DB::transaction(function () use ($key, $request) {
            $record = IdempotencyRecord::firstOrCreate(
                ['key' => $key],
                ['request_hash' => $request->hash(), 'status' => 'in_progress'],
            );

            if ($record->request_hash !== $request->hash()) {
                throw new IdempotencyKeyReused();
            }

            if ($record->status === 'completed') {
                return ChargeResponse::fromRecord($record);
            }

            $response = $this->gateway->charge($request);

            $record->update([
                'status' => 'completed',
                'response_code' => 200,
                'response_body' => $response->toArray(),
            ]);

            return $response;
        });
    }
}
```

The transaction makes the record and the business change atomic when they share a connection. The gateway call inside the transaction is a trade-off: it holds the transaction open during a network call. For a slow gateway, an outbox plus a worker is safer.

## Generating the key

The client should generate the key, not the server. A UUID v4 or a random 128-bit value is enough; it must be unique per intent and stable across retries. If the client generates a new key on every retry, the protection is gone. Libraries usually accept a key from the caller; a CLI or mobile app should persist the key with the pending operation so a crash and restart can reuse it. The server should reject missing keys on endpoints that require them, rather than silently generating one.

## Conclusion

Idempotency keys are a contract between the client and the server: the client promises a stable key for one intent, and the server promises to execute that intent at most once. The implementation needs a fingerprint, an atomic claim, a stored response and a TTL. The hard part is not the table; it is making the record and the side effects consistent. Get that right and retries become boring, which is exactly what you want.
