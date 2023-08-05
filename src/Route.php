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

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final class Route {

  public function __construct(
      public string $method,
      public string $path,
      public ?array $attributes = null,
      public ?string $name = null,
  ) {
    
  }

}
