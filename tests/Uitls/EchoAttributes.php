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

use PHPUnit\Framework\TestCase;
use OpenCore\Exceptions\{
  InconsistentParamsException,
  AmbiguousRouteException
};
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\RequestFactoryInterface;
use OpenCore\Uitls\{
  Logger,
  ControllerResolver
};
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Log\LoggerInterface;
use Relay\Relay;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;
use OpenCore\AbstractMiddeware;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\ResponseFactoryInterface;

final class EchoAttributes extends AbstractMiddeware {

  public function __construct(
      private StreamFactoryInterface $streamFactory,
      ResponseFactoryInterface $responseFactory,
  ) {
    parent::__construct($responseFactory);
  }

  public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
    return $this->responseFactory->createResponse()
            ->withBody($this->streamFactory->createStream(json_encode($request->getAttributes())));
  }

}
