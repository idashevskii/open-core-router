<?php

declare(strict_types=1);

/**
 * @license   MIT
 *
 * @author    Ilya Dashevsky
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace OpenCore\Router;

use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use OpenCore\Router\Exceptions\RoutingException;
use Psr\Http\Message\UriInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use InvalidArgumentException;
use JsonException;

final class Router implements MiddlewareInterface {

  public const REQUEST_ATTRIBUTE = '$$router';
  public const KIND_SEGMENT = 1;
  public const KIND_BODY = 2;
  public const KIND_QUERY = 3;
  public const KIND_REQUEST = 4;
  public const KIND_RESPONSE = 5;

  private ?array $tree;
  private ?array $classes;
  private ?array $handlers;
  private ?array $namedHandlers;
  private ?array $currentRouteData = null;

  public function __construct(
      private RouterConfig $config,
      private ResponseFactoryInterface $responseFactory,
      private UriFactoryInterface $uriFactoryInterface,
  ) {
    list($this->tree, $this->classes, $this->handlers, $this->namedHandlers) = $config->storeCompiledData(function ()use ($config) {
      $compiler = new RouterCompiler();
      foreach ($config->getControllerDirs() as $ns => $dir) {
        $compiler->scan($ns, $dir);
      }
      return $compiler->compile(); // heavy operation
    });
  }

  private function resolveUriHandlers(string $uri) {
    $segments = array_values(array_filter(explode('/', $uri), fn($s) => $s !== ''));
    $walk = function (array $node, $segmentIdx, $routeParams)use (&$walk, $segments) {
      list($static, $dynamic, $methodHandlers) = $node;
      if (!isset($segments[$segmentIdx])) { // all segments passed
        if ($methodHandlers) {
          return [$methodHandlers, $routeParams];
        }
        return null; // not found
      }
      $segment = $segments[$segmentIdx];
      // static segments has priority
      if ($static && isset($static[$segment])) {
        $res = $walk($static[$segment], $segmentIdx + 1, $routeParams);
        if ($res) {
          return $res;
        }
      }
      // fallback to dynamic segment
      if ($dynamic) {
        $routeParams[] = $segment;
        $res = $walk($dynamic, $segmentIdx + 1, $routeParams);
        if ($res) {
          return $res;
        }
      }
      return null;
    };
    return $walk($this->tree, 0, []);
  }

  public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
    list($methodHandlers, $segmentParams) = $this->resolveUriHandlers($request->getUri()->getPath());
    $queryParams = $request->getQueryParams();
    if (!$methodHandlers) {
      throw new RoutingException(code: 404);
    }
    $httpMethod = $request->getMethod();
    if ($httpMethod === 'HEAD') {
      $httpMethod = 'GET';
    }
    if (!isset($methodHandlers[$httpMethod])) {
      throw new RoutingException(code: 405);
    }
    list($classIndex, $controllerMethod, $paramsProps, $routeAttrs, $routeName) = $this->handlers[$methodHandlers[$httpMethod]];
    $paramMap = [];
    foreach ($paramsProps as list($paramKind, $paramType, $paramName, $segmentIndex)) {
      $paramMap[$paramName] = match ($paramKind) {
        self::KIND_SEGMENT => $this->config->deserialize($paramType, $segmentParams[$segmentIndex]),
        self::KIND_BODY => $this->config->deserialize($paramType, (string) $request->getBody()),
        self::KIND_REQUEST => $request,
        self::KIND_RESPONSE => $this->responseFactory->createResponse(),
        self::KIND_QUERY => isset($queryParams[$paramName]) ? $this->config->deserialize($paramType, $queryParams[$paramName]) : null,
      };
    }
    if ($routeName) {
      $this->currentRouteData = [$routeName, $paramMap];
    }
    $routeAttrs[self::REQUEST_ATTRIBUTE] = [$this->classes[$classIndex], $controllerMethod, $paramMap];
    foreach ($routeAttrs as $key => $value) {
      $request = $request->withAttribute($key, $value);
    }
    return $handler->handle($request);
  }

  /**
   * @return RouteLocation|null Returns `null` if current route has no `name`
   */
  public function currentLocation(): ?RouteLocation {
    if (!$this->currentRouteData) {
      return null;
    }
    list($routeName, $paramMap) = $this->currentRouteData;
    return $this->createLocation($routeName, $paramMap);
  }

  public function createLocation(string $name, array $params = null) {
    $normalizedParams = [];
    list($handlerIdx) = $this->namedHandlers[$name];
    list(,, $paramsProps) = $this->handlers[$handlerIdx];
    foreach ($paramsProps as list($paramKind,, $paramName,)) {
      if ($paramKind === self::KIND_SEGMENT || $paramKind === self::KIND_QUERY) {
        $normalizedParams[$paramName] = $params[$paramName] ?? null;
      }
    }
    return new RouteLocation($name, $normalizedParams);
  }

  public function reverseLocation(RouteLocation $location): UriInterface {
    return $this->reverse($location->name, $location->params);
  }

  public function reverse(string $name, array $params = null): UriInterface {
    if (!isset($this->namedHandlers[$name])) {
      throw new InvalidArgumentException("Route $name is not defined");
    }
    list($handlerIdx, $resSegments) = $this->namedHandlers[$name];

    list(,, $paramsProps) = $this->handlers[$handlerIdx];

    $relToAbsSegmentIdx = [];
    $relIdx = 0;
    foreach ($resSegments as $i => $segmentVal) {
      if ($segmentVal === null) {
        $relToAbsSegmentIdx[$relIdx++] = $i;
      }
    }

    $query = [];
    foreach ($paramsProps as list($paramKind, $paramType, $paramName, $segmentIndex)) {
      if ($paramKind !== self::KIND_QUERY && $paramKind !== self::KIND_SEGMENT) {
        continue;
      }
      $value = $params[$paramName] ?? null;
      if ($paramKind === self::KIND_QUERY) {
        if ($value === null) {
          continue;
        }
        if ($paramType === 'bool') {
          $value = $value ? 'true' : 'false';
        }
        $query[$paramName] = $value;
      } else { // RouterMiddleware::KIND_SEGMENT
        if ($value === null) {
          throw new InvalidArgumentException("Param $paramName in route '$name' is required");
        }
        $resSegments[$relToAbsSegmentIdx[$segmentIndex]] = $value;
      }
    }
    $ret = $this->uriFactoryInterface->createUri('/' . implode('/', $resSegments));
    if ($query) {
      $ret = $ret->withQuery(http_build_query($query));
    }
    return $ret;
  }

}
