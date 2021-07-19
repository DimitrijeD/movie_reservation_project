<?php
namespace Drupal\all_movies_listing\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\Entity\Node;

class AllMoviesListing extends ControllerBase{
  public function content(){

    $allNodeIds = \Drupal::entityQuery('node')->condition('type', 'movies')->execute();
    $movies = Node::loadMultiple($allNodeIds);

    return [
      '#theme' => 'all-movies-listing',
      '#pageTitle' => 'A List of all movies',
      '#movies' => $movies,
    ];
  }

  public function movie_reservation(){
    return [
      '#theme' => 'movie-reservation',
      '#pageTitle' => 'Welcome to our movie reservation page',
    ];
  }
}




