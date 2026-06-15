<?php

  $today = date("Y-m-d");

  if (have_rows('calendar', 'option')):
    while(have_rows('calendar', 'option')): the_row();

      $date = get_sub_field('date');

      if ($date == $today) :

        $waterpark = get_sub_field('waterpark');
        $funpark   = get_sub_field('fun_park');
        $notes     = get_sub_field('notes');

        echo '<div id="hours-data-container"';

        echo ' data-wp-status="'       . esc_attr($waterpark['status'])                        . '"';
        echo ' data-wp-open-time="'    . esc_attr($waterpark['open_time'])                     . '"';
        echo ' data-wp-close-time="'   . esc_attr($waterpark['close_time'])                    . '"';
        echo ' data-wp-open-time-2="'  . esc_attr($waterpark['open_time_2'])                   . '"';
        echo ' data-wp-close-time-2="' . esc_attr($waterpark['close_time_2'])                  . '"';
        echo ' data-wp-weather="'      . esc_attr($waterpark['enable_weather_guarantee'] ? '1' : '0') . '"';

        echo ' data-fp-status="'       . esc_attr($funpark['status'])                          . '"';
        echo ' data-fp-open-time="'    . esc_attr($funpark['open_time'])                       . '"';
        echo ' data-fp-close-time="'   . esc_attr($funpark['close_time'])                      . '"';
        echo ' data-fp-open-time-2="'  . esc_attr($funpark['open_time_2'])                     . '"';
        echo ' data-fp-close-time-2="' . esc_attr($funpark['close_time_2'])                    . '"';

        echo ' data-notes="'           . esc_attr($notes)                                      . '"';

        echo '></div>';
        break;
      endif;
    endwhile;

    if ($date != $today) :
      echo '<div id="hours-data-container"';
      echo ' data-wp-status="closed"';
      echo ' data-fp-status="closed"';
      echo '></div>';
    endif;
  else:
    echo '<div id="hours-data-container"';
    echo ' data-wp-status="closed"';
    echo ' data-fp-status="closed"';
    echo '></div>';
  endif;
?>
