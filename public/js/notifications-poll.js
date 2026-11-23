/**
 * AJAX Short Polling untuk pembaruan lonceng notifikasi secara real-time.
 * Berjalan otomatis tiap 10 detik saat tab aktif (menggunakan Page Visibility API).
 */
(function () {
    'use strict';

    var pollUrl = '/notifikasi/unread-count';
    var intervalMs = 10000; // 10 detik
    var timer = null;
    var lastCount = null;

    // Ambil initial count dari lonceng di DOM jika ada
    var initialBell = document.querySelector('[data-notification-bell]');
    if (initialBell && initialBell.dataset.count !== undefined) {
        lastCount = parseInt(initialBell.dataset.count, 10);
        if (isNaN(lastCount)) {
            lastCount = 0;
        }
    }

    function updateUI(count, label) {
        // 1. Update elemen lonceng (navbar & topbar admin)
        var bells = document.querySelectorAll('[data-notification-bell]');
        bells.forEach(function (el) {
            el.dataset.count = count;
            el.setAttribute('aria-label', count > 0 ? 'Notifikasi, ' + count + ' belum dibaca' : 'Notifikasi');

            var icon = el.querySelector('i.bi');
            if (icon) {
                if (count > 0) {
                    icon.classList.remove('bi-bell');
                    icon.classList.add('bi-bell-fill');
                } else {
                    icon.classList.remove('bi-bell-fill');
                    icon.classList.add('bi-bell');
                }
            }

            var badge = el.querySelector('[data-notification-badge]');
            if (badge) {
                if (count > 0) {
                    badge.textContent = label;
                    badge.classList.remove('d-none');
                } else {
                    badge.textContent = '';
                    badge.classList.add('d-none');
                }
            }

            // Animasi lonceng bergetar jika ada notifikasi baru masuk
            if (count > (lastCount || 0)) {
                el.classList.add('bell-ringing');
                setTimeout(function () {
                    el.classList.remove('bell-ringing');
                }, 1000);
            }
        });

        // 2. Update badge di sidebar menu admin / mekanik
        var sidebars = document.querySelectorAll('[data-notification-sidebar]');
        sidebars.forEach(function (el) {
            var badge = el.querySelector('[data-notification-badge]');
            if (badge) {
                if (count > 0) {
                    var displayCount = count > 99 ? '99+' : count;
                    badge.innerHTML = displayCount + '<span class="visually-hidden"> belum dibaca</span>';
                    badge.classList.remove('d-none');
                } else {
                    badge.classList.add('d-none');
                }
            }
        });
    }

    function fetchCount() {
        // Jangan request jika tab browser sedang tidak aktif / diminimize
        if (document.hidden) {
            return;
        }

        fetch(pollUrl, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        })
        .then(function (res) {
            // Sesi habis atau logout: hentikan polling otomatis agar tidak memicu error di console
            if (res.status === 401 || res.status === 419) {
                if (timer) {
                    clearInterval(timer);
                    timer = null;
                }
                return null;
            }
            if (!res.ok) {
                return null;
            }
            return res.json();
        })
        .then(function (data) {
            if (!data || typeof data.unread !== 'number') {
                return;
            }

            if (data.unread !== lastCount) {
                var prev = lastCount;
                updateUI(data.unread, data.unread_label);
                lastCount = data.unread;
            }
        })
        .catch(function () {
            // Abaikan kesalahan koneksi jaringan sementara secara senyap
        });
    }

    // Hanya jalankan polling jika ada elemen lonceng atau menu sidebar notifikasi di halaman
    if (document.querySelector('[data-notification-bell]') || document.querySelector('[data-notification-sidebar]')) {
        timer = setInterval(fetchCount, intervalMs);

        // Jika user kembali membuka tab ini setelah dari tab lain, langsung periksa 1 kali
        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) {
                fetchCount();
            }
        });
    }
})();
