document.addEventListener('DOMContentLoaded', function () {

    // =========================================================
    // DOM CACHE
    // Query the DOM once at startup.
    // =========================================================

    /** @type {Object.<string, HTMLElement>} */
    const DOM = {
        calendarDayContent: document.getElementById('calendar-day-content-container'),
        dayDisplay:         document.getElementById('calendar-day'),
        modalDate:          document.getElementById('modal-date'),
        hoursContainer:     document.getElementById('calendar-day-hours-container'),
        eventDisplay:       document.getElementById('event-display'),
        notesContainer:     document.getElementById('calendar-day-notes-container'),
        notesField:         document.getElementById('calendar-day-notes'),
        weatherMessage:     document.getElementById('weather-closure-message'),
        rainyDayMessage:    document.getElementById('rainy-day-guarantee-message'),
        dropdownToggle:     document.getElementById('calendar-key-toggle'),
        dropdownIcon:       document.getElementById('dropdown-icon'),
        keyItemsContainer:  document.getElementById('key-items-container'),
    };

    // =========================================================
    // ENTRY POINT
    // =========================================================

    initMonthNavigation();
    initCalendarCells();
    initPillTooltips();
    triggerTodayOnLoad();
    initDropdownToggle();

    // =========================================================
    // MONTH NAVIGATION
    // =========================================================

    /**
     * Sets up previous/next arrow navigation between monthly calendar views.
     */
    function initMonthNavigation() {
        const months   = document.querySelectorAll('.calendar__month');
        const prevBtns = document.querySelectorAll('.calendar__nav--prev');
        const nextBtns = document.querySelectorAll('.calendar__nav--next');
        let currentIndex = 0;

        function updateMonthView() {
            months.forEach(function (month, i) {
                month.classList.toggle('calendar__month--active', i === currentIndex);
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
     * Cells are identified by the presence of data-date on the <td>.
     */
    function initCalendarCells() {
        document.querySelectorAll('[data-date]').forEach(function (cell) {
            cell.addEventListener('click', function () {
                handleDayClick(cell);
                scrollToModal();
            });
        });
    }

    /**
     * Simulates a click on today's cell when the page loads so the modal is
     * pre-populated without requiring user interaction.
     */
    function triggerTodayOnLoad() {
        // en-CA produces YYYY-MM-DD, matching the data-date attribute format.
        const today     = new Date().toLocaleDateString('en-CA');
        const todayCell = document.querySelector('[data-date="' + today + '"]');

        if (todayCell) {
            DOM.calendarDayContent.classList.add('fade-in');
            handleDayClick(todayCell);
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
        updateModalDate(dayData.date);
        updateModalPills(dayData);
        updateModalStatus(dayData);
        updateWeatherMessage(dayData);
        updateRainyDayMessage(dayData);
        updateNotes(dayData.notes);
        updateEventDisplay(dayData.events);
        animateModalContent();
    }

    /**
     * Reads all data needed for the modal from a day cell.
     *
     * Park-specific data (status, times) is read from the .calendar__pill-half
     * elements inside the cell rather than from td-level attributes, matching the
     * per-park data attribute structure. Cell-level attributes retain only
     * date, notes, and event JSON.
     *
     * JSON parsing is guarded — a malformed attribute should not break the
     * modal; the day details still render without events.
     *
     * @param  {HTMLElement} cell
     * @returns {{ date: string, notes: string, events: Array,
     *             waterpark: ParkData, funPark: ParkData }}
     */
    function readDayData(cell) {
        let events = [];
        try {
            events = JSON.parse(cell.getAttribute('data-special-event') || '[]');
        } catch (e) {
            console.error('Calendar: failed to parse event JSON for', cell.getAttribute('data-date'), e);
        }

        const wpPill = cell.querySelector('.calendar__pill-half[data-park="waterpark"]');
        const fpPill = cell.querySelector('.calendar__pill-half[data-park="fun-park"]');

        return {
            date:      cell.getAttribute('data-date')  || '',
            notes:     cell.getAttribute('data-notes') || '',
            events,
            waterpark: readParkData(wpPill, true),
            funPark:   readParkData(fpPill, false),
        };
    }

    /**
     * Extracts park-specific data from a single pill-half element.
     *
     * Falls back to a closed/empty state when the element is absent — handles
     * days where no ACF data was entered and no pill was rendered.
     *
     * @typedef {{ status: string, openTime: string, closeTime: string,
     *             openTime2: string, closeTime2: string,
     *             weatherGuarantee: boolean }} ParkData
     *
     * @param  {HTMLElement|null} pillEl    The .calendar__pill-half DOM node.
     * @param  {boolean}         isWaterpark  True when reading the waterpark half.
     * @returns {ParkData}
     */
    function readParkData(pillEl, isWaterpark) {
        if (!pillEl) {
            return {
                status:           'closed',
                openTime:         '',
                closeTime:        '',
                openTime2:        '',
                closeTime2:       '',
                weatherGuarantee: false,
            };
        }

        return {
            status:           pillEl.getAttribute('data-status')           || 'closed',
            openTime:         pillEl.getAttribute('data-open-time')         || '',
            closeTime:        pillEl.getAttribute('data-close-time')        || '',
            openTime2:        pillEl.getAttribute('data-open-time-2')       || '',
            closeTime2:       pillEl.getAttribute('data-close-time-2')      || '',
            // Weather guarantee exists only on the waterpark pill — reading it
            // from a fun-park pill would always return null / false.
            weatherGuarantee: isWaterpark
                ? pillEl.getAttribute('data-weather-guarantee') === 'true'
                : false,
        };
    }

    /**
     * Moves the 'selected' highlight to the newly clicked cell.
     *
     * @param {HTMLElement} cell
     */
    function updateSelectedCell(cell) {
        const prev = document.querySelector('.calendar__day--selected');
        if (prev) {
            prev.classList.remove('calendar__day--selected');
        }
        cell.classList.add('calendar__day--selected');
    }

    // =========================================================
    // MODAL UPDATE FUNCTIONS
    // =========================================================

    /**
     * Derives an overall status for the modal border from individual park statuses.
     *
     * The <td> no longer carries a status class, so we infer an aggregate here:
     *   weather (either park) → weather-closure border
     *   open/bonus/limited (either park) → open border
     *   both closed → closed border
     *
     * @param {{ waterpark: ParkData, funPark: ParkData }} dayData
     */
    function updateModalStatus(dayData) {
        DOM.dayDisplay.classList.remove(
            'calendar-modal--open',
            'calendar-modal--closed',
            'calendar-modal--limited',
            'calendar-modal--bonus',
            'calendar-modal--weather-closure'
        );

        const statuses   = [dayData.waterpark.status, dayData.funPark.status];
        const anyWeather = statuses.some(function (s) { return s === 'weather'; });
        const anyOpen    = statuses.some(function (s) {
            return s === 'open' || s === 'bonus' || s === 'limited';
        });

        if (anyWeather) {
            DOM.dayDisplay.classList.add('calendar-modal--weather-closure');
        } else if (anyOpen) {
            DOM.dayDisplay.classList.add('calendar-modal--open');
        } else {
            DOM.dayDisplay.classList.add('calendar-modal--closed');
        }
    }

    /**
     * Replaces the modal hours area with a freshly built split pill.
     *
     * Rebuilding on every click keeps the modal in sync with the cell without
     * requiring any patching of existing DOM nodes.
     *
     * @param {{ waterpark: ParkData, funPark: ParkData }} dayData
     */
    function updateModalPills(dayData) {
        DOM.hoursContainer.innerHTML = '';
        DOM.hoursContainer.appendChild(buildModalPill(dayData.waterpark, dayData.funPark));
    }

    /**
     * Builds the split-pill DOM structure for the modal hours area.
     *
     * @param {ParkData} wp  Waterpark data.
     * @param {ParkData} fp  Fun Park data.
     * @returns {HTMLElement}
     */
    function buildModalPill(wp, fp) {
        const wrap = document.createElement('div');
        wrap.className = 'calendar__pill-wrap';

        const pill   = document.createElement('div');
        const isTall = (wp.openTime2 && wp.closeTime2) || (fp.openTime2 && fp.closeTime2);
        pill.className = 'calendar__pill' + (isTall ? ' calendar__pill--tall' : '');

        pill.appendChild(buildModalPillHalf(wp, 'calendar__pill-half--left',  'svg-fancy_icon-331-1999', 'Waterpark'));
        pill.appendChild(buildModalPillHalf(fp, 'calendar__pill-half--right', 'svg-fancy_icon-338-1999', 'Fun Park'));

        wrap.appendChild(pill);
        return wrap;
    }

    /**
     * Builds one half of the modal split pill as a DOM node.
     *
     * DOM construction rather than innerHTML prevents any possibility of XSS
     * even though values originate from server-rendered data attributes.
     *
     * @param {ParkData} parkData
     * @param {string}   sideClass  e.g. 'calendar__pill-half--left'.
     * @param {string}   iconId     SVG symbol ID.
     * @param {string}   ariaLabel  Accessible label for the icon.
     * @returns {HTMLElement}
     */
    function buildModalPillHalf(parkData, sideClass, iconId, ariaLabel) {
        const div = document.createElement('div');
        div.className = 'calendar__pill-half ' + sideClass + ' calendar__pill-half--' + parkData.status;

        // Icon (SVG namespace required for createElementNS).
        const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.setAttribute('class', 'calendar__pill-icon');
        svg.setAttribute('aria-label', ariaLabel);
        const use = document.createElementNS('http://www.w3.org/2000/svg', 'use');
        use.setAttribute('href', '#' + iconId);
        svg.appendChild(use);
        div.appendChild(svg);

        // Text nodes for status / hours.
        buildPillTextNodes(parkData).forEach(function (node) {
            div.appendChild(node);
        });

        return div;
    }

    /**
     * Produces the .calendar__pill-text span elements for a given park status and times.
     *
     * Handles all status variants: open, closed, weather, bonus, limited.
     * Bonus displays time ranges like open (no "Bonus" label).
     * Times stored in data attributes are already compact-formatted by PHP
     * (e.g. "10am", "2:30pm") so no client-side time parsing is needed.
     *
     * @param {ParkData} parkData
     * @returns {HTMLElement[]}
     */
    function buildPillTextNodes(parkData) {
        const nodes = [];

        function makeSpan(text) {
            const span = document.createElement('span');
            span.className   = 'calendar__pill-text';
            span.textContent = text;
            return span;
        }

        switch (parkData.status) {
            case 'closed':
                nodes.push(makeSpan('Closed'));
                break;

            case 'weather':
                nodes.push(makeSpan('Weather'));
                break;

            default: {
                // open, bonus, and limited all display time ranges when available.
                // bonus intentionally falls here — no "Bonus" label rendered.
                const range1 = buildTimeRangeText(parkData.openTime,  parkData.closeTime);
                const range2 = buildTimeRangeText(parkData.openTime2, parkData.closeTime2);

                if (parkData.status === 'limited' && !range1) {
                    nodes.push(makeSpan('Limited'));
                } else if (range1) {
                    nodes.push(makeSpan(range1));
                }

                if (range2) {
                    nodes.push(makeSpan(range2));
                }
                break;
            }
        }

        return nodes;
    }

    /**
     * Formats two compact time strings into a range separated by an en dash.
     *
     * Times arrive pre-formatted from PHP data attributes (e.g. "10am", "2pm"),
     * so this function only joins them — no parsing or conversion required.
     *
     * @param {string} open
     * @param {string} close
     * @returns {string} e.g. "10am–2pm", or empty string when either is absent.
     */
    function buildTimeRangeText(open, close) {
        if (!open || !close) {
            return '';
        }
        return open + '–' + close;
    }

    /**
     * Shows or hides the weather-closure banner.
     *
     * Now driven by individual park pill statuses rather than a td-level
     * data-weather-status attribute. Banner appears when either park is 'weather'.
     *
     * @param {{ waterpark: ParkData, funPark: ParkData }} dayData
     */
    function updateWeatherMessage(dayData) {
        const isWeatherClosure = dayData.waterpark.status === 'weather'
            || dayData.funPark.status === 'weather';

        DOM.weatherMessage.classList.toggle('hide', !isWeatherClosure);
    }

    /**
     * Shows or hides the Rainy Day Guarantee badge.
     *
     * The guarantee flag now belongs exclusively to the waterpark group per the
     * updated ACF structure — fun park no longer carries this field.
     *
     * @param {{ waterpark: ParkData }} dayData
     */
    function updateRainyDayMessage(dayData) {
        const hasGuarantee = dayData.waterpark.weatherGuarantee;
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
        container.className = 'calendar-modal__event-card';

        const img       = document.createElement('img');
        img.src         = event.image || '';
        img.alt         = event.name  || '';
        img.className   = 'calendar-modal__event-img';

        const details   = document.createElement('div');
        details.className = 'calendar-modal__event-details';

        const iconTitle = document.createElement('div');
        iconTitle.className = 'calendar-modal__event-title-wrap';
        // SVG use element is safe static markup — no user data injected here.
        iconTitle.innerHTML = '<svg class="calendar-modal__event-icon"><use xlink:href="#FontAwesomeicon-star"></use></svg>';

        const title     = document.createElement('h4');
        title.className = 'calendar-modal__event-name';
        title.textContent = event.name || '';
        iconTitle.appendChild(title);

        const timeEl    = document.createElement('p');
        timeEl.className = 'calendar-modal__event-time';
        // innerHTML is safe: buildEventTimeText() returns plain text only.
        timeEl.innerHTML = '<strong>Event Time:</strong> ' + buildEventTimeText(event);

        const link      = document.createElement('a');
        link.href       = event.url || '#';
        link.target     = '_blank';
        link.className  = 'calendar-modal__event-link nz-button-blue';
        link.textContent = 'View Event';

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
            return event.start_time + ' – ' + event.end_time;
        }
        return event.start_time || event.end_time;
    }

    /**
     * Triggers a CSS fade-out → fade-in transition on the modal content panel.
     *
     * The forced reflow (offsetWidth read) resets the animation so it replays
     * correctly on every day click.
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
     * Smoothly scrolls the modal into view, offset by the sticky nav height.
     * Nav is 103px at ≥1120px viewport width, 87px below that.
     */
    function scrollToModal() {
        const nav       = document.getElementById('_header-3-124');
        const navHeight = nav ? nav.offsetHeight : 0;
        const top       = DOM.dayDisplay.getBoundingClientRect().top + window.scrollY - navHeight;
        window.scrollTo({ top: top, behavior: 'smooth' });
    }

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
    // PILL HOVER TOOLTIP
    // =========================================================

    /**
     * Creates a single shared .calendar__pill-tooltip element at document.body
     * level (position:fixed) so it is never clipped by table cells or any
     * overflow:hidden ancestor. JS positions it above each pill on hover.
     *
     * Only active on hover-capable screens wider than 768px; mobile is unaffected.
     */
    function initPillTooltips() {
        if (!window.matchMedia('(hover: hover) and (min-width: 769px)').matches) {
            return;
        }

        const tooltip = document.createElement('div');
        tooltip.className = 'calendar__pill-tooltip';
        document.body.appendChild(tooltip);

        function makeRow(label, detail, status) {
            const row = document.createElement('div');
            row.className = 'calendar__pill-tooltip-row';

            const lbl = document.createElement('span');
            lbl.className = 'calendar__pill-tooltip-label';
            lbl.textContent = label;

            const val = document.createElement('span');
            // status comes from a data attribute set by PHP esc_attr() — safe as textContent.
            val.className = 'calendar__pill-tooltip-value calendar__pill-tooltip-value--' + status;
            val.textContent = detail;

            row.appendChild(lbl);
            row.appendChild(val);
            return row;
        }

        function showTooltip(cell, wrap) {
            const wpDetail = wrap.getAttribute('data-wp-detail') || '';
            const fpDetail = wrap.getAttribute('data-fp-detail') || '';

            if (!wpDetail && !fpDetail) { return; }

            const wpStatus = wrap.getAttribute('data-wp-status') || 'closed';
            const fpStatus = wrap.getAttribute('data-fp-status') || 'closed';

            tooltip.innerHTML = '';
            tooltip.appendChild(makeRow('Waterpark', wpDetail, wpStatus));
            tooltip.appendChild(makeRow('Fun Park',  fpDetail, fpStatus));

            // Measure then position flush above the <td> top edge.
            // Never flipped below — that would overlap the hovered cell.
            tooltip.style.display = 'block';
            const rect = cell.getBoundingClientRect();
            const ttW  = tooltip.offsetWidth;
            const ttH  = tooltip.offsetHeight;

            let left = rect.left + rect.width / 2 - ttW / 2;
            let top  = rect.top - ttH - 8;

            // Clamp horizontally within viewport.
            left = Math.max(8, Math.min(left, window.innerWidth - ttW - 8));

            // Clamp to viewport top — no flip below (would cover the td).
            top = Math.max(8, top);

            tooltip.style.left = left + 'px';
            tooltip.style.top  = top  + 'px';
        }

        function hideTooltip() {
            tooltip.style.display = 'none';
        }

        // Attach to the <td> so the full cell area triggers the tooltip,
        // but only for cells that have pill data to display.
        document.querySelectorAll('.calendar__day').forEach(function (cell) {
            const wrap = cell.querySelector('.calendar__pill-wrap[data-wp-detail]');
            if (!wrap) { return; }

            cell.addEventListener('mouseenter', function () { showTooltip(cell, wrap); });
            cell.addEventListener('mouseleave', hideTooltip);
        });

        // Hide on scroll so it doesn't drift from its anchor.
        window.addEventListener('scroll', hideTooltip, { passive: true });
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
