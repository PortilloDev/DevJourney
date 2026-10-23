---
title: CQRS without event sourcing
category: Architecture
level: B2
tags: cqrs, ddd
excerpt: Separating writes from reads can be a small, boring win — if you avoid the urge to turn it into a distributed system on day one.
featured: false
---
## A small idea with a heavy reputation

CQRS stands for Command Query Responsibility Segregation. In practice it means that the model used to change state does not have to be the same model used to read it. That is all. It does not require Kafka, event sourcing, microservices or a second database. It is a design choice about shape, and it can be introduced incrementally.

The reason it is useful is that reads and writes usually have different needs. A write needs invariants, transactions and a clear model of the rules. A read needs speed, denormalized data and a shape that matches a screen. Forcing one model to do both leads to either slow reads or complicated writes. Separating them lets each side be simple in its own way.

## The mismatch in a typical Laravel app

Imagine an orders dashboard. The write side has `Order`, `OrderLine`, `Customer` and a set of rules: an order cannot be placed twice, a line cannot be negative, a discount cannot exceed the subtotal. The read side wants a table with one row per order: order number, customer name, total, status and the number of lines. Building that query from the normalized tables requires joins, aggregates and careful filtering. It works, but the query grows with every new column on the screen.

With CQRS, the write side stays normalized. A projection table `order_summaries` is maintained from domain events or from the same transaction. The dashboard reads one table with the exact columns it needs. The query is trivial, and the write model is not polluted with reporting concerns.

## Two ways to keep the projection fresh

The first is synchronous: inside the same transaction that saves the order, update the projection. This keeps the system strongly consistent and is easy to reason about. The cost is that the write path does a little more work, and a failure in the projection can roll back the write. For a small application this is often the right trade-off.

The second is asynchronous: the write side publishes an event, and a listener or queue worker updates the projection. The write path stays fast, and the read side can be scaled independently. The cost is eventual consistency: for a few milliseconds or seconds, the dashboard may not show the order that was just placed. That is usually acceptable for a list, but it is not acceptable for a confirmation screen that must show the order immediately.

```php
final class ProjectOrderSummary
{
    public function __construct(private Connection $db) {}

    public function handle(OrderPlaced $event): void
    {
        $this->db->table('order_summaries')->insert([
            'order_id' => $event->orderId,
            'customer_name' => $event->customerName,
            'total_cents' => $event->totalCents,
            'status' => 'placed',
            'lines' => $event->lineCount,
        ]);
    }
}
```

The handler is small because it is not trying to enforce rules. It is a translator from the write model to the read model.

## Eventual consistency is a product decision

The hardest part of CQRS is not the code. It is the conversation about what the user sees after a write. If I place an order and the list does not show it yet, is that a bug? If I update my profile and the header still shows the old name, is that acceptable? The answer depends on the screen and the business.

A useful pattern is to return the new state from the command itself. The command handler knows the order it just created, so the confirmation page can render that result without waiting for the projection. The list can catch up a moment later. This gives the user immediate feedback without forcing the read model to be synchronous everywhere.

Another pattern is to show an optimistic row in the UI until the projection catches up. That is more work and more code; I only do it when the UX genuinely needs it.

## Rebuilding a projection

A projection is derived data. It should be possible to delete it and rebuild it from the source of truth. If the source is the normalized tables, the rebuild is a query. If the source is an event log, the rebuild is a replay. Either way, the projection is not the system of record; it is a cache with structure.

I always write down how to rebuild each projection. If I cannot rebuild it, it is not a projection; it is a second source of truth, and now I have two places to keep consistent. That is worse than the problem I was trying to solve.

## Pitfalls I have seen

The first pitfall is dual writes without a transaction. The order is saved, then the projection is updated in a separate call, and one of them fails. The system is now inconsistent and nobody knows. Use a transaction for synchronous projections, or an outbox for asynchronous ones.

The second is treating CQRS as an excuse to duplicate every table. Most screens do not need a projection. Start with the normalized query; add a projection when the query is genuinely painful or when the read load is high.

The third is letting the read model drive the write model. If the dashboard needs a field, it does not automatically become a domain concept. Sometimes it is just a reporting column, computed and stored for convenience. Keep the domain model honest.

## When I reach for CQRS

I reach for it when the read shape is very different from the write shape, when the read load is much higher than the write load, or when the reporting queries are slowing down the transactional path. I also reach for it when the domain has events that naturally describe what happened, because then the projection is a small step away.

I do not reach for it for a simple CRUD screen. Two models, two paths and eventual consistency are real costs. If the screen is a form and a list, Eloquent is enough. CQRS is a tool for asymmetry, not a badge of maturity.

## Conclusion

CQRS is a quiet pattern. It says: let the write model protect the rules and let the read model match the screen. It can be synchronous and small, or asynchronous and scalable. The important decisions are where the source of truth lives, how the projection is rebuilt, and what the user is allowed to see while the two sides converge. Get those right and the code is simpler than the alternative.
