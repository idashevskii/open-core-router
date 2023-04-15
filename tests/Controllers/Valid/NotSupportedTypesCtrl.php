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

namespace OpenCore\Controllers\Valid;

use OpenCore\Controller;
use OpenCore\Route;
use OpenCore\Body;

#[Controller('/types')]
class NotSupportedTypesCtrl {

  #[Route('GET', 'array/{arr}')]
  public function argArr(array $arr) {
    return null;
  }

  #[Route('GET', 'object/{arr}')]
  public function argObject(object $arr) {
    return null;
  }

  #[Route('GET', 'mixed/{arr}')]
  public function argMixed(mixed $arr) {
    return null;
  }

  #[Route('POST', 'body-object')]
  public function bodyObject(#[Body] object $arr) {
    return null;
  }

  #[Route('POST', 'body-string')]
  public function bodyString(#[Body] string $arr) {
    return null;
  }

}
