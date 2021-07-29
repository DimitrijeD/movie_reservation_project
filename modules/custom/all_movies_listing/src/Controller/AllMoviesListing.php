<?php
namespace Drupal\all_movies_listing\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\serialization\Encoder\XmlEncoder;
use SimpleXMLElement;

class AllMoviesListing extends ControllerBase {

  /**
   * Function for providing '/all-movies-listing' page with all movies from database with page title.
   *
   * @return array
   */
  public function content()
  {
    return [
      '#theme' => 'all-movies-listing',
      '#pageTitle' => 'A List of all movies',
      '#movies' => $this->get_all_movie_nodes(),
    ];
  }

  /**
   * Function for providing '/all-movies-listing' page with movies with page title.
   *
   * Function calls get_category_filters() which checks $_POST if user submited movie categories,
   * and if he did, filtered movies will be passed to page based on input.
   *
   * @return array
   */
  public function movie_reservation()
  {
    if( empty($this->get_category_filters()) ){
      return [
        '#theme' => 'movie-reservation',
        '#pageTitle' => 'Welcome to our movie reservation page',
        '#movie_categories' => $this->get_all_movie_categories(),
        '#movies' => $this->get_all_movie_nodes(),
      ];
    }
    return [
      '#theme' => 'movie-reservation',
      '#pageTitle' => 'Welcome to our movie reservation page',
      '#movie_categories' => $this->get_all_movie_categories(),
      '#movies' => $this->get_movies_by_categories(),
    ];
  }

  /**
   * Function returns all available movie categories from "Movie type" vocabulary.
   *
   * @return array
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
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

  /**
   * Function returns all movie nodes.
   *
   * @return \Drupal\Core\Entity\EntityBase[]|\Drupal\Core\Entity\EntityInterface[]|Node[]
   */
  public function get_all_movie_nodes()
  {
    $allNodeIds = \Drupal::entityQuery('node')->condition('type', 'movies')->execute();
    return Node::loadMultiple($allNodeIds);
  }

  /**
   * Function for searching movies by title.
   *
   * @param null $title
   * @return \Drupal\Core\Entity\EntityBase[]|\Drupal\Core\Entity\EntityInterface[]|Node[]
   */
  public function get_movies_by_title($title = null)
  {
    $allNodeIds = \Drupal::entityQuery('node')
      ->condition('type', 'movies')
      ->condition('title', $title, 'CONTAINS' )
      ->execute();
    return Node::loadMultiple($allNodeIds);
  }

  /**
   * Function for filtering movies based on user defined movie categories.
   *
   * Returns all movies which belong to exact categories user defined, no more, no less.
   *
   * @return \Drupal\Core\Entity\EntityBase[]|\Drupal\Core\Entity\EntityInterface[]|Node[]
   */
  public function get_movies_by_categories()
  {
    $category_tid = [];
    $category_filters = $this->get_category_filters();
    foreach($category_filters as $c_tid){
      $category_tid[] = $c_tid;
    }

    $query = \Drupal::entityQuery('node')->accessCheck(FALSE);
    foreach($category_tid as $tid)
    {
      $group = $query
        ->andConditionGroup()
        ->condition('field_category.target_id', $tid);
      $query->condition($group);
    }
    $entity_ids = $query->execute();
    return Node::loadMultiple($entity_ids);
  }

  /**
   * Returns array of movie categories form $_POST['categories'] .
   *
   * @return mixed
   */
  private function get_category_filters()
  {
    return \Drupal::request()->request->get('categories');
  }

  public function movie_exporter(){
    return [
      '#theme' => 'movie-exporter',
      '#pageTitle' => 'Movie exporter form',
//      '#movies_for_export' => $this->get_movies_for_export(),
    ];
  }

  public function get_users_roles(){
    return \Drupal::currentUser()->getRoles();
  }

  public function get_movies_for_export(){
    $movies_for_export = \Drupal::entityQuery('node')
      ->condition('type', 'movies')
      ->condition('field_include_in_exporter','1')
      ->execute();
    $movies = Node::loadMultiple($movies_for_export);

    $movie1 = $this->object_to_array($movies[1]);
    $plz = $this->array2xml($movie1);

    $a = 2;
    return Node::loadMultiple($movies_for_export);
  }

  // https://stackoverflow.com/questions/4345554/convert-a-php-object-to-an-associative-array?rq=1
  public function object_to_array($data)
  {
    if (is_array($data) || is_object($data))
    {
      $result = [];
      foreach ($data as $key => $value)
      {
        $result[$key] = (is_array($data) || is_object($data)) ? $this->object_to_array($value) : $value;
      }
      return $result;
    }
    return $data;
  }

  public function array2xml($data, $root = null){
    $xml = new SimpleXMLElement($root ? '<' . $root . '/>' : '<root/>');
    array_walk_recursive($data, function($value, $key)use($xml){
      $xml->addChild($key, $value);
    });
    return $xml->asXML();
  }

}




