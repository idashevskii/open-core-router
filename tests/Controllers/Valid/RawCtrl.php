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

namespace OpenCore\Router\Controllers\Valid;

use OpenCore\Router\Controller;
use OpenCore\Router\Route;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

#[Controller('raw/echo')]
class RawCtrl {

  public function __construct() {
    
  }

  #[Route('POST', 'str')]
  public function getStr(ServerRequestInterface $req, ResponseInterface $res) {
    return $res->withBody($req->getBody());
  }

  #[Route('POST', 'stream')]
  public function getStream(ServerRequestInterface $req) {
    return $req->getBody();
  }

}
