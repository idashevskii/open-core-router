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

final class RouterOutput {

  public function __construct(
      private readonly string $controllerClass,
      private readonly string $controllerMethod,
      private readonly array $paramKinds,
      private readonly array $paramTypes,
      private readonly array $paramRawValues,
  ) {
    
  }

  public function getParamKinds() {
    return $this->paramKinds;
  }

  public function getParamTypes() {
    return $this->paramTypes;
  }

  public function getParamRawValues() {
    return $this->paramRawValues;
  }

  public function getControllerClass(): string {
    return $this->controllerClass;
  }

  public function getControllerMethod(): string {
    return $this->controllerMethod;
  }

}
