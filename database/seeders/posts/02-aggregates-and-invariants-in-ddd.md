---
title: Aggregates and invariants in Domain-Driven Design
category: Architecture
level: B2
tags: ddd, php
excerpt: What an aggregate really protects, how to enforce invariants without anaemic setters, and how to keep aggregates small enough to stay useful.
featured: false
---
## The word that hides the point

In Domain-Driven Design, an aggregate is often described as a cluster of entities and value objects that change together. That definition is correct but not very useful on its own. The useful question is: what must be true after every change? An aggregate is the boundary inside which those truths are guaranteed. If a rule involves two objects and the rule must never be violated, those objects probably belong to the same aggregate.

The classic example is an order with lines. The order has a maximum number of lines, the total is derived from the lines, and a line cannot have a negative quantity. If a controller adds a line directly to the database, it can bypass all three rules. The aggregate exists to make that bypass impossible.

## Anaemic models and where they leak

An anaemic model is a set of classes with getters and setters and no behaviour. The rules live in services, controllers or, worse, in several places at once. It looks simple because each class is small, but the complexity has not disappeared; it has been distributed. A new developer has to know which service to call and in which order. Tests have to reconstruct the sequence. Bugs appear when one entry point forgets a rule that another entry point remembers.

The alternative is not to put every method on the entity. It is to make the invariant impossible to break from the outside. The order exposes `addLine()`, not `setLines()`. The line is created through a factory that validates the quantity. The total is a method, not a stored column that someone can desynchronize. The setters disappear because there is no legitimate use for them.

## A concrete aggregate

Consider an order that can have at most ten lines and must have at least one line before it is placed. The aggregate enforces both rules.

```php
final class Order
{
    /** @var list<OrderLine> */
    private array $lines = [];

    private function __construct(
        private OrderId $id,
        private CustomerId $customerId,
    ) {}

    public static function place(CustomerId $customerId): self
    {
        return new self(OrderId::generate(), $customerId);
    }

    public function addLine(ProductId $productId, int $quantity): void
    {
        if ($quantity < 1) {
            throw new InvalidQuantity();
        }

        if (count($this->lines) >= 10) {
            throw new TooManyLines();
        }

        $this->lines[] = new OrderLine($productId, $quantity);
    }

    public function total(): Money
    {
        return array_reduce(
            $this->lines,
            fn (Money $carry, OrderLine $line) => $carry->add($line->subtotal()),
            Money::zero(),
        );
    }
}
```

The constructor is private. The only way to create an order is `place()`, and the only way to add a line is `addLine()`. The rules are close to the data they protect, and the tests are small: create an order, add eleven lines, expect an exception.

## Reference other aggregates by identity

An aggregate should not hold a direct object reference to another aggregate. If an order holds a `Customer` object with its own lines and history, the boundary becomes ambiguous: changing the customer could accidentally change the order, and loading an order could load half the database. Store the `CustomerId` instead, and let the application layer load the customer when it needs it.

This rule also keeps transactions small. A transaction should modify one aggregate. If an operation needs to change two aggregates, it usually means the boundary is wrong, or the operation should be coordinated with domain events and eventual consistency. That second option is not free, but it is honest about the cost.

## Domain events as a release valve

Sometimes a change in one aggregate must trigger a reaction in another: an order is placed and the inventory must be reserved, or a payment is captured and the order must move to `paid`. The aggregate can record a domain event, and the application layer can dispatch it after the transaction commits. The aggregate stays focused on its own invariants; the reaction is an application concern.

The key is to record the event inside the aggregate and publish it outside, after persistence. Publishing inside a transaction is a classic way to send an email for an order that later rolls back.

## Sizing aggregates

A small aggregate is easier to load, lock and test. A large aggregate is a sign that we are trying to enforce a rule that spans too much. Ask: does this rule need to be strongly consistent right now, or can it be eventually consistent? If a customer's loyalty points must update when an order is placed, does the customer need to be inside the order aggregate? Usually not. The order publishes an event, and a separate handler updates the points. The order does not need to know about loyalty.

The opposite mistake is to make aggregates so small that every rule becomes a distributed transaction. If two objects must always change together to stay valid, they belong together. The boundary is a consistency decision, not a matter of taste.

## Testing invariants

I test aggregates without a database. I create the object, call the intention-revealing method, and assert either the new state or the exception. I do not test getters. I do not mock the aggregate. The test reads like a specification: "an order cannot have more than ten lines", "a line cannot have a negative quantity", "placing an empty order fails". Those tests survive refactors because they describe the rule, not the implementation.

When a rule changes, one test changes. That is the signal that the aggregate is doing its job: it concentrates the impact of change instead of scattering it.

## Conclusion

An aggregate is a promise: inside this boundary, these rules always hold. The promise is only credible if the outside cannot mutate the internals directly. Private constructors, intention-revealing methods, value objects and identity references are the tools. Keep the boundary small, publish events for reactions, and test the rules instead of the structure. The result is a model that is easier to reason about and much harder to break by accident.
