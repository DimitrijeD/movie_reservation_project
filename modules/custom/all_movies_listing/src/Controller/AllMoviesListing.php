<?php
namespace Drupal\all_movies_listing\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;

class AllMoviesListing extends ControllerBase {

  public function content()
  {
    return [
      '#theme' => 'all-movies-listing',
      '#pageTitle' => 'A List of all movies',
      '#movies' => $this->get_all_movie_nodes(),
    ];
  }

  public function movie_reservation()
  {
    return [
      '#theme' => 'movie-reservation',
      '#pageTitle' => 'Welcome to our movie reservation page',
      '#movie_categories' => $this->get_all_movie_categories(),
      '#movies' => $this->get_all_movie_nodes(),
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

  public function get_all_movie_nodes()
  {
    $allNodeIds = \Drupal::entityQuery('node')->condition('type', 'movies')->execute();
    return Node::loadMultiple($allNodeIds);
  }

/*  public function test()
  {
    $movies = $this->get_all_movie_nodes();
    $days_in_week = [
      'monday'    => 'field_monday',
      'tuesday'   => 'field_tuesday',
      'wednesday' => 'field_wednesday',
      'thursday'  => 'field_thursday',
      'friday'    => 'field_friday',
    ];

    foreach ($movies as $movie)
    {
      $available_days = [];
      $paragraph = $movie->field_movie_paragraph->getValue();
      $target_id = $paragraph[0]['target_id'];
      $p = Paragraph::load($target_id);

      if(!empty($p)) {
        foreach ($days_in_week as $day => $field) {
          $text = $p->{$field}->getValue();
          $available_days[$day] = $text[0]['value'];
        }
      }
      $s = 4;
    }
    $a = 2;

  }*/
}




