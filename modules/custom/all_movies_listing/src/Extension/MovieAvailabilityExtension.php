<?php

namespace Drupal\all_movies_listing\Extension;

use Drupal\Core\Datetime\Element\Datetime;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class MovieAvailabilityExtension extends AbstractExtension
{
  private $days_in_week = [
    'monday'    => 'field_monday',
    'tuesday'   => 'field_tuesday',
    'wednesday' => 'field_wednesday',
    'thursday'  => 'field_thursday',
    'friday'    => 'field_friday',
  ];

  public function getName()
  {
    return 'movie_availability_extension';
  }

  public function getFunctions()
  {
    return [
      new TwigFunction("get_movie_availability", [$this, "get_movie_availability"]),
      new TwigFunction("get_day_of_week_from_date", [$this, "get_day_of_week_from_date"]),
      new TwigFunction("set_all_movie_categories", [$this, "set_all_movie_categories"]),
      new TwigFunction("rearange_movie_categories_structure", [$this, "rearange_movie_categories_structure"]),
      new TwigFunction("get_available_movie_data", [$this, "get_available_movie_data"]),
      new TwigFunction("get_all_para_info_data", [$this, "get_all_para_info_data"]),
      new TwigFunction("set_all_halls", [$this, "set_all_halls"]),
      new TwigFunction("check_if_airing_has_remaining_tickets", [$this, "check_if_airing_has_remaining_tickets"]),
      new TwigFunction("get_time_from_datetime", [$this, "get_time_from_datetime"]),
    ];
  }

  /**
   * @deprecated
   * Checks if movie is available for reservation.
   *
   * If movie is available for any day of the week, it will return those days, otherwise returns false.
   * It also checks if movie_paragraph is set because some movies might not have paragraph instantiated, in that case, returns false in order to show to user
   * that movie is not available for that day. This function can be replaced with entityQuery which only returns movies which are available for at at least one day.
   *
   * @param $movie
   * @return array|false
   */
  public function get_movie_availability($movie)
  {
    $paragraph = $movie->field_movie_paragraph->getValue();
    if(!$paragraph){
      return false;
    }
    $target_id = $paragraph[0]['target_id'];
    $movie_paragraph = Paragraph::load( $target_id );

    if(!$movie_paragraph){
      // movie hasn't been set to be available
      return false;
    }

    $available_days = [];

    foreach($this->days_in_week as $day => $field){
      $text = $movie_paragraph->{$field}->getValue();
      if($text != null){
        if ($text[0]['value']){
          $available_days[$day] = $text[0]['value'];
        }
      }
    }

    if(!empty($available_days)){
      return $available_days;
    }
    return false;
  }

  /**
   * Returns array of all times one movie is airing.
   *
   * @todo Checks if paragraph exists or not, are a complete mess. It is going to backfire sooner or later.
   * Each paragraph_info holds:
   *    date movie is airing,
   *    hall (term),
   *    number of available tickets,
   *    number of max tickets (const)
   * These values are checked for movie airing availability and prepared (formated) for display into individual arrays.
   * e.g.
   * return [
   *  'movie 1' airing 98 =>[
   *    'hall'              => 'Hall 1',
   *    'remaining_tickets' => '10',
   *    'day'               => 'Monday'
   *    'air_info_id'       => '98',
   *    'time'              => '19:00:00',
   *  ],
   *  'movie 1' airing 99 =>[
   *    ...
   *  ]
   * ]
   *
   * @param $movie
   * @return array|null
   */
  public function get_available_movie_data($movie)
  {
    // some movies might not even have set movie_air_info paragraph, in which case movie can't be reserved
    if( !empty($movie->field_movie_air_info) ){
      $paragraph_info = $movie->field_movie_air_info->getValue();
    } else {
      return NULL;
    }

    // movie is not airing
    // there might be an issue in the future, if movie availability is dependent on weekly schedule
    // in taht case just add functionality here
    if(!$paragraph_info){
      return NULL;
    }

    // array of all times (paragraphs: 'field_movie_air_info') movie is airing in a week
    $arr_of_para_info = [];
    foreach($paragraph_info as $value){
      $arr_of_para_info[] =  Paragraph::load( $value['target_id'] );
    }

    // foreach time movie is airing, prepare relevant data
    $airing_data = [];
    foreach($arr_of_para_info as $movie_info_para){
      $airing_data[] = $this->get_all_para_info_data($movie_info_para);
    }
    $airing_data = $this->check_if_airing_has_remaining_tickets($airing_data);
    return $airing_data;
  }

  /**
   * Child function of get_available_movie_data() used for preparing airing (paragraph) data.
   *
   * @param $para_info
   * @return array
   */
  public function get_all_para_info_data($para_info)
  {
    $hall = $para_info->field_hall->getValue();
    $hall_name_str = $this->all_halls[$hall[0]['target_id']];

    $remaining_tickets = $para_info->field_num_remain_tickets->getValue();
    $remaining_tickets_int = $remaining_tickets[0]['value'];

    $datetime = $para_info->field_time_of_airing->getValue();
    if($datetime) {
      $datetime = $datetime[0]['value'];

      $day = $this->get_day_of_week_from_date($datetime);
      $time = $this->get_time_from_datetime($datetime);
    } else {
      // This can' stay like this !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
      $day = 'Sunday';
      $time = 'Time of airing isn\'t set and im too lazy to fix this';
      // !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
    }

    $air_info_id = $para_info->id->getValue();
    $air_info_id = $air_info_id[0]['value'];

    return [
      'hall' => $hall_name_str,
      'remaining_tickets' => $remaining_tickets_int,
      'day' => $day,
      'air_info_id' => $air_info_id,
      'time' => $time,
    ];
  }

  /**
   * Returns string day of the week (e.g. 'Monday', 'Friday') from datetime ($date)
   *
   * @param $date
   * @return false|string
   */
  public function get_day_of_week_from_date($datetime)
  {
    $unixTimestamp = strtotime($datetime);
    return date("l", $unixTimestamp);
  }

  /**
   * Returns time (e.g. '19:00:00') from datetime format
   *
   * @param $datetime
   * @return false|string
   */
  public function get_time_from_datetime($datetime){
    $date_str = strtotime($datetime);
    return date('H:i:s', $date_str);
  }

  /**
   * @param $all_movie_categories
   * @return string
   */
  public function set_all_movie_categories($all_movie_categories)
  {
    $this->movie_categories = $this->rearange_movie_categories_structure($all_movie_categories);
    return '';
  }

  /**
   * @param $halls
   * @return string
   */
  public function set_all_halls($halls)
  {
    $this->all_halls = $halls;
    return '';
  }

  /**
   * Returns hash table of category terms (category_term_id => name_of_category)
   *
   * e.g. [
   *  '1' => 'Comedy',
   *  '2' => 'Horror',
   * ]
   *
   * @param $all_movie_categories
   * @return array
   */
  private function rearange_movie_categories_structure($all_movie_categories)
  {
    $hash_tid_term = [];
    foreach($all_movie_categories as $value){
      $hash_tid_term[ $value['tid'] ] = $value['name'];
    }
    return $hash_tid_term;
  }

  /**
   * Returns only those movie airing (paragraph) which have more than 0 remaining tickets.
   *
   * @param $airing_data
   * @return array
   */
  private function check_if_airing_has_remaining_tickets($airing_data)
  {
    $airings_with_avail_tickets = [];
    foreach($airing_data as $airing){
      if( (int)$airing['remaining_tickets'] > 0 ){
        $airings_with_avail_tickets[] = $airing;
      }
    }
    return $airings_with_avail_tickets;
  }

}
