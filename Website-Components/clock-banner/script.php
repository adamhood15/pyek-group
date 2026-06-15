<?php
$now = new DateTime('2025-06-06 18:01:00', new DateTimeZone('America/Los_Angeles'));
$current_date = $now->format('Y-m-d');
$now_time = $now->format('H:i');

// Testing out the variables (force for test)
$current_date = '2025-06-06';
$now_time = '18:01';

$need_next_open_date = false;
$found_next_open_date = false;
$park = 'Cowabunga Canyon';
$today_data = null;
$next_open_data = null;
$status = 'closed_no_data';

if (have_rows('canyon_calendar_day', 'option')) :
    while (have_rows('canyon_calendar_day', 'option')) : the_row();

        $date = get_sub_field('date');
        $weather_status = get_sub_field('weather_status');
        $open_time = get_sub_field('open_time');
        $close_time = get_sub_field('close_time');

        // Here we respect the need_another_open_time field
        $need_another_open_time = get_sub_field('need_another_open_time'); // expected values: 'yes' or 'no'

        if ($need_another_open_time === 'yes') {
            $open_time_2 = get_sub_field('open_time_2');
            $close_time_2 = get_sub_field('close_time_2');
        } else {
            // force them to empty if not needed
            $open_time_2 = '';
            $close_time_2 = '';
        }

        // === PROCESS TODAY'S DATA ===
        if ($date === $current_date) {

            $today_data = [
                'date' => $date,
                'open_time' => $open_time,
                'close_time' => $close_time,
                'open_time_2' => $open_time_2,
                'close_time_2' => $close_time_2,
                'weather_status' => $weather_status
            ];

            // Optional debug
            echo '<pre>TODAY DATA: ';
            print_r($today_data);
            echo '</pre>';

            // Determine today's status
            if (
                ($now_time >= $open_time && $now_time <= $close_time) ||
                (!empty($open_time_2) && !empty($close_time_2) && $now_time >= $open_time_2 && $now_time <= $close_time_2)
            ) {
                $status = 'open_now';
            } elseif (
                ($now_time < $open_time) ||
                (!empty($open_time_2) && $now_time < $open_time_2)
            ) {
                $status = 'opens_later';
            } else {
                $status = 'closed_today';
            }

            // In all cases → after today is processed, start looking for next open date
            $need_next_open_date = true;
        }

        // === PROCESS NEXT OPEN DATE ===
        elseif ($need_next_open_date && !$found_next_open_date) {

            // If this day has opening hours → treat as next open day
            if (!empty($open_time) || !empty($open_time_2)) {
                $next_open_data = [
                    'date' => $date,
                    'open_time' => $open_time,
                    'close_time' => $close_time
                ];

                // Optional debug
                echo '<pre>NEXT OPEN DATA: ';
                print_r($next_open_data);
                echo '</pre>';

                $found_next_open_date = true; // stop looking after this
                // Optional: break here if performance matters (once you find next open, no need to keep looping)
                // break;
            }
        }

    endwhile;
else :
    echo 'No data found';
endif;

// Output data for JS
echo '<div id="status-data" ';
echo 'data-park="' . esc_attr($park) . '" ';
echo 'data-status="' . esc_attr($status) . '" ';
echo 'data-now="' . esc_attr($now->format('Y-m-d H:i:s')) . '" ';

if ($today_data) {
    echo 'data-today-date="' . esc_attr($today_data['date']) . '" ';
    echo 'data-today-open="' . esc_attr($today_data['open_time']) . '" ';
    echo 'data-today-close="' . esc_attr($today_data['close_time']) . '" ';
    echo 'data-today-weather="' . esc_attr($today_data['weather_status']) . '" ';
}

if ($next_open_data) {
    echo 'data-next-date="' . esc_attr($next_open_data['date']) . '" ';
    echo 'data-next-open="' . esc_attr($next_open_data['open_time']) . '" ';
    echo 'data-next-close="' . esc_attr($next_open_data['close_time']) . '" ';
}

echo '></div>';
?>