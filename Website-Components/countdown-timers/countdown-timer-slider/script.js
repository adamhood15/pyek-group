const countdownDate = new Date("2025-11-28T00:00:00").getTime();

const timer = setInterval(() => {
  const now = new Date().getTime();
  const distance = countdownDate - now;

  if (distance <= 0) {
    document.getElementById("countdown").innerHTML = "Sale is LIVE!";
    clearInterval(timer);
    return;
  }

  const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
  const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
  const seconds = Math.floor((distance % (1000 * 60)) / 1000);

  updateSegment("hours", hours);
  updateSegment("minutes", minutes);
  updateSegment("seconds", seconds);
}, 1000);

function updateSegment(segmentId, newValue) {
  const segment = document.getElementById(segmentId + "-segment");
  const currentSpan = segment.querySelector("span");
  const currentValue = parseInt(currentSpan.textContent);

  // Only animate if the value actually changed
  if (newValue !== currentValue) {
    const nextSpan = document.createElement("span");
    nextSpan.textContent = newValue.toString().padStart(2, "0");
    nextSpan.style.transform = "translateY(100%)";
    segment.appendChild(nextSpan);

    requestAnimationFrame(() => {
      currentSpan.style.transform = "translateY(-100%)";
      nextSpan.style.transform = "translateY(0)";
    });

    // Clean up old element
    setTimeout(() => {
      segment.removeChild(currentSpan);
    }, 500);
  }
}

// Close banner functionality
document.getElementById("close-banner").addEventListener("click", () => {
  const banner = document.getElementById("countdown-banner");
  banner.style.transition = "transform 0.4s ease, opacity 0.4s ease";
  banner.style.transform = "translateY(-100%)";
  banner.style.opacity = "0";
  setTimeout(() => banner.remove(), 400);
});