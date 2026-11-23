/**
 * Interactive Service Booking Helper (/servis/booking)
 */
document.addEventListener('DOMContentLoaded', function () {
    var serviceSelect = document.getElementById('service_id');
    var timeSelect = document.getElementById('preferred_time');
    var vehicleSelect = document.getElementById('userVehicleSelect');

    var modelInput = document.getElementById('vehicle_model');
    var plateInput = document.getElementById('plate_number');
    var yearInput = document.getElementById('vehicle_year');
    var mileageInput = document.getElementById('mileage');

    // Preview DOM elements
    var previewPlaceholder = document.getElementById('servicePreviewPlaceholder');
    var previewSelected = document.getElementById('servicePreviewSelected');
    var previewName = document.getElementById('previewServiceName');
    var previewDesc = document.getElementById('previewServiceDesc');
    var previewDuration = document.getElementById('previewServiceDuration');
    var previewPrice = document.getElementById('previewServicePrice');
    var previewEstimatedEnd = document.getElementById('previewEstimatedEnd');
    var previewVehicleSummary = document.getElementById('previewVehicleSummary');

    function calculateEndTime(startTimeStr, durationMinutes) {
        if (!startTimeStr) return '-';
        var parts = startTimeStr.split(':');
        if (parts.length < 2) return '-';
        var hours = parseInt(parts[0], 10);
        var minutes = parseInt(parts[1], 10);
        if (isNaN(hours) || isNaN(minutes)) return '-';

        var totalMinutes = hours * 60 + minutes + parseInt(durationMinutes || 60, 10);
        var endHours = Math.floor(totalMinutes / 60) % 24;
        var endMinutes = totalMinutes % 60;

        var hStr = endHours < 10 ? '0' + endHours : '' + endHours;
        var mStr = endMinutes < 10 ? '0' + endMinutes : '' + endMinutes;
        return hStr + ':' + mStr + ' WIB';
    }

    function updateServiceSummary() {
        if (!serviceSelect) return;
        var selectedOpt = serviceSelect.options[serviceSelect.selectedIndex];

        if (!selectedOpt || !selectedOpt.value) {
            if (previewPlaceholder) previewPlaceholder.classList.remove('d-none');
            if (previewSelected) previewSelected.classList.add('d-none');
            return;
        }

        var duration = selectedOpt.getAttribute('data-duration') || '60';
        var price = selectedOpt.getAttribute('data-price') || '0';
        var desc = selectedOpt.getAttribute('data-description') || 'Layanan servis resmi JAF Service.';
        var serviceName = selectedOpt.textContent.split('(')[0].trim();

        if (previewName) previewName.textContent = serviceName;
        if (previewDesc) previewDesc.textContent = desc;
        if (previewDuration) previewDuration.textContent = duration + ' Menit';
        if (previewPrice) {
            var pNum = parseInt(price, 10);
            previewPrice.textContent = pNum > 0 ? 'Mulai Rp ' + pNum.toLocaleString('id-ID') : 'Hubungi Bengkel';
        }

        var selectedTime = timeSelect ? timeSelect.value : '';
        if (previewEstimatedEnd) {
            if (selectedTime) {
                previewEstimatedEnd.textContent = calculateEndTime(selectedTime, duration) + ' (Estimasi)';
            } else {
                previewEstimatedEnd.textContent = 'Pilih jam kedatangan';
            }
        }

        if (previewPlaceholder) previewPlaceholder.classList.add('d-none');
        if (previewSelected) previewSelected.classList.remove('d-none');
    }

    function updateVehicleSummary() {
        if (!previewVehicleSummary) return;
        var model = modelInput ? modelInput.value.trim() : '';
        var plate = plateInput ? plateInput.value.trim() : '';

        if (plate && typeof window.parsePlateSegments === 'function') {
            plate = window.parsePlateSegments(plate);
        }

        if (model || plate) {
            previewVehicleSummary.textContent = (model || 'Kendaraan') + (plate ? ' [' + plate.toUpperCase() + ']' : '');
        } else {
            previewVehicleSummary.textContent = 'Belum diisi';
        }
    }

    // Quick fill from user vehicle history
    if (vehicleSelect) {
        var vehicles = [];
        try {
            var raw = vehicleSelect.getAttribute('data-user-vehicles');
            if (raw) vehicles = JSON.parse(raw);
        } catch (e) {
            vehicles = [];
        }

        vehicleSelect.addEventListener('change', function () {
            var idx = vehicleSelect.value;
            if (idx === '' || !vehicles[idx]) return;
            var v = vehicles[idx];

            if (modelInput && v.vehicle_model) {
                modelInput.value = v.vehicle_model;
            }
            if (plateInput && v.plate_number) {
                plateInput.value = v.plate_number;
            }
            if (yearInput && v.vehicle_year) {
                yearInput.value = v.vehicle_year;
            }
            if (mileageInput && v.mileage) {
                mileageInput.value = parseInt(v.mileage, 10).toLocaleString('id-ID');
            }
            updateVehicleSummary();
        });
    }

    if (serviceSelect) {
        serviceSelect.addEventListener('change', updateServiceSummary);
    }
    if (timeSelect) {
        timeSelect.addEventListener('change', updateServiceSummary);
    }
    if (modelInput) {
        modelInput.addEventListener('input', updateVehicleSummary);
    }
    if (plateInput) {
        plateInput.addEventListener('input', updateVehicleSummary);
    }

    var dateInput = document.getElementById('preferred_date');

    function updateAvailableTimeSlots() {
        if (!dateInput || !timeSelect) return;

        var todayStr = dateInput.getAttribute('data-today');
        var rawAvailable = dateInput.getAttribute('data-available-today');
        var availableToday = [];
        try {
            if (rawAvailable) availableToday = JSON.parse(rawAvailable);
        } catch (e) {
            availableToday = [];
        }

        var selectedDate = dateInput.value;
        var isToday = (selectedDate && todayStr && selectedDate === todayStr);

        var options = timeSelect.options;
        for (var i = 0; i < options.length; i++) {
            var opt = options[i];
            if (!opt.value) continue;

            if (!opt.hasAttribute('data-orig-label')) {
                opt.setAttribute('data-orig-label', opt.textContent);
            }
            var origLabel = opt.getAttribute('data-orig-label');

            if (isToday) {
                var isAvailable = availableToday.indexOf(opt.value) !== -1;
                opt.disabled = !isAvailable;
                if (!isAvailable) {
                    opt.textContent = origLabel + ' (Sudah Lewat / Tidak Tersedia)';
                } else {
                    opt.textContent = origLabel;
                }
            } else {
                opt.disabled = false;
                opt.textContent = origLabel;
            }
        }

        // If the selected option is now disabled, reset value
        if (timeSelect.selectedIndex >= 0 && timeSelect.options[timeSelect.selectedIndex].disabled) {
            timeSelect.value = '';
            updateServiceSummary();
        }
    }

    if (dateInput) {
        dateInput.addEventListener('change', updateAvailableTimeSlots);
        dateInput.addEventListener('input', updateAvailableTimeSlots);
    }

    // Run on initial load
    updateAvailableTimeSlots();
    updateServiceSummary();
    updateVehicleSummary();
});
