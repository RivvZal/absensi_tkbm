<?php

require_once 'auth.php';
requireLogin();
require_once 'koneksi.php';

function userPageEscape($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function loadUsers($conn, $category)
{
    if ($category === 'tkbm') {
        $sql = "
            SELECT
                e.emp_pin,
                TRIM(CONCAT(COALESCE(e.emp_firstname, ''), ' ', COALESCE(e.emp_lastname, ''))) AS nama,
                COALESCE(d.dept_name, '-') AS departemen,
                COALESCE(pos.posi_name, '-') AS jabatan
            FROM hr_employee e
            INNER JOIN hr_department d ON d.id = e.department_id
            LEFT JOIN hr_position pos ON pos.id = e.position_id
            WHERE e.emp_active = 1
                AND EXISTS (
                    SELECT 1
                    FROM hr_department d_parent
                    WHERE d_parent.dept_code = d.dept_parentcode
                        AND UPPER(TRIM(d_parent.dept_name)) = 'TKBM'
                )
            ORDER BY UPPER(TRIM(d.dept_name)), CAST(e.emp_pin AS UNSIGNED), e.id
        ";
    } else {
        $sql = "
            SELECT
                e.emp_pin,
                TRIM(CONCAT(COALESCE(e.emp_firstname, ''), ' ', COALESCE(e.emp_lastname, ''))) AS nama,
                COALESCE(d.dept_name, '-') AS departemen,
                COALESCE(pos.posi_name, '-') AS jabatan
            FROM hr_employee e
            LEFT JOIN hr_department d ON d.id = e.department_id
            LEFT JOIN hr_position pos ON pos.id = e.position_id
            WHERE e.emp_active = 1
                AND UPPER(TRIM(COALESCE(d.dept_name, ''))) <> 'TKBM'
                AND NOT EXISTS (
                    SELECT 1
                    FROM hr_department d_parent
                    WHERE d_parent.dept_code = d.dept_parentcode
                        AND UPPER(TRIM(d_parent.dept_name)) = 'TKBM'
                )
            ORDER BY UPPER(TRIM(COALESCE(d.dept_name, ''))), e.emp_firstname, e.emp_lastname, e.id
        ";
    }

    $result = $conn->query($sql);
    return $result->fetch_all(MYSQLI_ASSOC);
}

$queryError = false;
$hrdUsers = [];
$tkbmUsers = [];

try {
    $hrdUsers = loadUsers($conn, 'hrd');
    $tkbmUsers = loadUsers($conn, 'tkbm');
} catch (mysqli_sql_exception $e) {
    error_log('Gagal memuat daftar user: ' . $e->getMessage());
    $queryError = true;
}

function renderUserRows($users, $emptyMessage)
{
    if (!$users) {
        echo '<tr><td class="user-empty" colspan="4">' . userPageEscape($emptyMessage) . '</td></tr>';
        return;
    }

    foreach ($users as $index => $user) {
        $nama = trim($user['nama'] ?? '') ?: 'Nama belum diisi';
        $initial = strtoupper(function_exists('mb_substr') ? mb_substr($nama, 0, 1, 'UTF-8') : substr($nama, 0, 1));
        echo '<tr class="user-row">';
        echo '<td class="user-number" data-label="No">' . ($index + 1) . '</td>';
        echo '<td data-label="Nama"><div class="user-identity"><span class="user-avatar" aria-hidden="true">' . userPageEscape($initial) . '</span><span class="user-name">' . userPageEscape($nama) . '</span></div></td>';
        echo '<td data-label="PIN"><span class="user-pin">' . userPageEscape($user['emp_pin'] ?? '-') . '</span></td>';
        echo '<td data-label="Departemen / Jabatan"><div class="user-role">' . userPageEscape($user['departemen'] ?? '-') . '<span>' . userPageEscape($user['jabatan'] ?? '-') . '</span></div></td>';
        echo '</tr>';
    }
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar User | Sistem Report Absensi</title>
    <link rel="stylesheet" href="assets/app.css?v=7">
    <script src="assets/app.js?v=1" defer></script>
</head>
<body class="page-report page-users page-transition">
    <header class="header">
        <a class="app-back" href="index.php">← Kembali ke beranda</a>
        <h1>Daftar User</h1>
        <p>Data karyawan aktif dari server database absensi</p>
    </header>

    <main class="container">
        <?php if ($queryError): ?>
            <div class="users-error" role="alert">Data user belum dapat dimuat. Silakan periksa koneksi database atau hubungi administrator.</div>
        <?php else: ?>
            <section class="users-overview" aria-label="Ringkasan user">
                <div class="users-overview-icon" aria-hidden="true">♙</div>
                <div>
                    <span class="users-overview-label">Total user aktif</span>
                    <strong><?= number_format(count($hrdUsers) + count($tkbmUsers), 0, ',', '.') ?></strong>
                </div>
                <span class="users-live"><i></i> Data database</span>
            </section>

            <section class="users-panel" aria-label="Daftar user berdasarkan kategori">
                <div class="users-toolbar">
                    <div class="users-tabs" role="tablist" aria-label="Kategori user">
                        <button type="button" class="users-tab is-active" id="tab-hrd" role="tab" aria-selected="true" aria-controls="panel-hrd" data-category="hrd">
                            HRD <span><?= number_format(count($hrdUsers), 0, ',', '.') ?></span>
                        </button>
                        <button type="button" class="users-tab" id="tab-tkbm" role="tab" aria-selected="false" aria-controls="panel-tkbm" data-category="tkbm" tabindex="-1">
                            TKBM <span><?= number_format(count($tkbmUsers), 0, ',', '.') ?></span>
                        </button>
                    </div>
                    <label class="users-search">
                        <span aria-hidden="true">⌕</span>
                        <input type="search" id="user-search" placeholder="Cari nama, PIN, departemen..." autocomplete="off" aria-label="Cari user">
                    </label>
                </div>

                <div class="users-category" id="panel-hrd" role="tabpanel" aria-labelledby="tab-hrd" data-category="hrd">
                    <div class="users-category-heading"><div><span class="users-category-mark hrd-mark">H</span><div><h2>HRD</h2><p>Karyawan kantor dan departemen non-TKBM</p></div></div><span class="users-category-count"><?= number_format(count($hrdUsers), 0, ',', '.') ?> user</span></div>
                    <div class="users-table-wrap">
                        <table class="users-table">
                            <thead><tr><th scope="col">No</th><th scope="col">Nama</th><th scope="col">PIN</th><th scope="col">Departemen / Jabatan</th></tr></thead>
                            <tbody><?php renderUserRows($hrdUsers, 'Belum ada user HRD aktif.'); ?></tbody>
                        </table>
                        <p class="users-no-results" hidden>Tidak ada user yang cocok dengan pencarian.</p>
                    </div>
                </div>

                <div class="users-category" id="panel-tkbm" role="tabpanel" aria-labelledby="tab-tkbm" data-category="tkbm" hidden>
                    <div class="users-category-heading"><div><span class="users-category-mark tkbm-mark">T</span><div><h2>TKBM</h2><p>Tenaga kerja bongkar muat di seluruh grup</p></div></div><span class="users-category-count"><?= number_format(count($tkbmUsers), 0, ',', '.') ?> user</span></div>
                    <div class="users-table-wrap">
                        <table class="users-table">
                            <thead><tr><th scope="col">No</th><th scope="col">Nama</th><th scope="col">PIN</th><th scope="col">Departemen / Jabatan</th></tr></thead>
                            <tbody><?php renderUserRows($tkbmUsers, 'Belum ada user TKBM aktif.'); ?></tbody>
                        </table>
                        <p class="users-no-results" hidden>Tidak ada user yang cocok dengan pencarian.</p>
                    </div>
                </div>
            </section>
        <?php endif; ?>
        <p class="users-footnote">Daftar ini hanya menampilkan user dengan status aktif.</p>
    </main>

    <script>
        (function () {
            var tabs = Array.prototype.slice.call(document.querySelectorAll('.users-tab'));
            var panels = Array.prototype.slice.call(document.querySelectorAll('.users-category'));
            var search = document.getElementById('user-search');

            function setCategory(category) {
                tabs.forEach(function (tab) {
                    var active = tab.dataset.category === category;
                    tab.classList.toggle('is-active', active);
                    tab.setAttribute('aria-selected', active ? 'true' : 'false');
                    tab.tabIndex = active ? 0 : -1;
                });
                panels.forEach(function (panel) {
                    panel.hidden = panel.dataset.category !== category;
                });
                filterUsers();
            }

            function filterUsers() {
                if (!search) return;
                var activePanel = document.querySelector('.users-category:not([hidden])');
                if (!activePanel) return;
                var term = search.value.trim().toLocaleLowerCase('id');
                var rows = activePanel.querySelectorAll('.user-row');
                var visible = 0;
                rows.forEach(function (row) {
                    var matches = row.textContent.toLocaleLowerCase('id').indexOf(term) !== -1;
                    row.hidden = !matches;
                    if (matches) visible += 1;
                });
                var noResults = activePanel.querySelector('.users-no-results');
                if (noResults) noResults.hidden = visible !== 0 || rows.length === 0;
            }

            tabs.forEach(function (tab) {
                tab.addEventListener('click', function () { setCategory(tab.dataset.category); });
                tab.addEventListener('keydown', function (event) {
                    if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
                        event.preventDefault();
                        var next = tab.dataset.category === 'hrd' ? 'tkbm' : 'hrd';
                        setCategory(next);
                        document.getElementById('tab-' + next).focus();
                    }
                });
            });
            if (search) search.addEventListener('input', filterUsers);
        }());
    </script>
</body>
</html>
