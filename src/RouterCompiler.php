<?php declare(strict_types=1);

/**
 * @license   MIT
 *
 * @author    Ilya Dashevsky
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace OpenCore\Router;

use ReflectionClass;
use ReflectionAttribute;
use ReflectionMethod;
use ReflectionNamedType;
use OpenCore\Router\Exceptions\NoControllersException;
use OpenCore\Router\Exceptions\InvalidParamTypeException;
use OpenCore\Router\Exceptions\InconsistentParamsException;
use OpenCore\Router\Exceptions\AmbiguousRouteException;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

final class RouterCompiler {

  private const SEGMENT_STATIC = 1;
  private const SEGMENT_DYNAMIC = 2;

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
      [&$staticLink, &$dynamicLink, &$methodHandlersLink] = $node;
      if (!$segments) {
        $methodHandlerLink =& $methodHandlersLink[$httpMethod];
        if ($methodHandlerLink !== null) {
          $this->throwAmbiguousRouteException($handlerIdx, $methodHandlerLink);
        }
        $methodHandlerLink = $handlerIdx;
        break;
      }
      [$segmentType, $segmentArg] = array_shift($segments);
      if ($segmentType === self::SEGMENT_STATIC) {
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
    foreach ($segmets as [$segmentType, $segmentArg]) {
      if ($segmentType === self::SEGMENT_DYNAMIC) {
        $expectedDynamicSegments[] = $segmentArg;
      }
    }
    $actualDynamicSegments = [];
    $segnemtParamIndexMap = array_flip($expectedDynamicSegments);

    foreach ($rMethod->getParameters() as $rParam) {
      $rType = $rParam->getType();
      if (!($rType instanceof ReflectionNamedType)) {
        throw new InvalidParamTypeException(
          "Param '$paramName' for " . $this->stringifyCallable($handler) . " in route '$uri' must be typed");
      }
      $paramType = $rType->getName();
      if ($rParam->getAttributes(Body::class, ReflectionAttribute::IS_INSTANCEOF)) {
        $paramKind = Router::KIND_BODY;
      } else if (is_a($paramType, ServerRequestInterface::class, true)) {
        $paramKind = Router::KIND_REQUEST;
        $paramType = null; // no needs to store in cache
      } else if (is_a($paramType, ResponseInterface::class, true)) {
        $paramKind = Router::KIND_RESPONSE;
        $paramType = null; // no needs to store in cache
      } else if ($rParam->isOptional()) {
        $paramKind = Router::KIND_QUERY;
      } else {
        $paramKind = Router::KIND_SEGMENT;
      }
      $paramName = $rParam->name;
      if (!self::validateParamType($paramKind, $rType)) {
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

  private static function validateParamType(int $kind, ReflectionNamedType $rType): bool {
    return match ($kind) {
      Router::KIND_BODY => $rType->getName() !== 'mixed',
      Router::KIND_QUERY => in_array($rType->getName(), ['string', 'int', 'bool', 'float']),
      Router::KIND_SEGMENT => in_array($rType->getName(), ['string', 'int']),
      default => true,
    };
  }

  private function parseClass(string $class, int $classIndex) {
    $rClass = new ReflectionClass($class);
    $controller = self::extractControllerAttr($rClass);
    if (!$controller) {
      return;
    }
    $commonAttrs = self::parseAttributes($rClass);
    foreach ($rClass->getMethods(ReflectionMethod::IS_PUBLIC) as $rMethod) {
      $route = self::extractRouteAttr($rMethod);
      if (!$route) {
        continue;
      }
      $path = self::makePath($controller, $route);
      $segments = self::parsePath($path);
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
      if ($segmentType === self::SEGMENT_STATIC) {
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
    $scanDir = function (string $namespace, string $dir) use (&$scanDir) {
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

  public static function parsePath(string $path) {
    $ret = [];
    foreach (explode('/', $path) as $segment) {
      if ($segment === '') {
        continue;
      }
      $placeholder = self::matchPlaceholder($segment);
      if ($placeholder) {
        $ret[] = [self::SEGMENT_DYNAMIC, $placeholder];
      } else {
        $ret[] = [self::SEGMENT_STATIC, $segment];
      }
    }
    return $ret;
  }

  private static function matchPlaceholder(string $str): ?string {
    if (str_starts_with($str, '{') && str_ends_with($str, '}')) {
      return substr($str, 1, strlen($str) - 2);
    }
    return null;
  }

  private static function extractControllerAttr(ReflectionClass $rClass): ?Controller {
    $rCtrlAttrs = $rClass->getAttributes(Controller::class, ReflectionAttribute::IS_INSTANCEOF);
    if (!$rCtrlAttrs) {
      return null;
    }
    return $rCtrlAttrs[0]->newInstance();
  }

  private static function makePath(Controller $controller, Route $route): string {
    return ($controller->prefix ? $controller->prefix . '/' : '') . $route->path;
  }

  private static function extractRouteAttr(ReflectionMethod $rMethod): ?Route {
    $rRouteAttrs = $rMethod->getAttributes(Route::class);
    if (!$rRouteAttrs) {
      return null;
    }
    return $rRouteAttrs[0]->newInstance();
  }

}
