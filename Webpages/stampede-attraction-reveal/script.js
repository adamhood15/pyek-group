/* ============================================================
   RIDE SELECTOR — signed-off component, unmodified
   ============================================================ */
(function () {
    console.log('Function 1');
  var root = document.getElementById("rs");
  var list = document.getElementById("rsList");
  var reset = document.getElementById("rsReset");
  var live = document.getElementById("rsLive");
  var dot = document.getElementById("rsDot");
  var capN = document.getElementById("rsCapName");
  var capM = document.getElementById("rsCapModel");
  var allImg = document.getElementById("rsAll");

  if (!root || !list) return;

  var items = Array.prototype.slice.call(list.querySelectorAll(".rs__item"));
  var layers = Array.prototype.slice.call(
    root.querySelectorAll(".rs__layer--ride"),
  );
  var IDLE_NAME = capN.textContent;
  var IDLE_MODEL = capM.textContent;
  var IDLE_ALT = allImg.getAttribute("alt");

  var pinned = null; // ride key the user clicked
  var hover = null; // ride key currently previewed
  var quiet = false; // skip the preview for one programmatic focus

  function itemFor(key) {
    for (var i = 0; i < items.length; i++) {
      if (items[i].dataset.ride === key) return items[i];
    }
    return null;
  }

  function render() {
    var key = hover || pinned;

    root.classList.toggle("is-active", !!key);
    root.classList.toggle("is-pinned", !!pinned);

    layers.forEach(function (l) {
      l.classList.toggle("is-on", l.dataset.ride === key);
    });

    items.forEach(function (it) {
      var on = it.dataset.ride === key;
      it.classList.toggle("is-on", on);
      var btn = it.querySelector(".rs__btn");
      btn.setAttribute(
        "aria-pressed",
        it.dataset.ride === pinned ? "true" : "false",
      );
    });

    var it = key ? itemFor(key) : null;
    if (it) {
      var btn = it.querySelector(".rs__btn");
      dot.style.setProperty(
        "--dot",
        getComputedStyle(it).getPropertyValue("--accent").trim(),
      );
      capN.textContent = btn.dataset.name;
      capM.textContent = btn.dataset.model;
      allImg.setAttribute(
        "alt",
        btn.dataset.name + " traced through the Stampede Tubing Complex.",
      );
    } else {
      dot.style.setProperty("--dot", "");
      capN.textContent = IDLE_NAME;
      capM.textContent = IDLE_MODEL;
      allImg.setAttribute("alt", IDLE_ALT);
    }
  }

  function announce(key) {
    var it = key ? itemFor(key) : null;
    live.textContent = it
      ? it.querySelector(".rs__btn").dataset.name + " shown on the complex."
      : "All four rides shown.";
  }

  // ---- pointer preview -------------------------------------------------
  items.forEach(function (it) {
    var btn = it.querySelector(".rs__btn");
    var key = it.dataset.ride;

    it.addEventListener("pointerenter", function (e) {
      if (e.pointerType === "touch") return; // touch uses tap-to-pin instead
      hover = key;
      render();
    });

    // keyboard focus previews exactly like hover
    btn.addEventListener("focus", function () {
      if (quiet) {
        quiet = false;
        return;
      } // focus we moved ourselves
      hover = key;
      render();
      announce(key);
    });
    btn.addEventListener("blur", function () {
      if (hover === key) {
        hover = null;
        render();
      }
    });

    btn.addEventListener("click", function () {
      pinned = pinned === key ? null : key;
      hover = pinned ? key : null;
      render();
      announce(pinned);
    });
  });

  list.addEventListener("pointerleave", function (e) {
    if (e.pointerType === "touch") return;
    hover = null;
    render();
  });

  reset.addEventListener("click", function () {
    pinned = null;
    hover = null;
    render();
    announce(null);
    var first = items[0] && items[0].querySelector(".rs__btn");
    if (first) {
      quiet = true;
      first.focus();
    }
  });

  // ---- arrow-key navigation between cards ------------------------------
  list.addEventListener("keydown", function (e) {
    var i = items
      .map(function (it) {
        return it.querySelector(".rs__btn");
      })
      .indexOf(document.activeElement);
    if (i < 0) return;
    var next = null;
    if (e.key === "ArrowDown" || e.key === "ArrowRight")
      next = (i + 1) % items.length;
    if (e.key === "ArrowUp" || e.key === "ArrowLeft")
      next = (i - 1 + items.length) % items.length;
    if (e.key === "Home") next = 0;
    if (e.key === "End") next = items.length - 1;
    if (next === null) return;
    e.preventDefault();
    items[next].querySelector(".rs__btn").focus();
  });

  render();
})();

/* ============================================================
FOUR SIGNATURE SLIDES — the bays
============================================================ */
(function () {
        console.log('Function 2');

  var row = document.getElementById("bays");
  if (!row) return;
  var bays = Array.prototype.slice.call(row.querySelectorAll(".bay"));
  var btns = bays.map(function (b) {
    return b.querySelector(".bay__btn");
  });
  var live = document.getElementById("baysLive");

  // one bay is open at rest so the section reads without interaction
  var pinned =
    row.querySelector('.bay[data-ride="wildhorse"]') || bays[0] || null;
  var hover = null;

  function paint() {
    var open = hover || pinned;
    bays.forEach(function (b, i) {
      var on = b === open;
      b.classList.toggle("is-open", on);
      btns[i].setAttribute("aria-expanded", on ? "true" : "false");
    });
  }

  bays.forEach(function (b, i) {
    b.addEventListener("pointerenter", function (e) {
      if (e.pointerType === "touch") return;
      hover = b;
      paint();
    });
    b.addEventListener("pointerleave", function (e) {
      if (e.pointerType === "touch") return;
      if (hover === b) {
        hover = null;
        paint();
      }
    });
    btns[i].addEventListener("focus", function () {
      hover = b;
      paint();
    });
    btns[i].addEventListener("blur", function () {
      if (hover === b) {
        hover = null;
        paint();
      }
    });
    btns[i].addEventListener("click", function () {
      pinned = pinned === b ? null : b;
      hover = pinned;
      paint();
      live.textContent = pinned
        ? btns[i].textContent.trim() + " open."
        : "All four slides closed.";
    });
  });

  row.addEventListener("keydown", function (e) {
    var i = btns.indexOf(document.activeElement);
    if (i < 0) return;
    var next = null;
    if (e.key === "ArrowRight" || e.key === "ArrowDown")
      next = (i + 1) % btns.length;
    if (e.key === "ArrowLeft" || e.key === "ArrowUp")
      next = (i - 1 + btns.length) % btns.length;
    if (e.key === "Home") next = 0;
    if (e.key === "End") next = btns.length - 1;
    if (next === null) return;
    e.preventDefault();
    btns[next].focus();
  });

  paint();
})();

/* ============================================================
GALLERY + LIGHTBOX
============================================================ */
(function () {
  var gal = document.getElementById("gal");
  var lb = document.getElementById("lb");
  if (!gal || !lb) return;

  var tiles = Array.prototype.slice.call(gal.querySelectorAll(".gal__tile"));
  var stage = document.getElementById("lbStage");
  var cap = document.getElementById("lbCap");
  var count = document.getElementById("lbCount");
  var prev = document.getElementById("lbPrev");
  var next = document.getElementById("lbNext");
  var closeBtn = document.getElementById("lbClose");

  var index = 0,
    opener = null;

  // Accepts whatever comes out of the ACF field: a full watch URL, a
  // youtu.be short link, an /embed/ or /shorts/ URL, one with extra
  // query params (playlists, timestamps, etc.), or just a bare 11-char
  // video ID. Returns null if none of those match.
  function extractYouTubeId(value) {
    if (!value) return null;
    value = value.trim();

    if (/^[\w-]{11}$/.test(value)) return value;

    var patterns = [
      /youtu\.be\/([\w-]{11})/,
      /[?&]v=([\w-]{11})/,
      /\/embed\/([\w-]{11})/,
      /\/shorts\/([\w-]{11})/,
    ];
    for (var i = 0; i < patterns.length; i++) {
      var m = value.match(patterns[i]);
      if (m) return m[1];
    }
    return null;
  }

  function renderYouTube(youtubeId, img) {
    var wrap = document.createElement("div");
    wrap.className = "lb__video";

    // muted is required for the browser to allow autoplay without a
    // user gesture
    var iframe = document.createElement("iframe");
    iframe.className = "lb__iframe";
    iframe.src =
      "https://www.youtube-nocookie.com/embed/" +
      youtubeId +
      "?autoplay=1&mute=1&rel=0";
    iframe.title = img.getAttribute("alt") || "Video";
    iframe.allow = "autoplay; encrypted-media; picture-in-picture; fullscreen";
    iframe.allowFullscreen = true;

    wrap.appendChild(iframe);
    stage.appendChild(wrap);
  }

  function show(i) {
    index = (i + tiles.length) % tiles.length;
    var tile = tiles[index];
    var img = tile.querySelector("img");
    var youtubeId = extractYouTubeId(tile.dataset.youtube);
    var isVideo = tile.dataset.video === "1";

    // clearing the stage on every navigation also tears down any
    // playing iframe, so Prev/Next/arrow keys always stop playback
    stage.textContent = "";

    if (youtubeId) {
      renderYouTube(youtubeId, img);
    } else {
      var big = document.createElement("img");
      big.src = img.getAttribute("src");
      big.alt = img.getAttribute("alt");
      big.width = img.getAttribute("width");
      big.height = img.getAttribute("height");
      stage.appendChild(big);

      if (isVideo) {
        var ph = document.createElement("p");
        ph.className = "lb__ph";
        var b = document.createElement("b");
        b.textContent = "Video placeholder";
        var s = document.createElement("span");
        s.textContent =
          "The ride film drops with the reveal. This is the poster frame — there is no video file yet.";
        ph.appendChild(b);
        ph.appendChild(s);
        stage.appendChild(ph);
      }
    }

    cap.textContent = tile.dataset.caption || img.getAttribute("alt");
    count.textContent = index + 1 + " of " + tiles.length;
  }

  function focusables() {
    return Array.prototype.slice.call(
      lb.querySelectorAll("button:not([disabled])"),
    );
  }

  function open(i, trigger) {
    opener = trigger;
    lb.hidden = false;
    document.body.style.overflow = "hidden";
    show(i);
    closeBtn.focus();
  }

  function close() {
    lb.hidden = true;
    document.body.style.overflow = "";
    stage.textContent = "";
    if (opener) opener.focus();
    opener = null;
  }

  tiles.forEach(function (t, i) {
    t.addEventListener("click", function () {
      open(i, t);
    });
  });

  prev.addEventListener("click", function () {
    show(index - 1);
  });
  next.addEventListener("click", function () {
    show(index + 1);
  });

  Array.prototype.slice
    .call(lb.querySelectorAll("[data-lb-close]"))
    .forEach(function (el) {
      el.addEventListener("click", function () {
        close();
      });
    });

  lb.addEventListener("keydown", function (e) {
    if (e.key === "Escape") {
      e.preventDefault();
      close();
      return;
    }
    if (e.key === "ArrowRight") {
      e.preventDefault();
      show(index + 1);
      return;
    }
    if (e.key === "ArrowLeft") {
      e.preventDefault();
      show(index - 1);
      return;
    }
    if (e.key === "Tab") {
      var f = focusables();
      if (!f.length) return;
      var first = f[0],
        last = f[f.length - 1];
      if (e.shiftKey && document.activeElement === first) {
        e.preventDefault();
        last.focus();
      } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault();
        first.focus();
      }
    }
  });
})();

/* ============================================================
IN THE NEWS
============================================================ */
/* ---- PRESS: replace with real coverage as it lands ---------------- */
const PRESS = [
  {
    outlet: "Houston daily",
    headline: "Placeholder — main announcement story goes here",
    date: "",
    url: "",
  },
  {
    outlet: "Katy community paper",
    headline: "Placeholder — local reaction and opening-date piece",
    date: "",
    url: "",
  },
  {
    outlet: "Local TV",
    headline: "Placeholder — reveal-day broadcast segment",
    date: "",
    url: "",
  },
  {
    outlet: "Industry trade",
    headline: "Placeholder — ProSlide installation write-up",
    date: "",
    url: "",
  },
];

(function () {
  var list = document.getElementById("newsList");
  if (!list) return;

  PRESS.forEach(function (item) {
    var li = document.createElement("li");
    li.className = "news__row";

    var inner = document.createElement(item.url ? "a" : "div");
    inner.className = "news__in";
    if (item.url) {
      inner.href = item.url;
      inner.target = "_blank";
      inner.rel = "noopener";
    }

    var outlet = document.createElement("span");
    outlet.className = "news__outlet";
    outlet.textContent = item.outlet + (item.date ? " · " + item.date : "");

    var head = document.createElement("span");
    head.className = "news__head";
    head.textContent = item.headline;

    var tail = document.createElement("span");
    if (item.url) {
      tail.className = "news__ext";
      tail.textContent = "Read ↗";
    } else {
      tail.className = "news__pending";
      tail.textContent = "Coverage pending";
    }

    inner.appendChild(outlet);
    inner.appendChild(head);
    inner.appendChild(tail);
    li.appendChild(inner);
    list.appendChild(li);
  });
})();

/* ============================================================
DOUBLE-T CLUB — Mailchimp signup (JSONP, never navigates away)
============================================================ */
(function () {
  var form = document.getElementById("mc-embedded-subscribe-form");
  if (!form) return;

  var email = document.getElementById("mce-EMAIL");
  var phone = document.getElementById("mce-SMSPHONE");
  var consent = document.getElementById("mc-SMSPHONE-ack");
  var parkRadios = Array.prototype.slice.call(
    form.querySelectorAll('input[name="MMERGE13"]'),
  );
  var submitBtn = document.getElementById("mc-embedded-subscribe");
  var submitLabel = submitBtn.querySelector(".btn__label");
  var submitLabelDefault = submitLabel.textContent;

  var eEmail = document.getElementById("e-email");
  var eMobile = document.getElementById("e-mobile");
  var eSms = document.getElementById("e-sms");
  var ePark = document.getElementById("e-park");
  var errorBox = document.getElementById("mce-error-response");
  var successBox = document.getElementById("mce-success-response");

  function digits(v) {
    return (v || "").replace(/\D/g, "");
  }

  function formatPhone(v) {
    var d = digits(v).slice(0, 10);
    if (d.length < 4) return d;
    if (d.length < 7) return "(" + d.slice(0, 3) + ") " + d.slice(3);
    return "(" + d.slice(0, 3) + ") " + d.slice(3, 6) + "-" + d.slice(6);
  }

  // find the index in `formatted` right after the nth digit, so the
  // caret lands in the same spot relative to the digits themselves
  // rather than jumping to the end every time
  function caretForDigitCount(formatted, digitCount) {
    if (digitCount <= 0) return 0;
    var seen = 0;
    for (var i = 0; i < formatted.length; i++) {
      if (/\d/.test(formatted[i])) {
        seen++;
        if (seen === digitCount) return i + 1;
      }
    }
    return formatted.length;
  }

  phone.addEventListener("input", function () {
    var caret =
      typeof phone.selectionStart === "number"
        ? phone.selectionStart
        : phone.value.length;
    var digitsBeforeCaret = digits(phone.value.slice(0, caret)).length;

    var formatted = formatPhone(phone.value);
    phone.value = formatted;

    var newCaret = caretForDigitCount(formatted, digitsBeforeCaret);
    phone.setSelectionRange(newCaret, newCaret);
  });

  function setErr(input, node, msg) {
    node.textContent = msg || "";
    if (input) input.setAttribute("aria-invalid", msg ? "true" : "false");
  }

  function showBox(box, msg) {
    box.textContent = msg;
    box.style.display = msg ? "block" : "none";
  }

  function clearMessages() {
    setErr(email, eEmail, "");
    setErr(phone, eMobile, "");
    setErr(null, eSms, "");
    setErr(null, ePark, "");
    showBox(errorBox, "");
    showBox(successBox, "");
  }

  function validate() {
    var bad = null;

    var ev = email.value.trim();
    if (!ev) {
      setErr(email, eEmail, "Please enter an email address.");
      bad = bad || email;
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(ev)) {
      setErr(
        email,
        eEmail,
        "Please enter a valid email address, like name@example.com.",
      );
      bad = bad || email;
    }

    var pv = phone.value.trim();
    if (!pv || digits(pv).length < 10 || digits(pv).length > 15) {
      setErr(
        phone,
        eMobile,
        "Please enter a valid mobile number, including area code.",
      );
      bad = bad || phone;
    }

    if (!consent.checked) {
      setErr(
        null,
        eSms,
        "You must agree to receive text alerts to continue.",
      );
      bad = bad || consent;
    }

    var parkChosen = parkRadios.some(function (r) {
      return r.checked;
    });
    if (!parkChosen) {
      setErr(null, ePark, "Please choose a park.");
      bad = bad || parkRadios[0];
    }

    return bad;
  }

  // mirrors jQuery's .serialize(): named, enabled controls only, and
  // unchecked checkboxes/radios are skipped entirely
  function serialize() {
    var parts = [];
    Array.prototype.slice.call(form.elements).forEach(function (el) {
      if (!el.name || el.disabled) return;
      if (el.type === "submit" || el.type === "button") return;
      if ((el.type === "checkbox" || el.type === "radio") && !el.checked)
        return;
      parts.push(
        encodeURIComponent(el.name) + "=" + encodeURIComponent(el.value),
      );
    });
    return parts.join("&");
  }

  // Mailchimp's embedded forms only accept cross-origin submissions via
  // JSONP — a real POST would either be blocked by CORS or, if allowed
  // through the <form> element itself, navigate the page to Mailchimp's
  // hosted response. Injecting a <script> tag avoids both.
  function jsonpSubmit(onDone) {
    var callbackName = "mcJsonpCallback_" + Date.now();
    var jsonUrl = form.action.replace(
      "/subscribe/post?",
      "/subscribe/post-json?",
    );
    var script = document.createElement("script");
    var timer = setTimeout(function () {
      cleanup();
      onDone({
        result: "error",
        msg: "We could not reach the sign-up service. Please try again.",
      });
    }, 12000);

    function cleanup() {
      clearTimeout(timer);
      delete window[callbackName];
      if (script.parentNode) script.parentNode.removeChild(script);
    }

    window[callbackName] = function (data) {
      cleanup();
      onDone(data);
    };

    script.onerror = function () {
      cleanup();
      onDone({
        result: "error",
        msg: "We could not reach the sign-up service. Please try again.",
      });
    };
    script.src = jsonUrl + "&" + serialize() + "&c=" + callbackName;
    document.head.appendChild(script);
  }

  form.addEventListener("submit", function (e) {
    e.preventDefault();
    clearMessages();

    var bad = validate();
    if (bad) {
      showBox(errorBox, "Please fix the highlighted fields and try again.");
      bad.focus();
      return;
    }

    submitBtn.disabled = true;
    submitBtn.classList.add("is-loading");
    submitLabel.textContent = "Submitting…";

    // Mailchimp expects the raw digits (e.g. 4095944750), not the
    // "(409) 594-4750" display format, so swap it in only for the
    // request and restore the formatted value right after.
    var displayPhone = phone.value;
    phone.value = digits(displayPhone);

    jsonpSubmit(function (data) {
      submitBtn.disabled = false;
      submitBtn.classList.remove("is-loading");
      submitLabel.textContent = submitLabelDefault;

      var msg = (data && data.msg) || "";
      msg = msg.replace(/^\d+\s*-\s*/, "");

      if (data && data.result === "success") {
        showBox(
          successBox,
          msg ||
            "Thanks for signing up! We will be sending a follow up email and text message to confirm your subscription.",
        );
        form.reset();
      } else {
        showBox(errorBox, msg || "Something went wrong. Please try again.");
      }
    });

    // the request URL is already built synchronously above, so it's
    // safe to put the formatted number back for display right away
    phone.value = displayPhone;
  });
})();
