/**
 * Spare Part Detail - Quantity Selector & Realtime Subtotal Calculator
 */
document.addEventListener('DOMContentLoaded', function () {
    var qtyInput = document.getElementById('partQtyInput');
    if (!qtyInput) return;

    var btnMinus = document.getElementById('btnQtyMinus');
    var btnPlus = document.getElementById('btnQtyPlus');
    var subtotalDisplay = document.getElementById('partSubtotalDisplay');
    var btnWhatsApp = document.getElementById('btnWhatsAppOrder');
    var btnService = document.getElementById('btnBookingService');

    var price = parseInt(qtyInput.getAttribute('data-price'), 10) || 0;
    var maxStock = parseInt(qtyInput.getAttribute('data-stock'), 10) || 1;
    var unit = qtyInput.getAttribute('data-unit') || 'pcs';
    var sku = qtyInput.getAttribute('data-sku') || '';
    var name = qtyInput.getAttribute('data-name') || '';
    var waBase = qtyInput.getAttribute('data-wa-base') || '';
    var serviceBase = qtyInput.getAttribute('data-service-url') || '';
    var dealerName = qtyInput.getAttribute('data-dealer-name') || 'JAF Dealer';
    var detailUrl = qtyInput.getAttribute('data-detail-url') || window.location.href;

    function formatRupiah(amount) {
        return 'Rp ' + amount.toLocaleString('id-ID');
    }

    function updateState(qty) {
        if (isNaN(qty) || qty < 1) qty = 1;
        if (qty > maxStock) qty = maxStock;

        qtyInput.value = qty;

        if (btnMinus) {
            btnMinus.disabled = (qty <= 1);
        }
        if (btnPlus) {
            btnPlus.disabled = (qty >= maxStock);
        }

        var subtotal = price * qty;
        if (subtotalDisplay) {
            subtotalDisplay.textContent = formatRupiah(subtotal);
        }

        if (btnService && serviceBase) {
            var url = serviceBase;
            url += (url.indexOf('?') >= 0 ? '&' : '?') + 'qty=' + qty;
            btnService.href = url;
        }

        if (btnWhatsApp && waBase) {
            var msg = "Halo " + dealerName + ", saya ingin memesan suku cadang:\n" +
                "• Part: " + name + "\n" +
                "• Kode SKU: " + sku + "\n" +
                "• Jumlah: " + qty + " " + unit + "\n" +
                "• Harga Satuan: " + formatRupiah(price) + "\n" +
                "• Estimasi Total: " + formatRupiah(subtotal) + "\n\n" +
                "Apakah stok masih tersedia? Link: " + detailUrl;

            btnWhatsApp.href = waBase + "?text=" + encodeURIComponent(msg);
        }
    }

    if (btnMinus) {
        btnMinus.addEventListener('click', function () {
            var current = parseInt(qtyInput.value, 10) || 1;
            updateState(current - 1);
        });
    }

    if (btnPlus) {
        btnPlus.addEventListener('click', function () {
            var current = parseInt(qtyInput.value, 10) || 1;
            updateState(current + 1);
        });
    }

    qtyInput.addEventListener('change', function () {
        var current = parseInt(qtyInput.value, 10) || 1;
        updateState(current);
    });

    // Run initial state setup
    var initial = parseInt(qtyInput.value, 10) || 1;
    updateState(initial);
});
