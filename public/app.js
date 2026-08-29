document.addEventListener('DOMContentLoaded', () => {
  const menuButton = document.querySelector('[data-menu-toggle]');
  const sidebar = document.querySelector('[data-sidebar]');
  menuButton?.addEventListener('click', () => sidebar?.classList.toggle('is-open'));

  document.querySelectorAll('[data-tab]').forEach((button) => {
    button.addEventListener('click', () => {
      document.querySelectorAll('[data-tab]').forEach((item) => item.classList.remove('selected'));
      button.classList.add('selected');
    });
  });

  const form = document.querySelector('#ride-request-form');
  const vehicle = document.querySelector('#vehicle_type_id');
  const distance = document.querySelector('#distance_km');
  const duration = document.querySelector('#duration_min');
  const total = document.querySelector('#fare-total');
  const breakdown = document.querySelector('#fare-breakdown');
  const message = document.querySelector('#ride-message');
  let statusTimer;
  let fareSettings = [];

  if (form) {
    const departure = document.createElement('label');
    departure.className = 'departure-field';
    departure.innerHTML = '<i class="fa-regular fa-calendar-check"></i><span><small>Departure time</small><input type="datetime-local" name="departure_time" required></span>';
    form.querySelector('.estimate-box')?.before(departure);

    const driverField = document.createElement('label');
    driverField.className = 'select-field driver-select';
    driverField.innerHTML = '<i class="fa-solid fa-user-tie"></i><span><small>Preferred driver</small><select name="driver_id"><option value="">Let NiaRide choose the nearest driver</option></select></span><i class="fa-solid fa-chevron-down"></i>';
    form.querySelector('.estimate-box')?.after(driverField);
    fetch('api/index.php?resource=drivers').then((response) => response.json()).then((result) => {
      const select = driverField.querySelector('select');
      (result.data?.drivers || []).forEach((driver) => {
        const option = document.createElement('option');
        option.value = driver.id;
        option.textContent = `${driver.full_name} | ${driver.model || 'Verified vehicle'} | ${driver.phone}`;
        select.appendChild(option);
      });
      if (!result.data?.drivers?.length) {
        select.options[0].textContent = 'No drivers online - choose nearest when available';
      }
    });
  }

  const updateFare = () => {
    const settings = fareSettings.find((item) => Number(item.vehicle_type_id) === Number(vehicle?.value));
    const km = Number(distance?.value || 0);
    const minutes = Number(duration?.value || 0);
    if (!settings || !km || !minutes) {
      if (total) total.textContent = 'KSh 0';
      if (breakdown) breakdown.textContent = 'Enter distance and time to see an estimate.';
      return;
    }
    const base = Number(settings.base_fare);
    const distanceCharge = km * Number(settings.per_km);
    const timeCharge = minutes * Number(settings.per_minute);
    const serviceFee = Number(settings.service_fee);
    const estimate = Math.max(Number(settings.minimum_fare), base + distanceCharge + timeCharge + serviceFee);
    total.textContent = `KSh ${Math.round(estimate).toLocaleString()}`;
    breakdown.textContent = `Base KSh ${base} + distance KSh ${Math.round(distanceCharge)} + time KSh ${Math.round(timeCharge)} + service KSh ${serviceFee}`;
  };

  if (form) {
    fetch('api/index.php?resource=fare-settings').then((response) => response.json()).then((result) => {
      fareSettings = result.data?.settings || [];
      updateFare();
    }).catch(() => { if (breakdown) breakdown.textContent = 'Fare estimate unavailable. You can still try again.'; });
    [vehicle, distance, duration].forEach((input) => input?.addEventListener('input', updateFare));
    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      message.textContent = 'Searching for nearby drivers...';
      message.className = 'form-message';
      const response = await fetch('api/index.php?resource=rides', { method: 'POST', headers: { 'X-CSRF-TOKEN': form.querySelector('[name="csrf"]').value }, body: new FormData(form) });
      const result = await response.json();
      message.textContent = result.message;
      message.className = `form-message ${result.success ? 'success-message' : 'error-message'}`;
      if (result.success && result.data?.ride_id) {
        let statusBox = document.querySelector('#ride-status-box');
        if (!statusBox) {
          statusBox = document.createElement('div');
          statusBox.id = 'ride-status-box';
          statusBox.className = 'ride-status-box';
          form.after(statusBox);
        }
        const pollStatus = async () => {
          const statusResponse = await fetch(`api/index.php?resource=ride-status&ride_id=${result.data.ride_id}`);
          const statusResult = await statusResponse.json();
          const ride = statusResult.data?.ride;
          if (!ride) return;
          const accepted = ride.status === 'ACCEPTED' || ride.status === 'DRIVER_ARRIVING' || ride.status === 'DRIVER_ARRIVED';
          const rejected = ride.status.includes('CANCELLED') || ride.status === 'NO_DRIVER_AVAILABLE';
          statusBox.className = `ride-status-box ${accepted ? 'accepted' : rejected ? 'rejected' : ''}`;
          statusBox.innerHTML = `<i class="fa-solid ${accepted ? 'fa-circle-check' : rejected ? 'fa-circle-xmark' : 'fa-magnifying-glass'}"></i><span><strong>${accepted ? 'Ride accepted' : rejected ? 'Ride not accepted' : 'Finding your driver...'}</strong><small>${accepted ? `${ride.driver_name || 'Your driver'} is assigned · ${ride.driver_phone || 'Contact available soon'}` : rejected ? 'Please request another ride.' : 'We are checking nearby available drivers.'}</small></span>${accepted && ride.driver_phone ? `<a href="tel:${ride.driver_phone}" aria-label="Call driver"><i class="fa-solid fa-phone"></i></a>` : ''}`;
          if (!accepted && !rejected) statusTimer = setTimeout(pollStatus, 5000);
        };
        pollStatus();
      }
    });
  }
});

window.initRideMap = () => {
  const mapElement = document.querySelector('#ride-map, .map');
  if (!mapElement || !window.google?.maps) return;
  const map = new google.maps.Map(mapElement, { center: { lat: -1.286389, lng: 36.817223 }, zoom: 12, mapTypeControl: false, streetViewControl: false, fullscreenControl: false });
  new google.maps.Marker({ position: { lat: -1.286389, lng: 36.817223 }, map, title: 'Nairobi pickup area' });
  const pickup = document.querySelector('[name="pickup_label"]');
  const destination = document.querySelector('[name="destination_label"]');
  if (google.maps.places && pickup && destination) {
    new google.maps.places.Autocomplete(pickup, { componentRestrictions: { country: 'ke' } });
    new google.maps.places.Autocomplete(destination, { componentRestrictions: { country: 'ke' } });
  }
};
