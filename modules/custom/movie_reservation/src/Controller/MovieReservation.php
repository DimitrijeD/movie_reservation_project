<?php
namespace Drupal\movie_reservation\Controller;

use Drupal\Core\Controller\ControllerBase;

class MovieReservation extends ControllerBase {
  public function view(){
    $content = array ();

    return [
      '#theme' => 'movie-reservation',
      '#content' => $content
    ];
  }
}

