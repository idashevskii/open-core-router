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

class ControllerResponse {

  private ?int $status = null;
  private mixed $body = null;
  private array $headers = [];

  private function __construct() {
    
  }

  public static function empty(): static {
    return new static();
  }

  public static function fromStatus(int $status): static {
    return (new static())->withStatus($status);
  }

  public static function fromBody(mixed $body): static {
    return (new static())->withBody($body);
  }

  public static function fromHeader(string $name, string $value): static {
    return (new static())->withHeader($name, $value);
  }

  public function withHeader(string $name, string $value): static {
    $this->headers[$name] = $value;
    return $this;
  }

  public function withStatus(int $status): static {
    $this->status = $status;
    return $this;
  }

  public function withBody(mixed $body): static {
    $this->body = $body;
    return $this;
  }

  public function getBody(): mixed {
    return $this->body;
  }

  public function getStatus(): ?int {
    return $this->status;
  }

  public function getHeaders(): array {
    return $this->headers;
  }

}
