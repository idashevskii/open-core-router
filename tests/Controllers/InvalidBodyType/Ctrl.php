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

namespace OpenCore\Router\Controllers\InvalidBodyType;

use OpenCore\Router\Controller;
use OpenCore\Router\Route;
use OpenCore\Router\Body;

#[Controller('')]
class Ctrl {

  #[Route('POST', '')]
  public function send(#[Body] float $arr) {
    return null;
  }

}
