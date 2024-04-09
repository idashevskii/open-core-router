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

use OpenCore\Inject;

use Closure;
use OpenCore\Router\Exceptions\RoutingException;

final class AppConfig implements RouterConfig {

  public const INJECT_CONTROLLER_SCAN_NS = 'scanNs';
  public const INJECT_ROUTER_DATA_FILE = 'routerDataFile';

  public function __construct(
    #[Inject(self::INJECT_CONTROLLER_SCAN_NS)] private array $scanNs,
    #[Inject(self::INJECT_ROUTER_DATA_FILE)] private string $routerDataFile,
  ) {

  }

  public function getControllerDirs(): array {
    $ret = [];
    foreach ($this->scanNs as $ns) {
      $ns = 'Controllers/' . $ns;
      $ret[__NAMESPACE__ . '\\' . strtr($ns, '/', '\\')] = __DIR__ . '/' . $ns;
    }
    return $ret;
  }

  public function storeCompiledData(Closure $dataProvider): array {
    if (!file_exists($this->routerDataFile)) {
      file_put_contents($this->routerDataFile, '<?php return ' . var_export($dataProvider(), true) . ';');
    }
    return include ($this->routerDataFile);
  }

  function deserialize(string $type, string $value): mixed {
    return match ($type) {
      'string' => $value,
      'int' => (int) $value,
      'float' => (float) $value,
      'bool' => match ($value) {
          'true', '1' => true,
          'false', '0' => false,
          default => throw new RoutingException("Boolean param has invalid boolean value", code: 400),
        },
      'array' => self::parseJson($value),
    };
  }

  private static function parseJson(string $json) {
    try {
      return json_decode($json, flags: JSON_THROW_ON_ERROR | JSON_OBJECT_AS_ARRAY);
    } catch (\JsonException $ex) {
      throw new RoutingException('Body parse error', code: 400, previous: $ex);
    }
  }

}
