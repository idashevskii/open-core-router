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
use Psr\Http\Message\ResponseFactoryInterface;
use OpenCore\Exceptions\NoControllersException;
use Closure;

final class Router extends AbstractMiddeware {

  public const REQUEST_ATTRIBUTE = '_routerPayload_';
  private const CACHE_FORMAT = 1;

  private function __construct(
      private array $tree,
      ResponseFactoryInterface $responseFactory,
  ) {
    parent::__construct($responseFactory);
  }

  /**
   * @param string|null $cacheFile Writable file path. If null, then caching of compiled tree will be disabled
   * @param Closure $define takes {RouterCompiler} as first argument
   */
  public static function create(?string $cacheFile, ResponseFactoryInterface $responseFactory, Closure $define) {
    $tree = null;
    if ($cacheFile && file_exists($cacheFile)) {
      $cache = include $cacheFile;
      if ($cache['version'] === self::CACHE_FORMAT) {
        $tree = $cache['tree'];
      }
    }
    if (!$tree) {
      $compiler = new RouterCompiler();
      $define($compiler);
      $tree = $compiler->compile(); // heavy operation
      if (!$tree) {
        throw new NoControllersException();
      }
      if ($cacheFile) {
        $cache = ['version' => self::CACHE_FORMAT, 'tree' => $tree];
        file_put_contents($cacheFile, '<?php return ' . var_export($cache, true) . ';');
      }
    }
    return new Router($tree, $responseFactory);
  }

  private function resolveUriHandlers(string $uri) {
    $segments = array_values(array_filter(explode('/', $uri), fn($s) => $s !== ''));
    $walk = function (array $node, $segmentIdx, $routeParams)use (&$walk, $segments) {
      list($static, $dynamic, $handler) = $node;
      if (!isset($segments[$segmentIdx])) { // all segments passed
        if ($handler) {
          return [$handler, $routeParams];
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
      return $this->errorResponse(404, 'Route not found');
    }
    $httpMethod = $request->getMethod();
    if ($httpMethod === 'HEAD') {
      $httpMethod = 'GET';
    }
    if (!isset($methodHandlers[$httpMethod])) {
      return $this->errorResponse(405, 'HTTP Method not found for route');
    }
    list($controllerClass, $controllerMethod, $paramsProps, $attrs) = $methodHandlers[$httpMethod];
    $params = [];
    foreach ($paramsProps as list($paramKind, $routeKey, $paramType)) {
      if ($paramKind === ExecutorPayloadParam::KIND_SEGMENT) {
        $paramValue = $segmentParams[$routeKey]; // must exist
      } else if ($paramKind === ExecutorPayloadParam::KIND_QUERY) {
        $paramValue = isset($queryParams[$routeKey]) ? $queryParams[$routeKey] : null;
      } else {
        $paramValue = null;
      }
      $params[] = new ExecutorPayloadParam($paramKind, $paramType, $paramValue);
    }
    $attrs[self::REQUEST_ATTRIBUTE] = new ExecutorPayload($controllerClass, $controllerMethod, $params);
    foreach ($attrs as $key => $value) {
      $request = $request->withAttribute($key, $value);
    }
    return $handler->handle($request);
  }

}
