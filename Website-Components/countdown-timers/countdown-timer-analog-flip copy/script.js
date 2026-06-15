const dateTimeString = "2025-11-26T09:00:00-08:00";
const date = new Date(dateTimeString);

const pacificTimeOptions = {
  timeZone: 'America/Los_Angeles',
  year: 'numeric',
  month: 'numeric',
  day: 'numeric',
  hour: 'numeric',
  minute: 'numeric',
  second: 'numeric',
  timeZoneName: 'short',
};

const formattedPacificTime = new Intl.DateTimeFormat('en-US', pacificTimeOptions).format(date);

function CountdownTracker(label, value) {
  const el = document.createElement("span");
  el.className = "flip-clock__piece";
  el.innerHTML = `
    <b class="flip-clock__card card">
      <b class="card__top"></b>
      <b class="card__bottom"></b>
      <b class="card__back"><b class="card__bottom"></b></b>
    </b>
  `;

  this.el = el;

  const top = el.querySelector(".card__top"),
    bottom = el.querySelector(".card__bottom"),
    back = el.querySelector(".card__back"),
    backBottom = el.querySelector(".card__back .card__bottom");

  this.currentValue = null;

  this.update = function (val) {
    if (val !== this.currentValue && val !== undefined) {
      back.setAttribute("data-value", this.currentValue ?? "");
      bottom.setAttribute("data-value", this.currentValue ?? "");
      this.currentValue = val;
      top.innerText = this.currentValue;
      backBottom.setAttribute("data-value", this.currentValue);

      this.el.classList.remove("flip");
      void this.el.offsetWidth; // trigger reflow
      this.el.classList.add("flip");
    }
  };

  this.update(value);
}

function getTimeRemaining(endtime) {
  const t = Date.parse(endtime) - Date.now();
  const seconds = Math.floor((t / 1000) % 60);
  const minutes = Math.floor((t / 1000 / 60) % 60);
  const hours = Math.floor((t / (1000 * 60 * 60)) % 24);
  const days = Math.floor(t / (1000 * 60 * 60 * 24));
  return { total: t, Days: days, Hours: hours, Minutes: minutes, Seconds: seconds };
}

function Clock(container, endtime) {
  const el = container;
  const trackers = [];

  const t = getTimeRemaining(endtime);

  for (const key of ["Days", "Hours", "Minutes", "Seconds"]) {
    const group = document.createElement("div");
    group.className = "flip-clock__group";

    // ✅ Pad all units to two digits (including Days)
    const displayValue = String(t[key]).padStart(2, "0");
    const digits = displayValue.split("");

    digits.forEach((digit, i) => {
      const tracker = new CountdownTracker(key + "_" + i, digit);
      group.appendChild(tracker.el);
      trackers.push(tracker);
    });

    const label = document.createElement("span");
    label.className = "flip-clock__slot";
    label.textContent = key;
    group.appendChild(label);

    el.appendChild(group);
  }

  function updateClock() {
    const now = getTimeRemaining(endtime);
    if (now.total <= 0) return;

    const values = [
      ...String(now.Days).padStart(2, "0").split(""),
      ...String(now.Hours).padStart(2, "0").split(""),
      ...String(now.Minutes).padStart(2, "0").split(""),
      ...String(now.Seconds).padStart(2, "0").split(""),
    ];

    trackers.forEach((tracker, i) => {
      const newVal = values[i];
      tracker.update(newVal);
    });
  }

  setInterval(updateClock, 1000);
}

const toggleBtn = document.getElementById("toggle-banner");
const bfBanner = document.querySelector(".bf-header");

toggleBtn.innerHTML = "-";

// Toggle open/minimized on click
toggleBtn.addEventListener("click", () => {
  if (bfBanner.classList.contains("minimized")) {
    bfBanner.classList.remove("minimized");
    bfBanner.classList.add("open");
    toggleBtn.innerHTML = "-";
  } else {
    bfBanner.classList.remove("open");
    bfBanner.classList.add("minimized");
    toggleBtn.innerHTML = "+";
  }
});

// ✅ Countdown to November 24th, 9:00 AM CT
const endTime = formattedPacificTime;
new Clock(document.getElementById("clock"), endTime);