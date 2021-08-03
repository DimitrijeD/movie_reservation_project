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
    $successful_reservation = $this->user_submitted_reserve_movie_for_day();

    if( empty($this->get_category_filters()) ){
      return [
        '#theme' => 'movie-reservation',
        '#pageTitle' => 'Welcome to our movie reservation page',
        '#movie_categories' => $this->get_all_movie_categories(),
        '#movies' => $this->get_all_movie_nodes(),
        '#successful_reservation' => $successful_reservation,
        '#halls' => $this->get_all_hall_terms(),
      ];
    }
    return [
      '#theme' => 'movie-reservation',
      '#pageTitle' => 'Welcome to our movie reservation page',
      '#movie_categories' => $this->get_all_movie_categories(),
      '#movies' => $this->get_movies_by_categories(),
      '#successful_reservation' => $successful_reservation,
      '#halls' => $this->get_all_hall_terms(),
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
   * Gets all $_GET['movie_reservation'] data from submitted form for movie reservation on specific day.
   *
   * Returns empty string in case one of 3 following vars are empty: customer name, movie id and air day of that movie.
   * Returns 'already_reserved' (after checking) if movie has already been reserved for 'that' movie on 'that' day.
   * Returns 'recorded' if movie reservation with there param doesn't exist.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function user_submitted_reserve_movie_for_day(){
    $successful_reservation = '';
    if(\Drupal::request()->query->get('movie_reservation') !== null){
      $movie_reservation_all_data = \Drupal::request()->query->get('movie_reservation');

      $customer_name_validated = $movie_reservation_all_data['customer_name_validated'];
      $movie_id_for_reservation = $movie_reservation_all_data['movie_id_for_reservation'];
      $day_for_reservation = $movie_reservation_all_data['day_for_reservation'];
      $air_info_id = $movie_reservation_all_data['air_info_id'];

      // user inserted his name correctly and clicked final button for movie reservation on specific day of the week
      if($customer_name_validated AND $movie_id_for_reservation AND $day_for_reservation){
        $successful_reservation = $this->reserve_movie_for_day($customer_name_validated, $movie_id_for_reservation, $day_for_reservation, $air_info_id);
      }
    }
    return $successful_reservation;
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
    if($title){
      $allNodeIds = \Drupal::entityQuery('node')
        ->condition('type', 'movies')
        ->condition('title', $title, 'CONTAINS' )
        ->execute();
      return Node::loadMultiple($allNodeIds);
    }
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
   * @todo save categories as JSON or categories ID in stead of str1/str2/ ...
   * @todo No check exists if query for reservation was successfull of not - conn might break or something.
   *
   * @param $customer_name
   * @param $movie_nid
   * @param $day
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function reserve_movie_for_day($customer_name, $movie_nid, $day, $air_info_id)
  {
    $movie = Node::load($movie_nid);
    $movie_name_arr = $movie->title->getValue();
    $movie_name_str = $movie_name_arr[0]['value'];
    $movie_categories = $movie->field_category->getValue();
    $all_movie_categories = $this->get_all_movie_categories();
    $str_of_all_cat_of_this_movie = '';

    // nznm sto sam ovo radio kad je useless
    $air_date = '';

    // if statement cant be on start of the function because title ins't loaded yet, only movie tile_id :/
    // must be '> 0' because some duplicate rows already exist in table. Once table is truncated, this can be changed to != 1
    if($this->check_if_customer_reserved_movie_for_day($customer_name, $movie_name_str, $day) > 0){
      return 'already_reserved';
    }

    foreach($movie_categories as $one_cat_of_movie){
      foreach($all_movie_categories as $all_cat_of_movies){
        if($one_cat_of_movie['target_id'] == $all_cat_of_movies['tid']){
          /* movie categories are saved in table as: categ1/categ2/categ3/ */
          $str_of_all_cat_of_this_movie .= $all_cat_of_movies['name'] . '/';
        }
      }
    }

    // first check if there are any remaining tickets available just in case user didnt reload page in a while
    $current_num_of_remaining_tickets = $this->get_num_remaining_tickets_for_airing($air_info_id);
    if($current_num_of_remaining_tickets > 0){
      // Update number of remaining tickets to -1
      $this->decrement_num_remaining_tickets_after_reservation($air_info_id, $current_num_of_remaining_tickets);
      $this->save_reservation_in_db($day, $movie_name_str, $str_of_all_cat_of_this_movie, $customer_name, $air_date, $air_info_id);
      return 'recorded';
    }
    if ($current_num_of_remaining_tickets == 0){
      return 'no_avail_tickets';
    }
    if ($current_num_of_remaining_tickets < 0){
      return 'Number of tickets is negative xd something in code doesnt work and I would like to know what!';
    }
    return '';
  }

  /**
   * Inserts record of customer reservation for movie.
   *
   * @param $day
   * @param $movie_name_str
   * @param $str_of_all_cat_of_this_movie
   * @param $customer_name
   * @param $air_date
   * @param $air_info_id
   */
  private function save_reservation_in_db($day, $movie_name_str, $str_of_all_cat_of_this_movie, $customer_name, $air_date, $air_info_id)
  {
    $connection = \Drupal::service('database');
    $result = $connection->insert('reservations')
      ->fields([
        'day_of_reservation' => $day,
        'reserved_movie_name' => $movie_name_str,
        'reserved_movie_genre' => $str_of_all_cat_of_this_movie,
        'customer_name' => $customer_name,
        'movie_air_date' => $air_date,
        'field_movie_air_info_target_id' => $air_info_id,
      ])
      ->execute();
  }

  /**
   * Decrements number of tickets for selected movie airing after save_reservation_in_db().
   *
   * @param $air_info_id
   * @param $tickets_remaining
   */
  private function decrement_num_remaining_tickets_after_reservation($air_info_id, $tickets_remaining)
  {
    $tickets_remaining = $tickets_remaining - 1;
    $connection = \Drupal::service('database');
    $num_updated = $connection->update('paragraph__field_num_remain_tickets')
      ->fields([
        'field_num_remain_tickets_value' => $tickets_remaining,
      ])
      ->condition('entity_id', $air_info_id, '=')
      ->execute();
  }

  /**
   * Returns number of remeining tickets for selected movie airing.
   *
   * @param $air_info_id
   * @return mixed
   */
  private function get_num_remaining_tickets_for_airing($air_info_id)
  {
    $database = \Drupal::database();
    $sql = "SELECT field_num_remain_tickets_value FROM paragraph__field_num_remain_tickets WHERE entity_id = '{$air_info_id}'";
    $query = $database->query($sql);
    $result = $query->fetchAll();
    return $result[0]->field_num_remain_tickets_value;
  }

  /**
   * Check if customer has already reserved same movie for specific day.
   *
   * Better way (or would it) would be to not show option for reservation on movie and day which customer already reserver.
   * Or even better, first check if user reserved a movie, if he did, show "you have already reserved for this movie, wanna unreserve?"
   *
   * @param $customer
   * @param $movie
   * @param $day
   * @return int
   */
  private function check_if_customer_reserved_movie_for_day($customer, $movie, $day){
    // need fetch num rows...
    $database = \Drupal::database();
    $sql = "SELECT * FROM reservations WHERE customer_name = '{$customer}' AND reserved_movie_name = '{$movie}' AND day_of_reservation = '{$day}'";
    $query = $database->query($sql);
    $result = $query->fetchAll();
    return count($result);
  }

  /**
   * Returns string day of the week (e.g. 'Monday', 'Friday') from datetime ($date)
   *
   * @param $date
   * @return false|string
   */
  public function get_day_of_week_from_date($date){
    $unixTimestamp = strtotime($date);
    return date("l", $unixTimestamp);
  }

  /**
   * Returns hash table of hall terms (hall_term_id => name_of_hall).
   * Passes this return to twig extension.
   *
   * e.g. [
   *  '1' => 'Hall 1',
   *  '2' => 'Hall 2',
   * ]
   * @return array
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function get_all_hall_terms(){
    $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree('cinema_halls');
    $hash_tid_name = [];
    foreach($terms as $term){
      $hash_tid_name[ $term->tid ] = $term->name;
    }
    return $hash_tid_name;
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




