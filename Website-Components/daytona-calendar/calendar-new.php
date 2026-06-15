<?php
/**
 * Daytona Calendar — Renders a rolling 12-month calendar grid with per-park
 * split-pill operating hours and special events sourced from ACF options and
 * the 'events' custom post type.
 *
 * Refactored from calendar.php to support the new ACF grouped field structure
 * (waterpark / fun_park sub-groups) and the diagonal split-pill UI.
 */

// =========================================================
// DATA LOADING
// =========================================================

/**
 * Loads ACF calendar day data from the options page and indexes it by Y-m-d.
 *
 * Field group: Calendar — Repeater: `calendar`.
 * Each entry contains `date`, `waterpark` group, `fun_park` group, and `notes`.
 *
 * @return array<string, array> Associative array keyed by date string.
 */
function load_calendar_data(): array {
    $calendar_days = get_field( 'calendar', 'option' );
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
 * Formats a raw time string (H:i) into 12-hour display format (g:i A).
 *
 * Used exclusively for event times in build_event_entry(). Park operating hours
 * use format_compact_time() instead for the shorter pill-text format.
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
 * Converts a raw H:i time string into compact display format.
 *
 * Minutes are omitted when they are :00 — "10am" not "10:00am".
 * This format is used inside split-pill spans and stored in pill data attributes
 * so JS can read them directly without additional formatting.
 *
 * @param string $time Raw H:i time from ACF (e.g. "14:30", "10:00").
 * @return string Compact string (e.g. "2:30pm", "10am"), or empty string.
 */
function format_compact_time( string $time ): string {
    if ( empty( $time ) ) {
        return '';
    }

    $ts     = strtotime( $time );
    $minute = (int) date( 'i', $ts );
    $hour   = (int) date( 'g', $ts );
    $ampm   = date( 'a', $ts );

    return $minute === 0
        ? $hour . $ampm
        : $hour . ':' . date( 'i', $ts ) . $ampm;
}

/**
 * Builds a formatted time-range string from two H:i values.
 *
 * Returns empty string when either input is absent so callers can use empty()
 * to decide whether to render a .calendar__pill-text span.
 *
 * @param string $open  H:i open time.
 * @param string $close H:i close time.
 * @return string e.g. "10am–2pm", or empty string.
 */
function format_time_range( string $open, string $close ): string {
    $open_fmt  = format_compact_time( $open );
    $close_fmt = format_compact_time( $close );

    if ( empty( $open_fmt ) || empty( $close_fmt ) ) {
        return '';
    }

    return $open_fmt . '–' . $close_fmt;
}

/**
 * Assembles a normalized event entry array for storage in $events_by_date.
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
 * @param array  $recurring_dates     Array of { start_date, end_date, start_time, end_time } entries.
 * @param array  $base_entry          Event entry built WITHOUT times.
 * @param string $fallback_start_time Raw top-level start_time fallback.
 * @param string $fallback_end_time   Raw top-level end_time fallback.
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

        $start_time = ! empty( $entry['start_time'] ) ? $entry['start_time'] : $fallback_start_time;
        $end_time   = ! empty( $entry['end_time'] )   ? $entry['end_time']   : $fallback_end_time;

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
 * Queries events and maps them to calendar dates.
 *
 * @return array<string, array[]> Date-keyed array of event entries.
 */
function load_events_by_date(): array {
    $query = new WP_Query( [
        'post_type'      => 'events',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
    ] );

    $events_by_date = [];

    if ( ! $query->have_posts() ) {
        return $events_by_date;
    }

    while ( $query->have_posts() ) {
        $query->the_post();

        $timestamp    = get_field( 'event_time_stamp' ) ?? [];
        $event_images = get_field( 'event_images' )     ?? [];

        $raw_start_time  = $timestamp['start_time']      ?? '';
        $raw_end_time    = $timestamp['end_time']        ?? '';
        $recurring_dates = $timestamp['recurring_dates'] ?? [];
        $start_date      = $timestamp['start_date']      ?? '';
        $end_date        = $timestamp['end_date']        ?? '';

        if ( ! empty( $recurring_dates ) ) {
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
 * Renders one half of the diagonal split pill for a single park.
 *
 * All time values are stored as pre-formatted compact strings in data attributes
 * so JS can read them directly without reformatting. The pill-text spans mirror
 * those same values for visual display inside the calendar cell.
 *
 * Status variants and their pill-text output:
 *   open    → time range(s), e.g. "10am–6pm"
 *   closed  → "Closed"
 *   weather → "Weather"
 *   bonus   → time range(s), displayed with blue styling (same logic as open)
 *   limited → time range when configured, "Limited" as fallback
 *
 * @param array  $park_data  Raw ACF group data (waterpark or fun_park).
 * @param string $side       'left' or 'right' — controls pill-half--left / pill-half--right modifier.
 * @param string $park_key   'waterpark' or 'fun-park' — written to data-park.
 * @param string $icon_id    SVG symbol ID, e.g. 'svg-fancy_icon-331-1999'.
 * @param string $icon_class Extra BEM modifier class on the SVG element.
 * @param string $aria_label Accessible label for the icon SVG.
 */
function render_park_pill_half(
    array  $park_data,
    string $side,
    string $park_key,
    string $icon_id,
    string $icon_class,
    string $aria_label
): void {
    $status  = strtolower( $park_data['status'] ?? 'closed' );
    $open_1  = $park_data['open_time']    ?? '';
    $close_1 = $park_data['close_time']   ?? '';
    $open_2  = $park_data['open_time_2']  ?? '';
    $close_2 = $park_data['close_time_2'] ?? '';

    $fmt_open_1  = format_compact_time( $open_1 );
    $fmt_close_1 = format_compact_time( $close_1 );
    $fmt_open_2  = format_compact_time( $open_2 );
    $fmt_close_2 = format_compact_time( $close_2 );

    $range_1 = format_time_range( $open_1, $close_1 );
    $range_2 = format_time_range( $open_2, $close_2 );

    // Data attributes store compact display values — JS reads them without
    // additional formatting when building the modal pill.
    $data_parts = [
        "data-park='"         . esc_attr( $park_key )    . "'",
        "data-status='"       . esc_attr( $status )      . "'",
        "data-open-time='"    . esc_attr( $fmt_open_1 )  . "'",
        "data-close-time='"   . esc_attr( $fmt_close_1 ) . "'",
        "data-open-time-2='"  . esc_attr( $fmt_open_2 )  . "'",
        "data-close-time-2='" . esc_attr( $fmt_close_2 ) . "'",
    ];

    // Weather guarantee exists only on the waterpark pill per the new ACF structure.
    if ( $park_key === 'waterpark' ) {
        $wg_value     = ! empty( $park_data['enable_weather_guarantee'] ) ? 'true' : 'false';
        $data_parts[] = "data-weather-guarantee='{$wg_value}'";
    }

    $data_str = implode( ' ', $data_parts );

    // Build pill-text HTML based on status.
    $pill_html = '';
    switch ( $status ) {
        case 'closed':
            $pill_html = '<span class="calendar__pill-text">Closed</span>';
            break;

        case 'weather':
            $pill_html = '<span class="calendar__pill-text">Weather</span>';
            break;

        case 'limited':
            $pill_html = $range_1
                ? '<span class="calendar__pill-text">' . esc_html( $range_1 ) . '</span>'
                : '<span class="calendar__pill-text">Limited</span>';
            if ( $range_2 ) {
                $pill_html .= '<span class="calendar__pill-text">' . esc_html( $range_2 ) . '</span>';
            }
            break;

        default: // open and bonus both display time ranges
            if ( $range_1 ) {
                $pill_html .= '<span class="calendar__pill-text">' . esc_html( $range_1 ) . '</span>';
            }
            if ( $range_2 ) {
                $pill_html .= '<span class="calendar__pill-text">' . esc_html( $range_2 ) . '</span>';
            }
            break;
    }

    echo "<div class='calendar__pill-half calendar__pill-half--{$side} calendar__pill-half--{$status}' {$data_str}>";
    echo "<svg class='calendar__pill-icon {$icon_class}' aria-label='" . esc_attr( $aria_label ) . "'><use href='#{$icon_id}'></use></svg>";
    echo $pill_html;
    echo '</div>';
}

/**
 * Returns the tooltip detail string for a single park (used inside the hover tooltip).
 *
 * @param array $park_data Raw ACF group data (waterpark or fun_park).
 * @return string Display string, e.g. "10am–6pm" or "Closed".
 */
function build_tooltip_detail( array $park_data ): string {
    $status  = strtolower( $park_data['status'] ?? 'closed' );
    $range_1 = format_time_range( $park_data['open_time'] ?? '', $park_data['close_time'] ?? '' );
    $range_2 = format_time_range( $park_data['open_time_2'] ?? '', $park_data['close_time_2'] ?? '' );

    switch ( $status ) {
        case 'closed':  return 'Closed';
        case 'weather': return 'Weather Closure';
        case 'limited':
            if ( $range_1 ) {
                return $range_2 ? $range_1 . ', ' . $range_2 : $range_1;
            }
            return 'Limited Hours';
        default: // open, bonus
            if ( $range_1 ) {
                return $range_2 ? $range_1 . ', ' . $range_2 : $range_1;
            }
            return 'Open';
    }
}

/**
 * Renders the full split-pill wrapper for a calendar day.
 *
 * Applies calendar__pill--tall when either park has a second time range, giving
 * each .calendar__pill-text span sufficient vertical space to remain legible.
 *
 * A .calendar__pill-tooltip div is appended inside the wrapper; CSS shows it
 * on hover (desktop only — mobile keeps the existing pill-text behavior).
 *
 * @param array $waterpark_data ACF waterpark group data.
 * @param array $fun_park_data  ACF fun_park group data.
 */
function render_split_pill( ?array $waterpark_data, ?array $fun_park_data ): void {
    // Null signals "no ACF entry exists" — semantically distinct from an entry
    // that is explicitly configured as closed. The --no-data modifier preserves
    // that distinction in the DOM so CSS can style them independently.
    $no_data = ( $waterpark_data === null && $fun_park_data === null );

    $waterpark_data = $waterpark_data ?? [ 'status' => 'closed' ];
    $fun_park_data  = $fun_park_data  ?? [ 'status' => 'closed' ];

    $wp_status = strtolower( $waterpark_data['status'] ?? 'closed' );
    $fp_status = strtolower( $fun_park_data['status']  ?? 'closed' );

    $data_tooltip = "data-wp-detail='" . esc_attr( build_tooltip_detail( $waterpark_data ) ) . "'"
        . " data-wp-status='" . esc_attr( $wp_status ) . "'"
        . " data-fp-detail='" . esc_attr( build_tooltip_detail( $fun_park_data ) ) . "'"
        . " data-fp-status='" . esc_attr( $fp_status ) . "'";

    $wrap_class = 'calendar__pill-wrap' . ( $no_data ? ' calendar__pill-wrap--no-data' : '' );

    echo "<div class='{$wrap_class}' {$data_tooltip}>";
    echo "<div class='calendar__pill'>";
    render_park_pill_half( $waterpark_data, 'left',  'waterpark', 'svg-fancy_icon-331-1999', 'calendar__pill-icon--waterpark', 'Waterpark' );
    render_park_pill_half( $fun_park_data,  'right', 'fun-park',  'svg-fancy_icon-338-1999', 'calendar__pill-icon--fun-park',  'Fun Park'  );
    echo '</div>';
    echo '</div>';
}

/**
 * Renders the icon strip inside a .calendar__day-number element.
 *
 * The clock/hours icon has been removed. Remaining icons: star (events),
 * bullhorn (notes), bolt (weather closure), shield (weather guarantee).
 * This output is placed inside .calendar__day-number so icons sit beside
 * the day number badge.
 *
 * @param array $info         Full ACF day data (includes waterpark, fun_park, notes).
 * @param array $events_today Events scheduled for this date.
 */
function render_day_icons( array $info, array $events_today ): void {
    $waterpark = $info['waterpark'] ?? [];
    $fun_park  = $info['fun_park']  ?? [];
    $notes     = $info['notes']     ?? '';

    $wp_status = strtolower( $waterpark['status'] ?? 'closed' );
    $fp_status = strtolower( $fun_park['status']  ?? 'closed' );

    $any_weather       = $wp_status === 'weather' || $fp_status === 'weather';
    $weather_guarantee = ! empty( $waterpark['enable_weather_guarantee'] );

    echo '<div class="calendar__icon-container">';

    if ( ! empty( $events_today ) ) {
        echo '<svg class="calendar__icon--event"><use xlink:href="#FontAwesomeicon-star"></use></svg>';
    }

    if ( ! empty( $notes ) ) {
        echo '<svg class="calendar__icon--notes"><use xlink:href="#FontAwesomeicon-bullhorn"></use></svg>';
    }

    if ( $any_weather ) {
        echo '<svg class="calendar__icon--weather"><use xlink:href="#FontAwesomeicon-bolt"></use></svg>';
    }

    if ( $weather_guarantee ) {
        echo '<svg class="calendar__icon--guarantee"><use xlink:href="#FontAwesomeicon-shield"></use></svg>';
    }

    echo '</div>';
}

/**
 * Renders the list of special event names and times inside a day cell.
 *
 * @param array $events_today Array of event entries for this date.
 */
function render_day_events( array $events_today ): void {
    foreach ( $events_today as $event ) {
        echo '<div class="calendar__event-name-wrapper">';
        echo '<p class="calendar-details calendar__event-name">' . esc_html( $event['name'] ) . '</p>';

        if ( ! empty( $event['start_time'] ) && ! empty( $event['end_time'] ) ) {
            echo '<p class="calendar-details calendar__event-hours">'
                . esc_html( $event['start_time'] ) . ' – ' . esc_html( $event['end_time'] )
                . '</p>';
        }

        echo '</div>';
    }
}

/**
 * Renders a single <td> day cell with the new split-pill park status display.
 *
 * Icons are rendered inside .calendar__day-number so they sit beside the
 * number badge. The <td> retains only data-date, data-notes, and
 * data-special-event for modal population.
 *
 * @param int        $day          Day of the month (1–31).
 * @param int        $month        Month number (1–12).
 * @param int        $year         Four-digit year.
 * @param array|null $info         ACF calendar data for this date, or null.
 * @param array      $events_today Events scheduled for this date.
 */
function render_day_cell( int $day, int $month, int $year, ?array $info, array $events_today ): void {
    $date  = sprintf( '%04d-%02d-%02d', $year, $month, $day );
    $notes = $info['notes'] ?? '';

    $special_event_json = htmlspecialchars(
        json_encode( array_values( $events_today ) ),
        ENT_QUOTES,
        'UTF-8'
    );

    $td_attrs = "data-date='"     . esc_attr( $date )              . "'"
        . " data-notes='"         . esc_attr( $notes )             . "'"
        . " data-special-event='" . $special_event_json            . "'";

    echo "<td {$td_attrs} class='calendar__day'>";
    echo '<div class="calendar__day-inner">';

    // Icons rendered inside day-number so they sit beside the number badge.
    echo '<div class="calendar__day-number">';
    echo "<strong>{$day}</strong>";
    if ( $info ) {
        render_day_icons( $info, $events_today );
    }
    echo '</div>';

    // Pass null when no ACF entry exists so render_split_pill can distinguish
    // "unconfigured date" from "date explicitly configured as closed".
    if ( $info ) {
        $wp_data = is_array( $info['waterpark'] ?? null ) ? $info['waterpark'] : [ 'status' => 'closed' ];
        $fp_data = is_array( $info['fun_park']  ?? null ) ? $info['fun_park']  : [ 'status' => 'closed' ];
    } else {
        $wp_data = null;
        $fp_data = null;
    }
    render_split_pill( $wp_data, $fp_data );

    render_day_events( $events_today );

    echo '</div></td>';
}

/**
 * Renders the navigation header row for a month block.
 *
 * @param int $first_day_timestamp Unix timestamp for the 1st of the month.
 */
function render_month_header( int $first_day_timestamp ): void {
    echo '<div class="calendar__month-header">';
    echo '<svg class="calendar__nav calendar__nav--prev"><use xlink:href="#FontAwesomeicon-arrow-circle-left"></use></svg>';
    echo '<h2 class="calendar__month-title guttery">' . esc_html( date( 'F Y', $first_day_timestamp ) ) . '</h2>';
    echo '<svg class="calendar__nav calendar__nav--next"><use xlink:href="#FontAwesomeicon-arrow-circle-right"></use></svg>';
    echo '</div>';
}

/**
 * Renders the full calendar grid for a single month.
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
    $active_class  = $index === 0 ? 'calendar__month--active' : '';

    echo "<div class='calendar__month {$active_class}' data-month-index='{$index}'>";
    render_month_header( $first_day );

    echo "<table class='calendar__table'>";
    echo "<tr>
        <th class='calendar__weekday'>Sun</th>
        <th class='calendar__weekday'>Mon</th>
        <th class='calendar__weekday'>Tue</th>
        <th class='calendar__weekday'>Wed</th>
        <th class='calendar__weekday'>Thu</th>
        <th class='calendar__weekday'>Fri</th>
        <th class='calendar__weekday'>Sat</th>
    </tr><tr>";

    $cell = 0;
    for ( $i = 0; $i < $start_day; $i++ ) {
        echo "<td class='calendar__day--empty'></td>";
        $cell++;
    }

    for ( $day = 1; $day <= $days_in_month; $day++ ) {
        $date         = sprintf( '%04d-%02d-%02d', $year, $month, $day );
        $info         = $calendar_data[ $date ] ?? null;
        $events_today = $events_by_date[ $date ] ?? [];

        render_day_cell( $day, $month, $year, $info, $events_today );
        $cell++;

        if ( $cell % 7 === 0 && $day !== $days_in_month ) {
            echo '</tr><tr>';
        }
    }

    while ( $cell % 7 !== 0 ) {
        echo "<td class='calendar__day--empty'></td>";
        $cell++;
    }

    echo '</tr></table></div>';
}

/**
 * Generates an ordered list of month/year pairs for the rolling 12-month window.
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
// ENTRY POINT
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

// Modal panel — populated by JavaScript on day cell click.
// The hours container is left empty; JS builds the split pill dynamically.
echo '<div id="calendar-day" class="calendar-modal">
    <div id="calendar-day-content-container" class="calendar-modal__content">
        <div class="calendar-modal__top">
            <h2 id="modal-date" class="calendar-modal__title"></h2>
            <div id="calendar-day-hours-container" class="calendar-modal__hours">
                <!-- Split pill injected by JS on day click -->
            </div>
        </div>
        <div id="weather-closure-message" class="calendar-modal__weather-message hide">
            <svg class="calendar-modal__weather-icon"><use xlink:href="#FontAwesomeicon-bolt"></use></svg>
            <p>The water park has been CLOSED for the day due to inclement weather.</p>
        </div>
        <div id="rainy-day-guarantee-message" class="calendar-modal__rainy-day-message hide">
            <svg class="calendar-modal__guarantee-icon"><use xlink:href="#FontAwesomeicon-shield"></use></svg>
            <p>Rainy Day Guarantee</p>
        </div>
        <div id="calendar-day-notes-container" class="calendar-modal__notes-container">
            <svg id="calendar-day-note-icon" class="calendar-modal__note-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" width="18" height="18">
                <path fill="#ff0482" d="M480 32c0-12.9-7.8-24.6-19.8-29.6s-25.7-2.2-34.9 6.9L381.7 53c-48 48-113.1 75-181 75l-8.7 0-32 0-96 0c-35.3 0-64 28.7-64 64l0 96c0 35.3 28.7 64 64 64l0 128c0 17.7 14.3 32 32 32l64 0c17.7 0 32-14.3 32-32l0-128 8.7 0c67.9 0 133 27 181 75l43.6 43.6c9.2 9.2 22.9 11.9 34.9 6.9s19.8-16.6 19.8-29.6l0-147.6c18.6-8.8 32-32.5 32-60.4s-13.4-51.6-32-60.4L480 32zm-64 76.7L416 240l0 131.3C357.2 317.8 280.5 288 200.7 288l-8.7 0 0-96 8.7 0c79.8 0 156.5-29.8 215.3-83.3z"></path>
            </svg>
            <p id="calendar-day-notes" class="calendar-modal__notes-text"></p>
        </div>
        <div id="event-display" class="calendar-modal__events"></div>
    </div>
</div>';
?>
