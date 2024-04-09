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

namespace OpenCore\Router;

use PHPUnit\Framework\TestCase;
use OpenCore\Router\Exceptions\{
  InconsistentParamsException,
  AmbiguousRouteException,
  NoControllersException,
  InvalidParamTypeException,
};
use Psr\Http\Message\ResponseInterface;

final class RouterTest extends TestCase {

  private static array $apps = [];

  public static function tearDownAfterClass(): void {
    foreach (self::$apps as $app) {
      $app->clear();
    }
  }

  private function makeApp(array $scanNs = null): App {
    $app = App::create($scanNs ?? ['Valid', 'UnusedHuge']);
    self::$apps[] = $app;
    return $app;
  }

  private static function toJson(ResponseInterface $response) {
    $data = (string) $response->getBody();
    return $data !== '' ? json_decode($data, true) : null;
  }

  public function testNotFound() {
    $response = $this->makeApp()->handleRequest('GET', '/not-found/');
    $this->assertEquals(404, $response->getStatusCode());
  }

  public function testFalsyValidParam() {
    $response = $this->makeApp()->handleRequest('GET', '/user/0');
    $this->assertEquals(404, $response->getStatusCode());
  }

  public function testFalsyValidBody() {
    $response = $this->makeApp()->handleRequest('POST', '/user', payload: []);
    $this->assertEquals(418, $response->getStatusCode());
  }

  public function testVoidControllerResponse() {
    $response = $this->makeApp()->handleRequest('GET', '/hello/noop');
    $this->assertEquals(200, $response->getStatusCode());
  }

  public function testDynamicSegment() {
    $response = $this->makeApp()->handleRequest('GET', '/hello/greet/World');
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals('Hello, World!', (string) $response->getBody());
  }

  public function testStaticOverDynamicSegmentPriorityWithRandomParamOrder() {
    $response = $this->makeApp()->handleRequest('GET', '/hello/greet/king');
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals('Greetings to the King!', (string) $response->getBody());
  }

  public function testMethodNotSupported() {
    $response = $this->makeApp()->handleRequest('POST', '/hello/greet/world');
    $this->assertEquals(405, $response->getStatusCode());
  }

  public function testDuplicatingStaticRoutes() {
    $this->expectException(AmbiguousRouteException::class);
    $this->makeApp(['DuplicatingStatic'])->handleRequest('DELETE', '/any/request');
  }

  public function testDuplicatingRouteNames() {
    $this->expectException(AmbiguousRouteException::class);
    $this->makeApp(['DuplicatingNames'])->handleRequest('DELETE', '/any/request');
  }

  public function testAmbiguousDynamicSegments() {
    $this->expectException(AmbiguousRouteException::class);
    $this->makeApp(['AmbiguousDynamic'])->handleRequest('DELETE', '/any/request');
  }

  public function testNoControllers() {
    $this->expectException(NoControllersException::class);
    $this->makeApp(['NotExistingPath'])->handleRequest('DELETE', '/any/request');
  }

  public function testRouteParamsNotConsistentMethodParams() {
    $this->expectException(InconsistentParamsException::class);
    $this->makeApp(['InconsistentParams'])->handleRequest('DELETE', '/any/request');
  }

  public function testResponseSerializing() {
    $response = $this->makeApp()->handleRequest('GET', '/user');
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertCount(2, self::toJson($response));
  }

  public function testRequiredBodyError() {
    $response = $this->makeApp()->handleRequest('POST', '/user');
    $this->assertEquals(400, $response->getStatusCode());
  }

  public function testStaticToDynamicBacktrace() {
    $app = $this->makeApp();
    $response = $app->handleRequest('GET', '/hello/welcome/great/king');
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals('Welcome, Great King!', (string) $response->getBody());

    $response = $app->handleRequest('GET', '/hello/welcome/noble/knight');
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals('Welcome, noble knight!', (string) $response->getBody());
  }

  public function testShuffledParamOrder() {
    $response = $this->makeApp()->handleRequest('POST', '/hello/specie/wolf/color/black');
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals('POST Hello, black wolf!', (string) $response->getBody());
  }

  public function testSimplifiedRouterResponse() {
    $app = $this->makeApp();
    $response = $app->handleRequest('GET', '/user/1');
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals('userA', self::toJson($response)['name']);

    $response = $app->handleRequest('HEAD', '/user/1');
    $this->assertEquals(200, $response->getStatusCode());

    $response = $app->handleRequest('GET', '/user/-1');
    $this->assertEquals(404, $response->getStatusCode());

    $response = $app->handleRequest('HEAD', '/user/-1');
    $this->assertEquals(404, $response->getStatusCode());

    $response = $app->handleRequest('GET', '/user/1/roles');
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals(['a', 'b', 'c'], self::toJson($response));

    $response = $app->handleRequest('POST', '/user', payload: ['id' => 1]);
    $this->assertEquals(409, $response->getStatusCode());

    $response = $app->handleRequest('POST', '/user', payload: ['id' => 3]);
    $this->assertEquals(201, $response->getStatusCode());
    $this->assertArrayHasKey('id', self::toJson($response));

    $response = $app->handleRequest('DELETE', '/user/3');
    $this->assertEquals(204, $response->getStatusCode());
  }

  public function testQueryParam() {
    $app = $this->makeApp();
    $response = $app->handleRequest('GET', '/user', query: ['filterKey' => 'role', 'filterValue' => 'b']);
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertCount(1, self::toJson($response));

    $response = $app->handleRequest('GET', '/user', query: ['filterKey' => 'role', 'filterValue' => 'b', 'active' => 'true']);
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertIsArray(self::toJson($response));
    $this->assertCount(0, self::toJson($response));

    $response = $app->handleRequest('GET', '/user', query: ['active' => 'false']);
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertCount(1, self::toJson($response));

    $response = $app->handleRequest('GET', '/user', query: ['active' => 'true']);
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertCount(1, self::toJson($response));

    $response = $app->handleRequest('GET', '/user', query: ['active' => '0']);
    $this->assertEquals(200, $response->getStatusCode());

    $response = $app->handleRequest('GET', '/user', query: ['active' => '1']);
    $this->assertEquals(200, $response->getStatusCode());

    $response = $app->handleRequest('GET', '/user', query: ['active' => 'no']);
    $this->assertEquals(400, $response->getStatusCode());

    $response = $app->handleRequest('GET', '/user', query: ['active' => 'yes']);
    $this->assertEquals(400, $response->getStatusCode());
  }

  public function testInvalidBody() {
    $response = $this->makeApp()->handleRequest('POST', '/user', payloadStr: '{');
    $this->assertEquals(400, $response->getStatusCode());
  }

  public function testInvalidParamType() {
    $this->expectException(InvalidParamTypeException::class);
    $this->makeApp(['InvalidParamType'])->handleRequest('GET', '/');
  }

  public function testInvalidBodyType() {
    $this->expectException(InvalidParamTypeException::class);
    $this->makeApp(['InvalidBodyType'])->handleRequest('POST', '/', payload: []);
  }

  public function testRequestAttributes() {
    $response = $this->makeApp()->handleRequest('POST', '/hello/validate-long-message', payload: [], routerOnly: true);
    $this->assertEquals(200, $response->getStatusCode());
    $attrs = self::toJson($response);
    $this->assertIsArray($attrs);
    $this->assertEquals(true, $attrs['auth']);
    $this->assertEquals(true, $attrs['noCsrf']);
    $this->assertEquals(true, $attrs['ctrlSpecific']);
    $this->assertEquals('b', $attrs['a']);
    $this->assertEquals('d', $attrs['c']);
  }

  public function testRawRequestResponse() {
    $msg = 'This is message!';
    $response = $this->makeApp()->handleRequest('POST', '/raw/echo/str', payloadStr: $msg);
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals($msg, (string) $response->getBody());
  }

  public function testControllerStreamResult() {
    $msg = 'This is message!';
    $response = $this->makeApp()->handleRequest('POST', '/raw/echo/stream', payloadStr: $msg);
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals($msg, (string) $response->getBody());
  }

  public function testReverseRouteStaticRoute() {
    $url = $this->makeApp()->getRouter()->reverse('addUser', ['hello' => 'world']);
    $this->assertEquals('/user', (string) $url);
  }

  public function testReverseRouteSegmentParams() {
    $id = 999;
    $url = $this->makeApp()->getRouter()->reverse('getUserRoles', ['id' => $id]);
    $this->assertEquals("/user/$id/roles", (string) $url);
  }

  public function testReverseRouteQueryParams() {
    $filterKey = 'some-key';
    $filterValue = 'some-value';
    $app = $this->makeApp();
    $url = $app->getRouter()->reverse('getUsers', ['filterKey' => $filterKey, 'filterValue' => $filterValue, 'active' => true]);
    $this->assertEquals("/user?filterKey=$filterKey&filterValue=$filterValue&active=true", (string) $url);

    $url = $app->getRouter()->reverse('getUsers', ['filterKey' => $filterKey, 'filterValue' => $filterValue, 'active' => false]);
    $this->assertEquals("/user?filterKey=$filterKey&filterValue=$filterValue&active=false", (string) $url);
  }

  public function testReverseRouteBodyParam() {
    $app = $this->makeApp();
    $id = 999;
    $url = $app->getRouter()->reverse('userEditUser', ['id' => $id]);
    $this->assertEquals("/user/$id", (string) $url);

    $url = $app->getRouter()->reverse('userEditUser', ['id' => $id, 'body' => []]);
    $this->assertEquals("/user/$id", (string) $url);
  }

  public function testGettingCurrentRouteLocation() {
    $id = 123;
    $fullInfo = true;
    $attrFilter = 'some-filter';
    $app = $this->makeApp();
    $app->handleRequest('GET', "/user/$id", query: ['fullInfo' => (string) $fullInfo, 'attrFilter' => $attrFilter]);
    // $req param must be ignored, order of params must be normalized
    $expected = $app->getRouter()->createLocation('getUser', ['req' => null, 'fullInfo' => $fullInfo, 'id' => $id, 'attrFilter' => $attrFilter]);
    $actual = $app->getRouter()->currentLocation();

    $this->assertEquals((string) $expected, (string) $actual);
  }

  public function testResolvingCurrentLocation() {
    $app = $this->makeApp();
    $app->handleRequest('GET', '/user');
    // $req param must be ignored, order of params must be normalized
    $actual = $app->getRouter()->reverseLocation($app->getRouter()->currentLocation());

    $this->assertEquals('/user', (string) $actual);
  }

  public function testResolvingUnnamedLocation() {
    $app = $this->makeApp();
    $app->handleRequest('GET', '/hello/noop');
    $actual = $app->getRouter()->currentLocation();
    $this->assertNull($actual);
  }

}
