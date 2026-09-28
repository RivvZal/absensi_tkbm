<?php

require_once 'auth.php';
requireLogin();

require 'koneksi.php';


// =====================================================
// FILTER TANGGAL
// =====================================================

$tanggal = $_GET['tanggal'] ?? date('Y-m-d');

$dateObj = DateTime::createFromFormat('Y-m-d', $tanggal);

if (!$dateObj || $dateObj->format('Y-m-d') !== $tanggal) {
    $tanggal = date('Y-m-d');
}

$isPastDate = $tanggal < date('Y-m-d');

$tanggalBesok = date(
    'Y-m-d',
    strtotime($tanggal . ' +1 day')
);


// =====================================================
// QUERY ABSENSI
// =====================================================
//
// IN  = punch pertama dengan workstate 1
// OUT = punch terakhir dengan workstate 2
//
// verifycode:
// 1  = Fingerprint
// 15 = Face
// 16 = Kode 16
//
// Jumlah IN/OUT TIDAK ditampilkan.
// Dipakai hanya untuk menentukan kelengkapan absensi.
// =====================================================

$sql = "

SELECT

    e.id AS employee_id,

    TRIM(
        CONCAT(
            COALESCE(e.emp_firstname, ''),
            ' ',
            COALESCE(e.emp_lastname, '')
        )
    ) AS nama,


    /* ============================
       PUNCH PERTAMA
       ============================ */

    MIN(
        CASE
            WHEN p.workstate = 1 THEN p.punch_time
            ELSE NULL
        END
    ) AS jam_in,


    /* ============================
       PUNCH TERAKHIR
       ============================ */

    MAX(
        CASE
            WHEN p.workstate = 2 THEN p.punch_time
            ELSE NULL
        END
    ) AS jam_out,


    /* ============================
       JUMLAH PUNCH
       INTERNAL SAJA
       ============================ */

    SUM(CASE WHEN p.workstate = 1 THEN 1 ELSE 0 END) AS jumlah_in,

    SUM(CASE WHEN p.workstate = 2 THEN 1 ELSE 0 END) AS jumlah_out,


    /* ============================
       METODE IN
       ============================ */

    (

        SELECT

            CASE

                WHEN p_in.verifycode = '1'
                    THEN 'Fingerprint'

                WHEN p_in.verifycode = '15'
                    THEN 'Face'

                WHEN p_in.verifycode = '16'
                    THEN 'Kode 16'

                WHEN p_in.verifycode IS NULL
                    THEN 'Tidak diketahui'

                ELSE
                    CONCAT(
                        'Kode ',
                        p_in.verifycode
                    )

            END

        FROM att_punches p_in

        WHERE

            p_in.employee_id = e.id

            AND p_in.workstate = 1

            AND p_in.punch_time >= ?

            AND p_in.punch_time < ?

        ORDER BY

            p_in.punch_time ASC,
            p_in.id ASC

        LIMIT 1

    ) AS metode_in,


    /* ============================
       METODE OUT
       ============================ */

    (

        SELECT

            CASE

                WHEN p_out.verifycode = '1'
                    THEN 'Fingerprint'

                WHEN p_out.verifycode = '15'
                    THEN 'Face'

                WHEN p_out.verifycode = '16'
                    THEN 'Kode 16'

                WHEN p_out.verifycode IS NULL
                    THEN 'Tidak diketahui'

                ELSE
                    CONCAT(
                        'Kode ',
                        p_out.verifycode
                    )

            END

        FROM att_punches p_out

        WHERE

            p_out.employee_id = e.id

            AND p_out.workstate = 2

            AND p_out.punch_time >= ?

            AND p_out.punch_time < ?

        ORDER BY

            p_out.punch_time DESC,
            p_out.id DESC

        LIMIT 1

    ) AS metode_out


FROM hr_employee e

LEFT JOIN hr_department d

    ON d.id = e.department_id


/* ============================
   DATA PUNCH
   ============================ */

LEFT JOIN att_punches p

    ON p.employee_id = e.id

    AND p.punch_time >= ?

    AND p.punch_time < ?

    AND p.workstate IN (1, 2)


/* ============================
   FILTER TANGGAL
   ============================ */

WHERE

    e.emp_active = 1

    AND UPPER(TRIM(COALESCE(d.dept_name, ''))) <> 'TKBM'

    AND NOT EXISTS (

        SELECT 1

        FROM hr_department d_parent

        WHERE d_parent.dept_code = d.dept_parentcode

            AND UPPER(TRIM(d_parent.dept_name)) = 'TKBM'

    )


/* ============================
   GROUP KARYAWAN
   ============================ */

GROUP BY

    e.id,
    e.emp_firstname,
    e.emp_lastname


ORDER BY

    jam_in ASC

";


// =====================================================
// PREPARE
// =====================================================

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Query gagal: " . $conn->error);
}


// =====================================================
// PARAMETER
// =====================================================

$stmt->bind_param(
    "ssssss",
    $tanggal,
    $tanggalBesok,
    $tanggal,
    $tanggalBesok,
    $tanggal,
    $tanggalBesok
);


// =====================================================
// EXECUTE
// =====================================================

$stmt->execute();

$result = $stmt->get_result();

$data = [];

while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}


// =====================================================
// FORMAT JAM
// =====================================================

function formatJam($datetime)
{
    if (empty($datetime)) {
        return '-';
    }

    return date(
        'H:i:s',
        strtotime($datetime)
    );
}


// =====================================================
// STATUS
// =====================================================

function getStatus($row, $isPastDate)
{
    if (empty($row['jam_in'])) {
        return $isPastDate
            ? 'TIDAK ABSEN'
            : 'BELUM ABSEN';
    }

    if ((int)$row['jumlah_out'] < 1) {
        return 'BELUM OUT';
    }

    return 'HADIR';
}


// =====================================================
// CLASS STATUS
// =====================================================

function getStatusClass($row, $isPastDate)
{
    $status = getStatus($row, $isPastDate);

    if ($status === 'HADIR') {
        return 'status-hadir';
    }

    if ($status === 'BELUM OUT') {
        return 'status-belum-out';
    }

    if ($status === 'BELUM ABSEN') {
        return 'status-belum-absen';
    }

    return 'status-tidak-absen';
}


// =====================================================
// CLASS METODE
// =====================================================

function getMetodeClass($metode)
{
    if ($metode === 'Fingerprint') {
        return 'metode-fingerprint';
    }

    if ($metode === 'Face') {
        return 'metode-face';
    }

    return 'metode-unknown';
}


// =====================================================
// RINGKASAN
// =====================================================

$totalData = count($data);
$totalHadir = 0;
$totalBelumOut = 0;
$totalAbsenKosong = 0;

foreach ($data as $row) {
    $status = getStatus($row, $isPastDate);

    if ($status === 'HADIR') {
        $totalHadir++;
    } elseif ($status === 'BELUM OUT') {
        $totalBelumOut++;
    } else {
        $totalAbsenKosong++;
    }
}

$absenKosongLabel = $isPastDate
    ? 'Tidak Absen'
    : 'Belum Absen';

$absenKosongColor = $isPastDate
    ? '#dc2626'
    : '#2563eb';

?>

<!DOCTYPE html>

<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>
        Report Absensi HRD
    </title>


    <!-- ==================================================
     DATATABLES CSS CDN
     ================================================== -->

    <link
        rel="stylesheet"
        href="https://cdn.datatables.net/2.3.3/css/dataTables.dataTables.min.css">

    <style>
        /* =====================================================
   GLOBAL
   ===================================================== */

        * {
            box-sizing: border-box;
        }

        body {

            margin: 0;

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            background: #f1f5f9;

            color: #1e293b;
        }


        /* =====================================================
   HEADER
   ===================================================== */

        .header {

            background:
                linear-gradient(135deg,
                    #0f172a,
                    #1e3a8a);

            color: white;

            padding: 25px 30px;
        }

        .header h1 {

            margin: 0;

            font-size: 27px;
        }

        .header p {

            margin: 7px 0 0;

            color: #cbd5e1;
        }


        /* =====================================================
   CONTAINER
   ===================================================== */

        .container {

            max-width: 1500px;

            margin: auto;

            padding: 25px;
        }


        /* =====================================================
   FILTER
   ===================================================== */

        .filter-box {

            background: white;

            padding: 20px;

            border-radius: 12px;

            margin-bottom: 20px;

            box-shadow:
                0 3px 12px rgba(0, 0, 0, .07);
        }


        .filter-row {

            display: flex;

            align-items: center;

            gap: 10px;

            flex-wrap: wrap;
        }


        .filter-row label {

            font-weight: bold;
        }


        .filter-row input {

            padding: 10px 12px;

            border:
                1px solid #cbd5e1;

            border-radius: 7px;

            font-size: 14px;
        }


        .btn-tampilkan {

            padding: 10px 20px;

            border: none;

            border-radius: 7px;

            background: #2563eb;

            color: white;

            font-weight: bold;

            cursor: pointer;
        }


        .btn-tampilkan:hover {

            background: #1d4ed8;
        }


        /* =====================================================
   INFO
   ===================================================== */

        .info-box {

            display: flex;

            justify-content: space-between;

            align-items: center;

            margin-bottom: 15px;

            gap: 10px;

            flex-wrap: wrap;
        }


        .info-tanggal {

            color: #64748b;
        }


        .info-total {

            background: #dbeafe;

            color: #1e40af;

            padding: 7px 13px;

            border-radius: 20px;

            font-size: 13px;

            font-weight: bold;
        }


        /* =====================================================
   SUMMARY
   ===================================================== */

        .summary {

            display: grid;

            grid-template-columns: repeat(4, minmax(0, 1fr));

            gap: 15px;

            margin-bottom: 20px;
        }


        .summary-card {

            background: white;

            padding: 17px 22px;

            border-radius: 10px;

            box-shadow: 0 3px 12px rgba(0, 0, 0, .06);
        }


        .summary-card-title {

            font-size: 12px;

            color: #64748b;
        }


        .summary-card-number {

            margin-top: 5px;

            font-size: 25px;

            font-weight: bold;
        }


        /* =====================================================
   TABLE
   ===================================================== */

        .table-box {

            background: white;

            border-radius: 12px;

            padding: 20px;

            box-shadow:
                0 3px 12px rgba(0, 0, 0, .07);

            overflow: hidden;
        }


        #tabelAbsensi {

            width: 100% !important;
        }


        #tabelAbsensi thead th {

            background: #1e293b;

            color: white;

            white-space: nowrap;
        }


        #tabelAbsensi tbody td {

            vertical-align: middle;
        }


        #tabelAbsensi tbody tr:hover {

            background: #f8fafc;
        }


        /* =====================================================
   JAM
   ===================================================== */

        .jam {

            font-weight: bold;

            color: #0f172a;

            white-space: nowrap;
        }


        /* =====================================================
   METODE
   ===================================================== */

        .metode {

            display: inline-block;

            padding: 6px 11px;

            border-radius: 20px;

            font-size: 12px;

            font-weight: bold;

            white-space: nowrap;
        }


        .metode-fingerprint {

            background: #dbeafe;

            color: #1d4ed8;
        }


        .metode-face {

            background: #f3e8ff;

            color: #7e22ce;
        }


        .metode-unknown {

            background: #f1f5f9;

            color: #475569;
        }


        /* =====================================================
   STATUS
   ===================================================== */

        .status {

            display: inline-block;

            padding: 6px 11px;

            border-radius: 20px;

            font-size: 12px;

            font-weight: bold;

            white-space: nowrap;
        }


        .status-hadir {

            background: #dcfce7;

            color: #166534;
        }


        .status-belum-out {

            background: #ffedd5;

            color: #9a3412;
        }


        .status-belum-absen {

            background: #dbeafe;

            color: #1d4ed8;
        }


        .status-tidak-absen {

            background: #fee2e2;

            color: #991b1b;
        }


        /* =====================================================
   EXPORT BUTTON
   ===================================================== */

        .dt-button {

            border: none !important;

            border-radius: 7px !important;

            background: #16a34a !important;

            color: white !important;

            padding: 9px 15px !important;

            font-weight: bold !important;
        }


        .dt-button:hover {

            background: #15803d !important;
        }


        /* =====================================================
   SEARCH
   ===================================================== */

        .dt-search input {

            border:
                1px solid #cbd5e1 !important;

            border-radius: 7px !important;

            padding: 8px 10px !important;
        }


        /* =====================================================
   RESPONSIVE
   ===================================================== */

        @media(max-width: 700px) {

            .container {

                padding: 12px;
            }

            .header {

                padding: 20px;
            }

            .header h1 {

                font-size: 22px;
            }

            .filter-row {

                align-items: stretch;
            }

            .filter-row input {

                width: 100%;
            }

            .btn-tampilkan {

                width: 100%;
            }

            .summary {

                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .summary-card {

                padding: 15px;
            }

            .table-box {

                padding: 10px;
            }

        }
</style>

    <link rel="stylesheet" href="assets/app.css?v=6">
    <script src="assets/app.js?v=1" defer></script>

</head>


<body class="page-report page-report-hrd page-transition">


    <!-- ==================================================
     HEADER
     ================================================== -->

    <div class="header">

        <a href="index.php" class="app-back">← Menu Utama</a>

        <h1>
            Report Absensi HRD
        </h1>

        <p>
            Monitoring kehadiran berdasarkan tanggal
        </p>

    </div>


    <div class="container">


        <!-- ==================================================
         FILTER TANGGAL
         ================================================== -->

        <div class="filter-box">

            <form method="GET">

                <div class="filter-row">

                    <label for="tanggal">
                        Pilih Tanggal:
                    </label>


                    <input
                        type="date"
                        id="tanggal"
                        name="tanggal"
                        value="<?= htmlspecialchars($tanggal) ?>"
                        required>


                    <button
                        type="submit"
                        class="btn-tampilkan">
                        🔍 Tampilkan
                    </button>

                </div>

            </form>

        </div>


        <!-- ==================================================
         INFO
         ================================================== -->

        <div class="info-box">

            <div class="info-tanggal">

                Menampilkan absensi tanggal:

                <strong>

                    <?= date(
                        'd-m-Y',
                        strtotime($tanggal)
                    ) ?>

                </strong>

            </div>


            <div class="info-total">

                <?= count($data) ?>

                Karyawan

            </div>

        </div>


        <!-- ==================================================
         TABLE
         ================================================== -->
        <?php if (isset($_GET['tanggal'])) { ?>

            <div class="summary">

                <div class="summary-card">
                    <div class="summary-card-title">Total Data</div>
                    <div class="summary-card-number"><?= $totalData ?></div>
                </div>

                <div class="summary-card">
                    <div class="summary-card-title">Hadir</div>
                    <div class="summary-card-number" style="color:#16a34a"><?= $totalHadir ?></div>
                </div>

                <div class="summary-card">
                    <div class="summary-card-title">Belum OUT</div>
                    <div class="summary-card-number" style="color:#ea580c"><?= $totalBelumOut ?></div>
                </div>

                <div class="summary-card">
                    <div class="summary-card-title"><?= htmlspecialchars($absenKosongLabel) ?></div>
                    <div class="summary-card-number" style="color:<?= $absenKosongColor ?>"><?= $totalAbsenKosong ?></div>
                </div>

            </div>

            <a
                href="print_hrd.php?tanggal=<?= urlencode($tanggal) ?>"
                target="_blank"
                class="btn-pdf"
            >
                📄 Generate Report PDF
            </a>

            <div class="table-box">

                <table
                    id="tabelAbsensi"
                    class="display">

                    <thead>

                        <tr>

                            <th>
                                No
                            </th>

                            <th>
                                Nama
                            </th>

                            <th>
                                IN
                            </th>

                            <th>
                                Metode IN
                            </th>

                            <th>
                                OUT
                            </th>

                            <th>
                                Metode OUT
                            </th>

                            <th>
                                Status
                            </th>

                        </tr>

                    </thead>


                    <tbody>

                        <?php

                        $no = 1;

                        foreach ($data as $row):

                            $status =
                                getStatus($row, $isPastDate);

                            $statusClass =
                                getStatusClass($row, $isPastDate);

                            $classMetodeIn =
                                getMetodeClass(
                                    $row['metode_in']
                                );

                            $classMetodeOut =
                                getMetodeClass(
                                    $row['metode_out']
                                );

                        ?>

                            <tr>


                                <!-- NO -->

                                <td>
                                    <?= $no++ ?>
                                </td>


                                <!-- NAMA -->

                                <td>

                                    <strong>

                                        <?= htmlspecialchars(
                                            $row['nama']
                                        ) ?>

                                    </strong>

                                </td>


                                <!-- IN -->

                                <td class="jam">

                                    <?= formatJam(
                                        $row['jam_in']
                                    ) ?>

                                </td>


                                <!-- METODE IN -->

                                <td>

                                    <span
                                        class="
                                metode
                                <?= $classMetodeIn ?>
                            ">

                                        <?= htmlspecialchars(
                                            $row['metode_in']
                                                ?? '-'
                                        ) ?>

                                    </span>

                                </td>


                                <!-- OUT -->

                                <td class="jam">

                                    <?php

                                    if (
                                        !empty($row['jam_in']) &&
                                        (int)$row['jumlah_out'] > 0
                                    ) {

                                        echo formatJam(
                                            $row['jam_out']
                                        );
                                    } else {

                                        echo '-';
                                    }

                                    ?>

                                </td>


                                <!-- METODE OUT -->

                                <td>

                                    <?php

                                    if (
                                        !empty($row['jam_in']) &&
                                        (int)$row['jumlah_out'] > 0
                                    ):

                                    ?>

                                        <span
                                            class="
                                    metode
                                    <?= $classMetodeOut ?>
                                ">

                                            <?= htmlspecialchars(
                                                $row['metode_out']
                                                    ?? '-'
                                            ) ?>

                                        </span>

                                    <?php

                                    else:

                                        echo '-';

                                    endif;

                                    ?>

                                </td>


                                <!-- STATUS -->

                                <td>

                                    <span
                                        class="
                                status
                                <?= $statusClass ?>
                            ">

                                        <?= $status ?>

                                    </span>

                                </td>

                            </tr>

                        <?php endforeach; ?>


                    </tbody>

                </table>

            </div>
        <?php } ?>

    </div>


    <!-- ==================================================
     JQUERY CDN
     ================================================== -->

    <script
        src="https://code.jquery.com/jquery-3.7.1.min.js"></script>


    <!-- ==================================================
     DATATABLES CDN
     ================================================== -->

    <script
        src="https://cdn.datatables.net/2.3.3/js/dataTables.min.js"></script>


    <!-- ==================================================
     DATATABLE INITIALIZE
     ================================================== -->

    <script>
        $(document).ready(function() {


            $('#tabelAbsensi').DataTable({

                pageLength: 25,


                lengthMenu: [

                    [10, 25, 50, 100, -1],

                    [
                        10,
                        25,
                        50,
                        100,
                        "Semua"
                    ]

                ],


                order: [

                    [2, 'asc']

                ],


                layout: {

                    topStart: 'pageLength',

                    topEnd: 'search',

                    bottomStart: 'info',

                    bottomEnd: 'paging'

                },


                language: {

                    search: "Cari:",

                    searchPlaceholder: "Cari nama...",


                    lengthMenu: "Tampilkan _MENU_ data",


                    info: "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",


                    infoEmpty: "Tidak ada data",


                    infoFiltered: "(difilter dari _MAX_ total data)",


                    zeroRecords: "Data tidak ditemukan",


                    emptyTable: "Tidak ada data absensi",


                    paginate: {

                        first: "Awal",

                        last: "Akhir",

                        next: "›",

                        previous: "‹"

                    }

                }

            });

        });
    </script>


</body>

</html>
