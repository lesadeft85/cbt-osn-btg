<?php
// ============================================================
// config/paths.php
// Centralized external resource URLs and social links
// ============================================================
// Pastikan `BASE_URL` tersedia ketika diperlukan
if (!defined('BASE_URL')) {
    require_once __DIR__ . '/database.php';
}

if (!defined('CDN_BOOTSTRAP_CSS')) {
    define('CDN_BOOTSTRAP_CSS', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css');
}
if (!defined('CDN_BOOTSTRAP_ICONS')) {
    define('CDN_BOOTSTRAP_ICONS', 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css');
}
if (!defined('CDN_DATATABLES_CSS')) {
    define('CDN_DATATABLES_CSS', 'https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css');
}
if (!defined('CDN_KATEX_CSS')) {
    define('CDN_KATEX_CSS', 'https://cdn.jsdelivr.net/npm/katex@0.16.10/dist/katex.min.css');
}

if (!defined('CDN_KATEX_JS')) {
    define('CDN_KATEX_JS', 'https://cdn.jsdelivr.net/npm/katex@0.16.10/dist/katex.min.js');
}
if (!defined('CDN_KATEX_AUTO_RENDER')) {
    define('CDN_KATEX_AUTO_RENDER', 'https://cdn.jsdelivr.net/npm/katex@0.16.10/dist/contrib/auto-render.min.js');
}
if (!defined('DATATABLES_I18N_ID')) {
    define('DATATABLES_I18N_ID', 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/id.json');
}

// Google Fonts commonly used in the app (make configurable)
if (!defined('FONTS_NUNITO')) {
    define('FONTS_NUNITO', 'https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap');
}
if (!defined('FONTS_CERTIFICATE')) {
    define('FONTS_CERTIFICATE', 'https://fonts.googleapis.com/css2?family=Cinzel:wght@700&family=Great+Vibes&family=Montserrat:wght@400;600;700&display=swap');
}
if (!defined('FONTS_PLUS_JAKARTA')) {
    define('FONTS_PLUS_JAKARTA', 'https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800;900&display=swap');
}

if (!defined('CDN_BOOTSTRAP_JS')) {
    define('CDN_BOOTSTRAP_JS', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js');
}
if (!defined('CDN_CHART_JS')) {
    define('CDN_CHART_JS', 'https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js');
}
if (!defined('CDN_JQUERY')) {
    define('CDN_JQUERY', 'https://code.jquery.com/jquery-3.7.1.min.js');
}
if (!defined('CDN_DATATABLES_JS')) {
    define('CDN_DATATABLES_JS', 'https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js');
}
if (!defined('CDN_DATATABLES_BOOTSTRAP_JS')) {
    define('CDN_DATATABLES_BOOTSTRAP_JS', 'https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js');
}
if (!defined('CDN_SWEETALERT2')) {
    define('CDN_SWEETALERT2', 'https://cdn.jsdelivr.net/npm/sweetalert2@11');
}

// Social / contact links
if (!defined('LINK_TIKTOK')) {
    define('LINK_TIKTOK', 'https://www.tiktok.com/@mrkuncen');
}
if (!defined('LINK_WA')) {
    define('LINK_WA', 'https://wa.me/6287781743048');
}

// Versi/file lain yang mungkin perlu disesuaikan dapat ditambahkan di sini

?>
