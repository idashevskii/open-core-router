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

namespace OpenCore\Router\RouteAnnotations;

use OpenCore\Router\RouteAnnotation;
use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Auth implements RouteAnnotation {

  function getAttributes(): array {
    return ['auth' => true];
  }

}
