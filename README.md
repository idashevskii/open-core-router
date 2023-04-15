# Strict Router

PSR-15 compliant fast Router Middleware based on precompiled searching tree.

## Features
- PSR-3, PSR-7, PSR-11, PSR-15, PSR-17 compliant.
- Controllers with attribute-based (annotations) routing.
- Passing parsed request body as param to controller method.
- Passing PSR request/response objects to controller method.
- Optional controller method params resolved as URL Query params.
- Params and body type casting according handler method signature.
- Router compile advantages:
  - No need to load all controller classes every time.
  - Because of pre-compiled tree structure, the complexity of matching algorithm is O(log n) instead of O(n)
- Almost no regex matches
- Reverse routing

## Lifecycle

Compilation:
- Looking for all Controller and its method according to routes
- Running all RouteAnnotations to resolve extra Route attributes.
- Building and caching route tree.

Runtime Phase 1 (assumption):
- Creating PSR-7 `ServerRequest` object.

Runtime Phase 2:
- Resolving Controller, method and attributes for PSR-7 `ServerRequest`.
- Modifying request by attaching custom attributes and data required to run controller.

Runtime Phase 3 (assumption):
- Running other middlewars, which can be enabled/disabled based on PSR-7 `ServerRequest` attributes.
  Example: Authorization, CORS or CSFR can be disabled for specific routes.

Runtime Phase 4:
- Reading the attributes from PSR-7 `ServerRequest`.
- Running controller with method resolved on Phase 2.
- The response body rerialized by `ResponseSerializer`

Runtime Phase 5 (assumption):
- Running the rest of Middleware chain.
- Emitting resulting SPR Response object.

## Why there is two middlewares for routing?

The main reason is to use annotations (PHP Attributes) to configure other middlewares usgin Request attributes.

## RouteAnnotations

RouteAnnotations is kind of syntax sugar. The same effect can be reached by `Route` `$attributes` parameter.
Because route attributes are resolved before route tree is cache, it should not to be dynamic.

## Special handler method arguments

There is ability to pass Request or even parsed body directly to route handler:

- If type of hander methond parameter is `ServerRequestInterface`, it will be resolved as raw $request.
- If parameter is annotated by `Body` attribute, it will be resolved by `parsedBody` property of request.

## TODO

- Reverse Routing
