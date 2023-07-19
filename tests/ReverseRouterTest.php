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
use OpenCore\Controllers\Valid\User;

final class ReverseRouterTest extends TestCase {

  private ReverseRouter $reverseRouter;

  protected function setUp(): void {
    $this->reverseRouter = new ReverseRouter(null);
  }

  public function testStaticRoute() {
    $url = $this->reverseRouter->for(User::class)->addUser(1, hello: 'world');
    $this->assertEquals('/user', $url);
  }

  public function testSegmentParams() {
    $id = 999;
    $url = $this->reverseRouter->for(User::class)->getUserRoles($id);
    $this->assertEquals("/user/$id/roles", $url);
  }

  public function testQueryParams() {
    $filterKey = 'some-key';
    $filterValue = 'some-value';

    $url = $this->reverseRouter->for(User::class)->getUsers(filterKey: $filterKey, filterValue: $filterValue, active: true);
    $this->assertEquals("/user?filterKey=$filterKey&filterValue=$filterValue&active=true", $url);

    $url = $this->reverseRouter->for(User::class)->getUsers($filterKey, $filterValue, active: false);
    $this->assertEquals("/user?filterKey=$filterKey&filterValue=$filterValue&active=false", $url);
  }

  public function testBodyParam() {
    $id = 999;
    $url = $this->reverseRouter->for(User::class)->editUser($id);
    $this->assertEquals("/user/$id", $url);

    $url = $this->reverseRouter->for(User::class)->editUser($id, body: []);
    $this->assertEquals("/user/$id", $url);
  }

}
