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
  private const CACHE_FORMAT = 2;

  public const KIND_SEGMENT = 1;
  public const KIND_BODY = 2;
  public const KIND_QUERY = 3;
  public const KIND_REQUEST = 4;
  public const KIND_RESPONSE = 5;

  private ?array $tree;
  private ?array $classes;
  private ?string $cacheFile = null;

  public function __construct(
      private RouterConfig $config,
  ) {
    $cacheEnabled = $config->isCacheEnabled();
    if ($cacheEnabled) {
      $this->cacheFile = sys_get_temp_dir() . '/op-router-cache.php';
      if (file_exists($this->cacheFile)) {
        list('version' => $version, 'tree' => $this->tree, 'classes' => $this->classes) = include ($this->cacheFile);
        if ($version === self::CACHE_FORMAT) {
          return;
        }
      }
    }
    // recompile if there is no usable cache
    $compiler = new RouterCompiler();
    $this->config->define($compiler);
    list($this->tree, $this->classes) = $compiler->compile(); // heavy operation
    if (!$this->classes) {
      throw new NoControllersException();
    }
    if ($cacheEnabled) {
      $cache = ['version' => self::CACHE_FORMAT, 'tree' => $this->tree, 'classes' => $this->classes];
      file_put_contents($this->cacheFile, '<?php return ' . var_export($cache, true) . ';');
    }
  }

  public function clearCache() {
    if ($this->cacheFile && file_exists($this->cacheFile)) {
      unlink($this->cacheFile);
    }
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
    list($classIndex, $controllerMethod, $paramsProps, $attrs) = $methodHandlers[$httpMethod];
    $paramKinds = [];
    $paramTypes = [];
    $rawParamValues = [];
    foreach ($paramsProps as list($paramKind, $routeKey, $paramType)) {
      if ($paramKind === Router::KIND_SEGMENT) {
        $paramValue = $segmentParams[$routeKey]; // must exist
      } else if ($paramKind === Router::KIND_QUERY) {
        $paramValue = isset($queryParams[$routeKey]) ? (string) $queryParams[$routeKey] : null;
      } else {
        $paramValue = null;
      }
      $paramKinds[] = $paramKind;
      $paramTypes[] = $paramType;
      $rawParamValues[] = $paramValue;
    }
    $attrs[self::REQUEST_ATTRIBUTE] = new ExecutorPayload(
        $this->classes[$classIndex], $controllerMethod, $paramKinds, $paramTypes, $rawParamValues);
    foreach ($attrs as $key => $value) {
      $request = $request->withAttribute($key, $value);
    }
    return $handler->handle($request);
  }

}
