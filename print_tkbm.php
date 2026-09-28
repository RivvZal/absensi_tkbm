<?php

require_once 'auth.php';
requireLogin();

require_once 'koneksi.php';

$reportMode = ($reportMode ?? 'TKBM') === 'HRD'
    ? 'HRD'
    : 'TKBM';

$isHrd = $reportMode === 'HRD';
$reportLabel = $isHrd ? 'HRD' : 'TKBM';
$reportNameLabel = $isHrd ? 'H.R.D' : 'T.K.B.M';

/*
|--------------------------------------------------------------------------
| TANGGAL
|--------------------------------------------------------------------------
*/

$tanggal = $_GET['tanggal'] ?? date('Y-m-d');


/*
|--------------------------------------------------------------------------
| VALIDASI TANGGAL
|--------------------------------------------------------------------------
*/

$dateObj = DateTime::createFromFormat(
    'Y-m-d',
    $tanggal
);

if (
    !$dateObj ||
    $dateObj->format('Y-m-d') !== $tanggal
) {

    $tanggal = date('Y-m-d');
}


/*
|--------------------------------------------------------------------------
| FORMAT TANGGAL 
|--------------------------------------------------------------------------
*/

function formatTanggalIndonesia($tanggal)
{

    $bulan = [

        1  => 'JANUARI',
        2  => 'FEBRUARI',
        3  => 'MARET',
        4  => 'APRIL',
        5  => 'MEI',
        6  => 'JUNI',
        7  => 'JULI',
        8  => 'AGUSTUS',
        9  => 'SEPTEMBER',
        10 => 'OKTOBER',
        11 => 'NOVEMBER',
        12 => 'DESEMBER'

    ];


    $timestamp = strtotime($tanggal);

    $tanggalAngka =
        date('d', $timestamp);

    $bulanAngka =
        (int) date('m', $timestamp);

    $tahun =
        date('Y', $timestamp);


    return
        $tanggalAngka .
        ' ' .
        $bulan[$bulanAngka] .
        ' ' .
        $tahun;
}


/*
|--------------------------------------------------------------------------
| INFORMASI FORM
|--------------------------------------------------------------------------
|
| Untuk sementara dibuat manual / dummy.
| Nanti bisa diubah 
|
*/

$nomorDokumen   = '';
$tanggalEfektif = '';
$revisi         = '';
$halaman        = '1';

$tkbmGroups = array_merge(
    range('A', 'Z'),
    ['AA', 'BB', 'CC']
);

$group = strtoupper(trim($_GET['group'] ?? 'A'));

if (!in_array($group, $tkbmGroups, true)) {
    $group = 'A';
}

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

$shiftId = isset($_GET['shift']) ? (int) $_GET['shift'] : 1;

if (!in_array($shiftId, [1, 2], true)) {
    $shiftId = 1;
}

/*
|--------------------------------------------------------------------------
| DATA TKBM DARI DATABASE
|--------------------------------------------------------------------------
*/

$tanggalBesok = date('Y-m-d', strtotime($tanggal . ' +1 day'));

if ($isHrd) {
    $sql = "
        SELECT
            e.id AS employee_id,
            TRIM(CONCAT(
                COALESCE(e.emp_firstname, ''),
                ' ',
                COALESCE(e.emp_lastname, '')
            )) AS nama,
            COALESCE(
                NULLIF(TRIM(pos.posi_name), ''),
                NULLIF(TRIM(e.emp_title), ''),
                ''
            ) AS jabatan,
            COALESCE(
                DATE_FORMAT(
                    MIN(CASE WHEN p.workstate = 1 THEN p.punch_time END),
                    '%H:%i:%s'
                ),
                ''
            ) AS masuk,
            CASE
                WHEN SUM(CASE WHEN p.workstate = 2 THEN 1 ELSE 0 END) > 0
                    THEN DATE_FORMAT(
                        MAX(CASE WHEN p.workstate = 2 THEN p.punch_time END),
                        '%H:%i:%s'
                    )
                ELSE ''
            END AS keluar,
            COALESCE(
                NULLIF(TRIM(e.emp_cardNumber), ''),
                NULLIF(TRIM(e.emp_pin), ''),
                ''
            ) AS kta
        FROM hr_employee e
        LEFT JOIN hr_department d
            ON d.id = e.department_id
        LEFT JOIN hr_position pos
            ON pos.id = e.position_id
        LEFT JOIN att_punches p
            ON p.employee_id = e.id
            AND p.punch_time >= ?
            AND p.punch_time < ?
            AND p.workstate IN (1, 2)
        WHERE e.emp_active = 1
            AND UPPER(TRIM(COALESCE(d.dept_name, ''))) <> 'TKBM'
            AND NOT EXISTS (
                SELECT 1
                FROM hr_department d_parent
                WHERE d_parent.dept_code = d.dept_parentcode
                    AND UPPER(TRIM(d_parent.dept_name)) = 'TKBM'
            )
        GROUP BY
            e.id,
            e.emp_firstname,
            e.emp_lastname,
            pos.posi_name,
            e.emp_title,
            e.emp_cardNumber,
            e.emp_pin
        ORDER BY
            nama ASC,
            e.id ASC
    ";
} else {
    $sql = "
        SELECT
            e.id AS employee_id,
            TRIM(CONCAT(
                COALESCE(e.emp_firstname, ''),
                ' ',
                COALESCE(e.emp_lastname, '')
            )) AS nama,
            COALESCE(
                NULLIF(TRIM(pos.posi_name), ''),
                NULLIF(TRIM(e.emp_title), ''),
                ''
            ) AS jabatan,
            COALESCE(
                DATE_FORMAT(
                    MIN(CASE WHEN p.workstate = 1 THEN p.punch_time END),
                    '%H:%i:%s'
                ),
                ''
            ) AS masuk,
            CASE
                WHEN SUM(CASE WHEN p.workstate = 2 THEN 1 ELSE 0 END) > 0
                    THEN DATE_FORMAT(
                        MAX(CASE WHEN p.workstate = 2 THEN p.punch_time END),
                        '%H:%i:%s'
                    )
                ELSE ''
            END AS keluar,
            COALESCE(
                NULLIF(TRIM(e.emp_cardNumber), ''),
                NULLIF(TRIM(e.emp_pin), ''),
                ''
            ) AS kta
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
        GROUP BY
            e.id,
            e.emp_firstname,
            e.emp_lastname,
            pos.posi_name,
            e.emp_title,
            e.emp_cardNumber,
            e.emp_pin
        ORDER BY
            CASE
                WHEN UPPER(TRIM(COALESCE(pos.posi_name, ''))) = 'KETUA REGU' THEN 0
                ELSE 1
            END ASC,
            CAST(e.emp_pin AS UNSIGNED) ASC,
            e.id ASC
    ";
}

$stmt = $conn->prepare($sql);

if ($isHrd) {
    $stmt->bind_param('ss', $tanggal, $tanggalBesok);
} else {
    $stmt->bind_param('sss', $tanggal, $tanggalBesok, $departmentName);
}

$stmt->execute();
$result = $stmt->get_result();

$dataReport = [];

while ($row = $result->fetch_assoc()) {
    $dataReport[] = $row;
}

?>

<!DOCTYPE html>

<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Absensi <?= htmlspecialchars($reportLabel) ?> Graha Segara
    </title>


    <style>

        /*
        |--------------------------------------------------------------------------
        | RESET
        |--------------------------------------------------------------------------
        */

        * {

            box-sizing: border-box;

        }


        body {

            margin: 0;

            padding: 0;

            background: #e5e7eb;

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            color: #000;

        }


        /*
        |--------------------------------------------------------------------------
        | TOOLBAR
        |--------------------------------------------------------------------------
        */

        .toolbar {

            width: 210mm;

            margin:
                20px
                auto
                10px;

            display: flex;

            justify-content: flex-end;

            gap: 10px;

        }


        .btn {

            border: none;

            padding:
                10px
                18px;

            border-radius: 6px;

            font-size: 14px;

            font-weight: bold;

            cursor: pointer;

            text-decoration: none;

            display: inline-block;

        }


        .btn-print {

            background: #dc2626;

            color: white;

        }


        .btn-back {

            background: #475569;

            color: white;

        }


        /*
        |--------------------------------------------------------------------------
        | A4 PAPER
        |--------------------------------------------------------------------------
        */

        .paper {

            width: 210mm;

            min-height: 297mm;

            margin:
                0
                auto
                30px;

            padding:
                10mm
                11mm
                8mm;

            background: white;

            box-shadow:
                0
                4px
                20px
                rgba(0, 0, 0, .15);

        }


        /*
        |--------------------------------------------------------------------------
        | GLOBAL TABLE
        |--------------------------------------------------------------------------
        */

        table {

            width: 100%;

            border-collapse: collapse;

        }


        /*
        |--------------------------------------------------------------------------
        | HEADER FORM
        |--------------------------------------------------------------------------
        */

        .form-header {

            table-layout: fixed;

        }


        .form-header td {

            border:
                1.2px
                solid
                #000;

        }


        /*
        |--------------------------------------------------------------------------
        | LOGO
        |--------------------------------------------------------------------------
        */

        .logo {

            width: 23%;

            height: 30mm;

            text-align: center;

            vertical-align: middle;

        }


        .logo img {

            width: 26mm;

            max-height: 25mm;

            object-fit: contain;

        }


        /*
        |--------------------------------------------------------------------------
        | FORM TITLE
        |--------------------------------------------------------------------------
        */

        .form-title {

            width: 49%;

            text-align: center;

            vertical-align: middle;

            font-size: 12pt;

            font-weight: bold;

        }


        /*
        |--------------------------------------------------------------------------
        | DOCUMENT INFORMATION
        |--------------------------------------------------------------------------
        */

        .document-label {

            width: 15%;

            height: 7.5mm;

            padding-left: 4px;

            font-size: 8pt;

            font-weight: bold;

            vertical-align: middle;

        }


        .document-value {

            width: 13%;

            font-size: 8pt;

            padding-left: 4px;

        }


        /*
        |--------------------------------------------------------------------------
        | COMPANY TITLE
        |--------------------------------------------------------------------------
        */

        .company-name {

            width: 35%;

            margin:
                5mm
                auto
                6mm;

            border:
                1.2px
                solid
                #000;

            text-align: center;

            padding:
                4mm
                2mm;

            font-size: 11pt;

            font-weight: bold;

        }


        /*
        |--------------------------------------------------------------------------
        | INFORMATION
        |--------------------------------------------------------------------------
        */

        .report-info {

            width: 78%;

            margin:
                0
                auto
                5mm;

            table-layout: fixed;

        }


        .report-info td {

            border: none;

            padding:
                2.2mm
                1mm;

            font-size: 9pt;

            font-weight: bold;

        }


        .info-left {

            width: 52%;

            text-align: left;

            text-decoration: underline;

        }


        .info-right {

            width: 48%;

            text-align: center;

        }


        /*
        |--------------------------------------------------------------------------
        | ATTENDANCE TABLE
        |--------------------------------------------------------------------------
        */

        .attendance {

            width: 100%;

            table-layout: fixed;

        }


        .attendance th,
        .attendance td {

            border:
                1px
                solid
                #000;

            vertical-align: middle;

        }


        .attendance th {

            height: 9mm;

            padding: 1mm;

            text-align: center;

            font-size: 7.5pt;

            font-weight: bold;

        }


        .attendance td {

            height: 10mm;

            padding:
                1mm
                1.5mm;

            font-size: 7.5pt;

        }


        /*
        |--------------------------------------------------------------------------
        | COLUMN WIDTH
        |--------------------------------------------------------------------------
        */

        .no {

            width: 5%;

            text-align: center;

        }


        .nama {

            width: 25%;

        }


        .jabatan {

            width: 14%;

            text-align: center;

        }


        .masuk {

            width: 13%;

            text-align: center;

        }


        .paraf {

            width: 11%;

            text-align: center;

        }


        .keluar {

            width: 13%;

            text-align: center;

        }


        .kta {

            width: 10%;

            text-align: center;

        }


        .employee-name {

            font-weight: bold;

        }


        /*
        |--------------------------------------------------------------------------
        | SIGNATURE
        |--------------------------------------------------------------------------
        */

        .signature {

            width: 90%;

            margin:
                7mm
                auto
                0;

            table-layout: fixed;

        }


        .signature td {

            border: none;

            width: 33.333%;

            text-align: center;

            font-size: 8pt;

        }


        .signature-label {

            height: 20mm;

            vertical-align: top;

        }


        .signature-person {

            font-weight: bold;

            vertical-align: bottom;

        }


        /*
        |--------------------------------------------------------------------------
        | NOTE
        |--------------------------------------------------------------------------
        */

        .note {

            margin-top: 10mm;

            font-size: 6.5pt;

            line-height: 1.35;

        }


        /*
        |--------------------------------------------------------------------------
        | PRINT SETTINGS
        |--------------------------------------------------------------------------
        */

        @page {

            size: 210mm 297mm;

            margin: 0;

        }


        @media print {

            * {

                -webkit-print-color-adjust: exact;

                print-color-adjust: exact;

            }


            html,
            body.page-print {

                width: 210mm !important;

                min-width: 210mm !important;

                max-width: 210mm !important;

                height: 297mm !important;

                min-height: 297mm !important;

                max-height: 297mm !important;

                margin: 0 !important;

                padding: 0 !important;

                overflow: hidden !important;

                background: white !important;

            }


            body.page-print {

                position: static !important;

                display: block !important;

                transform: none !important;

                rotate: 0deg !important;

            }


            body.page-print .toolbar {

                display: none !important;

            }


            body.page-print .paper {

                position: relative !important;

                left: 0 !important;

                top: 0 !important;

                display: flex !important;

                flex-direction: column !important;

                width: 210mm !important;

                min-width: 210mm !important;

                max-width: 210mm !important;

                height: 296.5mm !important;

                min-height: 296.5mm !important;

                max-height: 296.5mm !important;

                margin: 0 !important;

                padding: 8mm 10mm 6mm !important;

                overflow: hidden !important;

                border: 0 !important;

                border-radius: 0 !important;

                box-shadow: none !important;

                transform: none !important;

                rotate: 0deg !important;

                animation: none !important;

                transition: none !important;

                page-break-before: avoid !important;

                page-break-after: avoid !important;

                page-break-inside: avoid !important;

                break-before: avoid-page !important;

                break-after: avoid-page !important;

                break-inside: avoid-page !important;

            }


            body.page-print .attendance-scroll {

                overflow: visible !important;

            }


            body.page-print .note {

                position: absolute !important;

                right: 10mm !important;

                bottom: 6mm !important;

                left: 10mm !important;

                margin-top: 0 !important;

            }


            .attendance tr {

                page-break-inside: avoid;

                break-inside: avoid;

            }

        }

</style>

    <link rel="stylesheet" href="assets/app.css?v=6">
    <script src="assets/app.js?v=1" defer></script>

</head>


<body class="page-print page-transition">


    <!-- ==========================================================
         BUTTON
         ========================================================== -->

    <div class="toolbar">

        <a
            href="<?= $isHrd
                ? 'hrd_report.php?tanggal=' . urlencode($tanggal)
                : 'tkbm_report.php?tanggal=' . urlencode($tanggal) . '&amp;shift=' . $shiftId . '&amp;group=' . urlencode($group)
            ?>"
            class="btn btn-back"
        >

            ← Kembali

        </a>


        <button
            type="button"
            class="btn btn-print"
            onclick="window.print()"
        >

            🖨 Print / Save PDF

        </button>

    </div>



    <!-- ==========================================================
         A4 PAGE
         ========================================================== -->

    <div class="paper">


        <!-- ======================================================
             FORM HEADER
             ====================================================== -->

        <table class="form-header">

            <tr>

                <td
                    rowspan="4"
                    class="logo"
                >

                    <img
                        src="assets/logo_graha_segara.png"
                        alt="Graha Segara"
                    >

                </td>


                <td
                    rowspan="2"
                    class="form-title"
                >

                    FORM OPERATING

                </td>


                <td class="document-label">

                    Nomor Dokumen

                </td>


                <td class="document-value">

                    GS-OPS-HA-300-01-06

                </td>

            </tr>



            <tr>

                <td class="document-label">

                    Tanggal Efektif

                </td>


                <td class="document-value">

                    01 Juli 2022

                </td>

            </tr>



            <tr>

                <td
                    rowspan="2"
                    class="form-title"
                >

                    ABSEN <?= htmlspecialchars($reportLabel) ?>

                </td>


                <td class="document-label">

                    Revisi

                </td>


                <td class="document-value">

                   00

                </td>

            </tr>



            <tr>

                <td class="document-label">

                    Halaman

                </td>


                <td class="document-value">

                    <?= htmlspecialchars(
                        $halaman
                    ) ?>

                </td>

            </tr>

        </table>



        <!-- ======================================================
             COMPANY NAME
             ====================================================== -->

        <div class="company-name">

            GRAHA SEGARA

        </div>



        <!-- ======================================================
             REPORT INFORMATION
             ====================================================== -->

        <table class="report-info">

            <tr>

                <td class="info-left" style="text-align:center;">
                
                    <?= $isHrd ? 'DIVISI HRD' : 'DIVISI BEHANDEL' ?>
            
                </td>
                 <td class="info-left">
                
                 
            
                </td>


                <td class="info-right">

                    Tanggal
                    <?= htmlspecialchars(
                        formatTanggalIndonesia(
                            $tanggal
                        )
                    ) ?>

                </td>

            </tr>



            <tr>

                <td class="info-left" style="text-align:center;">

                    ABSENSI <?= htmlspecialchars($reportLabel) ?> GRAHA

                </td>
                
                <td class="info-right" >

                </td>


                <td class="info-right" >

                    <?php if ($isHrd): ?>
                        Departemen HRD
                    <?php else: ?>
                        Group
                        <?= htmlspecialchars($group) ?>
                    <?php endif; ?>

                </td>

            </tr>

        </table>



        <!-- ======================================================
             ATTENDANCE TABLE
             ====================================================== -->

        <div class="attendance-scroll">

        <table class="attendance">

            <thead>

                <tr>

                    <th class="no">
                        No.
                    </th>

                    <th class="nama">
                        Nama <?= htmlspecialchars($reportNameLabel) ?>
                    </th>

                    <th class="jabatan">
                        Jabatan
                    </th>

                    <th class="masuk">
                        Masuk
                    </th>

                    <th class="paraf">
                        Paraf
                    </th>

                    <th class="keluar">
                        Keluar
                    </th>

                    <th class="paraf">
                        Paraf
                    </th>

                    <th class="kta">
                        KTA
                    </th>

                </tr>

            </thead>


            <tbody>

                <?php

                $no = 1;

                foreach (
                    $dataReport
                    as $row
                ):

                    $actualMasuk = trim((string) ($row['masuk'] ?? ''));
                    $actualKeluar = trim((string) ($row['keluar'] ?? ''));
                    $hasCheckIn = $actualMasuk !== '';

                    $isShift2 = $hasCheckIn &&
                        $actualMasuk >= '14:00:00' &&
                        $actualMasuk < '22:00:00';

                    $printMasuk = $actualMasuk;

                    if (!$hasCheckIn) {
                        $printKeluar = '';
                    } elseif ($isHrd) {
                        $printKeluar = $actualKeluar;
                    } else {
                        $printKeluar = $isShift2 ? '00:00:00' : '16:00:00';
                    }

                ?>

                    <tr>

                        <td class="no">

                            <?= $no++ ?>

                        </td>


                        <td class="nama employee-name">

                            <?= htmlspecialchars(
                                $row['nama']
                            ) ?>

                        </td>


                        <td class="jabatan">

                            <?= htmlspecialchars(
                                $row['jabatan']
                            ) ?>

                        </td>


                        <td class="masuk">

                            <?= htmlspecialchars(
                                $printMasuk
                            ) ?>

                        </td>


                        <!-- PARAF MASUK -->

                        <td class="paraf">

                        </td>


                        <td class="keluar">

                            <?= htmlspecialchars(
                                $printKeluar
                            ) ?>

                        </td>


                        <!-- PARAF KELUAR -->

                        <td class="paraf">

                        </td>


                        <td class="kta">

                            <?= htmlspecialchars(
                                $row['kta']
                            ) ?>

                        </td>

                    </tr>


                <?php endforeach; ?>

            </tbody>

        </table>

        </div>



        <!-- ======================================================
             SIGNATURE
             ====================================================== -->

        <table class="signature">

            <tr>

                <td class="signature-label">

                    Dibuat Oleh,

                </td>


                <td class="signature-label">

                    Menyetujui,

                </td>


                <td class="signature-label">

                    Mengetahui,

                </td>

            </tr>


            <tr>

                <td class="signature-person">

                    Mauliya Zahra

                    <br>

                    Admin Ops

                </td>


                <td class="signature-person">

                    Agung Setiandar

                    <br>

                    Koordinator

                </td>


                <td class="signature-person">

                    A. Yudi Sujudi

                    <br>

                    Senior Manager Operasional

                </td>

            </tr>

        </table>



        <!-- ======================================================
             NOTE
             ====================================================== -->

        <div class="note">

            NOTE :
            KEPALA REGU DAN ANGGOTA <?= htmlspecialchars($reportLabel) ?> YANG BEKERJA
            DI AREA BEHANDEL PT. GRAHA SEGARA

            <br>

            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
            WAJIB MEMAKAI APD LENGKAP
            ( ALAT PELINDUNG DIRI ) DAN MASKER

            <br>

            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
            APABILA TIDAK MEMATUHI,
            SECURITY BERHAK MENGUSIR DARI WILAYAH
            BEHANDEL PT. GRAHA SEGARA

        </div>


    </div>


</body>

</html>
