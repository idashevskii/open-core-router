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

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Server\MiddlewareInterface;

final class EchoAttributes implements MiddlewareInterface {

  public function __construct(
      private StreamFactoryInterface $streamFactory,
      private ResponseFactoryInterface $responseFactory,
  ) {
    
  }

  public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
    return $this->responseFactory->createResponse()
            ->withBody($this->streamFactory->createStream(json_encode($request->getAttributes())));
  }

}
