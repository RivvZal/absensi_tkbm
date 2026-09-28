<?php

require_once 'auth.php';
requireLogin();

?>
<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>
        Sistem Report Absensi
    </title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {

            margin: 0;

            min-height: 100vh;

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            background:
                linear-gradient(135deg,
                    #0f172a 0%,
                    #1e3a8a 50%,
                    #2563eb 100%);

            display: flex;

            align-items: center;

            justify-content: center;

            padding: 20px;

        }


        .container {

            width: 100%;

            max-width: 900px;

        }

        .logout-button {

            position: fixed;

            top: 20px;

            right: 20px;

            padding:
                9px
                16px;

            background:
                rgba(255, 255, 255, .15);

            color: white;

            border:
                1px solid
                rgba(255, 255, 255, .3);

            border-radius: 7px;

            text-decoration: none;

            cursor: pointer;

            font: inherit;

            font-size: 13px;

            font-weight: bold;
        }


        .logout-button:hover {

            background:
                rgba(255, 255, 255, .25);

        }


        .header {

            text-align: center;

            color: white;

            margin-bottom: 35px;

        }


        .header .icon {

            font-size: 55px;

            margin-bottom: 10px;

        }


        .header h1 {

            margin: 0;

            font-size: 30px;

        }


        .header p {

            margin-top: 10px;

            color: #cbd5e1;

            font-size: 15px;

        }


        .menu {

            display: grid;

            grid-template-columns:
                repeat(2, 1fr);

            gap: 25px;

        }


        .menu-button {

            text-decoration: none;

            color: white;

            background: white;

            border-radius: 18px;

            padding: 35px 25px;

            text-align: center;

            transition:
                transform .2s,
                box-shadow .2s;

            box-shadow:
                0 15px 35px rgba(0, 0, 0, .20);

        }


        .menu-button:hover {

            transform:
                translateY(-7px);

            box-shadow:
                0 22px 45px rgba(0, 0, 0, .30);

        }


        .menu-icon {

            width: 80px;

            height: 80px;

            margin: 0 auto 20px;

            border-radius: 20px;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 40px;

        }


        .hrd .menu-icon {

            background: #dbeafe;

        }


        .tkbm .menu-icon {

            background: #dcfce7;

        }


        .menu-title {

            font-size: 22px;

            font-weight: bold;

            margin-bottom: 10px;

        }


        .hrd .menu-title {

            color: #1d4ed8;

        }


        .tkbm .menu-title {

            color: #15803d;

        }


        .menu-description {

            color: #64748b;

            font-size: 14px;

            line-height: 1.6;

        }


        .footer {

            text-align: center;

            color: #cbd5e1;

            font-size: 12px;

            margin-top: 30px;

        }


        @media(max-width: 650px) {

            .menu {

                grid-template-columns: 1fr;

            }


            .header h1 {

                font-size: 24px;

            }

        }
</style>

    <link rel="stylesheet" href="assets/app.css?v=7">
    <script src="assets/app.js?v=1" defer></script>

</head>


<body class="page-home page-transition">


    <div class="container">


        <!-- HEADER -->

        <div class="header">
            <form method="POST" action="logout.php">
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>"
                >
                <button type="submit" class="logout-button">
                    Logout
                </button>
            </form>

            <div class="icon">
                📊
            </div>

            <h1>
                SISTEM REPORT ABSENSI
            </h1>

            <p>
                Silakan pilih report absensi yang ingin dibuka
            </p>

        </div>


        <!-- MENU -->

        <div class="menu">


            <!-- =================================================
             HRD
             ================================================= -->

            <a
                href="hrd_report.php"
                class="menu-button hrd">

                <div class="menu-icon">

                    👨‍💼

                </div>


                <div class="menu-title">

                    ABSEN HRD REPORT

                </div>


                <div class="menu-description">

                    Laporan dan monitoring
                    absensi karyawan HRD

                </div>

            </a>



            <!-- =================================================
             TKBM
             ================================================= -->

            <a
                href="tkbm_report.php"
                class="menu-button tkbm">

                <div class="menu-icon">

                    👷

                </div>


                <div class="menu-title">

                    ABSEN TKBM REPORT

                </div>


                <div class="menu-description">

                    Laporan dan monitoring
                    absensi tenaga kerja TKBM

                </div>

            </a>


            <!-- =================================================
             DAFTAR USER
             ================================================= -->

            <a
                href="list_user.php"
                class="menu-button hrd user-directory">

                <div class="menu-icon" aria-hidden="true">

                    👥

                </div>


                <div class="menu-title">

                    DAFTAR USER

                </div>


                <div class="menu-description">

                    Lihat user aktif HRD dan TKBM

                </div>

            </a>


        </div>


        <div class="footer">

            Sistem Report Absensi

        </div>


    </div>


</body>

</html>
