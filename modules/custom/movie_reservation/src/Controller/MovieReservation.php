<?php
namespace Drupal\movie_reservation\Controller;

use Drupal\Core\Controller\ControllerBase;

class MovieReservation extends ControllerBase {
  public function content(){

    return [
      '#theme' => 'movie-reservation',
      '#pageTitle' => 'Welcome to our movie reservation page',
    ];
  }
}

