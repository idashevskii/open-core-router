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

namespace OpenCore;

use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ResponseFactoryInterface;
USE Psr\Log\{
  LoggerAwareInterface,
  LoggerAwareTrait
};

abstract class AbstractMiddeware implements MiddlewareInterface, LoggerAwareInterface {

  use LoggerAwareTrait;

  public function __construct(
      protected ResponseFactoryInterface $responseFactory,
  ) {
    
  }

  protected function errorResponse(int $status, string $logMsg, array $logContext = []) {
    if ($this->logger) {
      $this->logger->info($logMsg, $logContext);
    }
    return $this->responseFactory->createResponse($status);
  }

}
