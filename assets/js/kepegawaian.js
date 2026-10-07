(function () {
    'use strict';
    const cfg = window.kpConfig;
    let mode = 'list', rows = [], table = null, loading = false, saving = false, loadedDate = '';
    const form = document.getElementById('kpForm');
    const indexForm = document.getElementById('kpIndexForm');
    const number = value => Number(value || 0).toLocaleString('id-ID', { maximumFractionDigits: 2 });
    const money = value => 'Rp ' + number(value);
    const esc = value => String(value == null ? '' : value).replace(/[&<>"']/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]));
    const listColumns = [
        ['nik', 'NIP'], ['nama', 'Nama'], ['jk', 'J.K.'], ['jbtn', 'Jabatan'], ['jenjang', 'Jenjang'],
        ['kelompok', 'Kelompok Jabatan'], ['departemen_nama', 'Departemen'], ['bidang', 'Bagian'],
        ['risiko', 'Risiko Kerja'], ['emergency', 'Tingkat Emergency'], ['status_wp', 'Status WP'],
        ['status_kerja', 'Status Karyawan'], ['npwp', 'NPWP'], ['pendidikan', 'Pendidikan'],
        ['tmp_lahir', 'Tempat Lahir'], ['tgl_lahir', 'Tanggal Lahir'], ['alamat', 'Alamat'], ['kota', 'Kota'],
        ['mulai_kerja', 'Mulai Kerja'], ['lama_kerja', 'Lama Kerja'], ['ms_kerja', 'Kode Masa Kerja'],
        ['indexins', 'Kode Index'], ['bpd', 'Bank'], ['rekening', 'Rekening'], ['stts_aktif', 'Status'],
        ['wajibmasuk', 'Kode Wajib Masuk'], ['mulai_kontrak', 'Mulai Kontrak'], ['no_ktp', 'No. KTP']
    ];
    const indexColumns = [
        ['nik', 'NIP'], ['nama', 'Nama'], ['jbtn', 'Jabatan'], ['pendidikan', 'Pendidikan'],
        ['mulai_kerja', 'Mulai Kerja'], ['lama_kerja', 'Lama Kerja'], ['index_pendidikan', 'Index Pendidikan', 'number'],
        ['index_masa_kerja', 'Index Masa Kerja', 'number'], ['index_status', 'Index Status', 'number'],
        ['index_jabatan', 'Index Jenjang Jabatan', 'number'], ['index_kelompok', 'Index Kelompok Jabatan', 'number'],
        ['index_resiko', 'Index Risiko Kerja', 'number'], ['index_emergency', 'Index Tingkat Emergency', 'number'],
        ['index_evaluasi', 'Index Evaluasi Kinerja', 'number'], ['index_pencapaian', 'Index Pencapaian Kinerja', 'number'],
        ['indek', 'Index Struktural', 'number'], ['pengurang', 'Pengurang (%)', 'number'], ['total_index', 'Total Index', 'number'],
        ['mulai_kontrak', 'Mulai Kontrak'], ['lama_kontrak', 'Lama Kontrak'], ['gaji_pokok', 'Gaji Pokok', 'money'],
        ['hakcuti', 'Hak Cuti', 'number'], ['total_cuti_diambil', 'Cuti Diambil', 'number'], ['sisa_cuti', 'Sisa Cuti', 'number'],
        ['dankes', 'Dana Kesehatan', 'money'], ['sisa_dankes', 'Sisa Dana Kesehatan', 'money']
    ];
    function message(text, kind) {
        const box = document.getElementById('kpMessage');
        box.className = 'alert kp-message alert-' + (kind || 'info');
        box.textContent = text;
    }
    function setTab(tab) {
        mode = tab;
        document.querySelectorAll('[data-kp-tab]').forEach(btn => {
            const selected = btn.dataset.kpTab === tab;
            btn.classList.toggle('active', selected);
            btn.setAttribute('aria-selected', String(selected));
        });
        document.getElementById('kpInputPanel').classList.toggle('d-none', tab !== 'input');
        document.getElementById('kpDataPanel').classList.toggle('d-none', tab === 'input');
        document.getElementById('kpFilters').classList.toggle('d-none', tab === 'input');
        document.getElementById('kpReferenceHelp').classList.toggle('d-none', tab === 'input');
        document.getElementById('kpIndexEditor').classList.add('d-none');
        if (tab !== 'input') {
            document.getElementById('kpDataPanel').setAttribute('aria-labelledby', tab === 'index' ? 'kpIndexTab' : 'kpListTab');
            renderTable();
        }
    }
    function renderTable() {
        if (mode === 'input') return;
        const index = mode === 'index';
        document.getElementById('kpTableTitle').textContent = index ? 'Index Pegawai' : 'List Data Pegawai';
        document.getElementById('kpIndexNote').classList.toggle('d-none', !index);
        if (table) { table.destroy(); table = null; }
        $('#kpTable').empty();
        const columns = [{ title: 'Proses', data: 'id', orderable: false, searchable: false, render: id => {
            const safeId = Number(id);
            return '<button type="button" class="btn btn-outline-primary btn-sm kp-detail" data-id="' + safeId + '">' + (cfg.canWrite ? 'Edit Data' : 'Detail') + '</button>' + (index && cfg.canWrite ? ' <button type="button" class="btn btn-outline-info btn-sm kp-edit-index" data-id="' + safeId + '">Edit Index</button>' : '');
        }}];
        (index ? indexColumns : listColumns).forEach(def => columns.push({
            title: def[1], data: def[0], defaultContent: '', render: (value, type) => {
                if (type !== 'display') return def[2] ? Number(value || 0) : (value || '');
                return def[2] === 'money' ? money(value) : def[2] === 'number' ? number(value) : esc(value == null || value === '' ? '-' : value);
            }
        }));
        table = $('#kpTable').DataTable({
            data: rows, columns: columns, scrollX: true, pageLength: 25,
            lengthMenu: [10, 25, 50, 100], order: [],
            dom: 'Blfrtip', buttons: [{ extend: 'colvis', text: 'Kolom' }],
            language: { emptyTable: 'Tidak ada data pegawai untuk filter ini.', search: 'Cari di tabel:', lengthMenu: 'Tampilkan _MENU_ baris', info: '_START_–_END_ dari _TOTAL_ pegawai', infoEmpty: 'Tidak ada data', infoFiltered: '(dari _MAX_ pegawai)', zeroRecords: 'Data tidak ditemukan.', paginate: { previous: 'Sebelumnya', next: 'Selanjutnya' } }
        });
        table.columns.adjust();
    }
    async function request(url, options) {
        const response = await fetch(url, Object.assign({ credentials: 'same-origin', cache: 'no-store' }, options));
        let data;
        try { data = await response.json(); } catch (_) { throw new Error('Respons tidak valid. Periksa koneksi atau login kembali.'); }
        if (!response.ok || !data.success) throw new Error(data.message || 'Data gagal diproses.');
        return data;
    }
    async function loadData() {
        if (loading || !cfg.ready) return;
        loading = true;
        document.getElementById('kpLoad').disabled = true;
        document.getElementById('kpExport').disabled = true;
        document.getElementById('kpTableInfo').textContent = 'Memuat data...';
        try {
            const params = new URLSearchParams({ status: document.getElementById('kpStatus').value, departemen: document.getElementById('kpDepartment').value, keyword: document.getElementById('kpKeyword').value, tanggal: document.getElementById('kpDate').value });
            const data = await request('api/data_kepegawaian.php?' + params.toString());
            rows = data.rows;
            loadedDate = data.tanggal;
            document.getElementById('kpIndexNote').textContent = 'Perhitungan per ' + loadedDate + '. Masa kerja memakai tahun dan bulan yang sudah lengkap; index masa kerja 2 per tahun, maksimal 14. Index lainnya mengikuti nilai master kepegawaian.';
            document.getElementById('kpCount').textContent = number(data.total_pegawai);
            document.getElementById('kpActive').textContent = number(rows.filter(r => r.stts_aktif === 'AKTIF').length);
            document.getElementById('kpUnits').textContent = number(new Set(rows.map(r => r.departemen)).size);
            document.getElementById('kpIndexSum').textContent = number(rows.reduce((sum, row) => sum + Number(row.total_index || 0), 0));
            document.getElementById('kpTableInfo').textContent = rows.length + ' pegawai • Acuan ' + data.tanggal;
            renderTable();
        } catch (error) {
            rows = [];
            ['kpCount', 'kpActive', 'kpUnits', 'kpIndexSum'].forEach(id => document.getElementById(id).textContent = '0');
            document.getElementById('kpTableInfo').textContent = 'Gagal memuat data';
            renderTable();
            message(error.message, 'danger');
        } finally {
            loading = false;
            document.getElementById('kpLoad').disabled = false;
            document.getElementById('kpExport').disabled = !rows.length;
        }
    }
    function resetForm() {
        form.reset(); form.elements.id.value = '0'; form.elements.nik.readOnly = false;
        document.getElementById('kpNipHelp').classList.add('d-none');
        document.getElementById('kpFormTitle').textContent = 'Input Data Pegawai Baru';
    }
    function openEmployee(row) {
        resetForm();
        Array.from(form.elements).forEach(input => {
            if (input.name && Object.prototype.hasOwnProperty.call(row, input.name)) {
                const value = row[input.name] == null ? '' : String(row[input.name]);
                if (input.tagName === 'SELECT' && value && !Array.from(input.options).some(o => o.value === value)) input.add(new Option(value + ' (referensi lama)', value));
                input.value = value;
            }
        });
        form.elements.nik.readOnly = true;
        document.getElementById('kpNipHelp').classList.remove('d-none');
        document.getElementById('kpFormTitle').textContent = (cfg.canWrite ? 'Edit Data: ' : 'Detail: ') + row.nama;
        setTab('input');
    }
    async function save(event, target, action) {
        event.preventDefault();
        if (saving || !cfg.canWrite || !target.reportValidity()) return;
        saving = true;
        const button = target.querySelector('button[type="submit"]');
        button.disabled = true;
        const payload = new FormData(target);
        payload.set('action', action); payload.set('csrf', cfg.csrf);
        try {
            const result = await request('api/data_kepegawaian.php', { method: 'POST', body: payload });
            if (action === 'save') { resetForm(); setTab('list'); }
            document.getElementById('kpIndexEditor').classList.add('d-none');
            await loadData();
            message(result.message, 'success');
        } catch (error) { message(error.message, 'danger'); }
        finally { saving = false; button.disabled = false; }
    }
    function exportData() {
        if (!table || !rows.length) return;
        const defs = mode === 'index' ? indexColumns : listColumns;
        const quote = value => {
            let text = String(value == null ? '' : value);
            if (/^[\s]*[=+\-@]/.test(text) || /^[\t\r\n]/.test(text)) text = "'" + text;
            return '"' + text.replace(/"/g, '""') + '"';
        };
        const lines = [defs.map(d => quote(d[1])).join(';')];
        table.rows({ search: 'applied', order: 'applied' }).data().toArray().forEach(row => lines.push(defs.map(d => quote(row[d[0]])).join(';')));
        const url = URL.createObjectURL(new Blob(['\uFEFF' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8;' }));
        const link = document.createElement('a'); link.href = url; link.download = 'kepegawaian-' + mode + '-' + loadedDate + '.csv';
        document.body.appendChild(link); link.click(); link.remove(); setTimeout(() => URL.revokeObjectURL(url), 1000);
    }
    document.querySelectorAll('[data-kp-tab]').forEach(btn => btn.addEventListener('click', () => { if (!saving) setTab(btn.dataset.kpTab); }));
    document.getElementById('kpFilters').addEventListener('submit', event => { event.preventDefault(); loadData(); });
    document.getElementById('kpNew').addEventListener('click', () => { if (!saving) resetForm(); });
    document.getElementById('kpReset').addEventListener('click', () => { if (!saving) resetForm(); });
    document.getElementById('kpExport').addEventListener('click', exportData);
    document.getElementById('kpCancelIndex').addEventListener('click', () => { if (!saving) document.getElementById('kpIndexEditor').classList.add('d-none'); });
    form.addEventListener('submit', event => save(event, form, 'save'));
    indexForm.addEventListener('submit', event => save(event, indexForm, 'save_index'));
    $('#kpTable').on('click', '.kp-detail, .kp-edit-index', function () {
        if (saving) return;
        const row = rows.find(r => Number(r.id) === Number(this.dataset.id));
        if (!row) return;
        if (this.classList.contains('kp-detail')) { openEmployee(row); return; }
        if (!cfg.canWrite) return;
        ['id', 'indek', 'pengurang', 'cuti_diambil', 'dankes'].forEach(key => indexForm.elements[key].value = row[key] || 0);
        document.getElementById('kpIndexName').textContent = 'Edit Index: ' + row.nik + ' — ' + row.nama;
        document.getElementById('kpIndexEditor').classList.remove('d-none');
        document.getElementById('kpIndexEditor').scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
    $(document).ready(function () { resetForm(); renderTable(); loadData(); });
})();
