---
title: Ports and adapters in a real Laravel codebase
category: Architecture
level: B1
tags: laravel, hexagonal, ddd
excerpt: A pragmatic map from controllers and Eloquent models to ports, adapters and use cases, with the trade-offs that actually matter.
featured: true
---
## Hexagonal is a direction, not a folder tree

The first time I read about hexagonal architecture I thought it was a directory layout. Controllers in one folder, repositories in another, maybe an `Interfaces` folder with a few PHP interfaces. That version of hexagonal architecture produces a lot of files and very little clarity. The useful idea is simpler: the code that expresses the business rules should not know how the outside world talks to it. HTTP, queues, cron jobs, the database and third-party APIs are details. They change more often than the rules, and they are much harder to test. Hexagonal architecture is a way of putting a boundary between the two.

In a Laravel application the default direction is the opposite. A controller receives a request, validates it, calls an Eloquent model, maybe dispatches a job, and returns a response. That is fine for a CRUD screen. It becomes painful when the same operation has to be triggered from a command, from a queue worker, or from an admin panel. Suddenly the business rule lives in a controller, and every new entry point has to copy it.

## The shape I use

I separate three things. The **domain** contains entities, value objects and the rules that must always hold. The **application** layer contains use cases: one class per operation, with a `__invoke` or `execute` method that receives a command object and returns a result. The **infrastructure** layer contains adapters: an Eloquent repository, an HTTP controller, a queue consumer, a mailer, a payment client. The application layer defines **ports** — interfaces it needs — and infrastructure implements them.

The dependency direction is always inward. The domain does not import Laravel. The application layer may use framework contracts for things like transactions or clocks, but it does not know about HTTP or Eloquent. Infrastructure knows about everything because it is the edge.

```php
final class PlaceOrder
{
    public function __construct(
        private OrderRepository $orders,
        private PaymentGateway $payments,
    ) {}

    public function execute(PlaceOrderCommand $command): OrderId
    {
        $order = Order::place($command->customerId, $command->lines);

        $this->payments->charge($order->total());

        $this->orders->save($order);

        return $order->id();
    }
}
```

`OrderRepository` and `PaymentGateway` are ports. The use case does not care whether the repository is Eloquent, Doctrine or an in-memory fake during a test. That is the whole payoff: the test of `PlaceOrder` can run in milliseconds, without a database, and it can simulate a declined payment without mocking HTTP.

## Wiring it in Laravel

Laravel's container makes the wiring almost free. Bind the interfaces in a service provider:

```php
$this->app->bind(OrderRepository::class, EloquentOrderRepository::class);
$this->app->bind(PaymentGateway::class, StripePaymentGateway::class);
```

The controller becomes a thin adapter. It validates the request, builds a command, calls the use case, and maps the result to a response. It does not contain business rules and it does not know how an order is stored. If tomorrow the same operation needs to be triggered from a console command, the command builds the same `PlaceOrderCommand` and calls the same use case. There is one implementation of the rule.

## Where this pays off — and where it does not

It pays off when the operation has real rules: pricing, permissions, state transitions, invariants that involve more than one field. It pays off when the operation has several entry points. It pays off when you want fast tests around behaviour that matters.

It does not pay off for a simple CRUD resource with no rules beyond validation. Wrapping `Post::create()` in a repository and a use case adds indirection without adding a decision. I still use Eloquent directly for admin panels and straightforward content management. The boundary is worth it where change is likely, not everywhere.

A good rule of thumb: if I cannot explain the difference between the controller and the model in one sentence, I probably do not need a use case yet.

## A smaller example: the price rule

Not every port needs a repository. Consider a checkout that applies a discount only when the customer has more than three items and the order is above fifty euros. That rule has nothing to do with HTTP. Put it in a value object or a domain service, and both the web checkout and a future mobile endpoint get the same answer. The adapter only translates the request into the inputs the rule needs. If the rule changes next month, there is one place to change and one set of tests to update.

This is also where I draw the line between a value object and a helper. A value object protects an invariant for its whole lifetime: money cannot be negative, an email is normalized on creation, a quantity is always positive. A helper is a function that happens to be useful. If the concept has rules, make it a type; if it is only formatting, a helper is enough.

## Testing the boundary

The most valuable test is not "does the controller return 200". It is "does placing an order with an expired card leave the system unchanged". That test belongs to the use case. I write it with an in-memory repository and a fake gateway. It runs fast, it documents the rule, and it does not break when I rename a route.

Then I add a small number of integration tests for the adapters: does the Eloquent repository actually persist and retrieve an order, does the controller map validation errors correctly. The adapters are where framework-specific bugs live, so they deserve tests too — just fewer, and at a lower level of abstraction.

## The trap of the perfect architecture

Hexagonal architecture can become an excuse for layers of abstraction that nobody asked for. I have seen projects with a `DomainService` that only forwards a call to a repository, and a repository that only forwards a call to Eloquent. That is not a boundary; it is a hallway.

The boundary should protect a decision. If there is no decision to protect, skip it. Start with the controller and the model, extract a use case when the second entry point appears, extract a port when the infrastructure is likely to change or when the test becomes slow. Architecture is a response to pressure, not a uniform to wear.

That is the version of hexagonal architecture I can defend in a code review: fewer folders, clearer dependencies, and tests that describe the business instead of the framework.
