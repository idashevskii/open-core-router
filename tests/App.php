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

use Psr\Http\Message\ResponseInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use Relay\Relay;
use OpenCore\Uitls\EchoAttributesMiddleware;
use OpenCore\Injector;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Container\ContainerInterface;
use OpenCore\Uitls\ErrorHandlerMiddleware;

final class App {

  public static function create(array $scanNs): App {
    $injector = Injector::create();
    $injector->set(ContainerInterface::class, $injector);
    $injector->alias(StreamFactoryInterface::class, Psr17Factory::class);
    $injector->alias(ResponseFactoryInterface::class, Psr17Factory::class);
    $injector->alias(ServerRequestFactoryInterface::class, Psr17Factory::class);
    $injector->alias(RouterConfig::class, AppConfig::class);
    $injector->set(AppConfig::INJECT_CONTROLLER_SCAN_NS, $scanNs);
    $injector->set(AppConfig::INJECT_ROUTER_DATA_FILE,
        sys_get_temp_dir() . '/' . strtolower(strtr(implode('_', $scanNs), '\\', '.')) . '.php');

    return $injector->get(self::class);
  }

  public function __construct(
      private RequestHandler $requestHandler,
      private Router $routerMiddleware,
      private EchoAttributesMiddleware $echoAttributes,
      private StreamFactoryInterface $streamFactory,
      private ServerRequestFactoryInterface $serverRequestFactory,
      private ErrorHandlerMiddleware $errorHandlerMiddleware,
      #[Inject(AppConfig::INJECT_ROUTER_DATA_FILE)] private string $routerDataFile,
  ) {
    
  }

  public function clear() {
    if (file_exists($this->routerDataFile)) {
      unlink($this->routerDataFile);
    }
  }

  public function handleRequest(string $method, string $uri,
      ?array $query = null, ?array $payload = null, ?string $payloadStr = null,
      bool $routerOnly = false): ResponseInterface {

    $request = $this->serverRequestFactory->createServerRequest($method, $uri);
    if ($payload !== null) {
      $request = $request->withBody($this->streamFactory->createStream(json_encode($payload)));
    } else if ($payloadStr !== null) {
      $request = $request->withBody($this->streamFactory->createStream($payloadStr));
    }
    if ($query) {
      $request = $request->withQueryParams($query);
    }
    if ($routerOnly) {
      $queue = [
        $this->errorHandlerMiddleware,
        $this->routerMiddleware,
        $this->echoAttributes,
      ];
    } else {
      $queue = [
        $this->errorHandlerMiddleware,
        $this->routerMiddleware,
        $this->requestHandler,
      ];
    }
    $relay = new Relay($queue);
    return $relay->handle($request);
  }

}
