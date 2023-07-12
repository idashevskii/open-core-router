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

namespace OpenCore\Controllers\UnusedHuge;

use OpenCore\Controller;
use OpenCore\Route;
use OpenCore\Body;
use OpenCore\ControllerResponse;

#[Controller('/crud1')]
class Crud1 {


  #[Route('GET', '')]
  public function getAll(?string $filter1=null, ?string $filter2=null, ?string $filter3=null, ?string $sort=null) {
    return [];
  }

  #[Route('POST', '')]
  public function add(#[Body] array $data) {
    return [];
  }

  #[Route('PUT', '')]
  public function putUser(#[Body] array $data) {
    return [];
  }

  #[Route('GET', '{id}')]
  public function get(#[Body] array $data) {
    return [];
  }

  #[Route('DELETE', '{id}')]
  public function delete(int $id) {
  }

  #[Route('PATCH', '{id}')]
  public function patch(int $id, #[Body] array $data) {
  }

  #[Route('GET', 'sub1/')]
  public function getAllSub1(?string $filter1=null, ?string $filter2=null, ?string $filter3=null, ?string $sort=null) {
    return [];
  }

  #[Route('POST', 'sub1/')]
  public function addSub1(#[Body] array $data) {
    return [];
  }

  #[Route('PUT', 'sub1/')]
  public function putUserSub1(#[Body] array $data) {
    return [];
  }

  #[Route('GET', 'sub1/{id}')]
  public function getSub1(#[Body] array $data) {
    return [];
  }

  #[Route('DELETE', 'sub1/{id}')]
  public function deleteSub1(int $id) {
  }

  #[Route('PATCH', 'sub1/{id}')]
  public function patchSub1(int $id, #[Body] array $data) {
  }

  #[Route('GET', 'sub2/')]
  public function getAllSub2(?string $filter1=null, ?string $filter2=null, ?string $filter3=null, ?string $sort=null) {
    return [];
  }

  #[Route('POST', 'sub2/')]
  public function addSub2(#[Body] array $data) {
    return [];
  }

  #[Route('PUT', 'sub2/')]
  public function putUserSub2(#[Body] array $data) {
    return [];
  }

  #[Route('GET', 'sub2/{id}')]
  public function getSub2(#[Body] array $data) {
    return [];
  }

  #[Route('DELETE', 'sub2/{id}')]
  public function deleteSub2(int $id) {
  }

  #[Route('PATCH', 'sub2/{id}')]
  public function patchSub2(int $id, #[Body] array $data) {
  }

  #[Route('GET', 'sub3/')]
  public function getAllSub3(?string $filter1=null, ?string $filter2=null, ?string $filter3=null, ?string $sort=null) {
    return [];
  }

  #[Route('POST', 'sub3/')]
  public function addSub3(#[Body] array $data) {
    return [];
  }

  #[Route('PUT', 'sub3/')]
  public function putUserSub3(#[Body] array $data) {
    return [];
  }

  #[Route('GET', 'sub3/{id}')]
  public function getSub3(#[Body] array $data) {
    return [];
  }

  #[Route('DELETE', 'sub3/{id}')]
  public function deleteSub3(int $id) {
  }

  #[Route('PATCH', 'sub3/{id}')]
  public function patchSub3(int $id, #[Body] array $data) {
  }
  
  
  #[Route('POST', 'sub4/')]
  public function addSub4(#[Body] array $data) {
    return [];
  }

  #[Route('PUT', 'sub4/')]
  public function putUserSub4(#[Body] array $data) {
    return [];
  }

  #[Route('GET', 'sub4/{id}')]
  public function getSub4(#[Body] array $data) {
    return [];
  }

  #[Route('DELETE', 'sub4/{id}')]
  public function deleteSub4(int $id) {
  }

  #[Route('PATCH', 'sub4/{id}')]
  public function patchSub4(int $id, #[Body] array $data) {
  }
  
  
  #[Route('POST', 'sub5/')]
  public function addSub5(#[Body] array $data) {
    return [];
  }

  #[Route('PUT', 'sub5/')]
  public function putUserSub5(#[Body] array $data) {
    return [];
  }

  #[Route('GET', 'sub5/{id}')]
  public function getSub5(#[Body] array $data) {
    return [];
  }

  #[Route('DELETE', 'sub5/{id}')]
  public function deleteSub5(int $id) {
  }

  #[Route('PATCH', 'sub5/{id}')]
  public function patchSub5(int $id, #[Body] array $data) {
  }
  
  
  
  #[Route('POST', 'sub6/')]
  public function addSub6(#[Body] array $data) {
    return [];
  }

  #[Route('PUT', 'sub6/')]
  public function putUserSub6(#[Body] array $data) {
    return [];
  }

  #[Route('GET', 'sub6/{id}')]
  public function getSub6(#[Body] array $data) {
    return [];
  }

  #[Route('DELETE', 'sub6/{id}')]
  public function deleteSub6(int $id) {
  }

  #[Route('PATCH', 'sub6/{id}')]
  public function patchSub6(int $id, #[Body] array $data) {
  }
  
  
  #[Route('POST', 'sub7/')]
  public function addSub7(#[Body] array $data) {
    return [];
  }

  #[Route('PUT', 'sub7/')]
  public function putUserSub7(#[Body] array $data) {
    return [];
  }

  #[Route('GET', 'sub7/{id}')]
  public function getSub7(#[Body] array $data) {
    return [];
  }

  #[Route('DELETE', 'sub7/{id}')]
  public function deleteSub7(int $id) {
  }

  #[Route('PATCH', 'sub7/{id}')]
  public function patchSub7(int $id, #[Body] array $data) {
  }
  
  
  #[Route('POST', 'sub8/')]
  public function addSub8(#[Body] array $data) {
    return [];
  }

  #[Route('PUT', 'sub8/')]
  public function putUserSub8(#[Body] array $data) {
    return [];
  }

  #[Route('GET', 'sub8/{id}')]
  public function getSub8(#[Body] array $data) {
    return [];
  }

  #[Route('DELETE', 'sub8/{id}')]
  public function deleteSub8(int $id) {
  }

  #[Route('PATCH', 'sub8/{id}')]
  public function patchSub8(int $id, #[Body] array $data) {
  }
}
