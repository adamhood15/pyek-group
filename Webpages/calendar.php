<?php
// === Load ACF Calendar Data ===
$calendar_days = get_field('austin_calendar_days', 'option');
$calendar_data = [];

if ($calendar_days) {
    foreach ($calendar_days as $day) {
        $date = $day['date']; // Format: Y-m-d
        $calendar_data[$date] = $day;
    }
}

// === Load Events from "austin" Category ===
$args = [
    'post_type'      => 'events',
    'posts_per_page' => -1,
    'category_name'  => 'austin',
];

$events_query = new WP_Query($args);
$events_by_date = [];

//Special Events Loop
if ($events_query->have_posts()) {
    while ($events_query->have_posts()) {
        $events_query->the_post();

        $event_name  = get_field('event_name');
        $event_url   = get_permalink();
        $event_time  = get_field('event_time_stamp');
        $event_image = null;
        $event_images = get_field('event_images');
        $image_id = $event_images['4x3_image'] ?? null;

        if ($image_id) {
            $image_src = wp_get_attachment_image_src($image_id, 'medium');
            $event_image = $image_src[0] ?? '';
        }

        $start_date = $event_time['start_date'] ?? '';
        $end_date   = $event_time['end_date'] ?? '';
        $start_time = $event_time['start_time'] ?? '';
        $end_time   = $event_time['end_time'] ?? '';

        if ($start_date) {
            $start = new DateTime($start_date);
            $end = $end_date ? new DateTime($end_date) : $start;

            $period = new DatePeriod(
                $start,
                new DateInterval('P1D'),
                (clone $end)->modify('+1 day')
            );

            foreach ($period as $date) {
                $formatted_date = $date->format('Y-m-d');
                $events_by_date[$formatted_date][] = [
                    'name'       => $event_name,
                    'image'      => $event_image,
                    'url'        => $event_url,
                    'start_time' => $start_time ? date('g:i A', strtotime($start_time)) : '',
                    'end_time'   => $end_time ? date('g:i A', strtotime($end_time)) : '',
                ];
            }
        }
    }
    wp_reset_postdata();
}

// === Render Monthly Calendar Grid ===
function render_month_grid($month, $year, $calendar_data, $events_by_date, $index) {
    $first_day     = strtotime("$year-$month-01");
    $days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);
    $start_day     = date('w', $first_day);
    $is_active     = $index === 0 ? 'active' : '';

    // === Creates skeleton for the calendar ===
    echo "<div class='month-wrapper $is_active' data-month-index='$index'>";
    echo "<div class='month-header-container'>";
    echo '<svg class="calendar-nav calendar-prev"><use xlink:href="#FontAwesomeicon-arrow-circle-left"></use></svg>';    
    echo "<h2 class='calendar-month-header'>" . date('F Y', $first_day) . "</h2>";
    echo '<svg class="calendar-nav calendar-next"><use xlink:href="#FontAwesomeicon-arrow-circle-right"></use></svg>';    

    echo "</div>";
    echo "<table class='calendar-table'>";
    echo "<tr>
        <th class='weekday-header'>Sun</th><th class='weekday-header'>Mon</th><th class='weekday-header'>Tue</th><th class='weekday-header'>Wed</th>
        <th class='weekday-header'>Thu</th><th class='weekday-header'>Fri</th><th class='weekday-header'>Sat</th>
    </tr><tr>";

    $cell = 0;

    for ($i = 0; $i < $start_day; $i++) {
        echo "<td class='empty'></td>";
        $cell++;
    }

    // === Pulls info from the acf fields
    for ($day = 1; $day <= $days_in_month; $day++) {
        $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
        $info = $calendar_data[$date] ?? null;
        $weather_status = $info['weather_status'] ?? null;
        $rainy_day_guarantee = $info['rainy_day_guarantee'] ?? null;
        $weather_status_class = $weather_status ? strtolower(str_replace(' ', '-', $weather_status)) : '';
        $weather_note = $info['weather_note'] ?? null;
        $events_today = $events_by_date[$date] ?? [];

      //Overrides the 'Open' status if weather closure is not normal  
      $status_class = 'closed';
        if ($info) {
            $status_class = strtolower($info['status']);
            if (!empty($info['weather_status']) && $info['weather_status'] !== 'Normal') {
                $status_class = 'closed';
            }
        }

        // Prepare default values
        $notes = $info['notes'] ?? '';
        $open_time = '';
        $close_time = '';
        $open_time_2 = '';
        $close_time_2 = '';
        
        if ($info) {
            $open_time  = !empty($info['open_time']) ? date('g:i A', strtotime($info['open_time'])) : '';
            $close_time = !empty($info['close_time']) ? date('g:i A', strtotime($info['close_time'])) : '';
        }

        if ($info && $info['need_another_open_time'] === 'Yes') {
            $open_time_2  = $info['open_time_2']  ? date('g:i A', strtotime($info['open_time_2'])) : '';
            $close_time_2 = $info['close_time_2'] ? date('g:i A', strtotime($info['close_time_2'])) : '';
        }

        

        $special_event_info = htmlspecialchars(json_encode(array_values($events_today)), ENT_QUOTES, 'UTF-8');

        echo "<td data-date='$date' data-open-time='$open_time' data-close-time='$close_time' data-open-time-2='$open_time_2' data-close-time-2='$close_time_2' data-notes='$notes' data-special-event='$special_event_info' data-weather-status='$weather_status' data-weather-note='$weather_note' data-rainy-day-guarantee='$rainy_day_guarantee' class='day-cell {$status_class} {$weather_status_class}'>";
        echo "<div class='day-inner'>";
        echo "<p class='day-number'><strong>$day </strong><span class='park-status'>$status_class</span></p>";

        // 1. Park Status & Hours
        if ($info) {
            echo '<div class="icon-container">';

            //Only displays the clock if weather is 'Normal'
            if (
                strtolower($info['status']) !== 'closed' &&
                (!isset($info['weather_status']) || $info['weather_status'] === 'Normal')
            ) {
                echo '<svg class="hours-icon"><use xlink:href="#FontAwesomeicon-clock-o"></use></svg>';
            }
        
            if ($events_today) {
                echo '<svg class="event-icon"><use xlink:href="#FontAwesomeicon-star"></use></svg>';
            }
        
            if (!empty($info['notes'])) {
                echo '<svg class="bullhorn-icon"><use xlink:href="#FontAwesomeicon-bullhorn"></use></svg>';
            }

            if ($weather_status != 'Normal') {
                echo '<svg class="bolt-icon"><use xlink:href="#FontAwesomeicon-bolt"></use></svg>';
            }

            if ($rainy_day_guarantee === 'Yes') {
                echo '<svg class="shield-icon"><use xlink:href="#FontAwesomeicon-shield"></use></svg>';
            }
        
            echo "</div>";
        
            if (strtolower($status_class) !== 'closed') {
                echo "<p class='calendar-details hours'><strong>Hours:</strong></p>";
                if (!empty($open_time) && !empty($close_time)) {
                    echo "<p class='calendar-details hours time'>{$open_time} – {$close_time}</p>";
                }
        
                if (!empty($open_time_2) && !empty($close_time_2)) {
                    echo "<p class='calendar-details hours time'>{$open_time_2} – {$close_time_2}</p>";
                }
            }
        }

        // 2. Events
        foreach ($events_today as $i => $event) {
            echo "<div class='event-name-wrapper'>";
            echo "<p class='calendar-details event-name'>" . esc_html($event['name']) . "</p>";
            if ($event['start_time'] && $event['end_time']) {
                echo "<p class='calendar-details hours'>{$event['start_time']} – {$event['end_time']}</p>";
            }
            echo "</div>";

        }

        echo "</div></td>";
        $cell++;

      
        if ($cell % 7 === 0 && $day !== $days_in_month) {
            echo "</tr><tr>";
        }
    }

    while ($cell % 7 !== 0) {
        echo "<td class='empty'></td>";
        $cell++;
    }

    echo "</tr></table></div>";
}

// === Output Calendar ===
$current_year = date('Y');
$months = range(4, 9); // April–September

//Dynamically sets the calendar to only display this month and 12 months in the future.
$start = new DateTime('first day of this month');
$months = [];

for ($i = 0; $i < 13; $i++) {
  $date = clone $start;
  $date->modify("+$i months");
  $months[] = [
    'month' => (int) $date->format('m'),
    'year' => (int) $date->format('Y')
  ];
}

echo '<div class="calendar-nav">';

echo '</div>';

echo '<div class="calendar-container">';
foreach ($months as $i => $month_data) {
    render_month_grid($month_data['month'], $month_data['year'], $calendar_data, $events_by_date, $i);
}
echo '</div>';

echo '<div id="calendar-day" class="calendar-day">
    <div id="calendar-day-content-container" class="calendar-day-content">
            <div class="calendar-day-top-container"> 
            <h2 id="modal-date" class="calendar-day-title"></h2>
            <div class="calendar-day-hours-container">
                <svg class="hours-icon" id="day-display-hours-icon"><use xlink:href="#FontAwesomeicon-clock-o"></use></svg>
                <p class="calendar-day-hours" id="modal-hours"></p>
            </div>
        </div>
            <div id="weather-closure-message" class="weather-message hide">
              <svg class="bolt-icon"><use xlink:href="#FontAwesomeicon-bolt"></use></svg>
              <p>The park has been CLOSED for the day due to inclement weather.</p>
            </div>
            <div id="rainy-day-guarantee-message" class="rainy-day-message hide">
              <svg class="shield-icon"><use xlink:href="#FontAwesomeicon-shield"></use></svg>
              <p> Rainy Day Guarantee</p>
            </div>
            <div id="calendar-day-notes-container" class="calendar-day-notes-container">
                <svg id="calendar-day-note-icon" class="note-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" width="18" height="18">
                    <!--!Font Awesome Free 6.7.2 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2025 Fonticons, Inc.-->
                    <path fill="#ff0482" d="M480 32c0-12.9-7.8-24.6-19.8-29.6s-25.7-2.2-34.9 6.9L381.7 53c-48 48-113.1 75-181 75l-8.7 0-32 0-96 0c-35.3 0-64 28.7-64 64l0 96c0 35.3 28.7 64 64 64l0 128c0 17.7 14.3 32 32 32l64 0c17.7 0 32-14.3 32-32l0-128 8.7 0c67.9 0 133 27 181 75l43.6 43.6c9.2 9.2 22.9 11.9 34.9 6.9s19.8-16.6 19.8-29.6l0-147.6c18.6-8.8 32-32.5 32-60.4s-13.4-51.6-32-60.4L480 32zm-64 76.7L416 240l0 131.3C357.2 317.8 280.5 288 200.7 288l-8.7 0 0-96 8.7 0c79.8 0 156.5-29.8 215.3-83.3z"></path>
                </svg>
                <p id="calendar-day-notes" class="calendar-modal-notes"></p>
            </div>
           
            <div id="event-display" class="calendar-event-display"></div>
        </div>
</div>';
?> 