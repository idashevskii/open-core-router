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

namespace OpenCore\Router\Controllers\AmbiguousDynamic;

use OpenCore\Router\Controller;
use OpenCore\Router\Route;

#[Controller]
class DynamicCtrlA {

  public function __construct() {
    
  }

  #[Route('GET', 'segment1/{param1}/segment2/{param2}')]
  public function handleRequest(int $param1, int $param2) {
    return null;
  }

}
