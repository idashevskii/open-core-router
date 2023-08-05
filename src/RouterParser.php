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

final class RouterParser {

  public const SEGMENT_STATIC = 1;
  public const SEGMENT_DYNAMIC = 2;

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

  public static function extractControllerAttr(ReflectionClass $rClass): ?Controller {
    $rCtrlAttrs = $rClass->getAttributes(Controller::class, ReflectionAttribute::IS_INSTANCEOF);
    if (!$rCtrlAttrs) {
      return null;
    }
    return $rCtrlAttrs[0]->newInstance();
  }

  public static function makePath(Controller $controller, Route $route): string {
    return ($controller->prefix ? $controller->prefix . '/' : '') . $route->path;
  }

  public static function extractRouteAttr(ReflectionMethod $rMethod): ?Route {
    $rRouteAttrs = $rMethod->getAttributes(Route::class);
    if (!$rRouteAttrs) {
      return null;
    }
    return $rRouteAttrs[0]->newInstance();
  }

  public static function parseParams(ReflectionMethod $rMethod): array {
    $ret = [];
    foreach ($rMethod->getParameters() as $rParam) {
      /* @var $rParam ReflectionParameter */
      $paramType = ltrim((string) $rParam->getType(), '?'); // strip optionality marker;
      if ($rParam->getAttributes(Body::class, ReflectionAttribute::IS_INSTANCEOF)) {
        $paramKind = Router::KIND_BODY;
      } else if (is_a($paramType, ServerRequestInterface::class, true)) {
        $paramKind = Router::KIND_REQUEST;
        $paramType = null;
      } else if (is_a($paramType, ResponseInterface::class, true)) {
        $paramKind = Router::KIND_RESPONSE;
        $paramType = null;
      } else if ($rParam->isOptional()) {
        $paramKind = Router::KIND_QUERY;
      } else {
        $paramKind = Router::KIND_SEGMENT;
      }
      $ret[] = [$rParam->name, $paramKind, $paramType];
    }
    return $ret;
  }

}
