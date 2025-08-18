document.addEventListener("DOMContentLoaded", function () {
    const calendarDayContent = document.getElementById('calendar-day-content-container');

    initMonthNavigation();
    initCalendarCells();
    triggerTodayOnLoad();
    initDropdownToggle();

    // === Utilities ===
    function decodeJson(json) {
        json = json.replace(/\\/g, '');
        return json.slice(1, -1);
    }

    function formatDate(date) {
        const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
            'July', 'August', 'September', 'October', 'November', 'December'];
        const [year, month, day] = date.split('-');
        return `${monthNames[+month - 1]} ${+day}, ${year}`;
    }

    // === Month Navigation ===
    function initMonthNavigation() {
        const months = document.querySelectorAll(".month-wrapper");
        const prevBtn = document.querySelectorAll(".calendar-prev");
        const nextBtn = document.querySelectorAll(".calendar-next");
        let currentIndex = 0;

        function updateMonthView() {
            months.forEach((month, i) => {
                month.classList.toggle("active", i === currentIndex);
            });
        }

        prevBtn.forEach(btn => {
            btn.addEventListener("click", () => {
                currentIndex = (currentIndex - 1 + months.length) % months.length;
                updateMonthView();
            });
        });

        nextBtn.forEach(btn => {
            btn.addEventListener("click", () => {
                currentIndex = (currentIndex + 1) % months.length;
                updateMonthView();
            });
        });
    }

    // === Handle Calendar Cell Click ===
    function handleDayClick(cell) {
    const specialEventJSON = cell.getAttribute('data-special-event');
    const dayData = {
        date: cell.getAttribute('data-date'),
        openTime: cell.getAttribute('data-open-time'),
        closeTime: cell.getAttribute('data-close-time'),
        openTime2: cell.getAttribute('data-open-time-2'),
        closeTime2: cell.getAttribute('data-close-time-2'),
        notes: cell.getAttribute('data-notes'),
        weatherStatus: cell.getAttribute('data-weather-status'),
        events: []
    };

    const dateDisplay = formatDate(dayData.date);
    const hoursIcon = document.getElementById('day-display-hours-icon');
    const dayDisplay = document.getElementById('calendar-day');
    const eventDisplay = document.getElementById('event-display');
    const notesContainer = document.getElementById('calendar-day-notes-container');
    const notesField = document.getElementById('calendar-day-notes');
    const weatherMessage = document.getElementById('weather-closure-message');
    const modalHours = document.getElementById('modal-hours');
    const calendarDayContent = document.getElementById('calendar-day-content-container');
    const selectedDay = document.querySelector('.day-cell.selected');

    if (selectedDay) selectedDay.classList.remove('selected');
    cell.classList.add('selected');

    try {
        dayData.events = JSON.parse(specialEventJSON);
    } catch (e) {
        console.error('Failed to parse specialEvent JSON:', e);
    }

    document.getElementById('modal-date').textContent = dateDisplay;

    // Handle inclement weather layout
    if (dayData.weatherStatus === 'Inclement Weather') {
        dayDisplay.classList.add('inclement-weather');
        modalHours.innerHTML = 'Closed';
        changeParkStatus(hoursIcon, 'closed');

        weatherMessage.classList.remove('hide');

        // Hide everything else
        eventDisplay.classList.replace('show', 'hide');
        notesContainer.style.display = 'none';

        modalHours.style.display = '';
        return; // Exit early, skip rest of layout
    }

    // Otherwise, reset state for normal open/closed day
    dayDisplay.classList.remove('inclement-weather');
    hoursIcon.classList.remove('closed');
    weatherMessage.classList.add('hide');

    let hoursText = '';

    // Show clock icon and base hours
    if (dayData.openTime && dayData.closeTime) {
        hoursText += `${dayData.openTime} – ${dayData.closeTime}`;
        changeParkStatus(dayDisplay, 'open');
        changeParkStatus(hoursIcon, 'open');
    } else {
        hoursText += 'Closed';
        changeParkStatus(dayDisplay, 'closed');
        changeParkStatus(hoursIcon, 'closed');
    }

    if (dayData.openTime2 && dayData.closeTime2) {
        hoursText += `<br> ${dayData.openTime2} – ${dayData.closeTime2}`;
    }

    modalHours.innerHTML = hoursText;
    modalHours.style.display = '';
    hoursIcon.style.display = '';

    notesField.textContent = dayData.notes;
    notesContainer.style.display = dayData.notes ? 'flex' : 'none';

    eventDisplay.innerHTML = '';
    eventDisplay.classList.add('hide');
    if (dayData.events.length === 0) {
        eventDisplay.classList.replace('show', 'hide');
    } else {
        eventDisplay.classList.replace('hide', 'show');
        dayData.events.forEach(event => {
              const eventTimeHTML = (event.start_time || event.end_time)
              ? `<p class='event-time special'><strong>Event Time:</strong> ${event.start_time || ''} – ${event.end_time || ''}</p>`
              : `<p class='event-time special'><strong>Event Time:</strong> All Day</p>`;
  
              const eventHTML = `
                  <div class="event-container">
                      <img src="${event.image}" alt="${event.name}" class="event-thumbnail">
                      <div class="event-details">
                          <div class="icon-title-container">
                              <svg class="event-icon"><use xlink:href="#FontAwesomeicon-star"></use></svg>
                              <h4 class='event-name special'>${event.name}</h4>
                          </div>
                          ${eventTimeHTML}
                          <a href="${event.url}" target="_blank" class="event-link nz-button-teal">View Event</a>
                      </div>
                  </div>
              `;
            eventDisplay.insertAdjacentHTML('beforeend', eventHTML);
        });
    }

    // Animate the content
    dayDisplay.classList.remove('hidden');
    calendarDayContent.classList.remove('fade-in');
    calendarDayContent.classList.add('fade-out');
    setTimeout(() => {
        calendarDayContent.classList.remove('fade-out');
        void calendarDayContent.offsetWidth;
        calendarDayContent.classList.add('fade-in');
    }, 10);
}

    // === Initialize Calendar Cell Clicks ===
    function initCalendarCells() {
        document.querySelectorAll('[data-date]').forEach(cell => {
            cell.addEventListener('click', () => handleDayClick(cell));
        });
    }

    // === Trigger Today Automatically on Load ===
    function triggerTodayOnLoad() {
        const today = new Date().toLocaleDateString('en-CA');
        const todayCell = document.querySelector(`[data-date="${today}"]`);
        if (todayCell) {
            calendarDayContent.classList.add('fade-in');
            todayCell.click();
        }
    }

   function changeParkStatus(e, status) {
    e.classList.remove('open', 'closed', 'inclement-weather');
    if (status === 'open') {
        e.classList.add('open');
    } else if (status === 'closed') {
        e.classList.add('closed');
    } else if (status === 'inclement-weather') {
        e.classList.add('inclement-weather');
    }
}

    // === Key Dropdown Toggle ===
    function initDropdownToggle() {
        const dropdownToggle = document.getElementById('calendar-key-toggle');
        const subcontainers = document.getElementsByClassName('key-subcontainer');

        dropdownToggle.addEventListener('click', () => {
            document.getElementById('dropdown-icon').classList.toggle('rotated');
            document.getElementById('key-items-container').classList.toggle('dropdown');
            Array.from(subcontainers).forEach(e => e.classList.toggle('hidden'));
        });
    }
});