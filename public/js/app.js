/*
 * Dealer Mobil — script kecil untuk halaman publik (tanpa library).
 */
document.addEventListener('DOMContentLoaded', function () {
    // <select data-auto-submit>: kirim form begitu pilihan berubah.
    // Tombol submit di form tetap ada sebagai cadangan bila JavaScript mati.
    document.querySelectorAll('[data-auto-submit]').forEach(function (field) {
        field.addEventListener('change', function () {
            var form = field.form;

            if (!form) {
                return;
            }

            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.submit();
            }
        });
    });

    // <form data-confirm="Pesan?">: minta konfirmasi sebelum dikirim (tanpa JS form langsung terkirim,
    // server tetap memeriksa status).
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!window.confirm(form.getAttribute('data-confirm'))) {
                event.preventDefault();
                event.stopImmediatePropagation();
            }
        });
    });

    // <form data-disable-on-submit>: nonaktifkan tombol submit agar tidak terkirim dua kali.
    // Didaftarkan setelah data-confirm, jadi tombol tidak terkunci bila konfirmasi dibatalkan.
    var lockedButtons = [];

    document.querySelectorAll('form[data-disable-on-submit]').forEach(function (form) {
        form.addEventListener('submit', function () {
            form.querySelectorAll('button[type="submit"]').forEach(function (button) {
                lockedButtons.push({ button: button, html: button.innerHTML });
                button.disabled = true;
                button.setAttribute('aria-busy', 'true');
                button.textContent = 'Memproses…';
            });
        });
    });

    // Kembali lewat tombol Back (halaman dari bfcache): aktifkan lagi tombol yang tadi dikunci.
    window.addEventListener('pageshow', function (event) {
        if (!event.persisted) {
            return;
        }

        lockedButtons.forEach(function (item) {
            item.button.disabled = false;
            item.button.removeAttribute('aria-busy');
            item.button.innerHTML = item.html;
        });
        lockedButtons = [];
    });

    // Masking & formatting real-time plat nomor mobil Indonesia (A-Z dan 0-9):
    // Segmen 1 (Wilayah): maks 2 huruf
    // Segmen 2 (Nomor Polisi): maks 4 angka (hanya setelah segmen 1 minimal 1 huruf)
    // Segmen 3 (Seri Akhir): maks 3 huruf (hanya setelah segmen 2 minimal 1 angka)
    function parsePlateSegments(val) {
        if (!val || typeof val !== 'string') return '';
        var upper = val.toUpperCase();
        var seg1 = '';
        var seg2 = '';
        var seg3 = '';

        for (var i = 0; i < upper.length; i++) {
            var ch = upper.charAt(i);

            // Segmen 1: huruf saja, maks 2 huruf, sebelum angka dimulai
            if (seg2.length === 0 && seg3.length === 0) {
                if (ch >= 'A' && ch <= 'Z') {
                    if (seg1.length < 2) {
                        seg1 += ch;
                    }
                    continue;
                }
            }

            // Segmen 2: angka saja, maks 4 angka, wajib ada segmen 1 minimal 1 huruf, sebelum seri akhir
            if (seg1.length >= 1 && seg3.length === 0) {
                if (ch >= '0' && ch <= '9') {
                    if (seg2.length < 4) {
                        seg2 += ch;
                    }
                    continue;
                }
            }

            // Segmen 3: huruf saja, maks 3 huruf, wajib ada segmen 2 minimal 1 angka
            if (seg2.length >= 1) {
                if (ch >= 'A' && ch <= 'Z') {
                    if (seg3.length < 3) {
                        seg3 += ch;
                    }
                    continue;
                }
            }
        }

        var result = seg1;
        if (seg2.length > 0) {
            result += ' ' + seg2;
        }
        if (seg3.length > 0) {
            result += ' ' + seg3;
        }
        return result;
    }

    // Ekspos secara global agar modul lain (seperti ringkasan booking) dapat memvalidasi plat nomor secara konsisten
    window.parsePlateSegments = parsePlateSegments;

    function getAlphaCountBeforeCaret(val, caretPos) {
        var count = 0;
        var max = Math.min(caretPos, val.length);
        for (var i = 0; i < max; i++) {
            var ch = val.charAt(i).toUpperCase();
            if ((ch >= 'A' && ch <= 'Z') || (ch >= '0' && ch <= '9')) {
                count++;
            }
        }
        return count;
    }

    function findCaretPosForAlphaCount(val, targetAlphaCount) {
        if (targetAlphaCount <= 0) return 0;
        var count = 0;
        for (var i = 0; i < val.length; i++) {
            var ch = val.charAt(i).toUpperCase();
            if ((ch >= 'A' && ch <= 'Z') || (ch >= '0' && ch <= '9')) {
                count++;
                if (count === targetAlphaCount) {
                    return i + 1;
                }
            }
        }
        return val.length;
    }

    // Masking real-time saat mengetik dengan posisi kursor stabil & backspace mulus (Capture Phase agar berjalan sebelum listener elemen)
    document.addEventListener('input', function (event) {
        var target = event.target;
        if (target && target.matches && (target.matches('input[data-plate-number]') || target.matches('input[name="plate_number"]'))) {
            var raw = target.value;
            var caretPos = target.selectionStart || 0;
            var targetAlpha = getAlphaCountBeforeCaret(raw, caretPos);

            var formatted = parsePlateSegments(raw);
            if (formatted !== raw) {
                target.value = formatted;
                var newCaret = findCaretPosForAlphaCount(formatted, targetAlpha);
                target.setSelectionRange(newCaret, newCaret);
            }
        }
    }, true);

    // Format saat kursor keluar (blur / change)
    document.addEventListener('blur', function (event) {
        var target = event.target;
        if (target && target.matches && (target.matches('input[data-plate-number]') || target.matches('input[name="plate_number"]'))) {
            var formatted = parsePlateSegments(target.value);
            if (formatted !== target.value) {
                target.value = formatted;
                target.dispatchEvent(new Event('input', { bubbles: true }));
                target.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }
    }, true);

    // Format saat form disubmit untuk memastikan data selalu rapi
    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (form && form.querySelectorAll) {
            form.querySelectorAll('input[data-plate-number], input[name="plate_number"]').forEach(function (input) {
                input.value = parsePlateSegments(input.value);
            });
        }
    });

    // Inisialisasi format plat saat halaman dimuat jika ada nilai awal
    document.querySelectorAll('input[data-plate-number], input[name="plate_number"]').forEach(function (input) {
        if (input.value) {
            input.value = parsePlateSegments(input.value);
        }
    });

    // Navbar: bayangan tipis setelah halaman digulir.
    var navbar = document.querySelector('[data-navbar]');

    if (navbar) {
        var syncNavbar = function () {
            navbar.classList.toggle('is-scrolled', window.scrollY > 4);
        };

        syncNavbar();
        window.addEventListener('scroll', syncNavbar, { passive: true });

        // Menu hamburger (HP): ketuk di luar navbar atau tekan Esc untuk menutup. Ketukan pertama hanya menutup menu,
        // tidak ikut menekan tombol/tautan di baliknya.
        var menu = navbar.querySelector('.navbar-collapse');

        if (menu && window.bootstrap) {
            var closeMenu = function () {
                window.bootstrap.Collapse.getOrCreateInstance(menu, { toggle: false }).hide();
            };

            document.addEventListener('click', function (event) {
                if (menu.classList.contains('show') && !navbar.contains(event.target)) {
                    event.preventDefault();
                    event.stopPropagation();
                    closeMenu();
                }
            }, true);

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && menu.classList.contains('show')) {
                    closeMenu();
                    navbar.querySelector('.navbar-toggler').focus();
                }
            });

            // Halaman digulir (lebih dari sedikit) saat menu terbuka: tutup menu.
            var openedAt = 0;

            menu.addEventListener('shown.bs.collapse', function () {
                openedAt = window.scrollY;
            });

            window.addEventListener('scroll', function () {
                if (menu.classList.contains('show') && Math.abs(window.scrollY - openedAt) > 24) {
                    closeMenu();
                }
            }, { passive: true });
        }
    }

    // Menu "tubelight" (desktop ≥1200px): satu lampu meluncur ke menu yang disorot, lalu kembali ke menu aktif.
    // Tanpa JavaScript, CSS sudah menandai menu aktif; di HP menu tetap hamburger biasa.
    var tubelight = document.querySelector('[data-tubelight]');

    if (tubelight) {
        var lamp = document.createElement('li');
        lamp.className = 'tubelight-lamp';
        lamp.setAttribute('aria-hidden', 'true');
        tubelight.appendChild(lamp);
        tubelight.classList.add('is-enhanced');

        var activeLink = tubelight.querySelector('.nav-link.active');

        var moveLamp = function (link) {
            if (!link) {
                lamp.classList.remove('is-visible');

                return;
            }

            lamp.style.setProperty('--lamp-x', link.offsetLeft + 'px');
            lamp.style.setProperty('--lamp-w', link.offsetWidth + 'px');
            lamp.classList.add('is-visible');
        };

        tubelight.querySelectorAll('.nav-link').forEach(function (link) {
            link.addEventListener('mouseenter', function () {
                moveLamp(link);
            });
            link.addEventListener('focus', function () {
                moveLamp(link);
            });
        });

        tubelight.addEventListener('mouseleave', function () {
            moveLamp(activeLink);
        });
        tubelight.addEventListener('focusout', function (event) {
            if (!tubelight.contains(event.relatedTarget)) {
                moveLamp(activeLink);
            }
        });

        // Posisi awal tanpa animasi; dihitung ulang bila lebar menu berubah (font selesai dimuat, ukuran layar).
        lamp.style.transition = 'none';
        moveLamp(activeLink);
        lamp.getBoundingClientRect();
        lamp.style.transition = '';

        if ('ResizeObserver' in window) {
            new ResizeObserver(function () {
                moveLamp(tubelight.contains(document.activeElement) ? document.activeElement : activeLink);
            }).observe(tubelight);
        }
    }

    // Video latar hero: hanya layar lebar (≥992px), tanpa "reduce motion", dan tidak dalam mode hemat data.
    // Satu video diputar berulang; beberapa video diputar bergantian.
    var heroVideo = document.querySelector('[data-hero-videos]');

    if (heroVideo) {
        var heroSection = heroVideo.closest('.home-hero');
        var sources = [];

        try {
            sources = JSON.parse(heroVideo.getAttribute('data-hero-videos')) || [];
        } catch (error) {
            sources = [];
        }

        var wide = window.matchMedia('(min-width: 992px)').matches;
        var calm = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        var saveData = navigator.connection && navigator.connection.saveData;

        if (sources.length && wide && !calm && !saveData) {
            var index = 0;

            // Autoplay hanya diizinkan bila video tanpa suara; set juga lewat properti (bukan hanya atribut).
            heroVideo.muted = true;
            heroVideo.defaultMuted = true;

            var retryLater = function () {
                // Autoplay ditolak (mis. tab belum terlihat): banner tanpa video tetap tampil,
                // lalu dicoba lagi saat tab terlihat atau saat pengunjung pertama kali berinteraksi.
                var retry = function () {
                    if (heroVideo.paused && !document.hidden) {
                        heroVideo.play().catch(function () {});
                    }
                };

                document.addEventListener('visibilitychange', retry);
                ['pointerdown', 'keydown', 'scroll'].forEach(function (type) {
                    window.addEventListener(type, retry, { once: true, passive: true });
                });
            };

            var playCurrent = function () {
                heroVideo.src = sources[index];
                var attempt = heroVideo.play();

                if (attempt && typeof attempt.catch === 'function') {
                    attempt.catch(retryLater);
                }
            };

            heroVideo.loop = sources.length === 1;
            heroVideo.addEventListener('playing', function () {
                heroVideo.classList.add('is-playing');
                heroSection.classList.add('video-on');
            });
            heroVideo.addEventListener('ended', function () {
                index = (index + 1) % sources.length;
                playCurrent();
            });

            playCurrent();
        }
    }

    // Color Swatches picker untuk galeri foto pada detail mobil:
    var colorPicker = document.getElementById('carColorPicker');
    if (colorPicker) {
        var selectedColorLabel = document.getElementById('selectedColorName');
        var selectedColorStockBadge = document.getElementById('selectedColorStockBadge');
        var btnPurchase = document.getElementById('btnPurchaseCar');
        var btnPurchaseText = document.getElementById('btnPurchaseText');
        var specCarColor = document.getElementById('specCarColor');
        var galleryEl = document.getElementById('carGallery');
        var swatchBtns = Array.prototype.slice.call(colorPicker.querySelectorAll('.color-swatch-btn'));

        var selectSwatch = function (btn) {
            if (!btn) return;
            var colorName = btn.getAttribute('data-color-name');
            var imgAttr = btn.getAttribute('data-image-index');
            var imageIndex = imgAttr !== null && imgAttr !== '' ? parseInt(imgAttr, 10) : parseInt(btn.getAttribute('data-color-index'), 10);
            var stockAttr = btn.getAttribute('data-color-stock');
            var stock = stockAttr !== null && stockAttr !== '' ? parseInt(stockAttr, 10) : 1;

            // Update active state pada tombol
            swatchBtns.forEach(function (b) {
                b.classList.remove('active');
                b.setAttribute('aria-checked', 'false');
            });
            btn.classList.add('active');
            btn.setAttribute('aria-checked', 'true');

            // Update label nama warna terpilih
            if (selectedColorLabel && colorName) {
                selectedColorLabel.textContent = colorName;
            }

            // Update badge ketersediaan stok warna
            if (selectedColorStockBadge) {
                if (stock > 0) {
                    selectedColorStockBadge.textContent = 'Stok: ' + stock + ' unit';
                    selectedColorStockBadge.className = 'badge ms-1 small bg-success-subtle text-success border border-success-subtle';
                } else {
                    selectedColorStockBadge.textContent = 'Stok Habis';
                    selectedColorStockBadge.className = 'badge ms-1 small bg-danger-subtle text-danger border border-danger-subtle';
                }
            }

            // Update tombol Ajukan Pembelian dengan parameter warna & status stok
            if (btnPurchase) {
                var baseUrl = btnPurchase.getAttribute('data-base-url');
                if (baseUrl && colorName) {
                    btnPurchase.href = baseUrl + '?warna=' + encodeURIComponent(colorName);
                }
                if (stock > 0) {
                    btnPurchase.classList.remove('disabled');
                    btnPurchase.removeAttribute('aria-disabled');
                    if (btnPurchaseText) {
                        btnPurchaseText.textContent = 'Ajukan Pembelian';
                    }
                } else {
                    btnPurchase.classList.add('disabled');
                    btnPurchase.setAttribute('aria-disabled', 'true');
                    if (btnPurchaseText) {
                        btnPurchaseText.textContent = 'Warna Ini Habis';
                    }
                }
            }

            // Update baris Warna pada tabel spesifikasi bila ada
            if (specCarColor && colorName) {
                specCarColor.textContent = colorName;
            }

            // Update tombol Hitung Simulasi Sendiri dengan parameter warna terpilih
            var btnCreditSimulation = document.getElementById('btnCreditSimulation');
            if (btnCreditSimulation && colorName) {
                var baseCreditUrl = btnCreditSimulation.getAttribute('data-base-url');
                if (baseCreditUrl) {
                    btnCreditSimulation.href = baseCreditUrl + '&warna=' + encodeURIComponent(colorName);
                }
            }

            // Update harga mobil dan ringkasan cicilan jika ada biaya cat khusus (Option B)
            var carPriceDisplay = document.getElementById('carPriceDisplay');
            var creditSummaryPrice = document.getElementById('creditSummaryPrice');
            var creditSummaryMinDp = document.getElementById('creditSummaryMinDp');
            var creditSummaryTable = document.getElementById('creditSummaryTable');
            var extraPriceAttr = btn.getAttribute('data-extra-price');
            var extraPrice = extraPriceAttr !== null && extraPriceAttr !== '' ? parseInt(extraPriceAttr, 10) : 0;
            var formatter = new Intl.NumberFormat('id-ID');

            if (carPriceDisplay) {
                var basePrice = parseInt(carPriceDisplay.getAttribute('data-base-price') || '0', 10);
                if (basePrice > 0) {
                    var adjustedPrice = basePrice + extraPrice;
                    carPriceDisplay.textContent = 'Rp ' + formatter.format(adjustedPrice);

                    if (creditSummaryPrice) {
                        creditSummaryPrice.textContent = 'Rp ' + formatter.format(adjustedPrice);
                    }
                    var dpMinPercent = creditSummaryTable ? parseInt(creditSummaryTable.getAttribute('data-dp-min') || '20', 10) : 20;
                    var minDp = Math.floor(adjustedPrice * (dpMinPercent / 100));
                    if (creditSummaryMinDp) {
                        creditSummaryMinDp.textContent = 'Rp ' + formatter.format(minDp);
                    }

                    if (creditSummaryTable) {
                        var rows = creditSummaryTable.querySelectorAll('tbody tr');
                        var rounding = parseInt(creditSummaryTable.getAttribute('data-rounding') || '1000', 10);
                        rows.forEach(function (row) {
                            var tenor = parseInt(row.getAttribute('data-tenor') || '12', 10);
                            var rate = parseFloat(row.getAttribute('data-rate') || '5');
                            var cell = row.querySelector('.credit-installment-cell');
                            if (cell && tenor > 0) {
                                var principal = adjustedPrice - minDp;
                                var rateBasisPoints = Math.round(rate * 100);
                                var totalScaled = principal * 120000 + principal * rateBasisPoints * tenor;
                                var divisor = 120000 * tenor * rounding;
                                var monthly = Math.floor((totalScaled + divisor - 1) / divisor) * rounding;
                                cell.textContent = 'Rp ' + formatter.format(monthly);
                            }
                        });
                    }
                }
            }

            // Ganti foto pada galeri utama dengan transisi halus
            if (galleryEl && !isNaN(imageIndex)) {
                var items = galleryEl.querySelectorAll('.carousel-item');
                if (items.length > 0) {
                    var targetIdx = imageIndex < items.length ? imageIndex : 0;
                    if (window.bootstrap && window.bootstrap.Carousel) {
                        var carouselInstance = window.bootstrap.Carousel.getOrCreateInstance(galleryEl, {
                            interval: false,
                            wrap: true
                        });
                        carouselInstance.to(targetIdx);
                    } else {
                        items.forEach(function (item, idx) {
                            item.classList.remove('active');
                            if (idx === targetIdx) {
                                item.classList.add('active');
                            }
                        });
                    }
                }
            }
        };

        swatchBtns.forEach(function (btn, idx) {
            btn.addEventListener('click', function () {
                selectSwatch(btn);
            });

            // Navigasi keyboard (Arrow Left / Arrow Right)
            btn.addEventListener('keydown', function (e) {
                var nextIdx = null;
                if (e.key === 'ArrowRight' || e.key === 'ArrowDown') {
                    nextIdx = (idx + 1) % swatchBtns.length;
                } else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
                    nextIdx = (idx - 1 + swatchBtns.length) % swatchBtns.length;
                }
                if (nextIdx !== null) {
                    e.preventDefault();
                    swatchBtns[nextIdx].focus();
                    selectSwatch(swatchBtns[nextIdx]);
                }
            });
        });
    }
});
