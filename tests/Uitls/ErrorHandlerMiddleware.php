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

namespace OpenCore\Uitls;

use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use OpenCore\Exceptions\RoutingException;
use Psr\Http\Message\ResponseFactoryInterface;

final class ErrorHandlerMiddleware implements MiddlewareInterface {

  public function __construct(
      private ResponseFactoryInterface $responseFactory,
  ) {
    
  }

  public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
    try {
      return $handler->handle($request);
    } catch (RoutingException $ex) {
      return $this->responseFactory->createResponse($ex->getCode());
    }
  }

}
