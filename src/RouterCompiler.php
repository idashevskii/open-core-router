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

use ReflectionClass;
use ReflectionAttribute;
use ReflectionMethod;
use ReflectionParameter;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use OpenCore\Exceptions\{
  InconsistentParamsException,
  AmbiguousRouteException
};

final class RouterCompiler {

  public function __construct() {
    
  }

  private const SEGMENT_STATIC = 1;
  private const SEGMENT_DYNAMIC = 2;

  // node: [staticSegments:{[value]:node}, dynamicSegmetns:[{param, node}], handlers:{[method]:{ctrl, fn, params}}]
  private array $tree = [];
  private array $classes = [];

  private static function stringifyCallable(array $handler) {
    list($class, $method) = $handler;
    return "$class::$method";
  }

  private function insertHandlerToTree(string $httpMethod, array $segments, array $handler) {

    $node = &$this->tree;
    while (true) {
      if (!$node) {
        $node = [null, null, null];
      }
      list(&$staticLink, &$dynamicLink, &$handlerLink) = $node;
      if (!$segments) {
        if (isset($handlerLink[$httpMethod])) {
          throw new AmbiguousRouteException("Ambiguous route for " . implode(' and ', [
                    self::stringifyCallable($handler),
                    self::stringifyCallable($handlerLink[$httpMethod]),
          ]));
        }
        $handlerLink[$httpMethod] = $handler;
        break;
      }
      list($segmentType, $segmentArg) = array_shift($segments);
      if ($segmentType === self::SEGMENT_STATIC) {
        $childNode = &$staticLink[$segmentArg];
      } else { // self::SEGMENT_DYNAMIC
        $childNode = &$dynamicLink;
      }
      $node = &$childNode;
    }
  }

  private function parseParams(array $segnemtParamIndexMap, ReflectionMethod $rMethod, array $handler, string $uri): array {
    $ret = [];
    foreach ($rMethod->getParameters() as $rParam) {
      /* @var $rParam ReflectionParameter */
      $paramType = ltrim((string) $rParam->getType(), '?'); // strip optionality marker;
      if ($rParam->getAttributes(Body::class, ReflectionAttribute::IS_INSTANCEOF)) {
        $paramKind = ExecutorPayloadParam::KIND_BODY;
      } else if (is_a($paramType, ServerRequestInterface::class, true)) {
        $paramKind = ExecutorPayloadParam::KIND_REQUEST;
      } else if (is_a($paramType, ResponseInterface::class, true)) {
        $paramKind = ExecutorPayloadParam::KIND_RESPONSE;
      } else if ($rParam->isOptional()) {
        $paramKind = ExecutorPayloadParam::KIND_QUERY;
      } else {
        $paramKind = ExecutorPayloadParam::KIND_SEGMENT;
      }
      $paramName = $rParam->name;
      if ($paramKind === ExecutorPayloadParam::KIND_SEGMENT) {
        if (isset($segnemtParamIndexMap[$paramName])) {
          $key = $segnemtParamIndexMap[$paramName];
        } else {
          throw new InconsistentParamsException(
                  "No param '$paramName' for " . self::stringifyCallable($handler) . " in route '$uri'");
        }
      } else if ($paramKind === ExecutorPayloadParam::KIND_QUERY) {
        $key = $paramName;
      } else {
        $key = null;
      }
      $ret[] = [
        $paramKind,
        $key,
        $paramType,
      ];
    }
    return $ret;
  }

  private static function matchPlaceholder(string $str): ?string {
    if (str_starts_with($str, '{') && str_ends_with($str, '}')) {
      return substr($str, 1, strlen($str) - 2);
    }
    return null;
  }

  private function parsePath(string $path) {
    $segments = [];
    $segmentParams = [];
    foreach (explode('/', $path) as $segment) {
      if ($segment === '') {
        continue;
      }
      $placeholder = self::matchPlaceholder($segment);
      if ($placeholder) {
        $segmentParams[] = $placeholder;
        $segments[] = [self::SEGMENT_DYNAMIC, null];
      } else {
        $segments[] = [self::SEGMENT_STATIC, $segment];
      }
    }
    return [$segments, array_flip($segmentParams)];
  }

  private function parseClass(string $class) {
    $rClass = new ReflectionClass($class);
    $rCtrlAttrs = $rClass->getAttributes(Controller::class, ReflectionAttribute::IS_INSTANCEOF);
    if (!$rCtrlAttrs) {
      return;
    }
    /* @var $rCtrlAttr Controller */
    $rCtrlAttr = $rCtrlAttrs[0]->newInstance();
    $prefix = $rCtrlAttr->prefix ?? '';
    $commonAttrs = $this->parseAttributes($rClass);
    foreach ($rClass->getMethods(ReflectionMethod::IS_PUBLIC) as $rMethod) {
      /* @var $rMethod ReflectionMethod */
      $rRouteAttrs = $rMethod->getAttributes(Route::class);
      if (!$rRouteAttrs) {
        continue;
      }
      /* @var $route Route */
      $route = $rRouteAttrs[0]->newInstance();
      $path = $prefix . '/' . $route->path;
      list($segments, $segnemtParamIndexMap) = $this->parsePath($path);
      $handler = [$class, $rMethod->getName()];
      $params = $this->parseParams($segnemtParamIndexMap, $rMethod, $handler, $path);
      $routeAttrs = array_merge($commonAttrs, $this->parseAttributes($rMethod), $route->attributes ?? []);
      $this->insertHandlerToTree($route->method, $segments, [...$handler, $params, $routeAttrs]);
    }
  }

  private function parseAttributes(ReflectionMethod|ReflectionClass $rMethod) {
    $rRouteAttrs = $rMethod->getAttributes(RouteAnnotation::class, ReflectionAttribute::IS_INSTANCEOF);
    return array_merge(...array_map(fn($rAttr) => $rAttr->newInstance()->getAttributes(), $rRouteAttrs));
  }

  public function scan(string $namespace, string $dir) {
    if (!is_dir($dir)) {
      return;
    }
    $scanDir = function (string $namespace, string $dir)use (&$scanDir) {
      foreach (scandir($dir, SCANDIR_SORT_NONE) as $file) {
        if ($file === '.' || $file === '..') {
          continue;
        }
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
          $scanDir($namespace . '\\' . $file, $path);
        } else if (is_file($path)) {
          $matches = [];
          if (preg_match('/^([A-Z].*)\.php$/', $file, $matches)) {
            $this->classes[] = $namespace . '\\' . $matches[1];
          }
        }
      }
    };
    $scanDir(trim($namespace, '\\'), $dir);
  }

  public function compile() {
    foreach ($this->classes as $class) {
      $this->parseClass($class);
    }
    // file_put_contents('/tmp/routes.json', json_encode($this->tree, JSON_PRETTY_PRINT));die;
    return $this->tree;
  }

}
