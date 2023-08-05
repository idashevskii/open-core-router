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
use OpenCore\Exceptions\NoControllersException;
use OpenCore\Exceptions\InvalidParamTypeException;
use OpenCore\Exceptions\InconsistentParamsException;
use OpenCore\Exceptions\AmbiguousRouteException;

final class RouterCompiler {

  public function __construct() {
    
  }

  // node: [staticSegments:{[value]:node}, dynamicSegmetns:[{param, node}], handlers:{[method]:{ctrl, fn, params}}]
  private array $tree = [];
  private array $classes = [];
  private array $handlers = [];
  private array $namedHandlers = [];

  private function stringifyCallable(array $handler) {
    list($classIndex, $method) = $handler;
    $class = $this->classes[$classIndex];
    return "$class::$method";
  }

  private function insertHandlerToTree(string $httpMethod, array $segments, int $handlerIdx) {

    $node = &$this->tree;
    while (true) {
      if (!$node) {
        $node = [null, null, null];
      }
      list(&$staticLink, &$dynamicLink, &$methodHandlersLink) = $node;
      if (!$segments) {
        if (isset($methodHandlersLink[$httpMethod])) {
          $this->throwAmbiguousRouteException($handlerIdx, $methodHandlersLink[$httpMethod]);
        }
        $methodHandlersLink[$httpMethod] = $handlerIdx;
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

  private function parseParams(array $segmets, ReflectionMethod $rMethod, array $handler, string $uri): array {
    $ret = [];

    $expectedDynamicSegments = [];
    foreach ($segmets as list($segmentType, $segmentArg)) {
      if ($segmentType === RouterParser::SEGMENT_DYNAMIC) {
        $expectedDynamicSegments[] = $segmentArg;
      }
    }
    $actualDynamicSegments = [];
    $segnemtParamIndexMap = array_flip($expectedDynamicSegments);

    foreach (RouterParser::parseParams($rMethod) as list($paramName, $paramKind, $paramType)) {
      $supportedParamTypes = match ($paramKind) {
        Router::KIND_BODY => ['array', 'string'],
        Router::KIND_QUERY => ['string', 'int', 'bool', 'float'],
        Router::KIND_SEGMENT => ['string', 'int'],
        default => null,
      };
      if ($supportedParamTypes && !in_array($paramType, $supportedParamTypes)) {
        throw new InvalidParamTypeException(
                "Type '$paramType' of param '$paramName' for " . $this->stringifyCallable($handler) . " in route '$uri' is not supported");
      }
      if ($paramKind === Router::KIND_SEGMENT) {
        $actualDynamicSegments[] = $paramName;
        $segmentIndex = $segnemtParamIndexMap[$paramName] ?? null;
      } else {
        $segmentIndex = null;
      }
      $ret[] = [
        $paramKind,
        $paramType,
        $paramName,
        $segmentIndex,
      ];
    }
    sort($expectedDynamicSegments);
    sort($actualDynamicSegments);
    if ($expectedDynamicSegments !== $actualDynamicSegments) {
      throw new InconsistentParamsException(
              "Inconsistent route '$uri' and method '" . $this->stringifyCallable($handler) . "' params."
              . " Expected: (" . implode(', ', $expectedDynamicSegments) . ")."
              . " Actual: (" . implode(', ', $actualDynamicSegments) . ")");
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
      $segments = RouterParser::parsePath($path);
      $handler = [$classIndex, $rMethod->getName()];
      $paramsProps = $this->parseParams($segments, $rMethod, $handler, $path);
      $routeAttrs = array_merge($commonAttrs, self::parseAttributes($rMethod), $route->attributes ?? []);
      $routeName = $route->name;
      $this->handlers[] = [...$handler, $paramsProps, $routeAttrs, $routeName];
      $handlerIdx = count($this->handlers) - 1;
      if ($routeName) {
        $this->insertReverseRoute($routeName, $handlerIdx, $segments);
      }
      $this->insertHandlerToTree($route->method, $segments, $handlerIdx);
    }
  }

  private function throwAmbiguousRouteException(int $handlerIdx1, int $handlerIdx2) {
    throw new AmbiguousRouteException("Ambiguous route for " . implode(' and ', [
              $this->stringifyCallable($this->handlers[$handlerIdx1]),
              $this->stringifyCallable($this->handlers[$handlerIdx2]),
    ]));
  }

  private function insertReverseRoute(string $name, int $handlerIdx, array $segments) {
    if (isset($this->namedHandlers[$name])) {
      $this->throwAmbiguousRouteException($handlerIdx, $this->namedHandlers[$name][0]);
    }
    $resSegments = [];
    foreach ($segments as list($segmentType, $segmentArg)) {
      if ($segmentType === RouterParser::SEGMENT_STATIC) {
        $resSegments[] = $segmentArg;
      } else {
        $resSegments[] = null;
      }
    }
    $this->namedHandlers[$name] = [$handlerIdx, $resSegments];
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
    if (!$this->classes) {
      throw new NoControllersException();
    }
    return [$this->tree, $this->classes, $this->handlers, $this->namedHandlers];
  }

  private static function parseAttributes(ReflectionMethod|ReflectionClass $rMethod) {
    $rRouteAttrs = $rMethod->getAttributes(RouteAnnotation::class, ReflectionAttribute::IS_INSTANCEOF);
    return array_merge(...array_map(fn($rAttr) => $rAttr->newInstance()->getAttributes(), $rRouteAttrs));
  }

}
