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

namespace OpenCore\Router\Controllers\Valid;

use OpenCore\Router\Controller;
use OpenCore\Router\Route;
use OpenCore\Router\Body;
use OpenCore\Router\ControllerResponse;
use Psr\Http\Message\ServerRequestInterface;

#[Controller('user')]
class User {

  public function __construct() {
    
  }

  private $users = [
    1 => ['id' => 1, 'name' => 'userA', 'active' => false, 'roles' => ['a', 'b', 'c']],
    2 => ['id' => 2, 'name' => 'userB', 'active' => true, 'roles' => ['a', 'd']],
  ];

  #[Route('GET', '', name: 'getUsers')]
  public function getUsers(ServerRequestInterface $req, ?string $filterKey = null, ?string $filterValue = null, ?bool $active = null) {
    $ret = $this->users;
    if ($filterKey === 'role') {
      $ret = array_filter($ret, fn($u) => in_array($filterValue, $u['roles']));
    }
    if ($active !== null) {
      $ret = array_filter($ret, fn($u) => $u['active'] === $active);
    }
    return ControllerResponse::fromBody(array_values($ret)); // should result in 200 status by default
  }

  #[Route('POST', '', name: 'addUser')]
  public function addUser(ServerRequestInterface $req, #[Body] array $user) {
    if (!isset($user['id'])) {
      return ControllerResponse::fromStatus(418);
    }
    if (isset($this->users[$user['id']])) {
      return ControllerResponse::fromStatus(409);
    }
    $this->users[$user['id']] = $user;
    return ControllerResponse::fromStatus(201)->withBody($user);
  }

  #[Route('PUT', '')]
  public function putUser(ServerRequestInterface $req, #[Body] array $user) {
    $id = $user['id'];
    $isNew = isset($this->users[$id]);
    $this->users[$id] = $user;
    return ControllerResponse::fromStatus($isNew ? 201 : 200)->withBody($user);
  }

  #[Route('GET', '{id}', name: 'getUser')]
  public function getUser(int $id, ServerRequestInterface $req, bool $fullInfo = null, string $attrFilter = null, int $opt = null) {
    if (!isset($this->users[$id])) {
      return ControllerResponse::fromStatus(404);
    }
    return $this->users[$id];
  }

  #[Route('DELETE', '{id}')]
  public function deleteUser(ServerRequestInterface $req, int $id) {
    if (!isset($this->users[$id])) {
      return ControllerResponse::fromStatus(404);
    }
    unset($this->users[$id]);
    return ControllerResponse::fromStatus(204);
  }

  #[Route('PATCH', '{id}', name: 'userEditUser')]
  public function editUser(int $id, ServerRequestInterface $req, #[Body] array $data, bool $optional = null) {
    if (isset($data['name'])) {
      $this->users[$id]['name'] = $data['name'];
    }
    if (isset($data['roles'])) {
      $this->users[$id]['roles'] = $data['roles'];
    }
    return $this->users[$id];
  }

  #[Route('GET', '{id}/roles', name: 'getUserRoles')]
  public function getUserRoles(ServerRequestInterface $req, int $id) {
    return $this->users[$id]['roles'];
  }

}
