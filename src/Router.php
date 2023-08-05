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

namespace OpenCore;

use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use OpenCore\Exceptions\RoutingException;
use Psr\Http\Message\UriInterface;
use Psr\Http\Message\UriFactoryInterface;
use InvalidArgumentException;

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

  public function __construct(
      RouterConfig $config,
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
    list($classIndex, $controllerMethod, $paramsProps, $routeAttrs) = $this->handlers[$methodHandlers[$httpMethod]];
    $paramKinds = [];
    $paramTypes = [];
    $rawParamValues = [];
    foreach ($paramsProps as list($paramKind, $paramType, $paramName, $segmentIndex)) {
      if ($paramKind === Router::KIND_SEGMENT) {
        $paramValue = $segmentParams[$segmentIndex]; // must exist
      } else if ($paramKind === Router::KIND_QUERY) {
        $paramValue = isset($queryParams[$paramName]) ? (string) $queryParams[$paramName] : null;
      } else {
        $paramValue = null;
      }
      $paramKinds[] = $paramKind;
      $paramTypes[] = $paramType;
      $rawParamValues[] = $paramValue;
    }
    $routeAttrs[self::REQUEST_ATTRIBUTE] = [$this->classes[$classIndex], $controllerMethod, $paramKinds, $paramTypes, $rawParamValues];
    foreach ($routeAttrs as $key => $value) {
      $request = $request->withAttribute($key, $value);
    }
    return $handler->handle($request);
  }

  public function getUri(): UriInterface {
    
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
    $argIndex = 0;
    foreach ($paramsProps as list($paramKind, $paramType, $paramName, $segmentIndex)) {
      if ($paramKind !== Router::KIND_QUERY && $paramKind !== Router::KIND_SEGMENT) {
        continue;
      }
      $value = $params[$argIndex++] ?? ($params[$paramName] ?? null);
      if ($paramKind === Router::KIND_QUERY) {
        if ($value === null) {
          continue;
        }
        if ($paramType === 'bool') {
          $value = $value ? 'true' : 'false';
        }
        $query[$paramName] = $value;
      } else { // RouterMiddleware::KIND_SEGMENT
        if ($value === null) {
          throw new ErrorException("Param $paramName in route '$name' is required");
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
