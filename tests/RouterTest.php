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
  InvalidParamTypeException,
};
use Psr\Http\Message\ResponseInterface;
use LogicException;

final class RouterTest extends TestCase {

  private static ?App $app = null;

  protected function setUp(): void {
    self::$app = App::create(['Valid', 'UnusedHuge']);
  }

  public static function tearDownAfterClass(): void {
    self::$app->clear();
  }

  private function makeApp(array $scanNs): App {
    return App::create($scanNs);
  }

  private static function toJson(ResponseInterface $response) {
    $data = (string) $response->getBody();
    return $data !== '' ? json_decode($data, true) : null;
  }

  public function testNotFound() {
    $response = self::$app->handleRequest('GET', '/not-found/');
    $this->assertEquals(404, $response->getStatusCode());
  }

  public function testFalsyValidParam() {
    $response = self::$app->handleRequest('GET', '/user/0');
    $this->assertEquals(404, $response->getStatusCode());
  }

  public function testFalsyValidBody() {
    $response = self::$app->handleRequest('POST', '/user', payload: []);
    $this->assertEquals(418, $response->getStatusCode());
  }

  public function testVoidControllerResponse() {
    $response = self::$app->handleRequest('GET', '/hello/noop');
    $this->assertEquals(200, $response->getStatusCode());
  }

  public function testDynamicSegment() {
    $response = self::$app->handleRequest('GET', '/hello/greet/World');
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals('Hello, World!', (string) $response->getBody());
  }

  public function testStaticOverDynamicSegmentPriorityWithRandomParamOrder() {
    $response = self::$app->handleRequest('GET', '/hello/greet/king');
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals('Greetings to the King!', (string) $response->getBody());
  }

  public function testMethodNotSupported() {
    $response = self::$app->handleRequest('POST', '/hello/greet/world');
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
    $response = self::$app->handleRequest('GET', '/user');
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertCount(2, self::toJson($response));
  }

  public function testRequiredBodyError() {
    $response = self::$app->handleRequest('POST', '/user');
    $this->assertEquals(400, $response->getStatusCode());
  }

  public function testStaticToDynamicBacktrace() {
    $response = self::$app->handleRequest('GET', '/hello/welcome/great/king');
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals('Welcome, Great King!', (string) $response->getBody());

    $response = self::$app->handleRequest('GET', '/hello/welcome/noble/knight');
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals('Welcome, noble knight!', (string) $response->getBody());
  }

  public function testShuffledParamOrder() {
    $response = self::$app->handleRequest('POST', '/hello/specie/wolf/color/black');
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals('POST Hello, black wolf!', (string) $response->getBody());
  }

  public function testSimplifiedRouterResponse() {
    $response = self::$app->handleRequest('GET', '/user/1');
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals('userA', self::toJson($response)['name']);

    $response = self::$app->handleRequest('HEAD', '/user/1');
    $this->assertEquals(200, $response->getStatusCode());

    $response = self::$app->handleRequest('GET', '/user/-1');
    $this->assertEquals(404, $response->getStatusCode());

    $response = self::$app->handleRequest('HEAD', '/user/-1');
    $this->assertEquals(404, $response->getStatusCode());

    $response = self::$app->handleRequest('GET', '/user/1/roles');
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals(['a', 'b', 'c'], self::toJson($response));

    $response = self::$app->handleRequest('POST', '/user', payload: ['id' => 1]);
    $this->assertEquals(409, $response->getStatusCode());

    $response = self::$app->handleRequest('POST', '/user', payload: ['id' => 3]);
    $this->assertEquals(201, $response->getStatusCode());
    $this->assertArrayHasKey('id', self::toJson($response));

    $response = self::$app->handleRequest('DELETE', '/user/3');
    $this->assertEquals(204, $response->getStatusCode());
  }

  public function testQueryParam() {
    $response = self::$app->handleRequest('GET', '/user', query: ['filterKey' => 'role', 'filterValue' => 'b']);
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertCount(1, self::toJson($response));

    $response = self::$app->handleRequest('GET', '/user', query: ['filterKey' => 'role', 'filterValue' => 'b', 'active' => 'true']);
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertIsArray(self::toJson($response));
    $this->assertCount(0, self::toJson($response));

    $response = self::$app->handleRequest('GET', '/user', query: ['active' => 'false']);
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertCount(1, self::toJson($response));

    $response = self::$app->handleRequest('GET', '/user', query: ['active' => 'true']);
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertCount(1, self::toJson($response));

    $response = self::$app->handleRequest('GET', '/user', query: ['active' => '0']);
    $this->assertEquals(200, $response->getStatusCode());

    $response = self::$app->handleRequest('GET', '/user', query: ['active' => '1']);
    $this->assertEquals(200, $response->getStatusCode());

    $response = self::$app->handleRequest('GET', '/user', query: ['active' => 'no']);
    $this->assertEquals(400, $response->getStatusCode());

    $response = self::$app->handleRequest('GET', '/user', query: ['active' => 'yes']);
    $this->assertEquals(400, $response->getStatusCode());
  }

  public function testInvalidBody() {
    $response = self::$app->handleRequest('POST', '/user', payloadStr: '{');
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
    $response = self::$app->handleRequest('POST', '/hello/validate-long-message', payload: [], routerOnly: true);
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
    $response = self::$app->handleRequest('POST', '/raw/echo/str', payloadStr: $msg);
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals($msg, (string) $response->getBody());
  }

  public function testControllerStreamResult() {
    $msg = 'This is message!';
    $response = self::$app->handleRequest('POST', '/raw/echo/stream', payloadStr: $msg);
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals($msg, (string) $response->getBody());
  }

  public function testReverseRouteStaticRoute() {
    $url = self::$app->getRouter()->reverse('addUser', ['hello' => 'world']);
    $this->assertEquals('/user', (string) $url);
  }

  public function testReverseRouteSegmentParams() {
    $id = 999;
    $url = self::$app->getRouter()->reverse('getUserRoles', ['id' => $id]);
    $this->assertEquals("/user/$id/roles", (string) $url);
  }

  public function testReverseRouteQueryParams() {
    $filterKey = 'some-key';
    $filterValue = 'some-value';

    $url = self::$app->getRouter()->reverse('getUsers', ['filterKey' => $filterKey, 'filterValue' => $filterValue, 'active' => true]);
    $this->assertEquals("/user?filterKey=$filterKey&filterValue=$filterValue&active=true", (string) $url);

    $url = self::$app->getRouter()->reverse('getUsers', ['filterKey' => $filterKey, 'filterValue' => $filterValue, 'active' => false]);
    $this->assertEquals("/user?filterKey=$filterKey&filterValue=$filterValue&active=false", (string) $url);
  }

  public function testReverseRouteBodyParam() {
    $id = 999;
    $url = self::$app->getRouter()->reverse('userEditUser', ['id' => $id]);
    $this->assertEquals("/user/$id", (string) $url);

    $url = self::$app->getRouter()->reverse('userEditUser', ['id' => $id, 'body' => []]);
    $this->assertEquals("/user/$id", (string) $url);
  }

  public function testGettingCurrentRouteLocation() {
    $id = 123;
    $fullInfo = true;
    $attrFilter = 'some-filter';

    self::$app->handleRequest('GET', "/user/$id", query: ['fullInfo' => (string) $fullInfo, 'attrFilter' => $attrFilter]);
    // $req param must be ignored, order of params must be normalized
    $expected = self::$app->getRouter()->createLocation('getUser', ['req' => null, 'fullInfo' => $fullInfo, 'id' => $id, 'attrFilter' => $attrFilter]);
    $actual = self::$app->getRouter()->currentLocation();

    $this->assertEquals((string) $expected, (string) $actual);
  }

  public function testResolvingCurrentLocation() {
    self::$app->handleRequest('GET', '/user');
    // $req param must be ignored, order of params must be normalized
    $actual = self::$app->getRouter()->reverseLocation(self::$app->getRouter()->currentLocation());

    $this->assertEquals('/user', (string) $actual);
  }

  public function testResolvingUnnamedLocation() {
    $this->expectException(LogicException::class);
    self::$app->handleRequest('GET', '/hello/noop');
    self::$app->getRouter()->currentLocation();
  }

}
