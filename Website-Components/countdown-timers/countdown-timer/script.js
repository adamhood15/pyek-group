// Set your countdown date (YYYY-MM-DD HH:MM:SS)
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

  document.getElementById("hours").textContent = hours.toString().padStart(2, "0");
  document.getElementById("minutes").textContent = minutes.toString().padStart(2, "0");
  document.getElementById("seconds").textContent = seconds.toString().padStart(2, "0");
}, 1000);

// Close banner functionality
document.getElementById("close-banner").addEventListener("click", () => {
  const banner = document.getElementById("countdown-banner");
  banner.style.transition = "transform 0.4s ease, opacity 0.4s ease";
  banner.style.transform = "translateY(-100%)";
  banner.style.opacity = "0";
  setTimeout(() => banner.remove(), 400);
});