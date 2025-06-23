document.addEventListener("DOMContentLoaded", function () {
  function makeReadable(val) {
    const stringArray = val.replace('-', ' ').split(' ');
    return stringArray.map(e => e.charAt(0).toUpperCase() + e.slice(1)).join(' ');
  }

  const data = document.querySelector(".day-countdown");
  if (!data) return;

  // Ensure all times are in Pacific Time
  const now = new Date(new Date().toLocaleString("en-US", { timeZone: "America/Los_Angeles" }));

  const park = makeReadable(data.dataset.park);
  const status = data.dataset.status;
  const weather = data.dataset.todayWeather;

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
    const h = parseInt(hour, 10);
    const suffix = h >= 12 ? "pm" : "am";
    const hour12 = h % 12 === 0 ? 12 : h % 12;
    return `${hour12}:${minute} ${suffix}`;
  };

  const formatDate = (dateStr) => {
    const date = new Date(
      new Date(`${dateStr}T00:00:00`).toLocaleString("en-US", {
        timeZone: "America/Los_Angeles",
      })
    );
    return date.toLocaleDateString([], {
      weekday: "long",
      month: "long",
      day: "numeric",
    });
  };

  const getPacificDate = (dateStr, timeStr) => {
    return new Date(
      new Date(`${dateStr}T${timeStr}:00`).toLocaleString("en-US", {
        timeZone: "America/Los_Angeles",
      })
    );
  };

  const pad = (n) => String(n).padStart(2, "0");

  const buildCountdown = (openTimeStr) => {
    const openTime = getPacificDate(todayDate, openTimeStr);

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

    let timer;

    const updateCountdown = () => {
      const currentTime = new Date(
        new Date().toLocaleString("en-US", {
          timeZone: "America/Los_Angeles",
        })
      );
      const diff = openTime - currentTime;

      if (diff <= 0) {
        if (todayClose) {
          const closeTime = formatTime(todayClose);
          container.innerHTML = `
            <p class="status-message"><strong>We are now OPEN!</strong></p>
            <p class="status-message">We will close today at <strong>${closeTime}</strong>.</p>
          `;
        } else {
          container.innerHTML = `<p class="status-message"><strong>We are now OPEN!</strong></p>`;
        }
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
    timer = setInterval(updateCountdown, 1000);
  };

  // ====== Display logic ======
  if (status === "offseason") {
    const year = now.getFullYear();
    container.innerHTML = `
      <p class="status-message">We are <strong>CLOSED for the Offseason</strong>.</p>
      <p class="status-message">Thanks for a great ${year} Season!</p>
    `;
  } else if (weather === "inclement-weather") {
    if (nextDate && nextOpen) {
      container.innerHTML = `
        <p class="status-message">We are currently <strong>CLOSED for the day</strong> due to Inclement Weather.</p>
        <p class="status-message">We will open again on <strong>${formatDate(nextDate)}</strong> at <strong>${formatTime(nextOpen)}</strong>.</p>
      `;
    } else {
      container.innerHTML = `
        <p class="status-message">We are currently <strong>CLOSED for the day</strong> due to Inclement Weather.</p>
        <p class="status-message">Check our event calendar for updates.</p>
      `;
    }
  } else if (status === "open-now" && todayClose) {
    container.innerHTML = `
      <p class="status-message"><strong>We are now OPEN!</strong></p>
      <p class="status-message">We will close today at <strong>${formatTime(todayClose)}</strong>.</p>
    `;
  } else if (status === "open-later" && todayOpen) {
    buildCountdown(todayOpen);
  } else {
    if (nextDate && nextOpen && nextClose) {
      container.innerHTML = `
        <p class="status-message">We are currently <strong>CLOSED</strong>.</p>
        <p class="status-message">We will open again on <strong>${formatDate(nextDate)}</strong> from <strong>${formatTime(nextOpen)}</strong> to <strong>${formatTime(nextClose)}</strong>.</p>
      `;
    } else {
      container.innerHTML = `
        <p class="status-message">We are currently <strong>CLOSED</strong>.</p>
        <p class="status-message">Check our event calendar for operating hours.</p>
      `;
    }
  }

  data.insertAdjacentElement("afterend", container);
});