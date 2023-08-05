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
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\StreamInterface;
use ErrorException;

final class RequestHandler implements RequestHandlerInterface {

  public function __construct(
      private StreamFactoryInterface $streamFactory,
      private ResponseFactoryInterface $responseFactory,
      private ContainerInterface $controllerResolver,
  ) {
    
  }

  public function handle(ServerRequestInterface $request): ResponseInterface {
    list($controllerClass, $controllerMethod, $callMethodParams) = $request->getAttribute(Router::REQUEST_ATTRIBUTE);

    $controller = $this->controllerResolver->get($controllerClass);
    $res = call_user_func_array([$controller, $controllerMethod], $callMethodParams);
    if ($res instanceof ResponseInterface) {
      return $res;
    }
    if ($res instanceof ControllerResponse) {
      $status = $res->getStatus();
      $headers = $res->getHeaders();
      $data = $res->getBody();
    } else {
      $status = 200;
      $headers = [];
      $data = $res;
    }
    $response = $this->responseFactory->createResponse($status);
    if ($data !== null) {
      if (is_string($data)) {
        $contentType = 'text/html; charset=utf-8';
        $body = $this->streamFactory->createStream($data);
      } else if (is_array($data)) {
        $contentType = 'application/json';
        $body = $this->streamFactory->createStream(self::stringifyJsonBody($data));
      } else if ($data instanceof StreamInterface) {
        $contentType = 'text/html; charset=utf-8';
        $body = $data;
      } else {
        throw new ErrorException('Response type ' . gettype($data) . ' not supported');
      }
      $headers['Content-Type'] = $contentType;
      $response = $response->withBody($body);
    }
    foreach ($headers as $headerName => $headerValue) {
      $response = $response->withHeader($headerName, $headerValue);
    }
    return $response;
  }

  private static function stringifyJsonBody(mixed $data) {
    return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_INVALID_UTF8_IGNORE);
  }

}
