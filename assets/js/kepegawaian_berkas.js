(function () {
    'use strict';

    const cfg = window.kpConfig || {};
    let summaryData = [];
    let categories = {};
    let activeCategory = ''; // '' = semua kategori
    let activeFilterStatus = 'all'; // all, complete, incomplete, empty
    let activeKeyword = '';
    let selectedNik = null;
    let selectedDetail = null;
    let isLoadingSummary = false;
    let isLoadingDetail = false;
    let isUploading = false;

    const esc = value => String(value == null ? '' : value).replace(/[&<>"']/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]));
    const number = value => Number(value || 0).toLocaleString('id-ID');

    async function request(url, options) {
        const response = await fetch(url, Object.assign({ credentials: 'same-origin', cache: 'no-store' }, options));
        let data;
        try { data = await response.json(); } catch (_) { throw new Error('Respons server tidak valid.'); }
        if (!response.ok || !data.success) throw new Error(data.message || 'Gagal memproses data berkas.');
        return data;
    }

    function showToast(message, type) {
        const box = document.getElementById('kpMessage');
        if (!box) return;
        box.className = 'alert kp-message alert-' + (type || 'info');
        box.textContent = message;
        box.classList.remove('d-none');
        box.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    async function loadSummary(forceReload = false) {
        if (isLoadingSummary && !forceReload) return;
        if (summaryData.length > 0 && !forceReload) {
            renderSummaryView();
            return;
        }

        isLoadingSummary = true;
        const container = document.getElementById('kpBerkasEmpList');
        if (container) {
            container.innerHTML = '<div class="text-center py-4 text-muted"><i class="fas fa-spinner fa-spin fa-2x mb-2"></i><br>Memuat data berkas kepegawaian...</div>';
        }

        try {
            const res = await request('api/berkas_pegawai.php?action=summary');
            summaryData = res.rows || [];
            categories = res.kategori || {};
            renderCategoryTabs();
            renderSummaryView();

            // Jika ada selectedNik, refresh detailnya juga
            if (selectedNik) {
                loadDetail(selectedNik);
            }
        } catch (err) {
            if (container) {
                container.innerHTML = '<div class="alert alert-danger m-3"><i class="fas fa-exclamation-triangle me-2"></i>' + esc(err.message) + '</div>';
            }
            showToast(err.message, 'danger');
        } finally {
            isLoadingSummary = false;
        }
    }

    function renderCategoryTabs() {
        const tabContainer = document.getElementById('kpBerkasCategoryTabs');
        if (!tabContainer) return;

        // Hitung count per kategori
        const counts = { '': summaryData.length };
        Object.keys(categories).forEach(k => counts[k] = 0);
        summaryData.forEach(row => {
            if (counts[row.kategori] !== undefined) counts[row.kategori]++;
        });

        let html = `
            <button type="button" class="btn btn-sm ${activeCategory === '' ? 'btn-success' : 'btn-outline-success'} kp-cat-filter" data-cat="">
                <i class="fas fa-layer-group me-1"></i>Semua Kategori <span class="badge bg-light text-dark ms-1">${counts[''] || 0}</span>
            </button>
        `;

        Object.entries(categories).forEach(([key, label]) => {
            const isActive = activeCategory === key;
            const count = counts[key] || 0;
            html += `
                <button type="button" class="btn btn-sm ${isActive ? 'btn-success' : 'btn-outline-success'} kp-cat-filter" data-cat="${esc(key)}">
                    ${esc(label)} <span class="badge ${isActive ? 'bg-white text-success' : 'bg-success text-white'} ms-1">${count}</span>
                </button>
            `;
        });

        tabContainer.innerHTML = html;
    }

    function getFilteredEmployees() {
        const kw = activeKeyword.toLowerCase().trim();
        return summaryData.filter(emp => {
            // Filter kategori
            if (activeCategory !== '' && emp.kategori !== activeCategory) {
                return false;
            }

            // Filter kelengkapan
            const percent = emp.wajib > 0 ? Math.round((emp.filled / emp.wajib) * 100) : 0;
            if (activeFilterStatus === 'complete' && percent < 100) return false;
            if (activeFilterStatus === 'incomplete' && (percent >= 100 || emp.files === 0)) return false;
            if (activeFilterStatus === 'empty' && emp.files > 0) return false;

            // Filter keyword
            if (kw) {
                const matchNik = (emp.nik || '').toLowerCase().includes(kw);
                const matchNama = (emp.nama || '').toLowerCase().includes(kw);
                const matchJbtn = (emp.jbtn || '').toLowerCase().includes(kw);
                const matchDep = (emp.departemen || '').toLowerCase().includes(kw);
                if (!matchNik && !matchNama && !matchJbtn && !matchDep) return false;
            }

            return true;
        });
    }

    function renderSummaryView() {
        const container = document.getElementById('kpBerkasEmpList');
        const countBadge = document.getElementById('kpBerkasEmpCount');
        if (!container) return;

        const list = getFilteredEmployees();
        if (countBadge) countBadge.textContent = `${list.length} pegawai`;

        if (list.length === 0) {
            container.innerHTML = `
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-folder-open fa-3x mb-2 opacity-50"></i>
                    <p class="mb-0">Tidak ada pegawai yang sesuai filter.</p>
                </div>
            `;
            return;
        }

        let html = '<div class="list-group list-group-flush">';
        list.forEach(emp => {
            const isSelected = selectedNik === emp.nik;
            const percent = emp.wajib > 0 ? Math.min(100, Math.round((emp.filled / emp.wajib) * 100)) : 0;
            let progressColor = 'bg-danger';
            if (percent >= 100) progressColor = 'bg-success';
            else if (percent >= 60) progressColor = 'bg-primary';
            else if (percent > 0) progressColor = 'bg-warning';

            const catLabel = categories[emp.kategori] || emp.kategori;

            html += `
                <a href="javascript:void(0)" class="list-group-item list-group-item-action kp-emp-card py-2 px-3 ${isSelected ? 'active' : ''}" data-nik="${esc(emp.nik)}">
                    <div class="d-flex w-100 justify-content-between align-items-center mb-1">
                        <strong class="text-truncate me-2 kp-emp-name" style="font-size: .88rem;">${esc(emp.nama)}</strong>
                        <span class="badge ${isSelected ? 'kp-badge-active-nip' : 'kp-badge-nip'} font-monospace" style="font-size: .72rem;">${esc(emp.nik)}</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center small mb-1 kp-emp-subtext">
                        <span class="text-truncate me-2" style="font-size: .76rem;"><i class="fas fa-briefcase me-1 opacity-75"></i>${esc(emp.jbtn || '-')}</span>
                        <span class="badge ${isSelected ? 'bg-light text-success border' : 'bg-success text-white'}" style="font-size: .68rem;">${esc(catLabel)}</span>
                    </div>
                    <div class="d-flex align-items-center gap-2 mt-1">
                        <div class="progress flex-grow-1" style="height: 5px;">
                            <div class="progress-bar ${progressColor}" role="progressbar" style="width: ${percent}%;" aria-valuenow="${percent}" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        <span class="small font-monospace fw-bold kp-emp-progress-text" style="font-size: .72rem; min-width: 58px; text-align: right;">${emp.filled}/${emp.wajib} (${percent}%)</span>
                    </div>
                    ${emp.files > 0 ? `
                        <div class="small mt-1 d-flex justify-content-between kp-emp-meta" style="font-size: .7rem;">
                            <span><i class="fas fa-file-alt me-1"></i>${emp.files} file terunggah</span>
                            ${emp.last_upload ? `<span><i class="fas fa-clock me-1"></i>${esc(emp.last_upload)}</span>` : ''}
                        </div>
                    ` : ''}
                </a>
            `;
        });
        html += '</div>';

        container.innerHTML = html;
    }

    async function loadDetail(nik, categoryOverride = '') {
        selectedNik = nik;
        isLoadingDetail = true;
        renderSummaryView(); // Update active selection state in list

        const detailPanel = document.getElementById('kpBerkasDetailPanel');
        if (!detailPanel) return;

        detailPanel.innerHTML = `
            <div class="card shadow-sm h-100">
                <div class="card-body text-center py-5 text-muted">
                    <i class="fas fa-spinner fa-spin fa-3x mb-3 text-primary"></i>
                    <h6>Memuat dokumen pegawai NIP: ${esc(nik)}...</h6>
                </div>
            </div>
        `;

        try {
            const url = `api/berkas_pegawai.php?action=detail&nik=${encodeURIComponent(nik)}` + (categoryOverride ? `&kategori=${encodeURIComponent(categoryOverride)}` : '');
            const res = await request(url);
            selectedDetail = res;
            renderDetailView();
        } catch (err) {
            detailPanel.innerHTML = `
                <div class="card shadow-sm h-100 border-danger">
                    <div class="card-body text-center py-5 text-danger">
                        <i class="fas fa-exclamation-triangle fa-3x mb-3"></i>
                        <h6>Gagal memuat berkas pegawai</h6>
                        <p class="small text-muted mb-3">${esc(err.message)}</p>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="window.kpBerkas.reloadDetail()">
                            <i class="fas fa-sync me-1"></i>Coba Lagi
                        </button>
                    </div>
                </div>
            `;
            showToast(err.message, 'danger');
        } finally {
            isLoadingDetail = false;
        }
    }

    function renderDetailView() {
        const detailPanel = document.getElementById('kpBerkasDetailPanel');
        if (!detailPanel || !selectedDetail) return;

        const p = selectedDetail.pegawai;
        const currentCategory = selectedDetail.kategori;
        const groups = selectedDetail.groups || [];

        // Hitung kelengkapan
        let totalWajib = 0;
        let totalFilled = 0;
        let totalFiles = 0;

        groups.forEach(g => {
            (g.items || []).forEach(item => {
                const count = (item.files || []).length;
                totalFiles += count;
                if (!item.lainnya) {
                    totalWajib++;
                    if (count > 0) totalFilled++;
                }
            });
        });

        const percent = totalWajib > 0 ? Math.min(100, Math.round((totalFilled / totalWajib) * 100)) : 0;
        let progressColor = 'bg-danger';
        if (percent >= 100) progressColor = 'bg-success';
        else if (percent >= 60) progressColor = 'bg-primary';
        else if (percent > 0) progressColor = 'bg-warning';

        let html = `
            <div class="card shadow-sm mb-3">
                <div class="card-body p-3">
                    <div class="row g-2 align-items-center">
                        <div class="col-md-7">
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <span class="badge bg-primary font-monospace">${esc(p.nik)}</span>
                                <h5 class="mb-0 fw-bold kp-detail-name">${esc(p.nama)}</h5>
                                <span class="badge kp-jk-badge border">${esc(p.jk || '-')}</span>
                            </div>
                            <div class="kp-detail-subtext small">
                                <span><i class="fas fa-briefcase me-1"></i>${esc(p.jbtn || '-')}</span> &bull; 
                                <span><i class="fas fa-building me-1"></i>${esc(p.departemen_nama || '-')}</span> &bull; 
                                <span><i class="fas fa-graduation-cap me-1"></i>${esc(p.pendidikan || '-')}</span>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="d-flex flex-column gap-1">
                                <div class="d-flex justify-content-between align-items-center small">
                                    <span class="fw-bold kp-detail-name">Kategori Berkas:</span>
                                    <select class="form-select form-select-sm w-auto py-0 px-2 fw-bold kp-category-select" id="kpBerkasDetailCategorySelect">
                                        ${Object.entries(categories).map(([k, label]) => `
                                            <option value="${esc(k)}" ${k === currentCategory ? 'selected' : ''}>${esc(label)}</option>
                                        `).join('')}
                                    </select>
                                </div>
                                <div class="d-flex align-items-center gap-2 mt-1">
                                    <div class="progress flex-grow-1" style="height: 8px;">
                                        <div class="progress-bar ${progressColor}" role="progressbar" style="width: ${percent}%;"></div>
                                    </div>
                                    <span class="small font-monospace fw-bold kp-emp-progress-text">${totalFilled}/${totalWajib} (${percent}%)</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center small kp-detail-meta" style="font-size: .75rem;">
                                    <span>Total file: <strong class="kp-detail-name">${totalFiles}</strong> dokumen</span>
                                    ${cfg.canWrite ? `
                                        <button type="button" class="btn btn-xs btn-primary kp-quick-upload py-0 px-2" data-nik="${esc(p.nik)}" data-cat="${esc(currentCategory)}" style="font-size: .75rem;">
                                            <i class="fas fa-cloud-upload-alt me-1"></i>Upload Berkas
                                        </button>
                                    ` : ''}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;

        if (!selectedDetail.storage) {
            html += `
                <div class="alert alert-warning py-2 small mb-3">
                    <i class="fas fa-info-circle me-1"></i>
                    Folder penyimpanan webapps belum terdeteksi di server ini. Anda tetap dapat mengaudit kelengkapan data database, namun upload/preview file fisik memerlukan sinkronisasi folder webapps Khanza.
                </div>
            `;
        }

        // Render Accordion per Group
        html += `<div class="accordion kp-berkas-groups" id="kpBerkasAccordion">`;

        groups.forEach((group, gIdx) => {
            const gItems = group.items || [];
            let gFilled = 0;
            let gWajib = 0;
            let gFiles = 0;

            gItems.forEach(item => {
                const count = (item.files || []).length;
                gFiles += count;
                if (!item.lainnya) {
                    gWajib++;
                    if (count > 0) gFilled++;
                }
            });

            const gPercent = gWajib > 0 ? Math.round((gFilled / gWajib) * 100) : (gFiles > 0 ? 100 : 0);
            const isComplete = gWajib > 0 ? gFilled >= gWajib : gFiles > 0;
            const collapseId = `kpGroup_${group.kode}_${gIdx}`;

            html += `
                <div class="accordion-item mb-2 shadow-sm border">
                    <h2 class="accordion-header" id="heading_${collapseId}">
                        <button class="accordion-button py-2 px-3 ${gIdx === 0 ? '' : 'collapsed'}" type="button" data-bs-toggle="collapse" data-bs-target="#${collapseId}" aria-expanded="${gIdx === 0 ? 'true' : 'false'}" aria-controls="${collapseId}">
                            <div class="d-flex w-100 justify-content-between align-items-center me-2">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="fas ${group.icon || 'fa-folder'} text-success fa-fw"></i>
                                    <strong class="kp-detail-name" style="font-size: .88rem;">${esc(group.label)}</strong>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge ${isComplete ? 'bg-success' : (gFiles > 0 ? 'bg-warning text-dark' : 'kp-badge-nip')}" style="font-size: .72rem;">
                                        ${gWajib > 0 ? `${gFilled}/${gWajib} Terpenuhi` : `${gFiles} file`}
                                    </span>
                                    <span class="badge bg-secondary font-monospace" style="font-size: .7rem;">${gFiles} file</span>
                                </div>
                            </div>
                        </button>
                    </h2>
                    <div id="${collapseId}" class="accordion-collapse collapse ${gIdx === 0 ? 'show' : ''}" aria-labelledby="heading_${collapseId}">
                        <div class="accordion-body p-2">
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0" style="font-size: .78rem;">
                                    <thead class="kp-table-header">
                                        <tr>
                                            <th style="width: 32px;" class="text-center">Status</th>
                                            <th style="width: 220px;">Jenis Berkas</th>
                                            <th>File Tersimpan</th>
                                            <th style="width: 75px;" class="text-center">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
            `;

            gItems.forEach(item => {
                const files = item.files || [];
                const hasFile = files.length > 0;
                const isOther = item.lainnya;

                html += `
                    <tr>
                        <td class="text-center">
                            ${hasFile ? `
                                <i class="fas fa-check-circle text-success fs-6" title="Sudah diunggah"></i>
                            ` : (isOther ? `
                                <i class="fas fa-circle text-muted opacity-50" style="font-size: .6rem;" title="Dokumen pelengkap/opsional"></i>
                            ` : `
                                <i class="fas fa-times-circle text-danger fs-6 opacity-75" title="Belum diunggah"></i>
                            `)}
                        </td>
                        <td>
                            <strong class="${hasFile ? 'kp-doc-name-filled' : 'kp-doc-name-empty'}">${esc(item.nama)}</strong>
                            <div class="kp-emp-subtext font-monospace" style="font-size: .68rem;">Kode: ${esc(item.kode)}</div>
                        </td>
                        <td>
                            ${files.length > 0 ? `
                                <div class="d-flex flex-wrap gap-1 align-items-center">
                                    ${files.map(f => {
                                        const isPdf = f.ext === 'pdf';
                                        const iconClass = isPdf ? 'fa-file-pdf text-danger' : 'fa-file-image text-primary';
                                        return `
                                            <div class="btn-group btn-group-sm kp-file-chip rounded" role="group">
                                                <button type="button" class="btn kp-file-btn btn-xs py-0 px-2 kp-preview-file text-truncate" 
                                                    style="max-width: 190px; font-size: .72rem;"
                                                    data-nik="${esc(p.nik)}" 
                                                    data-kode="${esc(item.kode)}" 
                                                    data-berkas="${esc(f.berkas)}" 
                                                    data-nama="${esc(item.nama)}" 
                                                    data-filename="${esc(f.nama_file)}"
                                                    data-ext="${esc(f.ext)}"
                                                    data-ada="${f.ada ? '1' : '0'}"
                                                    data-tgl="${esc(f.tgl)}">
                                                    <i class="fas ${iconClass} me-1"></i>${esc(f.nama_file)}
                                                    <span class="kp-emp-subtext ms-1" style="font-size: .65rem;">(${esc(f.tgl)})</span>
                                                </button>
                                                ${cfg.canWrite ? `
                                                    <button type="button" class="btn btn-outline-danger btn-xs py-0 px-1 kp-file-del-btn kp-delete-file" 
                                                        title="Hapus berkas ini"
                                                        data-nik="${esc(p.nik)}" 
                                                        data-kode="${esc(item.kode)}" 
                                                        data-berkas="${esc(f.berkas)}" 
                                                        data-filename="${esc(f.nama_file)}">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
                                                ` : ''}
                                            </div>
                                        `;
                                    }).join('')}
                                </div>
                            ` : `
                                <span class="kp-emp-subtext fst-italic" style="font-size: .72rem;">Belum ada file</span>
                            `}
                        </td>
                        <td class="text-center">
                            ${cfg.canWrite ? `
                                <button type="button" class="btn btn-outline-success btn-xs py-0 px-2 kp-item-upload" 
                                    data-nik="${esc(p.nik)}" 
                                    data-kode="${esc(item.kode)}" 
                                    data-nama="${esc(item.nama)}"
                                    data-cat="${esc(currentCategory)}"
                                    title="Upload ${esc(item.nama)}">
                                    <i class="fas fa-upload me-1"></i>Upload
                                </button>
                            ` : `
                                <span class="text-muted">-</span>
                            `}
                        </td>
                    </tr>
                `;
            });

            html += `
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });

        html += `</div>`;
        detailPanel.innerHTML = html;
    }

    // Modal Upload
    function openUploadModal(nik, kode = '', namaBerkas = '', kategori = '') {
        if (!cfg.canWrite) {
            showToast('Anda tidak memiliki hak akses upload berkas pegawai.', 'warning');
            return;
        }

        const modalEl = document.getElementById('kpUploadModal');
        if (!modalEl) return;

        const p = selectedDetail && selectedDetail.pegawai && selectedDetail.pegawai.nik === nik 
            ? selectedDetail.pegawai 
            : summaryData.find(e => e.nik === nik);

        document.getElementById('kpUploadNik').value = nik;
        document.getElementById('kpUploadNamaPegawai').value = p ? p.nama : nik;
        document.getElementById('kpUploadKategori').value = kategori || (selectedDetail ? selectedDetail.kategori : '');

        // Populate options jenis berkas
        const selectKode = document.getElementById('kpUploadKode');
        selectKode.innerHTML = '<option value="">Pilih Jenis Berkas...</option>';

        if (selectedDetail && selectedDetail.groups) {
            selectedDetail.groups.forEach(g => {
                const optgroup = document.createElement('optgroup');
                optgroup.label = g.label;
                (g.items || []).forEach(item => {
                    const opt = document.createElement('option');
                    opt.value = item.kode;
                    opt.textContent = `${item.nama} (${item.kode})`;
                    if (item.kode === kode) opt.selected = true;
                    optgroup.appendChild(opt);
                });
                selectKode.appendChild(optgroup);
            });
        }

        // Reset file input
        const fileInput = document.getElementById('kpUploadFileInput');
        fileInput.value = '';
        document.getElementById('kpUploadFileList').innerHTML = '';
        document.getElementById('kpUploadDate').value = new Date().toISOString().slice(0, 10);
        document.getElementById('kpUploadError').classList.add('d-none');
        document.getElementById('kpUploadProgress').classList.add('d-none');

        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
    }

    async function submitUpload(e) {
        e.preventDefault();
        if (isUploading) return;

        const form = document.getElementById('kpUploadForm');
        const fileInput = document.getElementById('kpUploadFileInput');
        const errBox = document.getElementById('kpUploadError');
        const progressBox = document.getElementById('kpUploadProgress');
        const progressBar = progressBox.querySelector('.progress-bar');
        const submitBtn = document.getElementById('kpUploadSubmitBtn');

        errBox.classList.add('d-none');

        const files = fileInput.files;
        if (!files || files.length === 0) {
            errBox.textContent = 'Silakan pilih file yang akan diunggah.';
            errBox.classList.remove('d-none');
            return;
        }

        const kode = document.getElementById('kpUploadKode').value;
        if (!kode) {
            errBox.textContent = 'Pilih jenis berkas terlebih dahulu.';
            errBox.classList.remove('d-none');
            return;
        }

        const formData = new FormData(form);
        formData.set('action', 'upload');
        formData.set('csrf', cfg.csrf);

        isUploading = true;
        submitBtn.disabled = true;
        progressBox.classList.remove('d-none');
        progressBar.style.width = '50%';
        progressBar.textContent = 'Mengunggah file...';

        try {
            const res = await request('api/berkas_pegawai.php', {
                method: 'POST',
                body: formData
            });

            progressBar.style.width = '100%';
            progressBar.textContent = 'Selesai!';

            showToast(res.message, 'success');

            // Tutup modal
            const modalEl = document.getElementById('kpUploadModal');
            const modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();

            // Refresh data
            const nik = document.getElementById('kpUploadNik').value;
            const currentCat = document.getElementById('kpUploadKategori').value;
            await loadSummary(true);
            await loadDetail(nik, currentCat);
        } catch (err) {
            errBox.textContent = err.message;
            errBox.classList.remove('d-none');
        } finally {
            isUploading = false;
            submitBtn.disabled = false;
            progressBox.classList.add('d-none');
            progressBar.style.width = '0%';
        }
    }

    function isDarkTheme() {
        const theme = localStorage.getItem('app_theme') || 'theme-glass-animated';
        return theme.includes('glass') || 
               document.documentElement.classList.contains('theme-glass-solid') || 
               document.documentElement.classList.contains('theme-glass-animated');
    }

    // Delete File
    async function deleteFile(nik, kode, berkas, filename) {
        const isDark = isDarkTheme();
        const displayFilename = filename || berkas || 'berkas';

        if (!cfg.canWrite) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'warning',
                    title: 'Akses Ditolak',
                    text: 'Anda tidak memiliki hak akses untuk menghapus berkas pegawai.',
                    confirmButtonText: 'Tutup',
                    confirmButtonColor: '#64748b',
                    background: isDark ? '#1e293b' : '#ffffff',
                    color: isDark ? '#f8fafc' : '#1e293b',
                    customClass: { popup: 'kp-swal-popup' }
                });
            } else {
                alert('Anda tidak memiliki hak akses untuk menghapus berkas pegawai.');
            }
            return;
        }

        if (typeof Swal !== 'undefined') {
            const result = await Swal.fire({
                title: 'Hapus Berkas?',
                html: `
                    <div class="text-start" style="font-size: 0.9rem;">
                        <p class="mb-2">Apakah Anda yakin ingin menghapus berkas berikut?</p>
                        <div class="kp-swal-filebox p-2 mb-3 rounded" style="word-break: break-all;">
                            <i class="fas fa-file-alt text-danger me-2"></i><strong>${esc(displayFilename)}</strong>
                        </div>
                        <div class="p-2 rounded small" style="background: rgba(245, 158, 11, 0.1); border-left: 3px solid #f59e0b; color: ${isDark ? '#fcd34d' : '#b45309'};">
                            <i class="fas fa-exclamation-triangle me-1"></i> File fisik dan data berkas pegawai ini akan dihapus dari sistem secara permanen.
                        </div>
                    </div>
                `,
                icon: 'warning',
                iconColor: '#ef4444',
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-trash-alt me-1"></i> Ya, Hapus Berkas',
                cancelButtonText: '<i class="fas fa-times me-1"></i> Batal',
                confirmButtonColor: '#dc2626',
                cancelButtonColor: isDark ? '#475569' : '#64748b',
                reverseButtons: true,
                focusCancel: true,
                background: isDark ? '#1e293b' : '#ffffff',
                color: isDark ? '#f8fafc' : '#1e293b',
                customClass: { popup: 'kp-swal-popup' }
            });

            if (!result.isConfirmed) return;

            // Tutup preview modal jika sedang terbuka
            const modalEl = document.getElementById('kpPreviewModal');
            if (modalEl) {
                const modal = bootstrap.Modal.getInstance(modalEl) || (bootstrap.Modal.getOrCreateInstance ? bootstrap.Modal.getOrCreateInstance(modalEl) : null);
                if (modal) modal.hide();
            }

            // Tampilkan loading dialog
            Swal.fire({
                title: 'Menghapus Berkas...',
                html: '<div class="small text-muted py-2">Sedang memproses penghapusan berkas...</div>',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                },
                background: isDark ? '#1e293b' : '#ffffff',
                color: isDark ? '#f8fafc' : '#1e293b',
                customClass: { popup: 'kp-swal-popup' }
            });

            const payload = new FormData();
            payload.set('action', 'delete');
            payload.set('csrf', cfg.csrf);
            payload.set('nik', nik);
            payload.set('kode', kode);
            payload.set('berkas', berkas);

            try {
                const res = await request('api/berkas_pegawai.php', {
                    method: 'POST',
                    body: payload
                });

                Swal.fire({
                    icon: 'success',
                    iconColor: '#10b981',
                    title: 'Berkas Berhasil Dihapus',
                    text: res.message || 'Berkas telah berhasil dihapus.',
                    timer: 2000,
                    timerProgressBar: true,
                    showConfirmButton: false,
                    background: isDark ? '#1e293b' : '#ffffff',
                    color: isDark ? '#f8fafc' : '#1e293b',
                    customClass: { popup: 'kp-swal-popup' }
                });

                showToast(res.message, 'success');
                await loadSummary(true);
                if (selectedNik) {
                    const curCat = selectedDetail ? selectedDetail.kategori : '';
                    await loadDetail(selectedNik, curCat);
                }
            } catch (err) {
                Swal.fire({
                    icon: 'error',
                    iconColor: '#ef4444',
                    title: 'Gagal Menghapus Berkas',
                    text: err.message || 'Terjadi kesalahan saat menghapus berkas.',
                    confirmButtonText: 'Tutup',
                    confirmButtonColor: '#dc2626',
                    background: isDark ? '#1e293b' : '#ffffff',
                    color: isDark ? '#f8fafc' : '#1e293b',
                    customClass: { popup: 'kp-swal-popup' }
                });
            }
        } else {
            // Fallback bawaan browser jika Swal tidak tersedia
            const ok = confirm(`Hapus berkas "${displayFilename}"?\n\nFile akan dihapus dari data berkas pegawai ini.`);
            if (!ok) return;

            const modalEl = document.getElementById('kpPreviewModal');
            if (modalEl) {
                const modal = bootstrap.Modal.getInstance(modalEl) || (bootstrap.Modal.getOrCreateInstance ? bootstrap.Modal.getOrCreateInstance(modalEl) : null);
                if (modal) modal.hide();
            }

            const payload = new FormData();
            payload.set('action', 'delete');
            payload.set('csrf', cfg.csrf);
            payload.set('nik', nik);
            payload.set('kode', kode);
            payload.set('berkas', berkas);

            try {
                const res = await request('api/berkas_pegawai.php', {
                    method: 'POST',
                    body: payload
                });

                showToast(res.message, 'success');
                await loadSummary(true);
                if (selectedNik) {
                    const curCat = selectedDetail ? selectedDetail.kategori : '';
                    await loadDetail(selectedNik, curCat);
                }
            } catch (err) {
                alert('Gagal menghapus berkas: ' + err.message);
            }
        }
    }

    // Preview File
    function previewFile(data) {
        const modalEl = document.getElementById('kpPreviewModal');
        if (!modalEl) return;

        const titleEl = document.getElementById('kpPreviewTitle');
        const metaEl = document.getElementById('kpPreviewMeta');
        const bodyEl = document.getElementById('kpPreviewBody');
        const openNewTabBtn = document.getElementById('kpPreviewOpenNewTab');
        const downloadBtn = document.getElementById('kpPreviewDownload');

        titleEl.textContent = `${data.nama} — ${data.filename}`;
        metaEl.textContent = `NIP: ${data.nik} • Tgl Upload: ${data.tgl} • Format: ${data.ext.toUpperCase()}`;

        const fileUrl = `api/berkas_pegawai.php?action=file&nik=${encodeURIComponent(data.nik)}&kode=${encodeURIComponent(data.kode)}&berkas=${encodeURIComponent(data.berkas)}`;
        const downloadUrl = fileUrl + '&download=1';

        openNewTabBtn.href = fileUrl;
        downloadBtn.href = downloadUrl;

        const deleteBtn = document.getElementById('kpPreviewDelete');
        if (deleteBtn) {
            deleteBtn.dataset.nik = data.nik || '';
            deleteBtn.dataset.kode = data.kode || '';
            deleteBtn.dataset.berkas = data.berkas || '';
            deleteBtn.dataset.filename = data.filename || '';
        }

        bodyEl.innerHTML = '<div class="text-center py-5 text-muted"><i class="fas fa-spinner fa-spin fa-2x mb-2"></i><br>Memuat preview...</div>';

        const isPdf = data.ext.toLowerCase() === 'pdf';
        const isImage = ['jpg', 'jpeg', 'png'].includes(data.ext.toLowerCase());

        if (data.ada === '0') {
            bodyEl.innerHTML = `
                <div class="alert alert-warning m-4 text-center">
                    <i class="fas fa-exclamation-triangle fa-2x mb-2 text-warning"></i>
                    <h6>File fisik belum tersinkronisasi di server lokal</h6>
                    <p class="small text-muted mb-0">
                        File tercatat di database Khanza (<code>${esc(data.berkas)}</code>), namun file fisik belum ada di folder webapps penggajian. 
                        Anda dapat mengunggah file baru untuk memperbarui berkas ini.
                    </p>
                </div>
            `;
        } else if (isPdf) {
            bodyEl.innerHTML = `
                <iframe src="${fileUrl}#toolbar=1" style="width: 100%; height: 75vh; border: none;" title="PDF Preview"></iframe>
            `;
        } else if (isImage) {
            bodyEl.innerHTML = `
                <div class="text-center p-2" style="background: rgba(0,0,0,0.03); overflow: auto; max-height: 75vh;">
                    <img src="${fileUrl}" class="img-fluid rounded shadow-sm" alt="Preview Gambar" style="max-height: 72vh; object-fit: contain;">
                </div>
            `;
        } else {
            bodyEl.innerHTML = `
                <div class="text-center py-5">
                    <i class="fas fa-file fa-3x text-secondary mb-3"></i>
                    <h6>File format .${esc(data.ext)}</h6>
                    <p class="small text-muted">Preview otomatis tidak didukung untuk tipe file ini.</p>
                    <a href="${downloadUrl}" class="btn btn-sm btn-primary"><i class="fas fa-download me-1"></i>Unduh File</a>
                </div>
            `;
        }

        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
    }

    // Inisialisasi Event Handlers
    function bindEvents() {
        // Filter kategori buttons
        $(document).on('click', '.kp-cat-filter', function () {
            activeCategory = this.dataset.cat || '';
            renderCategoryTabs();
            renderSummaryView();
        });

        // Filter status kelengkapan
        $('#kpBerkasStatusFilter').on('change', function () {
            activeFilterStatus = this.value;
            renderSummaryView();
        });

        // Filter keyword pencarian (debounce)
        let searchTimer = null;
        $('#kpBerkasSearch').on('input', function () {
            clearTimeout(searchTimer);
            const val = this.value;
            searchTimer = setTimeout(() => {
                activeKeyword = val;
                renderSummaryView();
            }, 250);
        });

        // Tombol reset search
        $('#kpBerkasResetSearch').on('click', function () {
            $('#kpBerkasSearch').val('');
            activeKeyword = '';
            renderSummaryView();
        });

        // Tombol refresh summary
        $('#kpBerkasRefresh').on('click', function () {
            loadSummary(true);
        });

        // Klik pegawai di list
        $(document).on('click', '.kp-emp-card', function () {
            const nik = this.dataset.nik;
            loadDetail(nik);
        });

        // Ganti kategori berkas di detail pegawai
        $(document).on('change', '#kpBerkasDetailCategorySelect', function () {
            if (selectedNik) {
                loadDetail(selectedNik, this.value);
            }
        });

        // Tombol quick upload di header detail
        $(document).on('click', '.kp-quick-upload', function () {
            const nik = this.dataset.nik;
            const cat = this.dataset.cat;
            openUploadModal(nik, '', '', cat);
        });

        // Tombol upload pada item dokumen tertentu
        $(document).on('click', '.kp-item-upload', function () {
            const nik = this.dataset.nik;
            const kode = this.dataset.kode;
            const nama = this.dataset.nama;
            const cat = this.dataset.cat;
            openUploadModal(nik, kode, nama, cat);
        });

        // Form submit upload
        $('#kpUploadForm').on('submit', submitUpload);

        // Preview file terpilih pada input file
        $('#kpUploadFileInput').on('change', function () {
            const listEl = document.getElementById('kpUploadFileList');
            listEl.innerHTML = '';
            const files = Array.from(this.files || []);
            if (files.length === 0) return;

            let html = '<div class="small fw-bold mb-1">File terpilih (' + files.length + '):</div><ul class="list-unstyled mb-0 small">';
            files.forEach(f => {
                const sizeKb = Math.round(f.size / 1024);
                html += `<li><i class="fas fa-check text-success me-1"></i>${esc(f.name)} <span class="text-muted">(${sizeKb} KB)</span></li>`;
            });
            html += '</ul>';
            listEl.innerHTML = html;
        });

        // Tombol preview file
        $(document).on('click', '.kp-preview-file', function () {
            previewFile(this.dataset);
        });

        // Tombol hapus file
        $(document).on('click', '.kp-delete-file, .kp-file-del-btn', function (e) {
            e.preventDefault();
            e.stopPropagation();
            const d = this.dataset;
            deleteFile(d.nik, d.kode, d.berkas, d.filename);
        });

        // Tombol hapus file dari modal preview
        $(document).on('click', '#kpPreviewDelete', function (e) {
            e.preventDefault();
            const d = this.dataset;
            if (!d.nik) return;
            deleteFile(d.nik, d.kode, d.berkas, d.filename);
        });
    }

    // Expose public API
    window.kpBerkas = {
        init: function () {
            loadSummary(false);
        },
        openEmployee: function (nik) {
            // Switch tab to berkas
            if (window.setKpTab) {
                window.setKpTab('berkas');
            } else {
                const tabBtn = document.getElementById('kpBerkasTab');
                if (tabBtn) tabBtn.click();
            }

            // Jika summary belum ada, load dulu
            if (summaryData.length === 0) {
                loadSummary(false).then(() => {
                    loadDetail(nik);
                });
            } else {
                loadDetail(nik);
            }
        },
        reloadDetail: function () {
            if (selectedNik) {
                const curCat = selectedDetail ? selectedDetail.kategori : '';
                loadDetail(selectedNik, curCat);
            }
        }
    };

    $(document).ready(function () {
        bindEvents();
    });
})();
