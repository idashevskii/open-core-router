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
use ErrorException;
use Psr\Http\Message\UriFactoryInterface;

final class ReverseRouterProxy {

  private array $cache = [];

  public function __construct(
      private Controller $controller,
      private ReflectionClass $rClass,
      private UriFactoryInterface $uriFactoryInterface,
  ) {
    
  }

  private function prepareMeta(string $methodName) {
    $rMethod = $this->rClass->getMethod($methodName);
    $route = RouterParser::extractRouteAttr($rMethod);
    if (!$route) {
      throw new ErrorException("Method '$methodName' is not route");
    }
    $path = RouterParser::makePath($this->controller, $route);
    list($segments, $segnemtParamIndexMap) = RouterParser::parsePath($path);
    $resSegments = [];
    $dynSeqIdxToAbsIdxMap = [];
    foreach ($segments as $i => list($segmentType, $segmentArg)) {
      if ($segmentType === RouterParser::SEGMENT_STATIC) {
        $resSegments[] = $segmentArg;
      } else {
        $dynSeqIdxToAbsIdxMap[] = $i;
        $resSegments[] = null;
      }
    }
    $params = RouterParser::parseParams($rMethod);
    $nameToAbsIdxMap = [];
    foreach ($segnemtParamIndexMap as $segmentName => $dynSeqIndex) {
      $nameToAbsIdxMap[$segmentName] = $dynSeqIdxToAbsIdxMap[$dynSeqIndex];
    }
    return [$params, $resSegments, $nameToAbsIdxMap, $path];
  }

  /**
   * @param string $methodName
   * @param array $arguments
   * @return UriFactoryInterface
   */
  public function __call(string $methodName, array $arguments) {
    if (!isset($this->cache[$methodName])) {
      $this->cache[$methodName] = $this->prepareMeta($methodName);
    }
    list($params, $resSegments, $nameToAbsIdxMap, $path) = $this->cache[$methodName];
    $query = [];
    $argIndex = 0;
    foreach ($params as list($paramName, $paramKind, $paramType)) {
      if ($paramKind !== Router::KIND_QUERY && $paramKind !== Router::KIND_SEGMENT) {
        continue;
      }
      $value = $arguments[$argIndex++] ?? ($arguments[$paramName] ?? null);
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
          throw new ErrorException("Param $paramName in route '$path' is required");
        }
        $resSegments[$nameToAbsIdxMap[$paramName]] = $value;
      }
    }
    $ret = $this->uriFactoryInterface->createUri('/' . implode('/', $resSegments));
    if ($query) {
      $ret = $ret->withQuery(http_build_query($query));
    }
    return $ret;
  }

}
