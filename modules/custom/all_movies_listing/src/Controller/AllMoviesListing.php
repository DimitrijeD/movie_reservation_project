<?php
namespace Drupal\all_movies_listing\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\Entity\Node;

class AllMoviesListing extends ControllerBase {

  public function content()
  {
    $allNodeIds = \Drupal::entityQuery('node')->condition('type', 'movies')->execute();
    $movies = Node::loadMultiple($allNodeIds);

    return [
      '#theme' => 'all-movies-listing',
      '#pageTitle' => 'A List of all movies',
      '#movies' => $movies,
    ];
  }

  public function movie_reservation()
  {
    return [
      '#theme' => 'movie-reservation',
      '#pageTitle' => 'Welcome to our movie reservation page',
      '#movie_categories' => $this->get_all_movie_categories(),
    ];
  }

  public function get_all_movie_categories()
  {
    $terms =\Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree('movie_type');
    foreach ($terms as $term) {
      $term_data[] = [
        'tid' => $term->tid,
        'name' => $term->name,
      ];
    }
    return $term_data;
  }
}




