// Houston
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
            weatherNote: cell.getAttribute('data-weather-note'),
            events: []
        };

        const dateDisplay = formatDate(dayData.date);
        let hoursText = '';

        const hoursIcon = document.getElementById('day-display-hours-icon');
        const dayDisplay = document.getElementById('calendar-day');
        const eventDisplay = document.getElementById('event-display');
        const notesContainer = document.getElementById('calendar-day-notes-container');
        const notesField = document.getElementById('calendar-day-notes');
        const weatherMessage = document.getElementById('weather-closure-message');

        const selectedDay = document.querySelector('.day-cell.selected');
        if (selectedDay) selectedDay.classList.remove('selected');
        cell.classList.add('selected');

        try {
            dayData.events = JSON.parse(specialEventJSON);
        } catch (e) {
            console.error('Failed to parse specialEvent JSON:', e);
        }

        if (dayData.openTime && dayData.closeTime) {
            hoursText += `${dayData.openTime} – ${dayData.closeTime}`;
            
            hoursIcon.classList.remove('closed');
            hoursIcon.classList.add('open');
        
            dayDisplay.classList.remove('closed');
            dayDisplay.classList.add('open');
        } else {
            hoursText += 'Closed';
            hoursIcon.classList.remove('open');
            hoursIcon.classList.add('closed');
        
            dayDisplay.classList.add('closed');
            dayDisplay.classList.remove('open');
        }

        

        if (dayData.openTime2 && dayData.closeTime2) {
            hoursText += `<br> ${dayData.openTime2} – ${dayData.closeTime2}`;
        }

        document.getElementById('modal-date').textContent = dateDisplay;
        document.getElementById('modal-hours').innerHTML = hoursText;
        notesField.textContent = dayData.notes;

        notesContainer.style.display = dayData.notes ? 'flex' : 'none';

        eventDisplay.innerHTML = '';
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
                          <a href="${event.url}" target="_blank" class="event-link nz-button-blue">View Event</a>
                      </div>
                  </div>
              `;
            eventDisplay.insertAdjacentHTML('beforeend', eventHTML);
        });
        }
      //Weather Closure
      if (dayData.weatherStatus === 'Weather Closure') {
            dayDisplay.classList.add('weather-closure');
            weatherMessage.classList.remove('hide');
            eventDisplay.classList.add('hide');
        
            // Hide clock icon and hours
            hoursIcon.style.display = 'none';
            document.getElementById('modal-hours').style.display = 'none';
            console.log(cell);
        } else {
            dayDisplay.classList.remove('weather-closure');
            weatherMessage.classList.add('hide');
            eventDisplay.classList.remove('hide');
            // Show clock icon and hours
            hoursIcon.style.display = '';
            document.getElementById('modal-hours').style.display = '';
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

