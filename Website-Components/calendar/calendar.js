document.addEventListener('DOMContentLoaded', function () {

    // =========================================================
    // DOM CACHE
    // Query the DOM once at startup. Caching prevents repeated
    // lookups on every click event, which is measurable at scale.
    // =========================================================

    /** @type {Object.<string, HTMLElement>} Cached DOM node references. */
    const DOM = {
        calendarDayContent:  document.getElementById('calendar-day-content-container'),
        dayDisplay:          document.getElementById('calendar-day'),
        modalDate:           document.getElementById('modal-date'),
        modalHours:          document.getElementById('modal-hours'),
        hoursIcon:           document.getElementById('day-display-hours-icon'),
        eventDisplay:        document.getElementById('event-display'),
        notesContainer:      document.getElementById('calendar-day-notes-container'),
        notesField:          document.getElementById('calendar-day-notes'),
        weatherMessage:      document.getElementById('weather-closure-message'),
        rainyDayMessage:     document.getElementById('rainy-day-guarantee-message'),
        dropdownToggle:      document.getElementById('calendar-key-toggle'),
        dropdownIcon:        document.getElementById('dropdown-icon'),
        keyItemsContainer:   document.getElementById('key-items-container'),
    };

    // =========================================================
    // ENTRY POINT
    // =========================================================

    initMonthNavigation();
    initCalendarCells();
    triggerTodayOnLoad();
    initDropdownToggle();

    // =========================================================
    // MONTH NAVIGATION
    // =========================================================

    /**
     * Sets up previous/next arrow navigation between monthly calendar views.
     *
     * All .month-wrapper divs are rendered by PHP and hidden via CSS.
     * Only the one matching currentIndex gets the 'active' class to display.
     */
    function initMonthNavigation() {
        const months   = document.querySelectorAll('.month-wrapper');
        const prevBtns = document.querySelectorAll('.calendar-prev');
        const nextBtns = document.querySelectorAll('.calendar-next');
        let currentIndex = 0;

        /** Toggles 'active' on only the month at currentIndex. */
        function updateMonthView() {
            months.forEach(function (month, i) {
                month.classList.toggle('active', i === currentIndex);
            });
        }

        prevBtns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                currentIndex = (currentIndex - 1 + months.length) % months.length;
                updateMonthView();
            });
        });

        nextBtns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                currentIndex = (currentIndex + 1) % months.length;
                updateMonthView();
            });
        });
    }

    // =========================================================
    // CALENDAR CELL INITIALIZATION
    // =========================================================

    /**
     * Attaches a click handler to every day cell in the rendered calendar grid.
     */
    function initCalendarCells() {
        document.querySelectorAll('[data-date]').forEach(function (cell) {
            cell.addEventListener('click', function () {
                handleDayClick(cell);
            });
        });
    }

    /**
     * Simulates a click on today's cell when the page loads so the modal is
     * pre-populated without requiring user interaction.
     */
    function triggerTodayOnLoad() {
        // en-CA locale produces the YYYY-MM-DD format that matches data-date attributes.
        const today     = new Date().toLocaleDateString('en-CA');
        const todayCell = document.querySelector('[data-date="' + today + '"]');

        if (todayCell) {
            DOM.calendarDayContent.classList.add('fade-in');
            todayCell.click();
        }
    }

    // =========================================================
    // DAY CLICK HANDLER
    // =========================================================

    /**
     * Orchestrates all modal updates when a calendar day cell is clicked.
     *
     * @param {HTMLElement} cell The clicked <td> day cell.
     */
    function handleDayClick(cell) {
        const dayData = readDayData(cell);

        updateSelectedCell(cell);
        updateModalStatus(dayData);
        updateModalHours(dayData);
        updateWeatherMessage(dayData);
        updateHoursVisibility(dayData);
        updateRainyDayMessage(dayData);
        updateModalDate(dayData.date);
        updateNotes(dayData.notes);
        updateEventDisplay(dayData.events);
        animateModalContent();
    }

    /**
     * Reads all data attributes from a day cell into a structured object.
     *
     * JSON parsing is guarded with try/catch — a malformed attribute should not
     * break the modal; the day details still display without events.
     *
     * @param  {HTMLElement} cell
     * @returns {{ date: string, status: string, openTime: string, closeTime: string,
     *             openTime2: string, closeTime2: string, notes: string,
     *             weatherStatus: string, weatherNote: string,
     *             rainyDayGuarantee: string, events: Array }}
     */
    function readDayData(cell) {
        let events = [];
        try {
            events = JSON.parse(cell.getAttribute('data-special-event') || '[]');
        } catch (e) {
            console.error('Calendar: failed to parse event JSON for', cell.getAttribute('data-date'), e);
        }

        return {
            date:              cell.getAttribute('data-date')               || '',
            status:            cell.getAttribute('data-status')              || 'closed',
            openTime:          cell.getAttribute('data-open-time')           || '',
            closeTime:         cell.getAttribute('data-close-time')          || '',
            openTime2:         cell.getAttribute('data-open-time-2')         || '',
            closeTime2:        cell.getAttribute('data-close-time-2')        || '',
            notes:             cell.getAttribute('data-notes')               || '',
            weatherStatus:     cell.getAttribute('data-weather-status')      || '',
            weatherNote:       cell.getAttribute('data-weather-note')        || '',
            rainyDayGuarantee: cell.getAttribute('data-rainy-day-guarantee') || '',
            events,
        };
    }

    /**
     * Moves the 'selected' highlight to the newly clicked cell.
     *
     * @param {HTMLElement} cell
     */
    function updateSelectedCell(cell) {
        const prev = document.querySelector('.day-cell.selected');
        if (prev) {
            prev.classList.remove('selected');
        }
        cell.classList.add('selected');
    }

    // =========================================================
    // MODAL UPDATE FUNCTIONS
    // =========================================================

    /**
     * Applies the correct status class to the modal and the hours icon.
     *
     * The hours icon mirrors open/closed state so its CSS colour matches the panel.
     *
     * @param {{ status: string }} dayData
     */
    function updateModalStatus(dayData) {
        DOM.dayDisplay.classList.remove('open', 'closed', 'limited');
        DOM.hoursIcon.classList.remove('open', 'closed');

        DOM.dayDisplay.classList.add(dayData.status);
        // Both 'open' and 'limited' use the open icon colour.
        DOM.hoursIcon.classList.add(dayData.status === 'closed' ? 'closed' : 'open');
    }

    /**
     * Populates the hours line in the modal, including an optional second time slot.
     *
     * @param {{ status: string, openTime: string, closeTime: string,
     *           openTime2: string, closeTime2: string }} dayData
     */
    function updateModalHours(dayData) {
        const isClosed = dayData.status === 'closed';
        let hoursHTML  = isClosed
            ? 'Closed'
            : dayData.openTime + ' \u2013 ' + dayData.closeTime;

        // Append the split-hours slot when both values are present.
        if (!isClosed && dayData.openTime2 && dayData.closeTime2) {
            hoursHTML += '<br> ' + dayData.openTime2 + ' \u2013 ' + dayData.closeTime2;
        }

        DOM.modalHours.innerHTML = hoursHTML;
    }

    /**
     * Shows or hides the weather-closure banner.
     *
     * Visibility of the clock icon and hours text is intentionally NOT managed here —
     * that is handled by updateHoursVisibility() which consolidates all conditions
     * that can suppress hours (weather closure + limited-with-no-times).
     *
     * @param {{ weatherStatus: string }} dayData
     */
    function updateWeatherMessage(dayData) {
        const isWeatherClosure = dayData.weatherStatus === 'Weather Closure';

        DOM.dayDisplay.classList.toggle('weather-closure', isWeatherClosure);
        DOM.weatherMessage.classList.toggle('hide', !isWeatherClosure);
    }

    /**
     * Controls whether the clock icon and hours text are visible in the modal.
     *
     * Two conditions independently hide them:
     *  1. Weather closure — hours are irrelevant when the park is closed for weather.
     *  2. Status is open/limited but no times are configured in ACF — showing a clock
     *     with nothing beneath it would imply hours exist when they don't.
     *
     * Centralising this logic here prevents ordering conflicts that arise when
     * multiple functions each set hoursIcon.style.display independently.
     *
     * @param {{ status: string, openTime: string, closeTime: string, weatherStatus: string }} dayData
     */
    function updateHoursVisibility(dayData) {
        const isWeatherClosure   = dayData.weatherStatus === 'Weather Closure';
        const isLimitedNoTimes   = dayData.status !== 'closed' && !dayData.openTime && !dayData.closeTime;
        const shouldHideHours    = isWeatherClosure || isLimitedNoTimes;

        DOM.hoursIcon.style.display  = shouldHideHours ? 'none' : '';
        DOM.modalHours.style.display = shouldHideHours ? 'none' : '';
    }

    /**
     * Shows or hides the Rainy Day Guarantee badge.
     *
     * @param {{ rainyDayGuarantee: string }} dayData
     */
    function updateRainyDayMessage(dayData) {
        const hasGuarantee = dayData.rainyDayGuarantee === 'Yes';
        DOM.rainyDayMessage.classList.toggle('show', hasGuarantee);
        DOM.rainyDayMessage.classList.toggle('hide', !hasGuarantee);
    }

    /**
     * Updates the modal heading with the formatted date.
     *
     * @param {string} dateString Y-m-d format.
     */
    function updateModalDate(dateString) {
        DOM.modalDate.textContent = formatDate(dateString);
    }

    /**
     * Populates the notes field, or hides the container when no notes exist.
     *
     * @param {string} notes
     */
    function updateNotes(notes) {
        DOM.notesField.textContent       = notes;
        DOM.notesContainer.style.display = notes ? 'flex' : 'none';
    }

    /**
     * Renders all special event cards for the selected day, or hides the panel.
     *
     * @param {Array} events
     */
    function updateEventDisplay(events) {
        DOM.eventDisplay.innerHTML = '';

        if (events.length === 0) {
            DOM.eventDisplay.classList.replace('show', 'hide');
            return;
        }

        DOM.eventDisplay.classList.replace('hide', 'show');
        events.forEach(function (event) {
            DOM.eventDisplay.appendChild(buildEventCard(event));
        });
    }

    /**
     * Constructs a DOM element for a single event card.
     *
     * DOM construction is used instead of innerHTML to prevent XSS — event names,
     * URLs, and image paths originate from data attributes set by PHP, but defence
     * in depth means we never trust them as safe HTML.
     *
     * @param {{ name: string, image: string, url: string,
     *           start_time: string, end_time: string }} event
     * @returns {HTMLElement}
     */
    function buildEventCard(event) {
        const container   = document.createElement('div');
        container.className = 'event-container';

        const img         = document.createElement('img');
        img.src           = event.image || '';
        img.alt           = event.name  || '';
        img.className     = 'event-thumbnail';

        const details     = document.createElement('div');
        details.className = 'event-details';

        const iconTitle   = document.createElement('div');
        iconTitle.className = 'icon-title-container';
        // SVG use element is safe static markup — no user data injected here.
        iconTitle.innerHTML = '<svg class="event-icon"><use xlink:href="#FontAwesomeicon-star"></use></svg>';

        const title       = document.createElement('h4');
        title.className   = 'event-name special';
        title.textContent = event.name || '';
        iconTitle.appendChild(title);

        const timeEl      = document.createElement('p');
        timeEl.className  = 'event-time special';
        // innerHTML is safe here because buildEventTimeText() returns plain text only.
        timeEl.innerHTML  = '<strong>Event Time:</strong> ' + buildEventTimeText(event);

        const link        = document.createElement('a');
        link.href         = event.url || '#';
        link.target       = '_blank';
        link.className    = 'event-link nz-button-blue';
        link.textContent  = 'View Event';

        details.appendChild(iconTitle);
        details.appendChild(timeEl);
        details.appendChild(link);
        container.appendChild(img);
        container.appendChild(details);

        return container;
    }

    /**
     * Produces the display string for an event's time range.
     *
     * Falls back to "All Day" when neither start nor end time is provided.
     *
     * @param {{ start_time: string, end_time: string }} event
     * @returns {string} Plain-text time description.
     */
    function buildEventTimeText(event) {
        if (!event.start_time && !event.end_time) {
            return 'All Day';
        }
        if (event.start_time && event.end_time) {
            return event.start_time + ' \u2013 ' + event.end_time;
        }
        return event.start_time || event.end_time;
    }

    /**
     * Triggers a CSS fade-out → fade-in transition on the modal content panel.
     *
     * The forced reflow (offsetWidth read) is intentional — it resets the CSS
     * animation so it replays correctly on every day click.
     */
    function animateModalContent() {
        DOM.dayDisplay.classList.remove('hidden');
        DOM.calendarDayContent.classList.remove('fade-in');
        DOM.calendarDayContent.classList.add('fade-out');

        setTimeout(function () {
            DOM.calendarDayContent.classList.remove('fade-out');
            void DOM.calendarDayContent.offsetWidth; // Force reflow to restart animation.
            DOM.calendarDayContent.classList.add('fade-in');
        }, 10);
    }

    // =========================================================
    // UTILITIES
    // =========================================================

    /**
     * Converts a Y-m-d date string to a human-readable "Month D, YYYY" format.
     *
     * @param  {string} dateString e.g. "2025-07-04"
     * @returns {string}           e.g. "July 4, 2025"
     */
    function formatDate(dateString) {
        const monthNames = [
            'January', 'February', 'March', 'April',
            'May', 'June', 'July', 'August',
            'September', 'October', 'November', 'December',
        ];
        const parts = dateString.split('-');
        return monthNames[ parseInt(parts[1], 10) - 1 ] + ' '
            + parseInt(parts[2], 10) + ', ' + parts[0];
    }

    // =========================================================
    // KEY DROPDOWN TOGGLE
    // =========================================================

    /**
     * Wires up the calendar legend dropdown toggle.
     *
     * Guarded against missing elements so this doesn't throw on pages
     * where the key widget is not rendered.
     */
    function initDropdownToggle() {
        if (!DOM.dropdownToggle) {
            return;
        }

        const subcontainers = document.getElementsByClassName('key-subcontainer');

        DOM.dropdownToggle.addEventListener('click', function () {
            DOM.dropdownIcon.classList.toggle('rotated');
            DOM.keyItemsContainer.classList.toggle('dropdown');
            Array.from(subcontainers).forEach(function (el) {
                el.classList.toggle('hidden');
            });
        });
    }

});
