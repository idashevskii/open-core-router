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

#[Attribute(Attribute::TARGET_CLASS)]
class CtrlCommon implements RouteAnnotation {

  function getAttributes(): array {
    return ['auth' => false, 'ctrlSpecific' => true];
  }

}
