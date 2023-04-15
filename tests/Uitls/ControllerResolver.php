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

namespace OpenCore\Uitls;

use Psr\Container\ContainerInterface;

class ControllerResolver implements ContainerInterface {

  private $cache = [];

  public function get(string $id): mixed {
    if (!isset($this->cache[$id])) {
      $this->cache[$id] = new $id;
    }
    return $this->cache[$id];
  }

  public function has(string $id): bool {
    return class_exists($id);
  }

}
