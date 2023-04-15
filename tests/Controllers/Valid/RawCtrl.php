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

namespace OpenCore\Controllers\Valid;

use OpenCore\Controller;
use OpenCore\Route;
use OpenCore\Body;
use OpenCore\ControllerResponse;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

#[Controller('/raw')]
class RawCtrl {

  public function __construct() {
    
  }

  #[Route('POST', 'echo')]
  public function getDefault(ServerRequestInterface $req, ResponseInterface $res) {
    return $res->withBody($req->getBody());
  }

}
