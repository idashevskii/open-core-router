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

use Psr\Log\AbstractLogger;

class Logger extends AbstractLogger {

  public function log($level, string|\Stringable $message, array $context = []): void {
    $search = [];
    $replace = [];
    foreach ($context as $key => $val) {
      if (!is_array($val) && (!is_object($val) || method_exists($val, '__toString'))) {
        $search[] = '{' . $key . '}';
        $replace[] = (string) $val;
      }
    }
    $message = str_replace($search, $replace, $message);
    $level = strtoupper($level);
    file_put_contents('php://stderr', "$level: $message\n");
  }

}
