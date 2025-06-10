<div
  id="status-data"
  class="day-data"
  data-park="cowabunga-canyon"
  data-status="open-now"
  data-now="2025-06-09T14:23:29-07:00"
  data-today-date="2025-06-09"
  data-today-open="11:00"
  data-today-close="18:00"
  data-today-weather="normal"
  data-next-date="2025-06-10"
  data-next-open="11:00"
  data-next-close="18:00"
></div>;

document.addEventListener("DOMContentLoaded", function () {
  const data = document.querySelector(".day-data");
  if (!data) return;

  let parks = [
    {
      park: "canyon",
      Url: "https://cowabungavegas.com/canyon/hours-and-location/",
    },
    {
      park: "bay",
      Url: "https://cowabungavegas.com/bay/hours-and-location/",
    },
    {
      park: "austin",
      Url: "https://typhoontexas.com/austin/hours-and-location/",
    },
    {
      park: "houston",
      Url: "https://typhoontexas.com/houston/hours-and-location/",
    },
  ];

  for (let i = 0; i < parks.length; i ++) {
    console.log(parks[i].park);
  }

  const park = data.dataset.park;
  const now = new Date(data.dataset.now); // your ISO string is perfect

  const todayDate = data.dataset.todayDate;
  const todayOpen = data.dataset.todayOpen;
  const todayClose = data.dataset.todayClose;

  const nextDate = data.dataset.nextDate;
  const nextOpen = data.dataset.nextOpen;
  const nextClose = data.dataset.nextClose;

  const container = document.createElement("div");
  container.classList.add("status-message");

  const formatTime = (timeStr) => {
    const [hour, minute] = timeStr.split(":");
    const h = parseInt(hour);
    const suffix = h >= 12 ? "pm" : "am";
    const hour12 = h % 12 === 0 ? 12 : h % 12;
    return `${hour12}:${minute} ${suffix}`;
  };

  const formatTimeFromDate = (date) =>
    date.toLocaleTimeString([], { hour: "numeric", minute: "2-digit" });

  const formatDate = (dateStr) =>
    new Date(dateStr + "T00:00:00-07:00").toLocaleDateString([], {
      weekday: "long",
      month: "long",
      day: "numeric",
    });

  const pad = (n) => String(n).padStart(2, "0");

  const buildCountdown = (openTime) => {
    container.innerHTML = `
            <p class="status-message">${park} will <strong>OPEN</strong> in...</p>
            <div class="flip-clock">
                <div class="flip-unit"><div class="label">Hours</div><div class="value" id="flip-hours">00</div></div>
                <div class="flip-unit"><div class="label">Minutes</div><div class="value" id="flip-minutes">00</div></div>
                <div class="flip-unit"><div class="label">Seconds</div><div class="value" id="flip-seconds">00</div></div>
            </div>
        `;

    const elHours = container.querySelector("#flip-hours");
    const elMinutes = container.querySelector("#flip-minutes");
    const elSeconds = container.querySelector("#flip-seconds");

    const updateCountdown = () => {
      const now = new Date();
      const diff = openTime - now;

      if (diff <= 0) {
        container.innerHTML = `<p><strong>We are now OPEN!</strong></p>`;
        clearInterval(timer);
        return;
      }

      const hours = Math.floor(diff / (1000 * 60 * 60));
      const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
      const seconds = Math.floor((diff % (1000 * 60)) / 1000);

      elHours.textContent = pad(hours);
      elMinutes.textContent = pad(minutes);
      elSeconds.textContent = pad(seconds);
    };

    updateCountdown();
    const timer = setInterval(updateCountdown, 1000);
  };

  // LOGIC
  if (todayDate) {
    const openTime = new Date(`${todayDate}T${todayOpen}:00-07:00`);
    const closeTime = new Date(`${todayDate}T${todayClose}:00-07:00`);

    if (now >= openTime && now <= closeTime) {
      container.innerHTML = `
                <strong>We are currently OPEN!</strong><br>
                We will close today at <strong>${formatTimeFromDate(
                  closeTime
                )}</strong>
            `;
    } else if (now < openTime) {
      buildCountdown(openTime);
    } else {
      // Today has passed, check for next day
      if (nextDate) {
        container.innerHTML = `
                    <p>We are currently <strong>CLOSED</strong>.</p>
                    <p>We will open again on <strong>${formatDate(
                      nextDate
                    )}</strong> from <strong>${formatTime(
          nextOpen
        )}</strong> to <strong>${formatTime(nextClose)}</strong>.</p>
                `;
      } else {
        container.innerHTML = `
                    <p>We are currently <strong>CLOSED</strong>.</p>
                    <p>Check our <a href="${parkCalendarURL}" target="_blank" class="status-message-link">event calendar</a> for operating hours.</p>
                `;
      }
    }
  } else if (nextDate) {
    container.innerHTML = `
            <p>We are currently <strong>CLOSED</strong>.</p>
            <p>We will open again on <strong>${formatDate(
              nextDate
            )}</strong> from <strong>${formatTime(
      nextOpen
    )}</strong> to <strong>${formatTime(nextClose)}</strong>.</p>
        `;
  } else {
    container.innerHTML = `
            <p>We are currently <strong>CLOSED</strong>.</p>
            <p>Check our <a href="${parkCalendarURL}" target="_blank" class="status-message-link">event calendar</a> for operating hours.</p>
        `;
  }

  data.insertAdjacentElement("afterend", container);
});
