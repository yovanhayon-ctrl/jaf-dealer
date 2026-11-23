/*
 * Dealer Mobil — script kecil untuk halaman admin.
 */
document.addEventListener('DOMContentLoaded', function () {
    // <select data-auto-submit>: kirim form begitu pilihan berubah.
    document.querySelectorAll('[data-auto-submit]').forEach(function (field) {
        field.addEventListener('change', function () {
            var form = field.form;
            if (!form) return;
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.submit();
            }
        });
    });

    // <form data-confirm="Pesan?">: minta konfirmasi sebelum dikirim (mis. menerima bukti / mencatat pembayaran).
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!window.confirm(form.getAttribute('data-confirm'))) {
                event.preventDefault();
            }
        });
    });

    // Form status: pilih status => catatan admin terisi kalimat bawaan status itu. Catatan yang sudah diketik
    // admin tidak ditimpa; catatan lama (belum diubah) atau isian otomatis sebelumnya boleh diganti.
    document.querySelectorAll('form[data-note-templates]').forEach(function (form) {
        var templates = JSON.parse(form.getAttribute('data-note-templates'));
        var select = form.querySelector('select[name="status"]');
        var note = form.querySelector('textarea[name="admin_note"]');

        if (!select || !note) {
            return;
        }

        var replaceable = [note.value];

        select.addEventListener('change', function () {
            if (replaceable.indexOf(note.value) === -1) {
                return;
            }

            var next = templates[select.value] || replaceable[0];
            note.value = next;
            replaceable.push(next);

            // Kursor ke tanda "…" agar admin langsung menulis alasan.
            var placeholder = next.indexOf('\u2026');

            if (placeholder !== -1) {
                note.focus();
                note.setSelectionRange(placeholder, placeholder + 1);
            }
        });
    });

    // Modal konfirmasi hapus: isi action form & nama item dari tombol pemicu.
    var deleteModal = document.getElementById('deleteModal');

    if (deleteModal) {
        deleteModal.addEventListener('show.bs.modal', function (event) {
            var trigger = event.relatedTarget;

            if (!trigger) {
                return;
            }

            deleteModal.querySelector('[data-delete-form]').action = trigger.getAttribute('data-delete-url');
            deleteModal.querySelector('[data-delete-name]').textContent = trigger.getAttribute('data-delete-name');
        });
    }

    // Form mobil: field kilometer hanya tampil untuk kondisi "bekas".
    var mileageField = document.querySelector('[data-mileage-field]');
    var conditionRadios = document.querySelectorAll('[data-condition-toggle]');

    if (mileageField && conditionRadios.length) {
        var syncMileage = function () {
            var checked = document.querySelector('[data-condition-toggle]:checked');
            mileageField.classList.toggle('d-none', !checked || checked.value !== 'bekas');
        };

        conditionRadios.forEach(function (radio) {
            radio.addEventListener('change', syncMileage);
        });
        syncMileage();
    }

    // Tombol cetak halaman: <button type="button" data-print>.
    document.querySelectorAll('[data-print]').forEach(function (button) {
        button.addEventListener('click', function () {
            window.print();
        });
    });

    // Pratinjau gambar sebelum upload: <input type="file" data-preview-target="#idGambar">.
    document.querySelectorAll('input[type="file"][data-preview-target]').forEach(function (input) {
        input.addEventListener('change', function () {
            var preview = document.querySelector(input.getAttribute('data-preview-target'));
            var file = input.files && input.files[0];

            if (!preview) {
                return;
            }

            if (file && file.type.indexOf('image/') === 0) {
                preview.src = URL.createObjectURL(file);
                preview.classList.remove('d-none');
            } else {
                preview.removeAttribute('src');
                preview.classList.add('d-none');
            }
        });
    });

    // Pengaturan: kolom gambar banner / video showcase hanya tampil saat opsi terkait dipilih.
    var heroImageField = document.getElementById('heroImageField');
    var heroShowcaseVideoField = document.getElementById('heroShowcaseVideoField');

    if (heroImageField || heroShowcaseVideoField) {
        var syncHeroFields = function () {
            var checked = document.querySelector('input[name="hero_background"]:checked');
            var val = checked ? checked.value : '';
            if (heroImageField) {
                heroImageField.classList.toggle('d-none', val !== 'image');
            }
            if (heroShowcaseVideoField) {
                heroShowcaseVideoField.classList.toggle('d-none', val !== 'showcase');
            }
        };

        document.querySelectorAll('input[name="hero_background"]').forEach(function (radio) {
            radio.addEventListener('change', syncHeroFields);
        });
    }

    // Baris tabel dashboard: klik di mana saja membuka detail (link di sel pertama tetap untuk keyboard).
    document.querySelectorAll('tr[data-row-link]').forEach(function (row) {
        row.addEventListener('click', function (event) {
            if (event.target.closest('a, button, input, select, textarea')) {
                return;
            }

            window.location.href = row.getAttribute('data-row-link');
        });
    });

    // Grafik dashboard: <canvas data-chart="activity|sales" data-chart-data='{...}'>.
    // Bila Chart.js gagal dimuat (CDN), tabel "Lihat angka" di bawahnya tetap berisi datanya.
    var chartCanvases = document.querySelectorAll('canvas[data-chart]');

    if (chartCanvases.length && window.Chart) {
        var number = new Intl.NumberFormat('id-ID');
        var rupiahShort = function (value) {
            if (value >= 1e9) {
                return 'Rp ' + number.format(Math.round(value / 1e8) / 10) + ' M';
            }

            if (value >= 1e6) {
                return 'Rp ' + number.format(Math.round(value / 1e6)) + ' jt';
            }

            return 'Rp ' + number.format(value);
        };

        window.Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;

        var builders = {
            activity: function (data) {
                return {
                    type: 'line',
                    data: {
                        labels: data.labels,
                        datasets: data.datasets.map(function (set) {
                            return {
                                label: set.label,
                                data: set.data,
                                borderColor: set.color,
                                backgroundColor: set.color,
                                cubicInterpolationMode: 'monotone',
                                pointRadius: 3
                            };
                        })
                    },
                    options: {
                        interaction: { mode: 'index', intersect: false },
                        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
                    }
                };
            },
            sales: function (data) {
                return {
                    type: 'bar',
                    data: {
                        labels: data.labels,
                        datasets: [
                            {
                                label: 'Unit terjual',
                                data: data.units,
                                backgroundColor: 'rgba(195, 0, 47, .75)',
                                yAxisID: 'y',
                                order: 2
                            },
                            {
                                type: 'line',
                                label: 'Nilai penjualan',
                                data: data.values,
                                borderColor: '#1c1c1e',
                                backgroundColor: '#1c1c1e',
                                cubicInterpolationMode: 'monotone',
                                yAxisID: 'value',
                                order: 1
                            }
                        ]
                    },
                    options: {
                        interaction: { mode: 'index', intersect: false },
                        scales: {
                            y: { beginAtZero: true, ticks: { precision: 0 }, title: { display: true, text: 'Unit' } },
                            value: {
                                position: 'right',
                                beginAtZero: true,
                                grid: { drawOnChartArea: false },
                                ticks: { callback: rupiahShort }
                            }
                        },
                        plugins: {
                            tooltip: {
                                callbacks: {
                                    label: function (context) {
                                        return context.dataset.yAxisID === 'value'
                                            ? context.dataset.label + ': Rp ' + number.format(context.parsed.y)
                                            : context.dataset.label + ': ' + number.format(context.parsed.y);
                                    }
                                }
                            }
                        }
                    }
                };
            }
        };

        chartCanvases.forEach(function (canvas) {
            var build = builders[canvas.getAttribute('data-chart')];

            if (!build) {
                return;
            }

            var config = build(JSON.parse(canvas.getAttribute('data-chart-data')));
            config.options.maintainAspectRatio = false;
            config.options.plugins = Object.assign({ legend: { position: 'bottom' } }, config.options.plugins);

            new window.Chart(canvas, config);
        });
    }

    // Live preview untuk foto Spare Part di panel Admin
    var sparePartPreview = document.getElementById('sparePartImagePreview');
    var sparePartEmpty = document.getElementById('sparePartEmptyPreview');
    var sparePartError = document.getElementById('sparePartImageError');
    var sparePartUrlInput = document.getElementById('image_url');
    var sparePartFileInput = document.getElementById('image');
    var sparePartRemove = document.getElementById('remove_image');
    var sparePartBtnCheck = document.getElementById('btnPreviewUrl');

    if (sparePartPreview && sparePartUrlInput && sparePartFileInput) {
        var showSparePartImage = function (src) {
            if (sparePartError) sparePartError.classList.add('d-none');
            if (sparePartEmpty) sparePartEmpty.classList.add('d-none');
            sparePartPreview.src = src;
            sparePartPreview.classList.remove('d-none');
            if (sparePartRemove) sparePartRemove.checked = false;
        };

        var showSparePartEmpty = function () {
            if (sparePartError) sparePartError.classList.add('d-none');
            sparePartPreview.classList.add('d-none');
            sparePartPreview.removeAttribute('src');
            if (sparePartEmpty) sparePartEmpty.classList.remove('d-none');
        };

        var showSparePartError = function () {
            sparePartPreview.classList.add('d-none');
            if (sparePartEmpty) sparePartEmpty.classList.add('d-none');
            if (sparePartError) sparePartError.classList.remove('d-none');
        };

        sparePartPreview.addEventListener('error', function () {
            if (sparePartPreview.getAttribute('src')) {
                showSparePartError();
            }
        });

        sparePartPreview.addEventListener('load', function () {
            if (sparePartPreview.getAttribute('src')) {
                if (sparePartError) sparePartError.classList.add('d-none');
                if (sparePartEmpty) sparePartEmpty.classList.add('d-none');
                sparePartPreview.classList.remove('d-none');
            }
        });

        var syncFromUrl = function () {
            var val = sparePartUrlInput.value.trim();
            if (val.indexOf('http://') === 0 || val.indexOf('https://') === 0) {
                sparePartFileInput.value = '';
                showSparePartImage(val);
            } else if (val === '') {
                if (sparePartFileInput.files && sparePartFileInput.files[0]) {
                    syncFromFile();
                } else {
                    showSparePartEmpty();
                }
            }
        };

        var syncFromFile = function () {
            var file = sparePartFileInput.files && sparePartFileInput.files[0];
            if (file && file.type.indexOf('image/') === 0) {
                sparePartUrlInput.value = '';
                showSparePartImage(URL.createObjectURL(file));
            } else if (!sparePartUrlInput.value.trim()) {
                showSparePartEmpty();
            }
        };

        sparePartUrlInput.addEventListener('input', syncFromUrl);
        sparePartUrlInput.addEventListener('change', syncFromUrl);
        if (sparePartBtnCheck) {
            sparePartBtnCheck.addEventListener('click', syncFromUrl);
        }

        sparePartFileInput.addEventListener('change', syncFromFile);

        if (sparePartRemove) {
            sparePartRemove.addEventListener('change', function () {
                if (sparePartRemove.checked) {
                    sparePartUrlInput.value = '';
                    sparePartFileInput.value = '';
                    showSparePartEmpty();
                }
            });
        }
    }
});
