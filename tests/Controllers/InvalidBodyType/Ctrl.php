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

namespace OpenCore\Controllers\InvalidBodyType;

use OpenCore\Controller;
use OpenCore\Route;
use OpenCore\Body;

#[Controller('')]
class Ctrl {

  #[Route('POST', '')]
  public function send(#[Body] float $arr) {
    return null;
  }

}
