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

  public static function create(array $scanNs, bool $useCache = false): App {
    $injector = Injector::create();
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
      private Executor $executor,
      private Router $router,
      private EchoAttributesMiddleware $echoAttributes,
      private StreamFactoryInterface $streamFactory,
      private ServerRequestFactoryInterface $serverRequestFactory,
      private ErrorHandlerMiddleware $errorHandlerMiddleware,
  ) {
    
  }

  public function clear() {
    $this->router->clearCache();
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
        $this->router,
        $this->echoAttributes,
      ];
    } else {
      $queue = [
        $this->errorHandlerMiddleware,
        $this->router,
        $this->executor,
      ];
    }
    $relay = new Relay($queue);
    return $relay->handle($request);
  }

}
