const wpIcon = '<span style="text-decoration:none">Waterpark:</span>';
const fpIcon = '<span style="text-decoration:none">Fun Park:</span>';

window.addEventListener('load', function() {
  let textBlocks = document.getElementsByClassName('nz-nav-clock-text');
  let hoursData = document.getElementById('hours-data-container');
  if (!hoursData) return;

  let wpStatus    = hoursData.getAttribute('data-wp-status');
  let wpOpenRaw   = hoursData.getAttribute('data-wp-open-time');
  let wpCloseRaw  = hoursData.getAttribute('data-wp-close-time');
  let wpOpenRaw2  = hoursData.getAttribute('data-wp-open-time-2');
  let wpCloseRaw2 = hoursData.getAttribute('data-wp-close-time-2');

  let fpStatus    = hoursData.getAttribute('data-fp-status');
  let fpOpenRaw   = hoursData.getAttribute('data-fp-open-time');
  let fpCloseRaw  = hoursData.getAttribute('data-fp-close-time');
  let fpOpenRaw2  = hoursData.getAttribute('data-fp-open-time-2');
  let fpCloseRaw2 = hoursData.getAttribute('data-fp-close-time-2');

  let wpOpen1  = wpOpenRaw   ? formatTime(wpOpenRaw   + ':00') : '';
  let wpClose1 = wpCloseRaw  ? formatTime(wpCloseRaw  + ':00') : '';
  let wpOpen2  = wpOpenRaw2  ? formatTime(wpOpenRaw2  + ':00') : '';
  let wpClose2 = wpCloseRaw2 ? formatTime(wpCloseRaw2 + ':00') : '';

  let fpOpen1  = fpOpenRaw   ? formatTime(fpOpenRaw   + ':00') : '';
  let fpClose1 = fpCloseRaw  ? formatTime(fpCloseRaw  + ':00') : '';
  let fpOpen2  = fpOpenRaw2  ? formatTime(fpOpenRaw2  + ':00') : '';
  let fpClose2 = fpCloseRaw2 ? formatTime(fpCloseRaw2 + ':00') : '';

  let wpLine = buildParkLine(wpIcon, wpStatus, wpOpen1, wpClose1, wpOpen2, wpClose2);
  let fpLine = buildParkLine(fpIcon, fpStatus, fpOpen1, fpClose1, fpOpen2, fpClose2);

  for (let i = 0; i < textBlocks.length; i++) {
    textBlocks[i].innerHTML = `<strong>Today's Park Hours</strong><br>${wpLine} <br>${fpLine}`;
  }
});

function buildParkLine(icon, status, open1, close1, open2, close2) {
  if (status === 'closed') {
    return `${icon} Closed`;
  } else if (status === 'weather') {
    return `${icon} Weather Closure`;
  } else if (open1 && close1) {
    let line = `${icon} ${open1} - ${close1}`;
    if (open2 && close2) {
      line += ` <br>${icon} ${open2} - ${close2}`;
    }
    return line;
  } else {
    return `${icon} See Calendar`;
  }
}

function formatTime(timeString) {
  return new Date('1970-01-01T' + timeString + 'Z')
    .toLocaleTimeString('en-US',
      { timeZone: 'UTC', hour12: true, hour: 'numeric', minute: 'numeric' }
    );
}
