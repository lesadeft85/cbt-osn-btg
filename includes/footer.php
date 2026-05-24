    </div><!-- /content-area -->
</div><!-- /main-wrapper -->

<!-- Scroll to Top -->
<button id="scrollTop" title="Ke atas"><i class="bi bi-arrow-up"></i></button>

<!-- jQuery -->
<script src="<?= defined('CDN_JQUERY') ? CDN_JQUERY : 'https://code.jquery.com/jquery-3.7.1.min.js' ?>"></script>
<!-- Bootstrap 5 -->
<script src="<?= defined('CDN_BOOTSTRAP_JS') ? CDN_BOOTSTRAP_JS : 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js' ?>"></script>
<!-- DataTables -->
<script src="<?= defined('CDN_DATATABLES_JS') ? CDN_DATATABLES_JS : 'https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js' ?>"></script>
<script src="<?= defined('CDN_DATATABLES_BOOTSTRAP_JS') ? CDN_DATATABLES_BOOTSTRAP_JS : 'https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js' ?>"></script>
<!-- Chart.js -->
<script src="<?= defined('CDN_CHART_JS') ? CDN_CHART_JS : 'https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js' ?>"></script>
<!-- SweetAlert2 -->
<script src="<?= defined('CDN_SWEETALERT2') ? CDN_SWEETALERT2 : 'https://cdn.jsdelivr.net/npm/sweetalert2@11' ?>"></script>
<!-- KaTeX (render rumus matematika) -->
<script src="<?= defined('CDN_KATEX_JS') ? CDN_KATEX_JS : 'https://cdn.jsdelivr.net/npm/katex@0.16.10/dist/katex.min.js' ?>"></script>
<script src="<?= defined('CDN_KATEX_AUTO_RENDER') ? CDN_KATEX_AUTO_RENDER : 'https://cdn.jsdelivr.net/npm/katex@0.16.10/dist/contrib/auto-render.min.js' ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    renderMathInElement(document.body, {
        delimiters: [
            { left: '$$', right: '$$', display: true  },
            { left: '$',  right: '$',  display: false },
            { left: '\\(', right: '\\)', display: false },
            { left: '\\[', right: '\\]', display: true  }
        ],
        throwOnError: false
    });
});
</script>
<!-- App JS -->
<script src="<?= BASE_URL ?>/assets/js/app.js"></script>

<!-- Cek Update Otomatis -->
<div id="notif-update" style="display:none; position:fixed; top:20px; right:20px; z-index:9999;
     background:#fff3cd; border:1px solid #ffc107; border-radius:10px; padding:15px 20px;
     box-shadow:0 4px 16px rgba(0,0,0,0.15); max-width:320px;">
    ⚠️ <strong>Update Tersedia!</strong><br>
    Versi terbaru: <strong id="notif-versi"></strong><br>
    <small id="notif-changelog" class="text-muted"></small><br>
    <a id="notif-link" href="#" target="_blank" class="btn btn-warning btn-sm mt-2">
        <i class="bi bi-download"></i> Download Update
    </a>
    <button onclick="document.getElementById('notif-update').style.display='none'"
        class="btn btn-sm btn-outline-secondary mt-2 ms-1">Tutup</button>
</div>
<script>
(function() {
    const LOCAL_VERSION = '<?= APP_VERSION ?>';
    fetch((typeof VERSION_URL !== 'undefined' ? VERSION_URL : 'https://raw.githubusercontent.com/mrkuncen89-ui/CBT-TKA-Kecamatan/main/version.json') + '?_=' + Date.now())
        .then(r => r.json())
        .then(data => {
            if (!data.version) return;
            const gv = data.version.split('.').map(Number);
            const lv = LOCAL_VERSION.split('.').map(Number);
            let needUpdate = false;
            for (let i = 0; i < 3; i++) {
                if ((gv[i]||0) > (lv[i]||0)) { needUpdate = true; break; }
                if ((gv[i]||0) < (lv[i]||0)) break;
            }
            if (needUpdate) {
                document.getElementById('notif-versi').textContent = data.version;
                document.getElementById('notif-changelog').textContent = data.changelog || '';
                document.getElementById('notif-link').href = data.download_url || '#';
                document.getElementById('notif-update').style.display = 'block';
            }
        })
        .catch(() => {});
})();
</script>
</body>
</html>
