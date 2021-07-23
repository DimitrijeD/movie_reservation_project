<?php

namespace Drupal\all_movies_listing\Extension;

use Drupal\paragraphs\Entity\Paragraph;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class MovieAvailabilityExtension extends AbstractExtension
{
  public function getName()
  {
    return 'movie_availability_extension';
  }

  public function getFunctions()
  {
    return [
      new TwigFunction("get_movie_availability", [$this, "get_movie_availability"] ),
    ];
  }

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
    $days_in_week = [
      'monday'    => 'field_monday',
      'tuesday'   => 'field_tuesday',
      'wednesday' => 'field_wednesday',
      'thursday'  => 'field_thursday',
      'friday'    => 'field_friday',
    ];

    foreach($days_in_week as $day => $field)
    {
      $text = $movie_paragraph->{$field}->getValue();
      if($text != null)
      {
        if ($text[0]['value'])
        {
          $available_days[$day] = $text[0]['value'];
        }
      }
    }

    if(!empty($available_days))
    {
      return $available_days;
    } else {
      return false;
    }
  }

}
