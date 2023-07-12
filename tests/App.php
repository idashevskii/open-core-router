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
use OpenCore\Uitls\Logger;
use Nyholm\Psr7\Factory\Psr17Factory;
use Relay\Relay;
use OpenCore\Uitls\EchoAttributes;
use OpenCore\Injector;
use Psr\Log\LoggerInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Container\ContainerInterface;

final class App {

  public static function create(array $scanNs, bool $useCache = false): App {
    $injector = Injector::create();
    $injector->set(LoggerInterface::class, new Logger());
    $injector->set(ContainerInterface::class, $injector);
    $psrFactory = new Psr17Factory();
    $injector->set(StreamFactoryInterface::class, $psrFactory);
    $injector->set(ResponseFactoryInterface::class, $psrFactory);
    $injector->set(ServerRequestFactoryInterface::class, $psrFactory);

    $injector->set(RouterConfig::class, new class($scanNs, $useCache) implements RouterConfig {

      public function __construct(private $scanNs, private $useCache) {
        
      }

      public function define(RouterCompiler $compiler) {
        foreach ($this->scanNs as $ns) {
          $ns = 'Controllers/' . $ns;
          $compiler->scan(__NAMESPACE__ . '\\' . strtr($ns, '/', '\\'), __DIR__ . '/' . $ns);
        }
      }

      public function isCacheEnabled(): bool {
        return $this->useCache;
      }
    });

    return $injector->get(self::class);
  }

  public function __construct(
      private LoggerInterface $logger,
      private ResponseSerializer $responseSerializer,
      private RequestParser $requestParser,
      private Executor $executor,
      private Router $router,
      private EchoAttributes $echoAttributes,
      private StreamFactoryInterface $streamFactory,
      private ServerRequestFactoryInterface $serverRequestFactory,
  ) {
    
  }

  public function clear() {
    $this->router->clearCache();
  }

  public function handleRequest(string $method, string $uri,
      ?array $query = null, ?array $payload = null, ?string $payloadStr = null,
      bool $routerOnly = false): ResponseInterface {
    $this->logger->debug("Request {0} {1}", [$method, $uri]);

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
        $this->router,
        $this->echoAttributes,
      ];
    } else {
      $queue = [
        $this->router,
        $this->requestParser,
        $this->executor,
        $this->responseSerializer,
      ];
    }
    $relay = new Relay($queue);
    return $relay->handle($request);
  }

}
