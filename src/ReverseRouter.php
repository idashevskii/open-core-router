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

final class ReverseRouter {

  private array $cache = [];

  public function __construct(
      private UriFactoryInterface $uriFactoryInterface,
  ) {
    
  }

  public function for(string $controllerClass) {
    if (!isset($this->cache[$controllerClass])) {
      $this->cache[$controllerClass] = $this->makeProxy($controllerClass);
    }
    return $this->cache[$controllerClass];
  }

  private function makeProxy(string $controllerClass) {
    $rClass = new ReflectionClass($controllerClass);
    $controller = RouterParser::extractControllerAttr($rClass);
    if (!$controller) {
      throw new ErrorException("Class '$controllerClass' is not controller");
    }
    return new ReverseRouterProxy($controller, $rClass, $this->uriFactoryInterface);
  }

}
