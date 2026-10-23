---
title: REST API design that ages well
category: Backend
level: B2
tags: api, rest
excerpt: Resources, status codes, pagination, errors and versioning — the decisions that are cheap now and expensive to change later.
featured: false
---
## The API is a contract

An API is a contract with clients you do not control. Every field name, status code and error shape becomes a promise. You can add to a contract more easily than you can change it. The decisions that age well are the ones that leave room for growth without breaking existing clients.

REST is not a protocol; it is an architectural style. The useful parts are resources, uniform interfaces, statelessness and a clear use of HTTP semantics. I do not obsess over purity. I care about predictability: a client should be able to guess how a new endpoint behaves because the previous ones behaved the same way.

## Resources, not verbs

A URL should name a thing, not an action. `GET /orders/42` is a resource. `POST /getOrder` is an RPC call wearing a REST costume. The HTTP method carries the verb: `GET` reads, `POST` creates, `PUT` replaces, `PATCH` partially updates, `DELETE` removes.

Nested resources express ownership: `GET /customers/7/orders` lists the orders of customer 7. Keep nesting shallow. Two levels are usually enough; three levels produce URLs that are hard to read and harder to cache. If a relationship is complex, expose it as a top-level resource with a filter.

## Status codes that tell the truth

The status code is the first thing a client sees. Use it precisely.

- `200 OK` for a successful read or update with a body.
- `201 Created` for a creation, with a `Location` header pointing to the new resource.
- `204 No Content` for a successful delete or update with no body.
- `400 Bad Request` for malformed input.
- `401 Unauthorized` when authentication is missing or invalid.
- `403 Forbidden` when the client is authenticated but not allowed.
- `404 Not Found` when the resource does not exist or is hidden.
- `409 Conflict` for a state conflict, such as a duplicate idempotency key.
- `422 Unprocessable Entity` for validation errors.
- `429 Too Many Requests` with `Retry-After`.
- `500 Internal Server Error` only for an unexpected failure.

Returning `200` with `{"success": false}` forces every client to parse the body to know whether the call worked. That is a contract that ages badly.

## A consistent error shape

Errors are part of the API. A single shape makes them predictable:

```json
{
  "error": "validation_failed",
  "message": "The request is invalid.",
  "fields": {
    "email": ["The email field is required."]
  }
}
```

The machine-readable `error` code is stable; the human message can change. The field errors are keyed by field name. I include a request id so support can correlate a report with a log line. I do not leak stack traces or SQL errors.

## Pagination

A list endpoint must be paginated from day one. Returning all rows works until it does not, and by then clients depend on it. Offset pagination (`?page=2&per_page=50`) is simple and works for stable data. Cursor pagination (`?cursor=...`) is better for feeds and large tables because it does not skip or duplicate rows when items are inserted.

The response should include the pagination metadata: the next cursor or the total count, depending on the strategy. I avoid `total` on very large tables; counting millions of rows on every request is expensive. A cursor with `has_more` is often enough.

## Filtering, sorting and sparse fields

Filters are query parameters: `?status=paid&created_after=2026-01-01`. Keep the names consistent and document the allowed values. Sorting is `?sort=-created_at` or `?sort=created_at` with an explicit direction. Do not invent a new syntax per endpoint.

Sparse fields (`?fields=id,total,status`) let mobile clients request less data. It is optional, but if you add it, apply it consistently. The default should return a useful set, not every column.

## Versioning

Every API changes. The question is how to change it without breaking clients. The common options are a version in the path (`/v1/orders`), a version in a header, or additive changes only. Path versioning is the easiest to understand and to route; header versioning keeps URLs clean but is harder to test with a browser.

I prefer additive changes and a path version only for breaking changes. Adding a field is safe; renaming or removing one is not. A deprecation policy with a timeline is more useful than a version number nobody tracks. The API should announce deprecations in headers and documentation, and the old version should keep working for a defined period.

## Idempotency and safety

`GET`, `PUT` and `DELETE` should be idempotent: repeating them has the same effect. `POST` is not, which is why creation endpoints benefit from an `Idempotency-Key` header. The client sends a key; the server stores the result and returns it on a retry. This turns a flaky network into a safe retry instead of a duplicate order.

## Documentation and discovery

An API without documentation is a guessing game. OpenAPI is the common format; it can generate docs, clients and contract tests. I keep the spec next to the code and fail the build if it drifts. Examples are more useful than exhaustive schemas: a realistic request and response teaches faster than a list of types.

## Caching and conditional requests

Read endpoints should support HTTP caching. `ETag` and `Last-Modified` let a client ask "has this changed?" and receive a 304 with no body, which saves bandwidth and server work. A `Cache-Control` header tells proxies and browsers how long a response is fresh. The API and the cache must agree on the representation: a versioned ETag avoids serving a stale body after a schema change. For a public API, these headers are part of the contract, not an optimization you add later.

## Conclusion

A REST API ages well when it is predictable: resources instead of verbs, honest status codes, one error shape, pagination from the start, consistent filters and sorting, additive changes and a clear deprecation policy. The contract is the product. Treat it with the same care as the database schema, because changing it later is just as expensive.
