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

class RouteLocation {

  private ?string $hash = null;

  public function __construct(
      public readonly string $name,
      public readonly ?array $params = null,
  ) {
    
  }

  public function __toString() {
    if ($this->hash === null) {
      $this->hash = $this->name . ($this->params ? '?' . implode(',', $this->params) . '<=' . implode(',', array_keys($this->params)) : '');
    }
    return $this->hash;
  }

}
