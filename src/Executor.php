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

use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\MiddlewareInterface;

final class Executor implements MiddlewareInterface {

  public const REQUEST_ATTRIBUTE = '_executorPayload_';

  public function __construct(
      private ResponseFactoryInterface $responseFactory,
      private ContainerInterface $controllerResolver,
  ) {
    
  }

  public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
    $payload = $request->getAttribute(Router::REQUEST_ATTRIBUTE);
    /* @var $payload ExecutorPayload */
    $methodParams = [];
    foreach ($payload->getParams() as $param) {
      $methodParams[] = match ($param->getKind()) {
        ExecutorPayloadParam::KIND_BODY => $request->getParsedBody(),
        ExecutorPayloadParam::KIND_REQUEST => $request,
        ExecutorPayloadParam::KIND_RESPONSE => $this->responseFactory->createResponse(),
        ExecutorPayloadParam::KIND_SEGMENT,
        ExecutorPayloadParam::KIND_QUERY => $param->getValue(),
      };
    }
    $controller = $this->controllerResolver->get($payload->getControllerClass());
    $res = call_user_func_array([$controller, $payload->getControllerMethod()], $methodParams);
    if ($res === null) {
      $payload = ControllerResponse::empty();
    } else {
      if ($res instanceof ResponseInterface) {
        return $res;
      }
      if ($res instanceof ControllerResponse) {
        $payload = $res;
      } else {
        $payload = ControllerResponse::fromBody($res);
      }
    }
    return $handler->handle($request->withAttribute(self::REQUEST_ATTRIBUTE, $payload));
  }

}
