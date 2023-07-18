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

namespace OpenCore\Controllers\InvalidParamType;

use OpenCore\Controller;
use OpenCore\Route;

#[Controller('/types')]
class Ctrl {

  #[Route('GET', '')]
  public function argArr(array $arr) {
    return null;
  }

}
