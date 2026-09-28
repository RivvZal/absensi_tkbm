<?php

require_once 'auth.php';
requireLogin();

require_once 'koneksi.php';

/*
|--------------------------------------------------------------------------
| CONFIG SHIFT
|--------------------------------------------------------------------------
*/

$shifts = [

    1 => [
        'nama'    => 'Shift 1',
        'mulai'   => '08:00:00',
        'selesai' => '16:00:00'
    ],

    2 => [
        'nama'    => 'Shift 2',
        'mulai'   => '16:00:00',
        'selesai' => '00:00:00'
    ]

];


/*
|--------------------------------------------------------------------------
| GROUP TKBM
|--------------------------------------------------------------------------
*/

$tkbmGroups = array_merge(
    range('A', 'Z'),
    ['AA', 'BB', 'CC']
);

$group = strtoupper(trim($_GET['group'] ?? 'A'));

if (!in_array($group, $tkbmGroups, true)) {
    $group = 'A';
}

/*
 * Pada database mesin, anggota Group J dan K masih berada di departemen
 * "GROUP J". Batas keduanya diambil dari PIN Ketua Regu kedua agar data
 * tetap dapat dipisahkan tanpa mengubah database sumber.
 */
$databaseGroup = $group === 'K' ? 'J' : $group;
$departmentName = 'GROUP ' . $databaseGroup;

$groupJBoundarySql = "(
    SELECT MAX(CAST(e_leader.emp_pin AS UNSIGNED))
    FROM hr_employee e_leader
    INNER JOIN hr_department d_leader
        ON d_leader.id = e_leader.department_id
    INNER JOIN hr_position pos_leader
        ON pos_leader.id = e_leader.position_id
    WHERE UPPER(TRIM(d_leader.dept_name)) = 'GROUP J'
        AND UPPER(TRIM(pos_leader.posi_name)) = 'KETUA REGU'
)";

$groupSplitFilter = '';

if ($group === 'J') {
    $groupSplitFilter = "
        AND CAST(e.emp_pin AS UNSIGNED) < $groupJBoundarySql";
} elseif ($group === 'K') {
    $groupSplitFilter = "
        AND CAST(e.emp_pin AS UNSIGNED) >= $groupJBoundarySql";
}


/*
|--------------------------------------------------------------------------
| TANGGAL
|--------------------------------------------------------------------------
*/

$tanggal = $_GET['tanggal'] ?? date('Y-m-d');

if (
    !preg_match(
        '/^\d{4}-\d{2}-\d{2}$/',
        $tanggal
    )
) {
    $tanggal = date('Y-m-d');
}

$isPastDate = $tanggal < date('Y-m-d');


/*
|--------------------------------------------------------------------------
| SHIFT
|--------------------------------------------------------------------------
*/

$shift_id = isset($_GET['shift'])
    ? (int) $_GET['shift']
    : 1;

if (!isset($shifts[$shift_id])) {
    $shift_id = 1;
}

$shift = $shifts[$shift_id];


/*
|--------------------------------------------------------------------------
| TANGGAL SHIFT
|--------------------------------------------------------------------------
*/

$waktuMulai = new DateTime(
    $tanggal . ' ' . $shift['mulai']
);

$waktuSelesai = new DateTime(
    $tanggal . ' ' . $shift['selesai']
);


/*
|--------------------------------------------------------------------------
| SHIFT LEWAT TENGAH MALAM
|--------------------------------------------------------------------------
|
| Shift 2:
|
| 16:00 - 00:00
|
| Maka:
|
| mulai   = 2026-08-13 16:00
| selesai = 2026-08-14 00:00
|
*/

if ($waktuSelesai <= $waktuMulai) {

    $waktuSelesai->modify('+1 day');
}


/*
|--------------------------------------------------------------------------
| JENDELA TAP PER SHIFT
|--------------------------------------------------------------------------
|
| Shift 1: IN 06:00-12:59, OUT 14:00-05:59 hari berikutnya.
| Shift 2: IN 14:00-21:59, OUT 22:00-11:59 hari berikutnya.
|
| Data Shift 2 tetap dimuat sejak 06:00 agar tap pagi dapat mengunci
| karyawan sebagai Shift 1 dan tidak berubah menjadi IN Shift 2.
|
*/

if ($shift_id === 1) {
    $queryMulai = new DateTime($tanggal . ' 06:00:00');
    $batasPunchMasuk = new DateTime($tanggal . ' 13:00:00');
    $awalPunchOut = new DateTime($tanggal . ' 14:00:00');
    $querySelesai = new DateTime($tanggal . ' 06:00:00');
    $querySelesai->modify('+1 day');
    $dataQueryMulai = clone $queryMulai;
} else {
    $queryMulai = new DateTime($tanggal . ' 14:00:00');
    $batasPunchMasuk = new DateTime($tanggal . ' 22:00:00');
    $awalPunchOut = new DateTime($tanggal . ' 22:00:00');
    $querySelesai = new DateTime($tanggal . ' 12:00:00');
    $querySelesai->modify('+1 day');
    $dataQueryMulai = new DateTime($tanggal . ' 06:00:00');
}

$datetimeQueryMulai = $dataQueryMulai->format('Y-m-d H:i:s');
$datetimeQuerySelesai = $querySelesai->format('Y-m-d H:i:s');


/*
|--------------------------------------------------------------------------
| QUERY
|--------------------------------------------------------------------------
*/

$sql = "

SELECT

    p.id,
    e.id AS employee_id,
    p.punch_time,
    p.verifycode,
    p.workstate,

    e.emp_firstname,
    e.emp_lastname

FROM hr_employee e

INNER JOIN hr_department d
    ON d.id = e.department_id

LEFT JOIN hr_position pos
    ON pos.id = e.position_id

LEFT JOIN att_punches p
    ON p.employee_id = e.id
    AND p.punch_time >= ?
    AND p.punch_time < ?
    AND p.workstate IN (1, 2)

WHERE e.emp_active = 1
    AND UPPER(TRIM(d.dept_name)) = ?
    AND EXISTS (
        SELECT 1
        FROM hr_department d_parent
        WHERE d_parent.dept_code = d.dept_parentcode
            AND UPPER(TRIM(d_parent.dept_name)) = 'TKBM'
    )
    $groupSplitFilter

ORDER BY

    CASE
        WHEN UPPER(TRIM(COALESCE(pos.posi_name, ''))) = 'KETUA REGU' THEN 0
        ELSE 1
    END ASC,
    CAST(e.emp_pin AS UNSIGNED) ASC,
    e.id ASC,
    p.punch_time ASC,
    p.id ASC

";


$stmt = $conn->prepare($sql);

if (!$stmt) {

    die("Query gagal: " .
        $conn->error);
}


$stmt->bind_param(
    "sss",
    $datetimeQueryMulai,
    $datetimeQuerySelesai,
    $departmentName
);


$stmt->execute();

$result = $stmt->get_result();


/*
|--------------------------------------------------------------------------
| GROUP PUNCH PER EMPLOYEE
|--------------------------------------------------------------------------
*/

$employees = [];


while ($row = $result->fetch_assoc()) {

    $employeeId =
        (int) $row['employee_id'];


    /*
    |--------------------------------------------------------------------------
    | NAMA
    |--------------------------------------------------------------------------
    */

    $nama = trim(

        ($row['emp_firstname'] ?? '') .
            ' ' .
            ($row['emp_lastname'] ?? '')

    );


    if ($nama === '') {

        $nama =
            'Employee #' .
            $employeeId;
    }


    /*
    |--------------------------------------------------------------------------
    | CREATE EMPLOYEE
    |--------------------------------------------------------------------------
    */

    if (!isset($employees[$employeeId])) {

        $employees[$employeeId] = [

            'employee_id' =>
            $employeeId,

            'nama' =>
            $nama,

            'punches' =>
            []

        ];
    }


    /*
    |--------------------------------------------------------------------------
    | PUNCH
    |--------------------------------------------------------------------------
    */

    if ($row['id'] !== null && $row['punch_time'] !== null) {

        $employees[$employeeId]['punches'][] = [

            'id' =>
            (int) $row['id'],

            'punch_time' =>
            $row['punch_time'],

            'verifycode' =>
            $row['verifycode'],

            'workstate' =>
            (int) $row['workstate']

        ];
    }
}


/*
|--------------------------------------------------------------------------
| METODE ABSENSI
|--------------------------------------------------------------------------
|
| Berdasarkan data yang sebelumnya kamu kirim:
|
| verifycode 1  = Fingerprint
| verifycode 15 = Face
|
*/

function getMetode($verifycode)
{

    if (
        $verifycode === null ||
        $verifycode === ''
    ) {

        return 'Tidak diketahui';
    }


    switch ((string) $verifycode) {

        case '1':

            return 'Fingerprint';


        case '15':

            return 'Face';


        case '16':

            return 'Kode 16';


        default:

            return 'Kode ' .
                $verifycode;
    }
}


/*
|--------------------------------------------------------------------------
| FORMAT JAM
|--------------------------------------------------------------------------
*/

function formatJam($datetime)
{

    if (
        empty($datetime)
    ) {

        return '-';
    }


    return date(
        'H:i:s',
        strtotime($datetime)
    );
}


/*
|--------------------------------------------------------------------------
| FORMAT TANGGAL
|--------------------------------------------------------------------------
*/

function formatTanggal($datetime)
{

    if (
        empty($datetime)
    ) {

        return '-';
    }


    return date(
        'd-m-Y H:i:s',
        strtotime($datetime)
    );
}


/*
|--------------------------------------------------------------------------
| METODE CLASS
|--------------------------------------------------------------------------
*/

function metodeClass($metode)
{

    if (
        $metode === 'Fingerprint'
    ) {

        return 'fingerprint';
    }


    if (
        $metode === 'Face'
    ) {

        return 'face';
    }


    return 'unknown';
}


/*
|--------------------------------------------------------------------------
| STATUS CLASS
|--------------------------------------------------------------------------
*/

function statusClass($status)
{

    if (
        $status === 'HADIR'
    ) {

        return 'hadir';
    }


    if (
        $status === 'BELUM OUT'
    ) {

        return 'belum-out';
    }


    if (
        $status === 'BELUM ABSEN'
    ) {

        return 'belum-absen';
    }


    return 'tidak-absen';
}


/*
|--------------------------------------------------------------------------
| REPORT
|--------------------------------------------------------------------------
*/

$report = [];


foreach (
    $employees
    as $employee
) {


    $punches =
        $employee['punches'];


    /*
    |--------------------------------------------------------------------------
    | SORT
    |--------------------------------------------------------------------------
    */

    usort(

        $punches,

        function ($a, $b) {

            $hasil =
                strcmp(
                    $a['punch_time'],
                    $b['punch_time']
                );


            if (
                $hasil !== 0
            ) {

                return $hasil;
            }


            return
                $a['id']
                <=>
                $b['id'];
        }

    );


    /* Jika ada tap pagi, karyawan sudah termasuk Shift 1. Tap sore miliknya
       tidak boleh dibaca lagi sebagai IN Shift 2. */
    $punyaInShift1 = false;

    if ($shift_id === 2) {
        $awalDeteksiShift1 = new DateTime($tanggal . ' 06:00:00');
        $akhirDeteksiShift1 = new DateTime($tanggal . ' 13:00:00');

        foreach ($punches as $punch) {
            $waktuPunch = new DateTime($punch['punch_time']);

            if (
                $punch['workstate'] === 1 &&
                $waktuPunch >= $awalDeteksiShift1 &&
                $waktuPunch < $akhirDeteksiShift1
            ) {
                $punyaInShift1 = true;
                break;
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | CARI IN
    |--------------------------------------------------------------------------
    |
    | IN = punch workstate 1 pertama sejak batas masuk awal sampai sebelum
    | batas akhir check-in shift.
    |
    */

    $punchIn = null;


    foreach (
        $punches
        as $punch
    ) {

        $waktuPunch =
            new DateTime(
                $punch['punch_time']
            );


        if (
            !($shift_id === 2 && $punyaInShift1) &&

            $punch['workstate'] === 1 &&

            $waktuPunch >=
            $queryMulai &&

            $waktuPunch <
            $batasPunchMasuk
        ) {

            $punchIn =
                $punch;

            break;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | CARI OUT
    |--------------------------------------------------------------------------
    |
    | OUT berasal dari workstate 2 setelah IN. Waktunya ditampilkan
    | apa adanya selama masih berada dalam jendela query shift.
    |
    */

    $punchOut = null;


    foreach (
        $punches
        as $punch
    ) {

        $waktuPunch =
            new DateTime(
                $punch['punch_time']
            );


        /*
        |--------------------------------------------------------------------------
        | HARUS DALAM PERIODE SHIFT
        |--------------------------------------------------------------------------
        */

        if (
            $punch['workstate'] === 2 &&

            $waktuPunch >=
            $awalPunchOut &&

            $waktuPunch <
            $querySelesai
        ) {


            /*
            |--------------------------------------------------------------------------
            | Jangan gunakan punch IN sebagai OUT
            |--------------------------------------------------------------------------
            */

            if (
                $punchIn !== null &&

                $waktuPunch <= new DateTime(
                    $punchIn['punch_time']
                )
            ) {

                continue;
            }


            /*
            |--------------------------------------------------------------------------
            | Ambil punch terakhir
            |--------------------------------------------------------------------------
            */

            $punchOut =
                $punch;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | FALLBACK OUT SHIFT 1 BERDASARKAN JAM
    |--------------------------------------------------------------------------
    |
    | Mesin menyimpan tap pulang sebagai workstate 1. Untuk Shift 1, tap
    | workstate 1 berikutnya mulai pukul 14:00 dibaca sebagai OUT,
    | tetapi hanya jika tap IN terjadi sebelum pukul 14:00.
    | Workstate 2 tetap selalu diprioritaskan bila tersedia.
    |
    */

    if ($punchOut === null && $punchIn !== null && $shift_id === 1) {
        $waktuPunchIn = new DateTime($punchIn['punch_time']);

        if ($waktuPunchIn < $batasPunchMasuk) {
            foreach ($punches as $punch) {
                if ($punch['workstate'] !== 1 || $punch['id'] === $punchIn['id']) {
                    continue;
                }

                $waktuPunch = new DateTime($punch['punch_time']);

                if (
                    $waktuPunch > $waktuPunchIn &&
                    $waktuPunch >= $awalPunchOut &&
                    $waktuPunch < $querySelesai
                ) {
                    $punchOut = $punch;
                }
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | FALLBACK OUT SHIFT 2 BERDASARKAN JAM
    |--------------------------------------------------------------------------
    |
    | Jika mesin tetap mengirim workstate 1, tap berikutnya pada pukul
    | 22:00 sampai sebelum 12:00 hari berikutnya dibaca sebagai OUT Shift 2.
    |
    */

    if (
        $punchOut === null &&
        $punchIn !== null &&
        $shift_id === 2 &&
        !$punyaInShift1
    ) {
        $waktuPunchIn = new DateTime($punchIn['punch_time']);

        foreach ($punches as $punch) {
            if ($punch['workstate'] !== 1 || $punch['id'] === $punchIn['id']) {
                continue;
            }

            $waktuPunch = new DateTime($punch['punch_time']);

            if (
                $waktuPunch > $waktuPunchIn &&
                $waktuPunch >= $awalPunchOut &&
                $waktuPunch < $querySelesai
            ) {
                $punchOut = $punch;
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | HASIL IN
    |--------------------------------------------------------------------------
    */

    if (
        $punchIn !== null
    ) {

        $jamIn =
            $punchIn['punch_time'];

        $metodeIn =
            getMetode(
                $punchIn['verifycode']
            );
    } else {

        $jamIn =
            null;

        $metodeIn =
            '-';
    }


    /*
    |--------------------------------------------------------------------------
    | HASIL OUT
    |--------------------------------------------------------------------------
    */

    if (
        $punchIn !== null &&

        $punchOut !== null
    ) {


        /*
        |--------------------------------------------------------------------------
        | Ada OUT normal
        |--------------------------------------------------------------------------
        */

        $jamOut =
            $punchOut['punch_time'];

        $metodeOut =
            getMetode(
                $punchOut['verifycode']
            );

        $status =
            'HADIR';
    } elseif (
        $punchIn !== null
    ) {


        /*
        |--------------------------------------------------------------------------
        | Ada IN tetapi belum ada OUT
        |--------------------------------------------------------------------------
        */

        $jamOut =
            null;

        $metodeOut =
            '-';

        $status =
            'BELUM OUT';
    } else {


        /*
        |--------------------------------------------------------------------------
        | Tidak ada absensi
        |--------------------------------------------------------------------------
        */

        $jamOut =
            null;

        $metodeOut =
            '-';

        $status = $isPastDate
            ? 'TIDAK ABSEN'
            : 'BELUM ABSEN';
    }


    /*
    |--------------------------------------------------------------------------
    | SIMPAN
    |--------------------------------------------------------------------------
    */

    $report[] = [

        'employee_id' =>
        $employee['employee_id'],

        'nama' =>
        $employee['nama'],

        'jam_in' =>
        $jamIn,

        'metode_in' =>
        $metodeIn,

        'jam_out' =>
        $jamOut,

        'metode_out' =>
        $metodeOut,

        'status' =>
        $status

    ];
}


/*
|--------------------------------------------------------------------------
| SORT NAMA
|--------------------------------------------------------------------------
*/

usort(

    $report,

    function ($a, $b) {

        return strcasecmp(
            $a['nama'],
            $b['nama']
        );
    }

);


/*
|--------------------------------------------------------------------------
| SUMMARY
|--------------------------------------------------------------------------
*/

$total =
    count($report);

$hadir =
    0;

$belumOut =
    0;

$absenKosong =
    0;

$absenKosongLabel = $isPastDate
    ? 'Tidak Absen'
    : 'Belum Absen';

$absenKosongColor = $isPastDate
    ? '#dc2626'
    : '#2563eb';


foreach (
    $report
    as $row
) {

    if (
        $row['status'] ===
        'HADIR'
    ) {

        $hadir++;
    }


    if (
        $row['status'] ===
        'BELUM OUT'
    ) {

        $belumOut++;
    }


    if (in_array(
        $row['status'],
        ['TIDAK ABSEN', 'BELUM ABSEN'],
        true
    )) {

        $absenKosong++;
    }
}

?>

<!DOCTYPE html>

<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>
        Report Absensi
    </title>


    <!-- =====================================================
     DATATABLE CSS
     ===================================================== -->

    <link
        rel="stylesheet"
        href="https://cdn.datatables.net/2.3.3/css/dataTables.dataTables.min.css">

    <link
        rel="stylesheet"
        href="https://cdn.datatables.net/buttons/3.2.6/css/buttons.dataTables.min.css">


    <style>
        /*
|--------------------------------------------------------------------------
| RESPONSIVE MOBILE
|--------------------------------------------------------------------------
*/

@media (max-width: 768px) {

    body {
        background: #e5e7eb;
        overflow-x: auto;
    }


    /*
    |--------------------------------------------------------------------------
    | TOOLBAR
    |--------------------------------------------------------------------------
    */

    .toolbar {

        width: 100%;

        margin:
            10px
            auto;

        padding:
            0
            10px;

        display: flex;

        flex-direction: column;

        gap: 8px;

    }


    .toolbar .btn {

        width: 100%;

        text-align: center;

        font-size: 13px;

    }


    /*
    |--------------------------------------------------------------------------
    | WRAPPER REPORT
    |--------------------------------------------------------------------------
    */

    .paper {

        /*
        Tetap pertahankan ukuran A4.
        Jangan pakai width:100% karena layout tabel bisa rusak.
        */

        width: 210mm;

        min-height: 297mm;

        margin:
            10px
            auto
            20px;

        /*
        Sedikit diperkecil untuk preview HP
        */

        transform: scale(0.72);

        transform-origin:
            top
            center;

    }

}


/*
|--------------------------------------------------------------------------
| MOBILE KECIL
|--------------------------------------------------------------------------
*/

@media (max-width: 480px) {

    .paper {

        transform: scale(0.55);

        transform-origin:
            top
            center;

    }

}
        /* =====================================================
   GLOBAL
   ===================================================== */

        * {
            box-sizing: border-box;
        }


        body {

            margin: 0;

            background: #f1f5f9;

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            color: #1e293b;

        }


        /* =====================================================
   HEADER
   ===================================================== */

        .header {

            background:
                linear-gradient(135deg,
                    #0f172a,
                    #1d4ed8);

            color: white;

            padding: 25px 30px;

        }


        .header h1 {

            margin: 0;

            font-size: 26px;

        }


        .header p {

            margin:
                7px 0 0;

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

            border-radius: 12px;

            padding: 20px;

            margin-bottom: 20px;

            box-shadow:
                0 3px 12px rgba(0, 0, 0, .07);

        }


        .filter-row {

            display: flex;

            align-items: end;

            flex-wrap: wrap;

            gap: 15px;

        }


        .filter-item {

            display: flex;

            flex-direction: column;

            gap: 7px;

        }


        .filter-item label {

            font-size: 13px;

            font-weight: bold;

            color: #475569;

        }


        .filter-item input,
        .filter-item select {

            padding:
                10px 12px;

            border:
                1px solid #cbd5e1;

            border-radius: 7px;

            min-width: 200px;

            font-size: 14px;

        }


        .btn {

            padding:
                11px 22px;

            border: 0;

            border-radius: 7px;

            background: #2563eb;

            color: white;

            font-weight: bold;

            cursor: pointer;

        }


        .btn:hover {

            background: #1d4ed8;

        }


        /* =====================================================
   INFO SHIFT
   ===================================================== */

        .shift-info {

            margin-top: 15px;

            padding: 13px 15px;

            border-radius: 8px;

            background: #eff6ff;

            border:
                1px solid #bfdbfe;

            color: #1e40af;

            line-height: 1.7;

        }


        .employee-info-box {

            display: flex;

            justify-content: space-between;

            align-items: center;

            gap: 10px;

            flex-wrap: wrap;

            margin-bottom: 15px;
        }


        .employee-info-text {

            color: #64748b;
        }


        .employee-info-total {

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

            display: flex;

            gap: 15px;

            flex-wrap: wrap;

            margin-bottom: 20px;

        }


        .card {

            background: white;

            padding: 17px 22px;

            border-radius: 10px;

            min-width: 170px;

            box-shadow:
                0 3px 12px rgba(0, 0, 0, .06);

        }


        .card-title {

            font-size: 12px;

            color: #64748b;

        }


        .card-number {

            margin-top: 5px;

            font-size: 25px;

            font-weight: bold;

        }


        /* =====================================================
   TABLE
   ===================================================== */

        .table-box {

            background: white;

            padding: 20px;

            border-radius: 12px;

            box-shadow:
                0 3px 12px rgba(0, 0, 0, .07);

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


        /* =====================================================
   JAM
   ===================================================== */

        .jam {

            font-weight: bold;

            white-space: nowrap;

        }


        /* =====================================================
   METODE
   ===================================================== */

        .metode {

            display: inline-block;

            padding:
                5px 10px;

            border-radius: 20px;

            font-size: 12px;

            font-weight: bold;

            white-space: nowrap;

        }


        .fingerprint {

            background: #dbeafe;

            color: #1d4ed8;

        }


        .face {

            background: #f3e8ff;

            color: #7e22ce;

        }


        .unknown {

            background: #e2e8f0;

            color: #475569;

        }


        /* =====================================================
   STATUS
   ===================================================== */

        .status {

            display: inline-block;

            padding:
                5px 10px;

            border-radius: 20px;

            font-size: 12px;

            font-weight: bold;

        }


        .hadir {

            background: #dcfce7;

            color: #166534;

        }


        .belum-out {

            background: #ffedd5;

            color: #9a3412;

        }


        .belum-absen {

            background: #dbeafe;

            color: #1d4ed8;

        }


        .tidak-absen {

            background: #fee2e2;

            color: #991b1b;

        }


        /* =====================================================
   EXPORT
   ===================================================== */

        .btn-pdf {

        display: inline-block;

        margin-bottom: 15px;

        padding:
            10px
            18px;

        background: #dc2626;

        color: white;

        border-radius: 7px;

        text-decoration: none;

        font-size: 14px;

        font-weight: bold;

        }


        .btn-pdf:hover {

            background: #b91c1c;

        }

        .dt-button {

            background: #16a34a !important;

            color: white !important;

            border: 0 !important;

            border-radius: 7px !important;

            padding:
                9px 15px !important;

            font-weight: bold !important;

        }


        .dt-button:hover {

            background: #15803d !important;

        }


        /* =====================================================
   MOBILE
   ===================================================== */

        @media(max-width:700px) {

            .container {

                padding: 12px;

            }


            .filter-row {

                display: block;

            }


            .filter-item {

                margin-bottom: 12px;

            }


            .filter-item input,
            .filter-item select {

                width: 100%;

            }


            .btn {

                width: 100%;

            }


            .table-box {

                padding: 10px;

            }

        }
</style>

    <link rel="stylesheet" href="assets/app.css?v=6">
    <script src="assets/app.js?v=1" defer></script>

</head>


<body class="page-report page-report-tkbm page-transition">


    <!-- =====================================================
     HEADER
     ===================================================== -->

    <div class="header">

        <a href="index.php" class="app-back">← Menu Utama</a>

        <h1>
            Report Absensi TKBM
        </h1>

        <p>
            Monitoring kehadiran berdasarkan tanggal dan shift
        </p>

    </div>


    <div class="container">


        <!-- =====================================================
     FILTER
     ===================================================== -->

        <div class="filter-box">

            <form method="GET">

                <div class="filter-row">


                    <div class="filter-item">

                        <label>
                            Tanggal
                        </label>

                        <input
                            type="date"
                            name="tanggal"
                            value="<?= htmlspecialchars($tanggal) ?>"
                            required>

                    </div>


                    <div class="filter-item">

                        <label>
                            Shift
                        </label>

                        <select name="shift">

                            <?php foreach (
                                $shifts
                                as $id => $s
                            ): ?>

                                <option
                                    value="<?= $id ?>"
                                    <?= (
                                        $shift_id == $id
                                    )
                                        ? 'selected'
                                        : ''
                                    ?>>

                                    <?= htmlspecialchars(
                                        $s['nama']
                                    ) ?>

                                    -

                                    <?= substr(
                                        $s['mulai'],
                                        0,
                                        5
                                    ) ?>

                                    s/d

                                    <?= substr(
                                        $s['selesai'],
                                        0,
                                        5
                                    ) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <div class="filter-item">

                        <label>
                            Group
                        </label>

                        <select name="group">

                            <?php foreach ($tkbmGroups as $groupOption): ?>

                                <option
                                    value="<?= htmlspecialchars($groupOption) ?>"
                                    <?= $group === $groupOption ? 'selected' : '' ?>>

                                    Group <?= htmlspecialchars($groupOption) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <button
                        type="submit"
                        class="btn">

                        🔍 Tampilkan

                    </button>


                </div>


                <!-- =====================================================
     INFO
     ===================================================== -->

                <div class="shift-info">

                    <strong>
                        <?= htmlspecialchars(
                            $shift['nama']
                        ) ?>
                    </strong>

                    — Group
                    <strong>
                        <?= htmlspecialchars($group) ?>
                    </strong>

                    :

                    <?= $waktuMulai->format(
                        'd-m-Y H:i'
                    ) ?>

                    s/d

                    <?= $waktuSelesai->format(
                        'd-m-Y H:i'
                    ) ?>

                    <br>

                    <small>

                        Jam OUT mengikuti checkout
                        <strong>workstate 2</strong>
                        yang tersimpan di database.

                    </small>

                </div>

            </form>

        </div>


        <?php if (isset($_GET['tanggal'])) { ?>
            <div class="employee-info-box">

                <div class="employee-info-text">
                    Jumlah karyawan Group
                    <strong><?= htmlspecialchars($group) ?></strong>
                    <?= htmlspecialchars($shift['nama']) ?>
                </div>

                <div class="employee-info-total">
                    <?= $total ?> Karyawan
                </div>

            </div>
        <?php } ?>


        <!-- =====================================================
     SUMMARY
     ===================================================== -->
        <?php if (isset($_GET['tanggal'])) { ?>
            <div class="summary">


                <div class="card">

                    <div class="card-title">
                        Total Data
                    </div>

                    <div class="card-number">

                        <?= $total ?>

                    </div>

                </div>


                <div class="card">

                    <div class="card-title">
                        Hadir
                    </div>

                    <div
                        class="card-number"
                        style="color:#16a34a">

                        <?= $hadir ?>

                    </div>

                </div>


                <div class="card">

                    <div class="card-title">
                        Belum OUT
                    </div>

                    <div
                        class="card-number"
                        style="color:#ea580c">

                        <?= $belumOut ?>

                    </div>

                </div>


                <div class="card">

                    <div class="card-title">
                        <?= htmlspecialchars($absenKosongLabel) ?>
                    </div>

                    <div
                        class="card-number"
                        style="color:<?= $absenKosongColor ?>">

                        <?= $absenKosong ?>

                    </div>

                </div>


            </div>


            <!-- =====================================================
        TABLE
        ===================================================== -->
            <a
                href="print_tkbm.php?tanggal=<?= urlencode($tanggal) ?>&amp;shift=<?= $shift_id ?>&amp;group=<?= urlencode($group) ?>"
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
                        foreach ( $report as $row ): ?>

                            <tr>
                                <td>
                                    <?= $no++ ?>
                                </td>

                                <td>
                                    <strong>
                                        <?= htmlspecialchars( $row['nama'] ) ?>
                                    </strong>
                                </td>

                                <td class="jam">
                                    <?= formatJam($row['jam_in'] ) ?>
                                </td>
                                <td>
                                    <?php if ($row['metode_in'] !== '-' ): ?>
                                        <span class=" metode <?= metodeClass($row['metode_in']) ?>"><?= htmlspecialchars($row['metode_in']) ?>
                                        </span>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td class="jam">
                                    <?= formatJam($row['jam_out'] ) ?>
                                </td>
                                <td>
                                    <?php if ( $row['metode_out'] !== '-' ): ?>

                                        <span class="metode <?= metodeClass($row['metode_out']) ?>">

                                            <?= htmlspecialchars(
                                                $row['metode_out']
                                            ) ?>

                                        </span>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>


                                <td>

                                    <span class="status <?= statusClass($row['status'] ) ?> ">

                                        <?= htmlspecialchars(
                                            $row['status']
                                        ) ?>

                                    </span>

                                </td>


                            </tr>

                        <?php endforeach; ?>


                    </tbody>

                </table>


            </div>


        <?php } ?>
    </div>


    <!-- =====================================================
     JQUERY CDN
     ===================================================== -->

    <script
        src="https://code.jquery.com/jquery-3.7.1.min.js"></script>


    <!-- =====================================================
     DATATABLES JS
     ===================================================== -->

    <script
        src="https://cdn.datatables.net/2.3.3/js/dataTables.min.js"></script>


    <!-- =====================================================
     BUTTONS
     ===================================================== -->

    <script
        src="https://cdn.datatables.net/buttons/3.2.6/js/dataTables.buttons.min.js"></script>


    <!-- =====================================================
     JSZIP
     ===================================================== -->

    <script
        src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>


    <!-- =====================================================
     EXCEL BUTTON
     ===================================================== -->

    <script
        src="https://cdn.datatables.net/buttons/3.2.6/js/buttons.html5.min.js"></script>


    <script>
        $(document).ready(function() {


            new DataTable(
                '#tabelAbsensi', {

                    pageLength: 25,


                    lengthMenu: [

                        [10, 25, 50, 100, -1],

                        [
                            10,
                            25,
                            50,
                            100,
                            'Semua'
                        ]

                    ],


                    order: [
                        [1, 'asc']
                    ],


                  layout: {

                                topEnd: 'search',

                                bottomStart: 'info',

                                bottomEnd: 'paging'

                            },


                    language: {

                        search: 'Cari:',


                        searchPlaceholder: 'Cari nama...',


                        lengthMenu: 'Tampilkan _MENU_ data',


                        info: 'Menampilkan _START_ sampai _END_ dari _TOTAL_ data',


                        infoEmpty: 'Tidak ada data',


                        infoFiltered: '(difilter dari _MAX_ data)',


                        zeroRecords: 'Data tidak ditemukan',


                        emptyTable: 'Tidak ada data absensi',


                        paginate: {

                            first: 'Awal',

                            last: 'Akhir',

                            next: '›',

                            previous: '‹'

                        }

                    }

                }
            );

        });
    </script>


</body>

</html>
