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

namespace OpenCore\Controllers\InconsistentParams;

use OpenCore\Controller;
use OpenCore\Route;

#[Controller('/hello')]
class Ctrl {

  public function __construct() {
    
  }

  #[Route('GET', 'segment1/{param1}/segment2/{param2}')]
  public function welcomeKing(string $param1, string $param2, string $param3) {
    return null;
  }

}
