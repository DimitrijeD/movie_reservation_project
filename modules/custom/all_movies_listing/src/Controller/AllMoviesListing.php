<?php
namespace Drupal\all_movies_listing\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Database;
use Drupal\node\Entity\Node;
use Drupal\media\Entity\Media;
use Drupal\file\Entity\File;

class AllMoviesListing extends ControllerBase{
  public function content(){

    $allNodeIds = \Drupal::entityQuery('node')->condition('type', 'movies')->execute();
    $nodes = Node::loadMultiple($allNodeIds);

    $movies = array();
    $key = 1;
    foreach ($nodes as $node){
      $movies[$key]['title'] = $node->get('title')->value;
      $movies[$key]['image_uri'] = $node->field_image_field->entity->getFileUri();
      $movies[$key]['description'] = $node->get('field_description')->value;
      $key++;
    }

    return [
      '#theme' => 'all-movies-listing',
      '#pageTitle' => 'A List of all movies',
      '#movies' => $movies,
    ];
  }
}




