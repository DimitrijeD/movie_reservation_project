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
   * Function for providing '/movies-reservation' page with available movies and functionality for their reservation.
   *
   * Function calls get_category_filters() which checks $_POST if user submited movie categories,
   * and if he did, filtered movies will be passed to page based on input.
   *
   * @return array
   */
  public function movie_reservation()
  {
    $successful_reservation = '';
    if(\Drupal::request()->query->get('movie_reservation') !== null){
      $movie_reservation_all_data = \Drupal::request()->query->get('movie_reservation');
      
      $customer_name_validated = $movie_reservation_all_data['customer_name_validated'];
      $movie_id_for_reservation = $movie_reservation_all_data['movie_id_for_reservation'];
      $day_for_reservation = $movie_reservation_all_data['day_for_reservation'];

      // user inserted his name correctly and clicked final button for movie reservation on specific day of the week
      if($customer_name_validated AND $movie_id_for_reservation AND $day_for_reservation){
        $this->reserve_movie_for_day($customer_name_validated, $movie_id_for_reservation, $day_for_reservation);
        $successful_reservation = 'Your reservation has been recorded.';
      }
    }

    if( empty($this->get_category_filters()) ){
      return [
        '#theme' => 'movie-reservation',
        '#pageTitle' => 'Welcome to our movie reservation page',
        '#movie_categories' => $this->get_all_movie_categories(),
        '#movies' => $this->get_all_movie_nodes(),
        '#successful_reservation' => $successful_reservation,
      ];
    }
    return [
      '#theme' => 'movie-reservation',
      '#pageTitle' => 'Welcome to our movie reservation page',
      '#movie_categories' => $this->get_all_movie_categories(),
      '#movies' => $this->get_movies_by_categories(),
      '#successful_reservation' => $successful_reservation,
    ];
  }

  /**
   * Function returns all available movie categories(terms) from "Movie type" vocabulary.
   *
   * @return array
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function get_all_movie_categories()
  {
    $terms =\Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree('movie_type');
    foreach ($terms as $term){
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
   * Returns one movie node based on passed 'nid'
   *
   * @param $nid
   * @return \Drupal\Core\Entity\EntityBase[]|\Drupal\Core\Entity\EntityInterface[]|Node[]
   */
  private function get_movie_by_nid($nid)
  {
    return Node::load($nid);
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
    foreach($category_tid as $tid){
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

  /**
   * @return array
   */
  public function get_users_roles()
  {
    return \Drupal::currentUser()->getRoles();
  }

  /**
   * Function for saving movie reservation.
   *
   * @param $customer_name
   * @param $movie_nid
   * @param $day
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function reserve_movie_for_day($customer_name, $movie_nid, $day)
  {
    $movie = Node::load($movie_nid);
    $movie_name_arr = $movie->title->getValue();
    $movie_name_str = $movie_name_arr[0]['value'];
    $movie_categories = $movie->field_category->getValue();
    $all_movie_categories = $this->get_all_movie_categories();
    $str_of_all_cat_of_this_movie = '';

    foreach($movie_categories as $one_cat_of_movie){
      foreach($all_movie_categories as $all_cat_of_movies){
        if($one_cat_of_movie['target_id'] == $all_cat_of_movies['tid']){
          /* movie categories are saved in table as: categ1/categ2/categ3/ */
          $str_of_all_cat_of_this_movie .= $all_cat_of_movies['name'] . '/';
        }
      }
    }

    $connection = \Drupal::service('database');
    $result = $connection->insert('reservations')
      ->fields([
        'day_of_reservation' => $day,
        'reserved_movie_name' => $movie_name_str,
        'reserved_movie_genre' => $str_of_all_cat_of_this_movie,
        'customer_name' => $customer_name,
      ])
      ->execute();
  }

  public function movie_exporter()
  {
    return [
      '#theme' => 'movie-exporter',
      '#pageTitle' => 'Movie exporter form',
    ];
  }

  public function get_movies_for_export()
  {
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
    if (is_array($data) || is_object($data)){
      $result = [];
      foreach ($data as $key => $value){
        $result[$key] = (is_array($data) || is_object($data)) ? $this->object_to_array($value) : $value;
      }
      return $result;
    }
    return $data;
  }

  public function array2xml($data, $root = null)
  {
    $xml = new SimpleXMLElement($root ? '<' . $root . '/>' : '<root/>');
    array_walk_recursive($data, function($value, $key)use($xml){
      $xml->addChild($key, $value);
    });
    return $xml->asXML();
  }


}




