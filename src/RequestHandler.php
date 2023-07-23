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
use OpenCore\Exceptions\RoutingException;
use Psr\Http\Message\StreamInterface;
use JsonException;
use ErrorException;

final class RequestHandler implements RequestHandlerInterface {

  public const REQUEST_ATTRIBUTE = '_executorPayload_';

  public function __construct(
      private StreamFactoryInterface $streamFactory,
      private ResponseFactoryInterface $responseFactory,
      private ContainerInterface $controllerResolver,
  ) {
    
  }

  public function handle(ServerRequestInterface $request): ResponseInterface {
    $payload = $request->getAttribute(RouterMiddleware::REQUEST_ATTRIBUTE);
    /* @var $payload RouterOutput */
    $paramRawValues = $payload->getParamRawValues();
    $paramTypes = $payload->getParamTypes();
    $callMethodParams = [];
    foreach ($payload->getParamKinds() as $i => $kind) {
      if ($kind === RouterMiddleware::KIND_SEGMENT || $kind === RouterMiddleware::KIND_QUERY) {
        $rawValue = $paramRawValues[$i];
        if ($rawValue === null) {
          $value = null; // optional param not specified, nothing to convert
        } else {
          $value = match ($paramTypes[$i]) {
            'string' => $rawValue,
            'int' => (int) $rawValue,
            'float' => (float) $rawValue,
            'bool' => match ($rawValue) {
              'true', '1' => true,
              'false', '0' => false,
              default => throw new RoutingException("Param #$i has invalid boolean value", code: 400),
            },
            default => throw new ErrorException('Invalid param type'), // should be checked in compile step
          };
        }
      } else if ($kind === RouterMiddleware::KIND_BODY) {
        $rawValue = (string) $request->getBody();
        if (!$rawValue) {
          throw new RoutingException('Body not provided', code: 400);
        }
        $value = match ($paramTypes[$i]) {
          'string' => $rawValue,
          'array' => self::parseJsonBody($rawValue),
          default => throw new ErrorException('Invalid body type'), // should be prevented in compile step
        };
      } else if ($kind === RouterMiddleware::KIND_REQUEST) {
        $value = $request;
      } else if ($kind === RouterMiddleware::KIND_RESPONSE) {
        $value = $this->responseFactory->createResponse();
      } else {
        throw new ErrorException("Unknown param kind '$kind'");
      }
      $callMethodParams[] = $value;
    }
    $controller = $this->controllerResolver->get($payload->getControllerClass());
    $res = call_user_func_array([$controller, $payload->getControllerMethod()], $callMethodParams);
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

  private static function parseJsonBody(string $json) {
    try {
      return json_decode($json, flags: JSON_THROW_ON_ERROR | JSON_OBJECT_AS_ARRAY);
    } catch (JsonException $ex) {
      throw new RoutingException('Body parse error', code: 400, previous: $ex);
    }
  }

  private static function stringifyJsonBody(mixed $data) {
    return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_INVALID_UTF8_IGNORE);
  }

}
