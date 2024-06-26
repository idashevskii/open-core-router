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

use Closure;

interface RouterConfig {

  /**
   * Controllers lookup PSR-4 map [rootNamespace=>dir]
   * @return array
   */
  function getControllerDirs(): array;

  /**
   * Stores and restores compiled data
   * @param Closure $dataProvider generates data for first initial time
   * @return array
   */
  function storeCompiledData(Closure $dataProvider): array;

  /**
   * Converts string value to required type. It could be built-in types as well as class names
   */
  function deserialize(string $type, string $value): mixed;
}
