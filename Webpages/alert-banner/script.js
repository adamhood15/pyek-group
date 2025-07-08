const showHours = document.getElementById('clock-icon');
const hoursDialog = document.getElementById('banner-operating-hours-dialog');

showHours.addEventListener('click', () => {
    hoursDialog.showModal();
})