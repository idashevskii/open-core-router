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
use OpenCore\Exceptions\InvalidParamTypeException;
use OpenCore\Exceptions\InconsistentParamsException;
use OpenCore\Exceptions\AmbiguousRouteException;

final class RouterCompiler {

  public function __construct() {
    
  }

  // node: [staticSegments:{[value]:node}, dynamicSegmetns:[{param, node}], handlers:{[method]:{ctrl, fn, params}}]
  private array $tree = [];
  private array $classes = [];

  private function stringifyCallable(array $handler) {
    list($classIndex, $method) = $handler;
    $class = $this->classes[$classIndex];
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
                    $this->stringifyCallable($handler),
                    $this->stringifyCallable($handlerLink[$httpMethod]),
          ]));
        }
        $handlerLink[$httpMethod] = $handler;
        break;
      }
      list($segmentType, $segmentArg) = array_shift($segments);
      if ($segmentType === RouterParser::SEGMENT_STATIC) {
        $childNode = &$staticLink[$segmentArg];
      } else { // self::SEGMENT_DYNAMIC
        $childNode = &$dynamicLink;
      }
      $node = &$childNode;
    }
  }

  private function parseParams(array $segnemtParamIndexMap, ReflectionMethod $rMethod, array $handler, string $uri): array {
    $ret = [];
    foreach (RouterParser::parseParams($rMethod) as list($paramName, $paramKind, $paramType)) {
      $supportedParamTypes = match ($paramKind) {
        RouterMiddleware::KIND_BODY => ['array', 'string'],
        RouterMiddleware::KIND_QUERY => ['string', 'int', 'bool', 'float'],
        RouterMiddleware::KIND_SEGMENT => ['string', 'int'],
        default => null,
      };
      if ($supportedParamTypes && !in_array($paramType, $supportedParamTypes)) {
        throw new InvalidParamTypeException(
                "Type '$paramType' of param '$paramName' for " . $this->stringifyCallable($handler) . " in route '$uri' is not supported");
      }
      if ($paramKind === RouterMiddleware::KIND_SEGMENT) {
        if (!isset($segnemtParamIndexMap[$paramName])) {
          throw new InconsistentParamsException(
                  "Route segment for param '$paramName' for " . $this->stringifyCallable($handler) . " in route '$uri' not found");
        }
        $key = $segnemtParamIndexMap[$paramName];
      } else if ($paramKind === RouterMiddleware::KIND_QUERY) {
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

  private function parseClass(string $class, int $classIndex) {
    $rClass = new ReflectionClass($class);
    $controller = RouterParser::extractControllerAttr($rClass);
    if (!$controller) {
      return;
    }
    $commonAttrs = self::parseAttributes($rClass);
    foreach ($rClass->getMethods(ReflectionMethod::IS_PUBLIC) as $rMethod) {
      $route = RouterParser::extractRouteAttr($rMethod);
      if (!$route) {
        continue;
      }
      $path = RouterParser::makePath($controller, $route);
      list($segments, $segnemtParamIndexMap) = RouterParser::parsePath($path);
      $handler = [$classIndex, $rMethod->getName()];
      $params = $this->parseParams($segnemtParamIndexMap, $rMethod, $handler, $path);
      $routeAttrs = array_merge($commonAttrs, self::parseAttributes($rMethod), $route->attributes ?? []);
      $this->insertHandlerToTree($route->method, $segments, [...$handler, $params, $routeAttrs]);
    }
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
    foreach ($this->classes as $classIndex => $class) {
      $this->parseClass($class, $classIndex);
    }
    // file_put_contents('/tmp/routes.json', json_encode($this->tree, JSON_PRETTY_PRINT));die;
    return [$this->tree, $this->classes];
  }

  private static function parseAttributes(ReflectionMethod|ReflectionClass $rMethod) {
    $rRouteAttrs = $rMethod->getAttributes(RouteAnnotation::class, ReflectionAttribute::IS_INSTANCEOF);
    return array_merge(...array_map(fn($rAttr) => $rAttr->newInstance()->getAttributes(), $rRouteAttrs));
  }

}
