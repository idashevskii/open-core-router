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
  ResponseInterface,
  ResponseFactoryInterface,
  StreamFactoryInterface
};
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Server\MiddlewareInterface;
use ErrorException;

final class ResponseSerializer implements MiddlewareInterface {

  private const JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_INVALID_UTF8_IGNORE;

  public function __construct(
      private StreamFactoryInterface $streamFactory,
      private ResponseFactoryInterface $responseFactory,
  ) {
    
  }

  public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
    $res = $request->getAttribute(Executor::REQUEST_ATTRIBUTE, null);
    /** @var ControllerResponse $res */
    if (!$res) {
      throw new ErrorException("No payload from Executor");
    }
    $response = $this->responseFactory->createResponse($res->getStatus() ?? 200);
    $headers = $res->getHeaders();
    $data = $res->getBody();
    if ($data !== null) {
      $contentType = null;
      if (is_string($data)) {
        $contentType = 'text/html; charset=utf-8';
      } else if (is_array($data)) {
        $contentType = 'application/json';
        $data = json_encode($data, self::JSON_FLAGS);
      } else {
        throw new ErrorException('Response type ' . gettype($data) . ' not supported');
      }
      if ($contentType) {
        $headers['Content-Type'] = $contentType;
      }
      if ($data) {
        $response = $response->withBody($this->streamFactory->createStream($data));
      }
    }
    foreach ($headers as $headerName => $headerValue) {
      $response = $response->withHeader($headerName, $headerValue);
    }
    return $response;
  }

}
