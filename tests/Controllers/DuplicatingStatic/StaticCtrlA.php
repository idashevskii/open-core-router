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

namespace OpenCore\Router\Controllers\DuplicatingStatic;

use OpenCore\Router\Controller;
use OpenCore\Router\Route;

#[Controller]
class StaticCtrlA {

  public function __construct() {
    
  }

  #[Route('GET', 'segment1/segment2/segment3')]
  public function handleRequest() {
    return null;
  }

}
