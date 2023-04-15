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

final class ExecutorPayloadParam {

  public const KIND_SEGMENT = 1;
  public const KIND_BODY = 2;
  public const KIND_QUERY = 3;
  public const KIND_REQUEST = 4;
  public const KIND_RESPONSE = 5;

  public function __construct(
      private readonly int $kind,
      private readonly string $type,
      private mixed $value,
  ) {
    
  }

  public function withValue(mixed $value) {
    $ret = clone $this;
    $ret->value = $value;
    return $ret;
  }

  public function getKind(): int {
    return $this->kind;
  }

  public function getType(): string {
    return $this->type;
  }

  public function getValue(): mixed {
    return $this->value;
  }

}
