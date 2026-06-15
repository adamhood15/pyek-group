const showHours = document.getElementById('clock-icon');
const hoursDialog = document.getElementById('banner-operating-hours-dialog');

showHours.addEventListener('click', () => {
    hoursDialog.showModal();
})





// WAve Code
let waveX = 0,                // current horizontal background position
    lastScrollY = scrollY,    // last scroll position to detect direction
    waveVelocity = 0,         // how fast the wave is moving
    driftInterval,            // interval ID for the drift motion
    waveElement = document.querySelector('.hero-card_wave'); // the wave element

addEventListener('scroll', () => {
clearInterval(driftInterval);

// Determine scroll direction and set velocity
waveVelocity = scrollY > lastScrollY ? 4 : -4;

// Update position immediately
waveX += waveVelocity;
waveElement.style.backgroundPosition = `${waveX}px 0`;

// Store new scroll position
lastScrollY = scrollY;

// Continue drifting after scroll stops
driftInterval = setInterval(() => {
    waveVelocity *= 0.98; // slow down smoothly
    waveX += waveVelocity;
    waveElement.style.backgroundPosition = `${waveX}px 0`;
    if (Math.abs(waveVelocity) < 0.3) clearInterval(driftInterval);
}, 16);
});