---
title: SOLID and the dependency inversion principle in practice
category: Architecture
level: B1
tags: solid, php
excerpt: The five principles are not a checklist. The one that changes code the most is dependency inversion — and it is easy to overdo.
featured: false
---
## SOLID is a set of consequences

SOLID is often taught as five rules to memorise. In practice, the principles are consequences of two ideas: code should be easy to change, and a module should depend on abstractions rather than on details. The single responsibility principle, the open/closed principle, the Liskov substitution principle and the interface segregation principle all describe different ways a design can become rigid. The dependency inversion principle is the one that most directly changes the direction of dependencies.

I do not apply all five to every class. I use them as questions during a review: does this class have more than one reason to change? Can I add a behaviour without editing this switch? Can I replace this implementation without touching the caller? The answers tell me where the design is fragile.

## Single responsibility

The single responsibility principle says a class should have one reason to change. The word "reason" is about the actor, not the number of methods. A class can have many methods and still have one responsibility if they all serve the same actor. A class with one method can have two responsibilities if it serves two actors.

A common violation is the "god service" that validates input, applies business rules, sends emails and writes logs. Each of those is a different reason to change. Splitting them is not about making files smaller; it is about making changes local. If the email template changes, I should not have to touch the pricing rule.

## Open/closed

The open/closed principle says a module should be open for extension but closed for modification. In practice it means adding a new case should not require editing a growing `switch`. A payment provider is the classic example: adding Stripe should not mean editing a `PaymentService` that already knows about PayPal and Adyen.

The fix is polymorphism: a `PaymentGateway` interface and one implementation per provider. The caller depends on the interface, and the new provider is a new class plus a binding. The switch moves to the composition root, where it belongs. That is not zero modification, but it is modification in one place instead of in the core.

## Liskov substitution

The Liskov substitution principle says a subtype must be usable wherever the supertype is expected, without surprising the caller. A `ReadOnlyRepository` that throws on `save()` is not a valid `Repository`; the caller expects to be able to save. The violation is not about syntax; it is about the contract.

In PHP this often shows up in interfaces with methods that some implementations cannot support. The answer is to split the interface (which is also the interface segregation principle) rather than to throw `NotImplementedException`. An exception in a method that the interface promises is a design smell.

## Interface segregation

The interface segregation principle says a client should not be forced to depend on methods it does not use. A fat interface with ten methods forces every implementation to define all ten, even if it only needs two. The result is empty methods, exceptions or a leaky abstraction.

Small interfaces are easier to implement and easier to fake in tests. A `ReadsPosts` interface with one method is more useful than a `PostRepository` with ten methods when the only thing a class needs is a lookup. The smaller interface also documents the actual dependency: this class reads posts, it does not write them.

## Dependency inversion

The dependency inversion principle says high-level modules should not depend on low-level modules; both should depend on abstractions. Abstractions should not depend on details; details should depend on abstractions.

In a Laravel application, the high-level module is the use case. The low-level module is the database, the HTTP client, the queue. If the use case imports `Illuminate\Support\Facades\DB` or `Http`, it depends on a detail. If it imports a `PostRepository` and a `PaymentGateway` interface, it depends on an abstraction. The detail implements the interface and is bound in the service provider.

```php
final class PublishPost
{
    public function __construct(private PostRepository $posts) {}

    public function execute(PostId $id): void
    {
        $post = $this->posts->find($id);
        $post->publish();

        $this->posts->save($post);
    }
}
```

The use case does not know about Eloquent, transactions or the database. It knows that a repository can find and save a post. The Eloquent implementation handles the details. The test uses an in-memory repository and runs in milliseconds.

## The trap of over-abstraction

Dependency inversion is easy to overdo. An interface with one implementation and no test double is often just indirection. A repository that only wraps `Post::find()` adds a layer without a decision. The principle is about protecting a boundary that is likely to change or that is expensive to test, not about putting an interface in front of every class.

I ask: is there a second implementation, a fake for tests, or a real possibility of swapping the detail? If the answer is no to all three, the interface is probably ceremony. The cost is not just the file; it is the extra hop a new developer has to follow to understand the code.

## A note on naming

The interface should describe the role the caller needs, not the implementation. `PostRepository` is a detail-oriented name; `FindsPosts` or `StoresPosts` describes the capability. The name is part of the design: if it is hard to name without mentioning the database, the abstraction is probably leaking.

## Conclusion

SOLID is a lens, not a checklist. Single responsibility keeps changes local, open/closed keeps new cases out of the core, Liskov keeps contracts honest, interface segregation keeps dependencies narrow, and dependency inversion points the arrows at abstractions. Apply them where change is likely or testing is expensive. Skip them where they add ceremony without a decision. The goal is code that is easy to change, not code that looks like a diagram.
