---
title: A practical testing pyramid for Laravel apps
category: Backend
level: B1
tags: testing, php
excerpt: Fast domain tests, a few honest integration tests and a thin layer of HTTP tests. Where each kind belongs and what it should assert.
featured: false
---
## The pyramid is about feedback

The testing pyramid says you should have many unit tests, fewer integration tests and very few end-to-end tests. The shape is not a law; it is a consequence of speed and cost. A unit test runs in milliseconds and tells you exactly what broke. An end-to-end test runs a browser, a database and a server, takes seconds and fails for reasons that have nothing to do with the code. The pyramid is a way to get most of the feedback from the cheap tests and use the expensive ones for the risks the cheap tests cannot cover.

In a Laravel application the layers are not always clean. A controller can call a model, a job, a service and a provider. The useful question is not "is this a unit or a feature test"; it is "what behaviour am I trying to protect, and what is the cheapest way to protect it?"

## Unit tests: the domain

The domain is where the rules live, so it is where the cheapest and most valuable tests live. An order cannot have more than ten lines. A discount cannot exceed the subtotal. A slug is unique. These tests do not need a database, a container or a request. They instantiate the object, call a method and assert the outcome or the exception.

```php
public function test_an_order_cannot_have_more_than_ten_lines(): void
{
    $order = Order::place(CustomerId::generate());

    foreach (range(1, 10) as $i) {
        $order->addLine(ProductId::generate(), 1);
    }

    $this->expectException(TooManyLines::class);

    $order->addLine(ProductId::generate(), 1);
}
```

The test reads like the rule. It survives a change from MySQL to PostgreSQL, from HTTP to a queue, from Laravel to something else. That is the point of keeping the domain independent: the tests are about the business, not the framework.

## Integration tests: the adapters

The adapters are where the framework and the infrastructure live: the Eloquent repository, the HTTP client, the queue, the cache. They deserve tests because they contain code that can be wrong in ways the domain tests cannot see. Does the repository actually persist and retrieve the aggregate? Does the client map a provider error to the right exception? Does the job update the projection?

These tests use a real database or a real Redis when the behaviour depends on it. Laravel's `RefreshDatabase` gives each test a clean schema. The tests are slower than unit tests but still fast enough to run on every pull request. I keep them focused: one test per adapter behaviour, not a full end-to-end scenario.

## Feature tests: the contract

A feature test hits the route and asserts the response. It is the right tool for the contract: status codes, validation errors, authentication, JSON shape, redirects. It is not the right tool for every business rule, because it is slower and it couples the test to the route, the middleware and the view.

```php
public function test_a_guest_cannot_create_a_post(): void
{
    $this->post('/admin/posts', ['title' => 'Hello'])
        ->assertRedirect('/admin/login');
}
```

I write feature tests for the paths a user actually takes and for the error cases that are easy to get wrong. I do not write one for every controller method. The domain tests already cover the rules; the feature tests cover the wiring.

## End-to-end tests

A browser test proves that the whole system works together: JavaScript, assets, cookies, redirects. It is also the most expensive and the most flaky. I keep a small smoke suite: the home page loads, the login works, the chat sends a message and renders a response. If those fail, the deploy is not healthy. I do not try to cover every form in the browser; the feature tests already cover the server contract.

## Fakes, mocks and the boundary

A fake is a working implementation with a simpler backend: an in-memory repository, a fake payment gateway, a fake LLM client. A mock is an assertion about a call: "the mailer was asked to send this message". Fakes are usually better for behaviour tests because they let the code run; mocks are useful at the boundary where the only thing you care about is the interaction.

The rule I follow is to mock what I do not own. The LLM provider, the payment gateway, the email service: those are boundaries, and a fake keeps the test deterministic. The database is usually real in integration tests because the query is part of the behaviour. Mocking the database tends to test the mock, not the code.

## Speed and flakiness

A test suite that takes twenty minutes stops being run. A test that fails one time in twenty trains the team to re-run instead of investigate. Both are worse than a smaller suite that is trusted. I keep the suite under a few minutes by using SQLite in memory for most tests, a real database only where the SQL matters, and fakes for the external providers.

Flaky tests usually come from time, randomness, shared state or the network. I freeze time when the behaviour depends on it, seed randomness, reset the database between tests, and never call a real network in the default suite. If a test needs the network, it is an integration test with its own tag.

## Coverage and confidence

Coverage tells you which lines ran, not which behaviour is protected. A line can be covered by a test that asserts nothing. I use coverage to find untested branches in the domain and the adapters, not as a target. A modest threshold in CI prevents a slide, and a handful of high-value tests around the critical paths gives more confidence than a hundred assertions on getters.

## Conclusion

The pyramid is a budget: spend most of the tests on the fast, precise layer, use integration tests for the adapters, and keep the end-to-end suite small and honest. Test the rules where they live, test the wiring at the boundary, and fake what you do not own. The goal is a suite that runs fast, fails for real reasons and tells you exactly where to look.
