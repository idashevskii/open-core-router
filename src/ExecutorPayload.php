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

final class ExecutorPayload {

  public function __construct(
      private readonly string $controllerClass,
      private readonly string $controllerMethod,
      private array $params,
  ) {
    
  }

  public function withParamValue(int $index, mixed $value): ExecutorPayload {
    $ret = clone $this;
    $ret->params[$index] = $ret->params[$index]->withValue($value);
    return $ret;
  }

  public function getControllerClass(): string {
    return $this->controllerClass;
  }

  public function getControllerMethod(): string {
    return $this->controllerMethod;
  }

  /**
   * @return ExecutorPayloadParam[]
   */
  public function getParams(): array {
    return $this->params;
  }

}
