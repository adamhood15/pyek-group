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
    if (val !== this.currentValue) {
      back.setAttribute("data-value", this.currentValue);
      bottom.setAttribute("data-value", this.currentValue);
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

function getTime() {
  const t = new Date();
  return {
    Hours: ("0" + (t.getHours() % 12 || 12)).slice(-2),
    Minutes: ("0" + t.getMinutes()).slice(-2),
    Seconds: ("0" + t.getSeconds()).slice(-2),
  };
}

function Clock(container) {
  const el = container;
  const trackers = [];

  const t = getTime();

  for (const key in t) {
    const group = document.createElement("div");
    group.className = "flip-clock__group";
    group.dataset.unit = key.toLowerCase();

    // Create individual digit trackers
    const digits = t[key].split("");
    digits.forEach((digit, i) => {
      const tracker = new CountdownTracker(key + "_" + i, digit);
      group.appendChild(tracker.el);
      trackers.push(tracker);
    });

    // Label below the group
    const label = document.createElement("span");
    label.className = "flip-clock__slot";
    label.textContent = key;
    group.appendChild(label);

    el.appendChild(group);
  }

  function updateClock() {
    const now = getTime();
    const allDigits = [
      ...now.Hours.split(""),
      ...now.Minutes.split(""),
      ...now.Seconds.split(""),
    ];

    trackers.forEach((tracker, i) => tracker.update(allDigits[i]));
  }

  setInterval(updateClock, 1000);
}

new Clock(document.getElementById("clock"));