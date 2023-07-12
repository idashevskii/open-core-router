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

use Psr\Http\Message\{
  ServerRequestInterface,
  ResponseInterface
};
use Psr\Http\Server\RequestHandlerInterface;
use OpenCore\ExecutorPayload;
use OpenCore\ExecutorPayloadParam;
use Exception;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Log\LoggerInterface;
use Psr\Http\Message\ResponseFactoryInterface;

class RequestParser implements MiddlewareInterface {

  public function __construct(
      private LoggerInterface $logger,
      private ResponseFactoryInterface $responseFactory,
  ) {
    
  }

  public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
    $payload = $request->getAttribute(Router::REQUEST_ATTRIBUTE);
    /* @var $payload ExecutorPayload */
    $changedValues = [];
    $parsedBody = null;
    foreach ($payload->getParams() as $i => $param) {
      $kind = $param->getKind();
      if ($kind === ExecutorPayloadParam::KIND_SEGMENT || $kind === ExecutorPayloadParam::KIND_QUERY) {
        $value = $param->getValue();
        if ($value === null) {
          continue;
        }
        $type = $param->getType();
        if ($type === 'string') {
          // type is string by default
        } else if ($type === 'int') {
          $changedValues[$i] = (int) $value;
        } else if ($type === 'bool') {
          if ($value === 'true') {
            $changedValues[$i] = true;
          } else if ($value === 'false') {
            $changedValues[$i] = false;
          } else {
            $this->logger->debug('Param #{0} has invalid boolean value', [$i]);
            return $this->responseFactory->createResponse(400);
          }
        } else if ($type === 'float') {
          $changedValues[$i] = (float) $value;
        } else {
          $this->logger->critical('Param #{0} type {1} is not supported', [$i, $type]);
          return $this->responseFactory->createResponse(501);
        }
      } else if ($kind === ExecutorPayloadParam::KIND_BODY) {
        $type = $param->getType();
        $bodyStr = (string) $request->getBody();
        if (!$bodyStr) {
          $this->logger->debug('Body is required', [$i]);
          return $this->responseFactory->createResponse(400);
        }
        if ($type === 'array') {
          try {
            $parsedBody = json_decode($bodyStr, flags: JSON_THROW_ON_ERROR | JSON_OBJECT_AS_ARRAY);
          } catch (Exception $ex) {
            $this->logger->debug('Can not parse body', [$type]);
            return $this->responseFactory->createResponse(415);
          }
        } else {
          $this->logger->critical('Body type {0} is not supported', [$type]);
          return $this->responseFactory->createResponse(501);
        }
      }
    }
    if ($changedValues) {
      foreach ($changedValues as $i => $value) {
        $payload = $payload->withParamValue($i, $value);
      }
      $request = $request->withAttribute(Router::REQUEST_ATTRIBUTE, $payload);
    }
    if ($parsedBody !== null) {
      $request = $request->withParsedBody($parsedBody);
    }
    return $handler->handle($request);
  }

}
