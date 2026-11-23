/**
 * Dynamic Car Preview for Test Drive Booking (/test-drive)
 */
document.addEventListener('DOMContentLoaded', function () {
    var select = document.getElementById('car_id');
    if (!select) return;

    var cars = {};
    try {
        var raw = select.getAttribute('data-test-drive-cars');
        if (raw) cars = JSON.parse(raw);
    } catch (e) {
        return;
    }

    var selectedContainer = document.getElementById('testDrivePreviewSelected');
    var placeholderContainer = document.getElementById('testDrivePreviewPlaceholder');
    var imgEl = document.getElementById('previewCarImg');
    var imgEmptyEl = document.getElementById('previewCarImgEmpty');
    var nameEl = document.getElementById('previewCarName');
    var conditionEl = document.getElementById('previewCarCondition');
    var categoryEl = document.getElementById('previewCarCategory');
    var stockEl = document.getElementById('previewCarStock');
    var transEl = document.getElementById('previewCarTransmission');
    var fuelEl = document.getElementById('previewCarFuel');
    var priceEl = document.getElementById('previewCarPrice');
    var linkEl = document.getElementById('previewCarDetailLink');

    function updatePreview(carId) {
        var car = cars[carId];
        if (!car) {
            if (selectedContainer) selectedContainer.classList.add('d-none');
            if (placeholderContainer) placeholderContainer.classList.remove('d-none');
            return;
        }

        if (nameEl) nameEl.textContent = car.name;
        if (conditionEl) {
            conditionEl.textContent = car.condition;
            if (car.is_new) {
                conditionEl.className = 'badge bg-success-subtle text-success border border-success-subtle';
            } else {
                conditionEl.className = 'badge bg-secondary-subtle text-secondary border';
            }
        }
        if (categoryEl) categoryEl.textContent = car.category;
        if (stockEl) stockEl.textContent = car.stock_label || ('Stok: ' + car.stock + ' unit');
        if (transEl) transEl.textContent = car.transmission;
        if (fuelEl) fuelEl.textContent = car.fuel_type;
        if (priceEl) priceEl.textContent = car.price;
        if (linkEl) linkEl.href = car.detail_url;

        if (imgEl && imgEmptyEl) {
            if (car.image_url) {
                imgEl.src = car.image_url;
                imgEl.alt = car.name;
                imgEl.classList.remove('d-none');
                imgEmptyEl.classList.add('d-none');
            } else {
                imgEl.classList.add('d-none');
                imgEmptyEl.classList.remove('d-none');
            }
        }

        if (selectedContainer) selectedContainer.classList.remove('d-none');
        if (placeholderContainer) placeholderContainer.classList.add('d-none');
    }

    select.addEventListener('change', function () {
        updatePreview(select.value);
    });

    if (select.value) {
        updatePreview(select.value);
    }
});
