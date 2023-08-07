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
use OpenCore\RouteAnnotations\Auth;
use OpenCore\RouteAnnotations\CtrlCommon;
use OpenCore\RouteAnnotations\NoCsrf;
use OpenCore\Body;
use Psr\Http\Message\ServerRequestInterface;

#[Controller('/hello')]
#[CtrlCommon()]
class HelloCtrl {

  public function __construct() {
    
  }

  #[Route('GET', 'greet/{name}')]
  public function sayHello(string $name) {
    return "Hello, $name!";
  }

  #[Route('GET', 'greet/king')]
  public function welcomeKing() {
    return "Greetings to the King!";
  }

  #[Route('GET', '{greeting}/{title}/{name}')]
  public function customGreeting(string $name, string $greeting, string $title) {
    $greeting = ucfirst($greeting);
    return "$greeting, $title $name!";
  }

  #[Route('POST', 'specie/{specie}/color/{color}')]
  public function shuffledOrder(string $color, ServerRequestInterface $request, string $specie) {
    return $request->getMethod() . " Hello, $color $specie!";
  }

  #[Route('GET', 'welcome/great/king')]
  public function welcomeGreatKing() {
    return "Welcome, Great King!";
  }

  #[Route('GET', 'noop', name: null)]
  public function noop() {
    
  }

  #[Route('POST', '/validate-long-message', ['a' => 'b', 'c' => 'd'])]
  #[Auth]
  #[NoCsrf]
  public function validateLongGreetingMessage(#[Body] array $message) {
    // ...
  }

}
