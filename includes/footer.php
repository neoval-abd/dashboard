</main> <!-- Penutup <main> -->
  </div> <!-- Penutup <div class="row"> -->
</div> <!-- Penutup <div class="container-fluid"> -->
<div id="dev-credit-bar">

    <!-- Identitas Developer -->
    <a href="#" class="dev-credit-brand text-decoration-none" data-bs-toggle="modal" data-bs-target="#devMessageModal" title="Pesan dari Developer">
        <img class="dev-avatar" src="assets/images/logo.png" alt="Developer Foto" style="object-fit: cover;">
        <div>            
            <div class="dev-name">RSU ADELLA</div>
            <div class="dev-role">Developer &amp; Designer <i class="fas fa-info-circle ms-1 small"></i></div>
        </div>
    </a>

    <div class="dev-divider"></div>

    <div class="dev-links">
        
        <a href="#" class="dev-link-btn dev-link-btn-neutral" data-bs-toggle="modal" data-bs-target="#changelogModal" title="Lihat Riwayat Pembaruan">
            <i class="fas fa-code-branch"></i>
            <span>Log Sistem</span>
        </a>
        
         <a id="dev-saweria-link"
           href="https://saweria.co" 
           target="_blank" 
           rel="noopener noreferrer"
           class="dev-link-btn dev-link-saweria"
           title="Dukung pengembangan via Saweria">
            <i class="fas fa-coffee"></i>
            <span>Donasi via Saweria</span>
        </a>

        <a href="https://wa.me/085959420216"
           target="_blank"
           rel="noopener noreferrer"
           class="dev-link-btn dev-link-wa"
           title="Hubungi via WhatsApp: 085959420216">
            <i class="fab fa-whatsapp"></i>
            <span>085959420216</span>
        </a>
        
        <a href="https://t.me/IchsanLeonhart"
           target="_blank"
           rel="noopener noreferrer"
           class="dev-link-btn dev-link-tg"
           title="Hubungi via Telegram: @IchsanLeonhart">
            <i class="fab fa-telegram"></i>
            <span>@RSU ADELLA</span>
        </a>

    </div>

    <div class="dev-qris-wrap" tabindex="0" role="button" aria-label="Klik untuk lihat QRIS donasi">        
        <img id="dev-qris-img"
             src="https://raw.githubusercontent.com/ichsanleonhart/add-ons_webapps_khanza/main/qris-ichsan.png"
             alt="QRIS Ichsan Leonhart"
             loading="lazy">
        <div class="qris-label">QRIS</div>

        <!-- Popup QRIS ukuran besar saat hover/focus -->
        <div class="qris-tooltip">
            <img src="https://raw.githubusercontent.com/ichsanleonhart/add-ons_webapps_khanza/main/qris-ichsan.png"
                 alt="QRIS Donasi Ichsan Leonhart"
                 loading="lazy">
            <p>Scan untuk Donasi 🙏<br><small style="color:#888">Terima kasih atas dukungannya!</small></p>
        </div>
    </div>

</div>
<!-- ===== END DEVELOPER CREDIT BAR ===== -->

<!--
  Library JavaScript Global
-->
<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<!-- Bootstrap Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- DataTables -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>

<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.colVis.min.js"></script>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.0/dist/chart.min.js"></script>

<!-- [THEME ENGINE] Sinkronisasi Warna Chart.js dengan Tema -->
<script>
(function() {
    function updateChartColors() {
        if (typeof Chart === 'undefined') return;
        var theme = localStorage.getItem('app_theme') || 'theme-glass-animated';
        var isDark = theme.includes('glass');
        var textColor = isDark ? '#cbd5e1' : '#666';
        var gridColor = isDark ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.1)';
        
        // Set Global Default (untuk chart baru yang dibuat setelah load)
        Chart.defaults.color = textColor;
        if(Chart.defaults.scale) Chart.defaults.scale.grid.color = gridColor;

        // Update semua chart yang sudah di-render secara dinamis
        for (var id in Chart.instances) {
            var chart = Chart.instances[id];
            if (chart.options.scales) {
                if (chart.options.scales.x) {
                    if (chart.options.scales.x.ticks) chart.options.scales.x.ticks.color = textColor;
                    if (chart.options.scales.x.grid) chart.options.scales.x.grid.color = gridColor;
                }
                if (chart.options.scales.y) {
                    if (chart.options.scales.y.ticks) chart.options.scales.y.ticks.color = textColor;
                    if (chart.options.scales.y.grid) chart.options.scales.y.grid.color = gridColor;
                }
            }
            if (chart.options.plugins && chart.options.plugins.legend && chart.options.plugins.legend.labels) {
                chart.options.plugins.legend.labels.color = textColor;
            }
            chart.update();
        }
    }
    
    updateChartColors(); // Set saat pertama load
    // Update live saat User memencet dropdown tema
    window.addEventListener('themeChanged', updateChartColors);
})();
</script>

<!-- ===== GLOBAL AJAX LOADING HOOKS ===== -->
<script>
$(document).ready(function() {
    // Aktifkan overlay saat AJAX mulai
    $(document).ajaxStart(function() {
        $('#globalLoadingOverlay').css('display', 'flex');
    });
    // Sembunyikan overlay saat SEMUA AJAX selesai (sukses/error)
    $(document).ajaxStop(function() {
        $('#globalLoadingOverlay').fadeOut(200);
    });

    // DataTables: pesan empty-state untuk halaman tanpa auto-load
    $.extend(true, $.fn.dataTable.defaults, {
        language: {
            emptyTable: '<span class="text-muted"><i class="fas fa-info-circle me-1 text-info"></i>Data tidak dimuat otomatis untuk menjaga performa aplikasi. Atur filter lalu klik tombol untuk menampilkan data.</span>'
        }
    });
});

// Helper manual jika ada kasus di luar jQuery AJAX
window.showGlobalLoading = function() { $('#globalLoadingOverlay').css('display', 'flex'); };
window.hideGlobalLoading = function() { $('#globalLoadingOverlay').fadeOut(200); };
</script>
<!-- ===== END GLOBAL AJAX LOADING ===== -->

<?php
if (isset($page_js)) {
    echo $page_js;
}
?>

<!-- =========================================================================
     [KILL SWITCH — CLIENT SIDE — RULE #17]
     Script ini di-obfuskat menggunakan PHP base64_encode() pada render time.
     Berjalan via setInterval setiap 2 detik.
     Mengecek visibility element Saweria (id=dev-saweria-link) & QRIS (id=dev-qris-img)
     via window.getComputedStyle(). Jika salah satu disembunyikan (display:none,
     visibility:hidden, opacity mendekati 0) → document.body.innerHTML dikosongkan.
======================================================================== -->

<!-- Modal Developer / "Curhat" Monetisasi -->
<div class="modal fade" id="devMessageModal" tabindex="-1" aria-labelledby="devMessageModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
      <div class="modal-header border-0 text-white" style="background: linear-gradient(135deg, #0f0c29, #302b63, #24243e);">
        <h5 class="modal-title fw-bold" id="devMessageModalLabel"><i class="fas fa-code me-2"></i>Pesan dari Developer</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4">
        <div class="text-center mb-4">
            <img src="https://raw.githubusercontent.com/ichsanleonhart/add-ons_webapps_khanza/main/ichsan.jpg" alt="Ichsan Leonhart" class="rounded-circle shadow" style="width: 80px; height: 80px; border: 4px solid #fff; object-fit: cover;">
            <h6 class="mt-3 fw-bold mb-0">Ichsan Leonhart</h6>
        </div>
        
        <p class="small mb-3" style="text-align: justify;">
            <strong>Salam hormat,</strong><br><br>
            Saya sangat bersyukur aplikasi <strong>Dashboard Eksekutif</strong> ini dapat membantu mempermudah pimpinan dan manajemen dalam pengambilan keputusan strategis di Rumah Sakit Anda. Setiap baris kode, animasi, dan lapisan keamanan dalam sistem ini saya tulis secara mandiri dengan dedikasi penuh di luar jam kerja utama, dengan komitmen menghadirkan kualitas setara standar <em>enterprise</em>.
        </p>

        <p class="small mb-3" style="text-align: justify;">
            Pengembangan sistem yang responsif, stabil, dan amat aman dari peretas membutuhkan investasi waktu berjam-jam riset dan tenaga ekstra. Apabila pemikiran di balik aplikasi ini memberikan dampak dan <em>value</em> nyata bagi kemajuan instansi Anda, saya dengan rendah hati sangat mengapresiasi segala bentuk <strong>dukungan atau kado donasi sukarela</strong>. 
        </p>

        <p class="small mb-4" style="text-align: justify;">
            Dukungan pimpinan sangat berarti bagi kelangsungan inovasi ini — membantu saya menutupi biaya operasional infrastruktur <em>testing</em>, biaya kopi larut malam, dan yang terpenting, menyambung nafas semangat saya untuk terus berkarya di tengah berbagai keterbatasan.
        </p>

        <div class="alert alert-info py-2 px-3 mb-0 border-0" style="border-radius: 10px;">
            <h6 class="fw-bold mb-1 border-bottom border-info pb-2 text-primary" style="font-size: 0.85rem;"><i class="fas fa-lightbulb me-2"></i>Diskusi Fitur Khusus (Custom Request)</h6>
            <p class="small mb-2 mt-2" style="font-size: 0.75rem;">
                Apakah RS / Instansi Anda membutuhkan modul tambahan yang belum ada di versi ini? Misalnya <em>Custom Report</em> spesifik, penyesuaian logika tata kelola, atau interkoneksi Bridging lainnya? Hubungi saya via WA / Telegram untuk merancang kolaborasi pengembangan tingkat lanjut _(Freelance Project)_.
            </p>
            <div class="d-flex gap-2 mb-1 mt-2">
                <a href="https://wa.me/6285726123777" target="_blank" class="btn btn-sm btn-success d-inline-flex align-items-center" style="border-radius: 12px; font-size: 0.75rem; border:none;"><i class="fab fa-whatsapp me-2"></i>WhatsApp</a>
                <a href="https://t.me/IchsanLeonhart" target="_blank" class="btn btn-sm d-inline-flex align-items-center" style="background-color: #0088cc; color: white; border-radius: 12px; font-size: 0.75rem; border:none;"><i class="fab fa-telegram me-2"></i>Telegram</a>
            </div>
        </div>

        <div class="collapse mt-3" id="qrisCollapseModal">
            <div class="card card-body text-center border-0 shadow-sm p-3" style="background: rgba(125,125,125,0.1); border-radius: 12px;">
                <img src="https://raw.githubusercontent.com/ichsanleonhart/add-ons_webapps_khanza/main/qris-ichsan.png" alt="QRIS Donasi" class="img-fluid rounded mx-auto" style="max-width: 200px;">
                <p class="small mt-2 mb-0 fw-bold">Scan QRIS Ini</p>
            </div>
        </div>

      </div>
      <div class="modal-footer border-0 d-flex justify-content-between align-items-center">
        <div class="d-flex gap-2 flex-wrap">
            <a href="https://saweria.co/ichsanleonhart" target="_blank" class="btn btn-warning fw-bold shadow-sm d-flex align-items-center" style="border-radius: 12px; font-size: 0.85rem; background: linear-gradient(135deg, #ff6b6b, #ffa500); color: white; border: none;">
                <i class="fas fa-gift me-2 text-white"></i>Saweria
            </a>
            <button type="button" class="btn fw-bold shadow-sm d-flex align-items-center text-white" style="border-radius: 12px; font-size: 0.85rem; background: linear-gradient(135deg, #4facfe, #00f2fe); border: none;" data-bs-toggle="collapse" data-bs-target="#qrisCollapseModal" aria-expanded="false" aria-controls="qrisCollapseModal">
                <i class="fas fa-qrcode me-2 text-white"></i>QRIS
            </button>
        </div>
        <button type="button" class="btn btn-secondary shadow-sm" style="border-radius: 12px; font-size: 0.85rem;" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal Panduan Pengguna (User Documentation) -->
<div class="modal fade" id="guideModal" tabindex="-1" aria-labelledby="guideModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 20px; overflow: hidden;">
      <div class="modal-header border-0 text-white" style="background: linear-gradient(135deg, #0d6efd, #0dcafa);">
        <h5 class="modal-title fw-bold" id="guideModalLabel"><i class="fas fa-book-reader me-2"></i>Buku Sakti: Panduan Pengguna Dashboard</h5>
        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-sm btn-light shadow-sm fw-bold" id="btnPrintGuide" title="Cetak Panduan">
                <i class="fas fa-print me-1"></i> Cetak
            </button>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
      </div>
      <div class="modal-body p-0 bg-light" id="guideModalBody">
          <div id="guide-loader" class="text-center py-5">
              <div class="spinner-border text-primary mb-3" role="status"></div>
              <p class="text-muted fw-bold">Sedang memuat panduan canggih...</p>
          </div>
          <div id="guide-content" class="p-0 d-none">
              <!-- Content will be injected here as an Accordion -->
              <div class="accordion accordion-flush" id="guideAccordion"></div>
          </div>
      </div>
      <div class="modal-footer bg-white border-0">
        <button type="button" class="btn btn-secondary shadow-sm" style="border-radius: 12px;" data-bs-dismiss="modal">Tutup Panduan</button>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    var guideModal = document.getElementById('guideModal');
    if (!guideModal) return;

    guideModal.addEventListener('show.bs.modal', function() {
        var loader = document.getElementById('guide-loader');
        var content = document.getElementById('guide-content');
        var accordion = document.getElementById('guideAccordion');

        if (content.dataset.loaded === 'true') return;

        // Reset state
        loader.classList.remove('d-none');
        content.classList.add('d-none');
        accordion.innerHTML = '';

        // Fetch documentation (relative path for production compatibility)
        fetch('dokumentasi/dokumentasi_user.md?v=' + new Date().getTime())
            .then(response => {
                if (!response.ok) throw new Error("Gagal mengambil file dokumentasi");
                return response.text();
            })
            .then(md => {
                renderGuideContent(md);
                loader.classList.add('d-none');
                content.classList.remove('d-none');
                content.dataset.loaded = 'true';
            })
            .catch(err => {
                loader.innerHTML = '<div class="alert alert-danger mx-4 mt-4"><i class="fas fa-exclamation-triangle me-2"></i>' + err.message + '</div>';
            });
    });

    function renderGuideContent(md) {
        var accordion = document.getElementById('guideAccordion');
        // Split by modules (defined by ## headers)
        var sections = md.split(/^##\s+/m);
        
        // Intro section (before first ##)
        var intro = sections[0].trim();
        if (intro) {
            // Remove the main title (#) from intro if present to avoid double headers
            var introCleaned = intro.replace(/^#\s+.*$/m, '').trim();
            if (introCleaned) {
                var introDiv = document.createElement('div');
                introDiv.className = 'p-4 border-bottom bg-white';
                introDiv.innerHTML = parseMarkdownBody(introCleaned);
                accordion.appendChild(introDiv);
            }
        }

        for (var i = 1; i < sections.length; i++) {
            var section = sections[i].trim();
            if (!section) continue;

            var lines = section.split('\n');
            var title = lines[0].trim();
            var body = lines.slice(1).join('\n').trim();
            
            var itemId = 'guide-item-' + i;
            var headerId = 'guide-header-' + i;
            var collapseId = 'guide-collapse-' + i;

            var item = `
                <div class="accordion-item border-0 mb-2 shadow-sm mx-3 mt-3 overflow-hidden" style="border-radius:12px;">
                    <h2 class="accordion-header" id="${headerId}">
                        <button class="accordion-button collapsed fw-bold text-primary" type="button" data-bs-toggle="collapse" data-bs-target="#${collapseId}" aria-expanded="false" aria-controls="${collapseId}">
                            ${title}
                        </button>
                    </h2>
                    <div id="${collapseId}" class="accordion-collapse collapse" aria-labelledby="${headerId}" data-bs-parent="#guideAccordion">
                        <div class="accordion-body bg-white py-4 px-4 line-height-lg" style="font-size: 0.95rem; color: #444;">
                            ${parseMarkdownBody(body)}
                        </div>
                    </div>
                </div>
            `;
            accordion.insertAdjacentHTML('beforeend', item);
        }
    }

    function parseMarkdownBody(text) {
        // HR ---
        text = text.replace(/^---\s*$/gm, '<hr class="my-4">');

        // Images: ![alt](src) -> transform src to dokumentasi/src
        text = text.replace(/!\[([^\]]*)\]\((.+?(?:\.jpg|\.jpeg|\.png|\.gif|\.webp|\.svg))\)/gi, function(match, alt, src) {
            return '<div class="text-center my-4"><img src="dokumentasi/' + src.trim() + '" alt="' + alt + '" class="img-fluid rounded shadow border" style="max-height: 450px;"><div class="small text-muted mt-2 italic text-center">' + alt + '</div></div>';
        });

        // Bold & Italic
        text = text.replace(/\*\*\*([^*]+)\*\*\*/g, '<strong><em>$1</em></strong>');
        text = text.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
        text = text.replace(/\*([^*]+)\*/g, '<em>$1</em>');

        // Lists (Simplified)
        text = text.replace(/^\s*[\-\*]\s+(.*)$/gm, '<li class="mb-2">$1</li>');
        
        // Wrap lists in <ul> - this regex is a bit greedy but works for simple guides
        text = text.replace(/(<li.*<\/li>)/gs, '<ul class="ps-3 mb-3">$1</ul>');

        // Blockquote
        text = text.replace(/^>\s+(.*)$/gm, '<blockquote class="blockquote border-start border-4 border-info ps-3 py-1 bg-light small" style="border-radius:0 8px 8px 0;">$1</blockquote>');
        
        // Paragraphs & Line breaks
        text = text.replace(/\n\n/g, '<p class="mb-3"></p>');
        text = text.replace(/\n/g, '<br>');

        return text;
    }

    // Print logic
    var btnPrint = document.getElementById('btnPrintGuide');
    if (btnPrint) {
        btnPrint.addEventListener('click', function() {
            var content = document.getElementById('guideAccordion').innerHTML;
            var printWindow = window.open('', '_blank');
            printWindow.document.write('<html><head><title>Panduan Pengguna Dashboard</title>');
            printWindow.document.write('<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">');
            printWindow.document.write('<style>body{padding:40px; font-family: sans-serif;} .accordion-button:after{display:none;} .accordion-collapse{display:block !important;} .accordion-item{border:1px solid #eee !important; margin-bottom:30px !important; box-shadow:none !important;} .accordion-button{background:#f8f9fa !important; color:#000 !important; cursor:default !important; font-size: 1.2rem; border-bottom: 2px solid #0d6efd !important;} img{max-width:100% !important; height:auto; display:block; margin: 20px auto;} .text-primary{color: #0d6efd !important;}</style>');
            printWindow.document.write('</head><body>');
            printWindow.document.write('<div class="container">');
            printWindow.document.write('<h1 class="text-center mb-5 pb-3 border-bottom">Panduan Pengguna Dashboard Eksekutif</h1>');
            printWindow.document.write(content);
            printWindow.document.write('</div>');
            printWindow.document.write('</body></html>');
            printWindow.document.close();
            setTimeout(function() {
                printWindow.print();
            }, 800);
        });
    }
});
</script>

</body>
</html>