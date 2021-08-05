<?php
namespace Drupal\all_movies_listing\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\Element\Datetime;
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
    $reservation_status = $this->user_submitted_reserve_movie_for_day();

    return [
      '#theme' => 'movie-reservation',
      '#pageTitle' => 'Welcome to our movie reservation page',
      '#movie_categories' => $this->get_all_movie_categories(),
      '#movies' => $this->which_movies(),
      '#reservation_status' => $reservation_status,
      '#halls' => $this->get_all_hall_terms(),
      '#movie_basic_data' => $this->get_current_movie_basic_data(),
    ];
  }

  private function which_movies()
  {
    // no filters chosen
    if( empty($this->get_category_filters()) ){
      return $this->get_all_movie_nodes();
    }

    // user chose categories filter
    return $this->get_movies_by_categories();
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
  private function user_submitted_reserve_movie_for_day()
  {
    $reservation_status = '';
    if(\Drupal::request()->request->get('movie_reservation') !== null){
      $movie_reservation_all_data = \Drupal::request()->request->get('movie_reservation');

      $customer_name_validated  = $movie_reservation_all_data['customer_name_validated'];
      $movie_id_for_reservation = $movie_reservation_all_data['movie_id_for_reservation'];
      $day_for_reservation      = $movie_reservation_all_data['day_for_reservation'];
      $air_info_id              = $movie_reservation_all_data['air_info_id'];

      if( empty($customer_name_validated) ){
        return 'no_customer_name';
      }

      // user inserted his name correctly and clicked final button for movie reservation
      if($customer_name_validated && $movie_id_for_reservation && $day_for_reservation){
        $reservation_status = $this->reserve_movie_for_day(
          $customer_name_validated,
          $movie_id_for_reservation,
          $day_for_reservation,
          $air_info_id
        );
      }
    }
    return $reservation_status;
  }

  private function set_current_movie_basic_data($movie_title, $movie_datetime, $hall_name)
  {
    $this->movie_reservation_basic_data = [
      'title' => $movie_title,
      'date' => $movie_datetime,
      'hall' => $hall_name,
    ];
  }

  private function get_current_movie_basic_data()
  {
    if(isset($this->movie_reservation_basic_data)){
      return $this->movie_reservation_basic_data;
    } else {
      return [];
    }
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
   * @todo No check exists if query for reservation was successful of not - conn might break or something.
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

    $paragraph_info = $this->get_paragraph_from_id($air_info_id);
    $movie_air_datetime = $paragraph_info->field_time_of_airing->getValue();
    $movie_air_datetime = $movie_air_datetime[0]['value'];

    $movie_air_hall = $paragraph_info->field_hall->getValue();
    $hall_id = $movie_air_hall[0]['target_id'];
    $hall_name = $this->get_hall_name($hall_id);

    $this->set_current_movie_basic_data($movie_name_str, $movie_air_datetime, $hall_name);

    // check if user have any other reservations at that time, but that still doesn't solve this problem:
    //    user reserved some movie. That movie lasts for some time, and in that time, another movie (Movie X) starts. User wants to reserve for movie X. And this creates conflict.
    // thats why I need to [add movie duration in content - done] and add that value to movie_start. That means, if user already have a reservation in db, and wants to reserve Movie X which starts whil,
    // first movie lasts, this should create a "warning message".

    $movie_duration_arr = $paragraph_info->field_movie_duration->getValue();
    $movie_duration   = $movie_duration_arr[0]['duration'];
    $movie_in_seconds = $movie_duration_arr[0]['seconds'];

    $movie_ends =  $this->movie_ends($movie_air_datetime, $movie_in_seconds);

    // if statement cant be on start of the function because title ins't loaded yet, only movie tile_id :/
    // must be '> 0' because some duplicate rows already exist in table. Once table is truncated, this can be changed to != 1
    if($this->check_if_customer_reserved_movie_on_air($customer_name, $movie_name_str, $air_info_id) > 0){
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

    switch ($current_num_of_remaining_tickets){
      case $current_num_of_remaining_tickets > 0:
        $this->decrement_num_remaining_tickets($air_info_id, $current_num_of_remaining_tickets);
        $this->save_reservation_in_db(
          $day,
          $movie_name_str,
          $str_of_all_cat_of_this_movie,
          $customer_name,
          $movie_air_datetime,
          $air_info_id
        );
        return 'recorded';

      case $current_num_of_remaining_tickets == 0:
        return 'no_avail_tickets';

      case $current_num_of_remaining_tickets < 0:
        return 'num_tickets_negative';

      case $current_num_of_remaining_tickets === NULL:
        return 'num_tickets_null';

        default:
          return '';
    }
  }

  private function movie_ends($start, $movie_in_seconds)
  {
    $str_time = $start . " + " . $movie_in_seconds . " second";
    $newtimestamp = strtotime($str_time);
    return date('Y-m-d H:i:s', $newtimestamp);
  }

  /**
   * Returns paragraph from id.
   *
   * @param $air_info_id
   * @return \Drupal\Core\Entity\EntityBase|\Drupal\Core\Entity\EntityInterface|Paragraph|null
   */
  private function get_paragraph_from_id($air_info_id)
  {
    return Paragraph::load((string)$air_info_id);
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
  private function save_reservation_in_db($day, $movie_name_str, $str_of_all_cat_of_this_movie, $customer_name, $movie_air_datetime, $air_info_id)
  {
    $connection = \Drupal::service('database');
    $result = $connection->insert('reservations')
      ->fields([
        'day_of_reservation' => $day,
        'reserved_movie_name' => $movie_name_str,
        'reserved_movie_genre' => $str_of_all_cat_of_this_movie,
        'customer_name' => $customer_name,
        'movie_air_date' => $movie_air_datetime,
        'field_movie_air_info_target_id' => $air_info_id,
      ])
      ->execute();
  }

  /**
   * Decrements number of tickets for selected movie airing.
   *
   * @param $air_info_id
   * @param $tickets_remaining
   */
  private function decrement_num_remaining_tickets($air_info_id, $tickets_remaining)
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
   * Returns number of remaining tickets for selected movie airing.
   *
   * @param $air_info_id
   * @return mixed
   */
  private function get_num_remaining_tickets_for_airing($air_info_id = NULL)
  {
    if($air_info_id == NULL){
      return '';
    }
    $database = \Drupal::database();
    $sql = "SELECT field_num_remain_tickets_value FROM paragraph__field_num_remain_tickets WHERE entity_id = '{$air_info_id}'";
    $query = $database->query($sql);
    $result = $query->fetchAll();
    return $result[0]->field_num_remain_tickets_value;
  }

  /**
   * Check if customer has already reserved same movie.
   *
   * @param $customer
   * @param $movie
   * @param $day
   * @return int
   */
  private function check_if_customer_reserved_movie_on_air($customer, $movie, $movie_air_info_id){
    // need fetch num rows...
    $database = \Drupal::database();
    $sql = "SELECT * FROM reservations WHERE customer_name = '{$customer}' AND reserved_movie_name = '{$movie}' AND field_movie_air_info_target_id = '{$movie_air_info_id}'";
    $query = $database->query($sql);
    $result = $query->fetchAll();
    return count($result);
  }

  private function check_if_customer_reserved_two_movies_at_same_time($customer, $movie, $day){
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

  private function get_hall_name($hall_id)
  {
    $all_hall_terms = $this->get_all_hall_terms();
    return $all_hall_terms[$hall_id];
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




