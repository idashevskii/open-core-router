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

use PHPUnit\Framework\TestCase;
use OpenCore\Exceptions\{
  InconsistentParamsException,
  AmbiguousRouteException,
  NoControllersException,
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
use OpenCore\Uitls\EchoAttributes;

final class RouterTest extends TestCase {

  private ContainerInterface $ctrlResolver;

  protected function setUp(): void {
    $this->ctrlResolver = new ControllerResolver();
  }

  private function handleRequest(string $method, string $uri, array $scanNs = null,
      ?array $query = null, ?array $payload = null, ?string $payloadStr = null,
      bool $routerOnly = false): ResponseInterface {
    $logger = new Logger();
    $logger->debug("Request {0} {1}", [$method, $uri]);

    $psrFactory = new Psr17Factory();
    if(!$scanNs){
      $scanNs=['Valid'];
    }
    $router = Router::create(null, $psrFactory, function (RouterCompiler $r)use ($scanNs) {
          foreach ($scanNs as $ns) {
            $ns = 'Controllers/' . $ns;
            $r->scan(__NAMESPACE__ . '\\' . strtr($ns, '/', '\\'), __DIR__ . '/' . $ns);
          }
        });
    $request = $psrFactory->createServerRequest($method, $uri);
    if ($payload !== null) {
      $request = $request->withBody($psrFactory->createStream(json_encode($payload)));
    } else if ($payloadStr !== null) {
      $request = $request->withBody($psrFactory->createStream($payloadStr));
    }
    if ($query) {
      $request = $request->withQueryParams($query);
    }
    if ($routerOnly) {
      $queue = [
        $router,
        new EchoAttributes($psrFactory, $psrFactory),
      ];
    } else {
      $queue = [
        $router,
        new RequestParser($psrFactory),
        new Executor($psrFactory, $this->ctrlResolver),
        new ResponseSerializer($psrFactory, $psrFactory),
      ];
    }
    foreach ($queue as $loggerAware) {
      $loggerAware->setLogger($logger);
    }
    $relay = new Relay($queue);
    return $relay->handle($request);
  }

  private static function toJson(ResponseInterface $response) {
    $data = (string) $response->getBody();
    return $data !== '' ? json_decode($data, true) : null;
  }

  public function testNotFound() {
    $response = $this->handleRequest('GET', '/not-found/');
    $this->assertEquals(404, $response->getStatusCode());
  }

  public function testFalsyValidParam() {
    $response = $this->handleRequest('GET', '/user/0');
    $this->assertEquals(404, $response->getStatusCode());
  }

  public function testFalsyValidBody() {
    $response = $this->handleRequest('POST', '/user', payload: []);
    $this->assertEquals(418, $response->getStatusCode());
  }

  public function testVoidControllerResponse() {
    $response = $this->handleRequest('GET', '/hello/noop');
    $this->assertEquals(200, $response->getStatusCode());
  }

  public function testDynamicSegment() {
    $response = $this->handleRequest('GET', '/hello/greet/World');
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals('Hello, World!', (string) $response->getBody());
  }

  public function testStaticOverDynamicSegmentPriorityWithRandomParamOrder() {
    $response = $this->handleRequest('GET', '/hello/greet/king');
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals('Greetings to the King!', (string) $response->getBody());
  }

  public function testMethodNotSupported() {
    $response = $this->handleRequest('POST', '/hello/greet/world');
    $this->assertEquals(405, $response->getStatusCode());
  }

  public function testDuplicatingStaticRoutes() {
    $this->expectException(AmbiguousRouteException::class);
    $this->handleRequest('DELETE', '/any/request', scanNs: ['DuplicatingStatic']);
  }

  public function testAmbiguousDynamicSegments() {
    $this->expectException(AmbiguousRouteException::class);
    $this->handleRequest('DELETE', '/any/request', scanNs: ['AmbiguousDynamic']);
  }

  public function testNoControllers() {
    $this->expectException(NoControllersException::class);
    $this->handleRequest('DELETE', '/any/request', scanNs: ['NotExistingPath']);
  }

  public function testRouteParamsNotConsistentMethodParams() {
    $this->expectException(InconsistentParamsException::class);
    $this->handleRequest('DELETE', '/any/request', scanNs: ['InconsistentParams']);
  }

  public function testResponseSerializing() {
    $response = $this->handleRequest('GET', '/user');
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertCount(2, self::toJson($response));
  }

  public function testRequiredBodyError() {
    $response = $this->handleRequest('POST', '/user');
    $this->assertEquals(400, $response->getStatusCode());
  }

  public function testStaticToDynamicBacktrace() {
    $response = $this->handleRequest('GET', '/hello/welcome/noble/knight');
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals('Welcome, noble knight!', (string) $response->getBody());
  }

  public function testSimplifiedRouterResponse() {
    $response = $this->handleRequest('GET', '/user/1');
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals('userA', self::toJson($response)['name']);

    $response = $this->handleRequest('HEAD', '/user/1');
    $this->assertEquals(200, $response->getStatusCode());

    $response = $this->handleRequest('GET', '/user/-1');
    $this->assertEquals(404, $response->getStatusCode());

    $response = $this->handleRequest('HEAD', '/user/-1');
    $this->assertEquals(404, $response->getStatusCode());

    $response = $this->handleRequest('GET', '/user/1/roles');
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals(['a', 'b', 'c'], self::toJson($response));

    $response = $this->handleRequest('POST', '/user', payload: ['id' => 1]);
    $this->assertEquals(409, $response->getStatusCode());

    $response = $this->handleRequest('POST', '/user', payload: ['id' => 3]);
    $this->assertEquals(201, $response->getStatusCode());
    $this->assertArrayHasKey('id', self::toJson($response));

    $response = $this->handleRequest('DELETE', '/user/3');
    $this->assertEquals(204, $response->getStatusCode());
  }

  public function testQueryParam() {
    $response = $this->handleRequest('GET', '/user', query: ['filterKey' => 'role', 'filterValue' => 'b']);
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertCount(1, self::toJson($response));

    $response = $this->handleRequest('GET', '/user', query: ['filterKey' => 'role', 'filterValue' => 'b', 'active' => 'true']);
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertIsArray(self::toJson($response));
    $this->assertCount(0, self::toJson($response));

    $response = $this->handleRequest('GET', '/user', query: ['active' => 'false']);
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertCount(1, self::toJson($response));

    $response = $this->handleRequest('GET', '/user', query: ['active' => 'true']);
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertCount(1, self::toJson($response));

    $response = $this->handleRequest('GET', '/user', query: ['active' => '1']);
    $this->assertEquals(400, $response->getStatusCode());

    $response = $this->handleRequest('GET', '/user', query: ['active' => '0']);
    $this->assertEquals(400, $response->getStatusCode());
  }

  public function testInvalidBody() {
    $response = $this->handleRequest('POST', '/user', payloadStr: '{');
    $this->assertEquals(415, $response->getStatusCode());
  }

  public function testQueryArrayParam() {
    $response = $this->handleRequest('POST', '/user', payloadStr: '{');
    $this->assertEquals(415, $response->getStatusCode());
  }

  public function testHttpMethodNotSupported() {
    $response = $this->handleRequest('GET', '/types/array/-');
    $this->assertEquals(501, $response->getStatusCode());

    $response = $this->handleRequest('GET', '/types/object/-');
    $this->assertEquals(501, $response->getStatusCode());

    $response = $this->handleRequest('GET', '/types/mixed/-');
    $this->assertEquals(501, $response->getStatusCode());

    $response = $this->handleRequest('POST', '/types/body-object', payload: []);
    $this->assertEquals(501, $response->getStatusCode());

    $response = $this->handleRequest('POST', '/types/body-string', payload: []);
    $this->assertEquals(501, $response->getStatusCode());
  }

  public function testRequestAttributes() {
    $response = $this->handleRequest('POST', '/hello/validate-long-message', payload: [], routerOnly: true);
    $this->assertEquals(200, $response->getStatusCode());
    $attrs = self::toJson($response);
    $this->assertIsArray($attrs);
    $this->assertEquals(true, $attrs['auth']);
    $this->assertEquals(true, $attrs['no_csrf']);
    $this->assertEquals('b', $attrs['a']);
    $this->assertEquals('d', $attrs['c']);
  }

  public function testRawRequestResponse() {
    $msg = 'This is message!';
    $response = $this->handleRequest('POST', '/raw/echo', payloadStr: $msg);
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals($msg, (string) $response->getBody());
  }

//  public function testReverseRoute() {
//    // TODO
//  }
}
