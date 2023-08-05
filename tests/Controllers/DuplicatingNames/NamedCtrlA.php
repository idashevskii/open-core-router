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

namespace OpenCore\Controllers\DuplicatingNames;

use OpenCore\Controller;
use OpenCore\Route;

#[Controller]
class NamedCtrlA {

  public function __construct() {
    
  }

  #[Route('GET', 'named/a', name: 'someRouteName')]
  public function handleRequest() {
    return null;
  }

}
