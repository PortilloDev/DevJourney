---
title: Event sourcing, projections and replay
category: Architecture
level: C1
tags: cqrs, ddd, event-sourcing
excerpt: Storing what happened instead of what is gives you a perfect audit log and flexible read models — at the price of versioning, replay and deletion.
featured: false
---
## State is a lossy compression of history

Most applications store the current state of an entity. A row says that order 42 is `paid`, has a total of 120 euros and belongs to customer 7. That row is enough to render the order, but it does not tell you how it got there. Did it start as a draft? Was it cancelled and re-placed? Was the total changed after payment? If the business asks those questions, the row cannot answer.

Event sourcing flips the model. Instead of storing the current state, it stores the sequence of events that produced it: `OrderPlaced`, `LineAdded`, `DiscountApplied`, `PaymentCaptured`, `OrderShipped`. The current state is a projection built by applying those events in order. The events are the source of truth; the state is a cache that can be rebuilt at any time.

That sounds like a lot of work, and it is. Event sourcing is not a default choice. It is justified when history is valuable, when you need a complete audit trail, when you want multiple read models from the same facts, or when the domain is naturally event-shaped. It is a poor fit for simple CRUD.

## The event store

An event store is an append-only table. Each row has an aggregate id, a sequence number, an event type, a payload and metadata such as the time and the user. The sequence number is critical: it gives a total order per aggregate and lets you detect concurrent writes (optimistic concurrency). If two processes try to append sequence 5 to the same aggregate, one succeeds and the other gets a conflict.

```sql
create table events (
    aggregate_id   uuid not null,
    sequence       int  not null,
    type           varchar(100) not null,
    payload        json not null,
    recorded_at    timestamp not null,
    primary key (aggregate_id, sequence)
);
```

The payload should contain everything needed to reconstruct the event, not pointers to mutable rows. If `LineAdded` stores a product id and a quantity, replay does not need to look up the product table. That keeps the event self-contained and makes historical reconstruction reliable even when the product changes.

## Rebuilding an aggregate

To load an order, fetch its events in sequence order and apply them to a fresh instance. The aggregate's `apply` methods mutate state and must not perform side effects such as sending emails or calling APIs. Replay is pure: same events, same result.

```php
$order = Order::reconstitute($this->events->forAggregate($id));
```

`reconstitute` starts with an empty order and folds the events. The business methods (`place`, `addLine`, `pay`) validate the command against the current state, produce a new event, and append it. The state changes only after the event is persisted, or the event is persisted and then applied — the important part is that persistence and application are consistent.

This is where the write model differs from a normal ORM. You do not update a row; you append a fact. The row you read is a projection of those facts. That mental shift is the main cost of adoption.

## Projections

A projection listens to events and writes a read model optimized for a screen or a query. The order list projection might store one row per order with the customer name, the total and the status. The customer dashboard might store a different shape. Both are derived from the same events, and both can be rebuilt.

Projections can be synchronous (updated in the same transaction as the event) or asynchronous (updated by a consumer). Asynchronous projections scale better but lag. The lag is usually small, but it must be visible to the product: a user who just placed an order should see it in the confirmation, even if the list catches up a second later.

Each projection needs a checkpoint: the last event sequence it processed. On restart, it resumes from the checkpoint. If it crashes halfway through a batch, it reprocesses from the checkpoint. Projection handlers must therefore be idempotent, or the checkpoint must advance in the same transaction as the projection write.

## Replaying history

Replay is the superpower and the trap. Because events are the source of truth, you can delete a projection and rebuild it from the beginning. That is invaluable when you add a new read model or fix a bug in an existing one. But replay can take hours for a large event store, and it competes with live traffic.

The usual tools are snapshots and batch replay. A snapshot stores the aggregate state at a sequence number, so loading starts from the snapshot and applies only newer events. A batch replay processes events in chunks, with backpressure and monitoring. I treat replay as an operational procedure, not a button: who runs it, on which environment, with which cutoff, and how do we know it finished correctly.

## Versioning events

Events are immutable, but the code that interprets them changes. A field is renamed, a value object gains a rule, an event is split in two. You cannot rewrite history in place without losing the audit trail, so you version the events and write upcasters that translate old payloads to the current shape.

The simplest approach is to keep old event classes and add new ones. A `LineAddedV2` can be handled alongside `LineAdded`. The aggregate's `apply` method dispatches on the event type and can upcast on the fly. The important rule is that an old event must always remain readable, even if the current code would never produce it.

## Deletion and privacy

An append-only log and the right to be forgotten are in tension. If a customer asks for deletion, you cannot silently rewrite the events without breaking the audit trail. The common answer is crypto-shredding: encrypt personal data with a per-customer key, and delete the key when the customer is deleted. The events remain, but the personal data becomes unreadable. Another option is to store personal data outside the event and keep only a reference, then delete the referenced row. Both require planning before the first event is written.

## When not to use event sourcing

Do not use event sourcing for a content site, a simple admin panel or a CRUD API. The overhead of event versioning, projections, replay and eventual consistency is not justified when the history has no business value. Use it when the audit trail is a requirement, when the domain has meaningful events, or when you need multiple read models from the same facts.

## Conclusion

Event sourcing is a commitment to history. It gives you a complete audit log, flexible projections and the ability to rebuild state, but it also gives you versioning, replay operations and privacy problems to solve. Adopt it deliberately, keep the write model pure, make projections idempotent, and treat replay as a first-class operational concern. Then the log becomes an asset instead of a liability.
