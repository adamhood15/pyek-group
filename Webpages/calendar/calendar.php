<?php
/**
 * Austin Calendar — Renders a rolling 12-month calendar grid with park hours
 * and special events sourced from ACF options and a custom 'events' post type.
 */

// =========================================================
// DATA LOADING
// =========================================================

/**
 * Loads ACF calendar day data from the options page and indexes it by Y-m-d.
 *
 * @return array<string, array> Associative array keyed by date string.
 */
function load_calendar_data(): array {
    $calendar_days = get_field( 'austin_calendar_days', 'option' );
    if ( empty( $calendar_days ) ) {
        return [];
    }

    $indexed = [];
    foreach ( $calendar_days as $day ) {
        $date = $day['date'] ?? '';
        if ( $date ) {
            $indexed[ $date ] = $day;
        }
    }

    return $indexed;
}

/**
 * Retrieves the medium-size image URL from an ACF event image group field.
 *
 * @param array $event_images ACF image group containing a '4x3_image' attachment ID.
 * @return string Image URL, or empty string when unavailable.
 */
function get_event_image_url( array $event_images ): string {
    $image_id = $event_images['4x3_image'] ?? null;
    if ( ! $image_id ) {
        return '';
    }

    $image_src = wp_get_attachment_image_src( $image_id, 'medium' );
    return $image_src[0] ?? '';
}

/**
 * Formats a raw time string (H:i:s) into 12-hour display format (g:i A).
 *
 * Returns an empty string rather than a garbled value when input is absent,
 * so callers can safely use empty() for conditional rendering.
 *
 * @param string $time Raw time string from ACF.
 * @return string Formatted time, or empty string.
 */
function format_display_time( string $time ): string {
    if ( empty( $time ) ) {
        return '';
    }
    return date( 'g:i A', strtotime( $time ) );
}

/**
 * Assembles a normalized event entry array for storage in $events_by_date.
 *
 * Times are pre-formatted here so downstream rendering code never needs to
 * call date() or strtotime() — they are always display-ready strings.
 *
 * @param string $frequency   Event frequency label (e.g. 'Weekly').
 * @param string $event_name  Display name of the event.
 * @param string $event_image URL of the event thumbnail.
 * @param string $event_url   Permalink to the event post.
 * @param string $start_time  Raw start time string.
 * @param string $end_time    Raw end time string.
 * @return array Normalized event data keyed for the JS modal.
 */
function build_event_entry(
    string $frequency,
    string $event_name,
    string $event_image,
    string $event_url,
    string $start_time,
    string $end_time
): array {
    return [
        'frequency'  => $frequency,
        'name'       => $event_name,
        'image'      => $event_image,
        'url'        => $event_url,
        'start_time' => format_display_time( $start_time ),
        'end_time'   => format_display_time( $end_time ),
    ];
}

/**
 * Generates a DatePeriod spanning $start through $end (both inclusive).
 *
 * @param DateTime $start Start date.
 * @param DateTime $end   End date (included via +1 day on the exclusive boundary).
 * @return DatePeriod One entry per calendar day in the range.
 */
function build_date_range( DateTime $start, DateTime $end ): DatePeriod {
    return new DatePeriod(
        $start,
        new DateInterval( 'P1D' ),
        ( clone $end )->modify( '+1 day' )
    );
}

/**
 * Indexes an event into $events_by_date for each date in its recurring entries,
 * applying per-entry time overrides when present.
 *
 * Each entry in $recurring_dates may now carry its own start_time and end_time
 * (added to the ACF repeater to support events that recur on multiple dates but
 * run at different times on different dates). When those fields are populated they
 * take priority; otherwise the top-level event times are used as the fallback.
 *
 * @param array  $recurring_dates     Array of { start_date, end_date, start_time, end_time } entries.
 *                                    Dates are Ymd-formatted (ACF default); times are raw H:i:s strings.
 * @param array  $base_entry          Event entry built WITHOUT times — times are resolved per entry below.
 * @param string $fallback_start_time Raw top-level start_time to use when an entry has none.
 * @param string $fallback_end_time   Raw top-level end_time to use when an entry has none.
 * @param array  &$events_by_date     Reference to the date-keyed event map.
 */
function index_recurring_dates(
    array $recurring_dates,
    array $base_entry,
    string $fallback_start_time,
    string $fallback_end_time,
    array &$events_by_date
): void {
    foreach ( $recurring_dates as $entry ) {
        $start_raw = $entry['start_date'] ?? '';
        if ( empty( $start_raw ) ) {
            continue;
        }

        $start = DateTime::createFromFormat( 'Ymd', $start_raw );
        if ( ! $start ) {
            continue;
        }

        $end_raw = $entry['end_date'] ?? '';
        $end     = ( $end_raw ? DateTime::createFromFormat( 'Ymd', $end_raw ) : null ) ?: clone $start;

        // Per-entry times take priority over the top-level event times.
        // This supports events that recur across multiple dates at different times on each.
        $start_time = ! empty( $entry['start_time'] ) ? $entry['start_time'] : $fallback_start_time;
        $end_time   = ! empty( $entry['end_time'] )   ? $entry['end_time']   : $fallback_end_time;

        // Merge the resolved times into a copy of the base entry for this date range.
        $dated_entry = array_merge( $base_entry, [
            'start_time' => format_display_time( $start_time ),
            'end_time'   => format_display_time( $end_time ),
        ] );

        foreach ( build_date_range( $start, $end ) as $date ) {
            $events_by_date[ $date->format( 'Y-m-d' ) ][] = $dated_entry;
        }
    }
}

/**
 * Indexes a single-range event into $events_by_date for every day it spans.
 *
 * Used as the fallback path when no recurring_dates entries exist on the event.
 * Falls back to a single day when $end_date is absent.
 *
 * @param string $start_date      Start date (Y-m-d).
 * @param string $end_date        End date (Y-m-d), optional.
 * @param array  $event_entry     Pre-built entry from build_event_entry().
 * @param array  &$events_by_date Reference to the date-keyed event map.
 */
function index_date_range_event(
    string $start_date,
    string $end_date,
    array $event_entry,
    array &$events_by_date
): void {
    $start = new DateTime( $start_date );
    $end   = $end_date ? new DateTime( $end_date ) : clone $start;

    foreach ( build_date_range( $start, $end ) as $date ) {
        $events_by_date[ $date->format( 'Y-m-d' ) ][] = $event_entry;
    }
}

/**
 * Queries all events in the 'austin' category and maps them to calendar dates.
 *
 * Routing logic:
 *  - recurring_dates present → index_recurring_dates() (per-entry time overrides supported)
 *  - no recurring_dates, but start_date present → index_date_range_event() (top-level times used)
 *
 * @return array<string, array[]> Date-keyed array of event entries.
 */
function load_events_by_date(): array {
    $query = new WP_Query( [
        'post_type'      => 'events',
        'posts_per_page' => -1,
        'category_name'  => 'austin',
    ] );

    $events_by_date = [];

    if ( ! $query->have_posts() ) {
        return $events_by_date;
    }

    while ( $query->have_posts() ) {
        $query->the_post();

        $timestamp    = get_field( 'event_time_stamp' ) ?? [];
        $event_images = get_field( 'event_images' )     ?? [];

        $raw_start_time  = $timestamp['start_time'] ?? '';
        $raw_end_time    = $timestamp['end_time']   ?? '';
        $recurring_dates = $timestamp['recurring_dates'] ?? [];
        $start_date      = $timestamp['start_date']      ?? '';
        $end_date        = $timestamp['end_date']        ?? '';

        if ( ! empty( $recurring_dates ) ) {
            // Build a timeless base entry — each recurring entry resolves its own times
            // via per-entry fields, falling back to $raw_start_time / $raw_end_time.
            $base_entry = build_event_entry(
                $timestamp['frequency']   ?? '',
                get_field( 'event_name' ) ?? '',
                get_event_image_url( $event_images ),
                get_permalink(),
                '',
                ''
            );

            index_recurring_dates(
                $recurring_dates,
                $base_entry,
                $raw_start_time,
                $raw_end_time,
                $events_by_date
            );
        } elseif ( ! empty( $start_date ) ) {
            // Simple date range: no per-entry overrides exist, so top-level times apply directly.
            $event_entry = build_event_entry(
                $timestamp['frequency']   ?? '',
                get_field( 'event_name' ) ?? '',
                get_event_image_url( $event_images ),
                get_permalink(),
                $raw_start_time,
                $raw_end_time
            );

            index_date_range_event( $start_date, $end_date, $event_entry, $events_by_date );
        }
    }

    wp_reset_postdata();

    return $events_by_date;
}

// =========================================================
// CALENDAR RENDERING — HELPERS
// =========================================================

/**
 * Resolves the CSS status class for a calendar day, factoring in weather overrides.
 *
 * A weather closure always overrides an 'open' or 'limited' scheduled status so
 * the day renders as closed on the grid, matching its real operational state.
 *
 * @param array|null $info ACF day data, or null when no data exists for this date.
 * @return string CSS-safe status string: 'open', 'limited', or 'closed'.
 */
function resolve_status_class( ?array $info ): string {
    if ( empty( $info ) ) {
        return 'closed';
    }

    $status         = strtolower( $info['status'] ?? 'closed' );
    $weather_status = $info['weather_status'] ?? '';

    // Non-normal weather overrides the scheduled open/limited status.
    if ( ! empty( $weather_status ) && $weather_status !== 'Normal' ) {
        return 'closed';
    }

    return $status;
}

/**
 * Extracts and formats the open/close time pairs for a calendar day.
 *
 * The second time slot is only populated when the ACF opt-in flag is set,
 * supporting days with split operating hours (e.g. morning and evening sessions).
 *
 * @param array|null $info ACF day data.
 * @return array{ open_time: string, close_time: string, open_time_2: string, close_time_2: string }
 */
function extract_day_times( ?array $info ): array {
    $defaults = [ 'open_time' => '', 'close_time' => '', 'open_time_2' => '', 'close_time_2' => '' ];

    if ( empty( $info ) ) {
        return $defaults;
    }

    $times = [
        'open_time'    => format_display_time( $info['open_time']  ?? '' ),
        'close_time'   => format_display_time( $info['close_time'] ?? '' ),
        'open_time_2'  => '',
        'close_time_2' => '',
    ];

    // Second time slot is opt-in via an explicit ACF flag to avoid rendering empty hour rows.
    if ( ( $info['need_another_open_time'] ?? '' ) === 'Yes' ) {
        $times['open_time_2']  = format_display_time( $info['open_time_2']  ?? '' );
        $times['close_time_2'] = format_display_time( $info['close_time_2'] ?? '' );
    }

    return $times;
}

/**
 * Builds the HTML attribute string for a calendar day cell.
 *
 * All values are escaped via esc_attr() to prevent XSS. Event JSON is
 * double-encoded with ENT_QUOTES so it survives the attribute context safely.
 *
 * @param string $date         Y-m-d date string.
 * @param array  $info         ACF day data (may be empty array when day has no data).
 * @param array  $events_today Events scheduled for this date.
 * @param string $status       Resolved status string.
 * @param array  $times        Formatted time values from extract_day_times().
 * @return string Attribute string ready for direct insertion into a <td>.
 */
function build_day_cell_attributes(
    string $date,
    array $info,
    array $events_today,
    string $status,
    array $times
): string {
    $special_event_json = htmlspecialchars(
        json_encode( array_values( $events_today ) ),
        ENT_QUOTES,
        'UTF-8'
    );

    $attrs = [
        "data-status='"              . esc_attr( $status )                              . "'",
        "data-date='"                . esc_attr( $date )                                . "'",
        "data-open-time='"           . esc_attr( $times['open_time'] )                  . "'",
        "data-close-time='"          . esc_attr( $times['close_time'] )                 . "'",
        "data-open-time-2='"         . esc_attr( $times['open_time_2'] )                . "'",
        "data-close-time-2='"        . esc_attr( $times['close_time_2'] )               . "'",
        "data-notes='"               . esc_attr( $info['notes']               ?? '' )   . "'",
        "data-special-event='"       . $special_event_json                              . "'",
        "data-weather-status='"      . esc_attr( $info['weather_status']      ?? '' )   . "'",
        "data-weather-note='"        . esc_attr( $info['weather_note']        ?? '' )   . "'",
        "data-rainy-day-guarantee='" . esc_attr( $info['rainy_day_guarantee'] ?? '' )   . "'",
    ];

    return implode( ' ', $attrs );
}

/**
 * Renders the icon strip inside a day cell (clock, star, bullhorn, bolt, shield).
 *
 * Each icon is conditional — the clock is hidden for closed/weather days to avoid
 * implying hours that don't apply; the bolt replaces it for weather closures.
 * The clock is also suppressed when the park is open/limited but no times have been
 * entered in ACF — showing a clock with no hours beneath it would be misleading.
 *
 * @param array  $info                ACF day data.
 * @param array  $events_today        Events for this date.
 * @param string $weather_status      Raw weather status string.
 * @param string $rainy_day_guarantee 'Yes' or empty string.
 * @param array  $times               Formatted times from extract_day_times().
 */
function render_day_icons(
    array $info,
    array $events_today,
    string $weather_status,
    string $rainy_day_guarantee,
    array $times
): void {
    $is_open           = strtolower( $info['status'] ?? '' ) !== 'closed';
    $is_normal_weather = empty( $weather_status ) || $weather_status === 'Normal';
    // Only show the clock when times are actually configured — guards against 'limited'
    // days where open/close times were left blank in ACF.
    $has_times         = ! empty( $times['open_time'] ) || ! empty( $times['close_time'] );

    echo '<div class="icon-container">';

    // Clock only when park is open, weather is normal, and times are configured.
    if ( $is_open && $is_normal_weather && $has_times ) {
        echo '<svg class="hours-icon"><use xlink:href="#FontAwesomeicon-clock-o"></use></svg>';
    }

    if ( ! empty( $events_today ) ) {
        echo '<svg class="event-icon"><use xlink:href="#FontAwesomeicon-star"></use></svg>';
    }

    if ( ! empty( $info['notes'] ) ) {
        echo '<svg class="bullhorn-icon"><use xlink:href="#FontAwesomeicon-bullhorn"></use></svg>';
    }

    if ( ! empty( $weather_status ) && $weather_status !== 'Normal' ) {
        echo '<svg class="bolt-icon"><use xlink:href="#FontAwesomeicon-bolt"></use></svg>';
    }

    if ( $rainy_day_guarantee === 'Yes' ) {
        echo '<svg class="shield-icon"><use xlink:href="#FontAwesomeicon-shield"></use></svg>';
    }

    echo '</div>';
}

/**
 * Renders the hours block inside a day cell.
 *
 * Hidden when the day is closed, or when open/limited but no times are configured.
 * Suppressing the "Hours:" label when times are absent avoids an orphaned heading
 * on limited days where ACF times were intentionally left blank.
 *
 * @param string $status_class Resolved CSS status class.
 * @param array  $times        Formatted times from extract_day_times().
 */
function render_day_hours( string $status_class, array $times ): void {
    if ( $status_class === 'closed' ) {
        return;
    }

    // No times configured — nothing to render even if the park is open or limited.
    if ( empty( $times['open_time'] ) && empty( $times['close_time'] ) ) {
        return;
    }

    echo '<p class="calendar-details hours"><strong>Hours:</strong></p>';

    if ( ! empty( $times['open_time'] ) && ! empty( $times['close_time'] ) ) {
        echo '<p class="calendar-details hours time">'
            . esc_html( $times['open_time'] ) . ' – ' . esc_html( $times['close_time'] )
            . '</p>';
    }

    if ( ! empty( $times['open_time_2'] ) && ! empty( $times['close_time_2'] ) ) {
        echo '<p class="calendar-details hours time">'
            . esc_html( $times['open_time_2'] ) . ' – ' . esc_html( $times['close_time_2'] )
            . '</p>';
    }
}

/**
 * Renders the list of special event names and times inside a day cell.
 *
 * @param array $events_today Array of event entries for this date.
 */
function render_day_events( array $events_today ): void {
    foreach ( $events_today as $event ) {
        echo '<div class="event-name-wrapper">';
        echo '<p class="calendar-details event-name">' . esc_html( $event['name'] ) . '</p>';

        if ( ! empty( $event['start_time'] ) && ! empty( $event['end_time'] ) ) {
            echo '<p class="calendar-details hours">'
                . esc_html( $event['start_time'] ) . ' – ' . esc_html( $event['end_time'] )
                . '</p>';
        }

        echo '</div>';
    }
}

/**
 * Renders a single <td> day cell with all its data attributes, icons, hours, and events.
 *
 * @param int        $day          Day of the month (1–31).
 * @param int        $month        Month number (1–12).
 * @param int        $year         Four-digit year.
 * @param array|null $info         ACF calendar data for this date, or null.
 * @param array      $events_today Events scheduled for this date.
 */
function render_day_cell( int $day, int $month, int $year, ?array $info, array $events_today ): void {
    $date                 = sprintf( '%04d-%02d-%02d', $year, $month, $day );
    $status_class         = resolve_status_class( $info );
    $weather_status       = $info['weather_status']      ?? '';
    $rainy_day_guarantee  = $info['rainy_day_guarantee'] ?? '';
    $weather_status_class = $weather_status
        ? strtolower( str_replace( ' ', '-', $weather_status ) )
        : '';
    $times = extract_day_times( $info );
    $attrs = build_day_cell_attributes( $date, $info ?? [], $events_today, $status_class, $times );

    echo "<td {$attrs} class='day-cell {$status_class} {$weather_status_class}'>";
    echo '<div class="day-inner">';
    echo "<p class='day-number'><strong>{$day} </strong><span class='park-status'>{$status_class}</span></p>";

    if ( $info ) {
        render_day_icons( $info, $events_today, $weather_status, $rainy_day_guarantee, $times );
        render_day_hours( $status_class, $times );
    }

    render_day_events( $events_today );

    echo '</div></td>';
}

/**
 * Renders the navigation header row for a month block.
 *
 * @param int $first_day_timestamp Unix timestamp for the 1st of the month.
 */
function render_month_header( int $first_day_timestamp ): void {
    echo '<div class="month-header-container">';
    echo '<svg class="calendar-nav calendar-prev"><use xlink:href="#FontAwesomeicon-arrow-circle-left"></use></svg>';
    echo '<h2 class="calendar-month-header guttery">' . esc_html( date( 'F Y', $first_day_timestamp ) ) . '</h2>';
    echo '<svg class="calendar-nav calendar-next"><use xlink:href="#FontAwesomeicon-arrow-circle-right"></use></svg>';
    echo '</div>';
}

/**
 * Renders the full calendar grid for a single month.
 *
 * The first month ($index === 0) receives the 'active' class so JS can show it
 * immediately; all other months are hidden until navigated to.
 *
 * @param int   $month          Month number (1–12).
 * @param int   $year           Four-digit year.
 * @param array $calendar_data  Date-indexed ACF day data.
 * @param array $events_by_date Date-indexed event entries.
 * @param int   $index          Zero-based position (0 = currently visible).
 */
function render_month_grid( int $month, int $year, array $calendar_data, array $events_by_date, int $index ): void {
    $first_day     = strtotime( "$year-$month-01" );
    $days_in_month = cal_days_in_month( CAL_GREGORIAN, $month, $year );
    $start_day     = (int) date( 'w', $first_day );
    $is_active     = $index === 0 ? 'active' : '';

    echo "<div class='month-wrapper {$is_active}' data-month-index='{$index}'>";
    render_month_header( $first_day );

    echo "<table class='calendar-table'>";
    echo "<tr>
        <th class='weekday-header'>Sun</th>
        <th class='weekday-header'>Mon</th>
        <th class='weekday-header'>Tue</th>
        <th class='weekday-header'>Wed</th>
        <th class='weekday-header'>Thu</th>
        <th class='weekday-header'>Fri</th>
        <th class='weekday-header'>Sat</th>
    </tr><tr>";

    // Pad the first row so the first day falls on the correct weekday column.
    $cell = 0;
    for ( $i = 0; $i < $start_day; $i++ ) {
        echo "<td class='empty'></td>";
        $cell++;
    }

    for ( $day = 1; $day <= $days_in_month; $day++ ) {
        $date         = sprintf( '%04d-%02d-%02d', $year, $month, $day );
        $info         = $calendar_data[ $date ] ?? null;
        $events_today = $events_by_date[ $date ] ?? [];

        render_day_cell( $day, $month, $year, $info, $events_today );
        $cell++;

        // Break into a new row every 7 cells, except after the very last day.
        if ( $cell % 7 === 0 && $day !== $days_in_month ) {
            echo '</tr><tr>';
        }
    }

    // Fill remaining cells to keep the last row at a full 7 columns.
    while ( $cell % 7 !== 0 ) {
        echo "<td class='empty'></td>";
        $cell++;
    }

    echo '</tr></table></div>';
}

/**
 * Generates an ordered list of month/year pairs for the rolling 12-month window.
 *
 * Starting from the current month ensures the calendar is always current without
 * requiring manual configuration updates each season.
 *
 * @return array[] Array of { month: int, year: int } maps.
 */
function get_calendar_months(): array {
    $months = [];
    $start  = new DateTime( 'first day of this month' );

    for ( $i = 0; $i < 12; $i++ ) {
        $date     = clone $start;
        $date->modify( "+{$i} months" );
        $months[] = [
            'month' => (int) $date->format( 'm' ),
            'year'  => (int) $date->format( 'Y' ),
        ];
    }

    return $months;
}

// =========================================================
// ENTRY POINT — Build data, render calendar grid and modal
// =========================================================

$calendar_data  = load_calendar_data();
$events_by_date = load_events_by_date();
$months         = get_calendar_months();

echo '<div class="calendar-nav"></div>';
echo '<div class="calendar-container">';

foreach ( $months as $index => $month_data ) {
    render_month_grid( $month_data['month'], $month_data['year'], $calendar_data, $events_by_date, $index );
}

echo '</div>';

// Modal panel — populated entirely by JavaScript on day cell click.
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
            <p>Rainy Day Guarantee</p>
        </div>
        <div id="calendar-day-notes-container" class="calendar-day-notes-container">
            <svg id="calendar-day-note-icon" class="note-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" width="18" height="18">
                <path fill="#ff0482" d="M480 32c0-12.9-7.8-24.6-19.8-29.6s-25.7-2.2-34.9 6.9L381.7 53c-48 48-113.1 75-181 75l-8.7 0-32 0-96 0c-35.3 0-64 28.7-64 64l0 96c0 35.3 28.7 64 64 64l0 128c0 17.7 14.3 32 32 32l64 0c17.7 0 32-14.3 32-32l0-128 8.7 0c67.9 0 133 27 181 75l43.6 43.6c9.2 9.2 22.9 11.9 34.9 6.9s19.8-16.6 19.8-29.6l0-147.6c18.6-8.8 32-32.5 32-60.4s-13.4-51.6-32-60.4L480 32zm-64 76.7L416 240l0 131.3C357.2 317.8 280.5 288 200.7 288l-8.7 0 0-96 8.7 0c79.8 0 156.5-29.8 215.3-83.3z"></path>
            </svg>
            <p id="calendar-day-notes" class="calendar-modal-notes"></p>
        </div>
        <div id="event-display" class="calendar-event-display"></div>
    </div>
</div>';
?>
