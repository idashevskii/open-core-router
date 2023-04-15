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

namespace OpenCore\RouteAnnotations;

use OpenCore\RouteAnnotation;
use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class NoCsrf implements RouteAnnotation {

  function getAttributes(): array {
    return ['no_csrf' => true];
  }

}
