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
use OpenCore\Exceptions\NoControllersException;
use OpenCore\Exceptions\RoutingException;

final class Router implements MiddlewareInterface {

  public const REQUEST_ATTRIBUTE = '_routerPayload_';
  private const CACHE_FORMAT = 1;
  public const DEFINE = 'routerDefine';

  private ?array $tree;
  private ?string $cacheFile = null;

  public function __construct(
      private RouterConfig $config,
  ) {
    $tree = null;
    if ($config->isCacheEnabled()) {
      $cacheFile = sys_get_temp_dir() . '/op-router-cache.php';
      if (file_exists($cacheFile)) {
        $cache = include $cacheFile;
        if ($cache['version'] === self::CACHE_FORMAT) {
          $tree = $cache['tree'];
        }
      }
      if (!$tree) {
        $tree = $this->makeTree();
        $cache = ['version' => self::CACHE_FORMAT, 'tree' => $tree];
        file_put_contents($cacheFile, '<?php return ' . var_export($cache, true) . ';');
      }
      $this->cacheFile = $cacheFile;
    } else {
      $tree = $this->makeTree();
    }
    $this->tree = $tree;
  }

  public function clearCache() {
    if ($this->cacheFile && file_exists($this->cacheFile)) {
      unlink($this->cacheFile);
    }
  }

  private function makeTree(): array {
    $compiler = new RouterCompiler();
    $this->config->define($compiler);
    $ret = $compiler->compile(); // heavy operation
    if (!$ret) {
      throw new NoControllersException();
    }
    return $ret;
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
      throw new RoutingException(code: 404);
    }
    $httpMethod = $request->getMethod();
    if ($httpMethod === 'HEAD') {
      $httpMethod = 'GET';
    }
    if (!isset($methodHandlers[$httpMethod])) {
      throw new RoutingException(code: 405);
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
