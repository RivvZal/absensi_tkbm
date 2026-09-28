<?php

require_once 'auth.php';


/*
|--------------------------------------------------------------------------
| JIKA SUDAH LOGIN
|--------------------------------------------------------------------------
*/

if (isLoggedIn()) {

    header('Location: index.php');

    exit;
}


/*
|--------------------------------------------------------------------------
| LOGIN
|--------------------------------------------------------------------------
*/

$error = '';
$maxLoginAttempts = 5;
$lockDuration = 60;


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!csrfIsValid($_POST['csrf_token'] ?? null)) {
        $error = 'Permintaan login tidak valid. Silakan muat ulang halaman.';
    } elseif ((int) ($_SESSION['login_locked_until'] ?? 0) > time()) {
        $remainingSeconds =
            (int) $_SESSION['login_locked_until'] - time();

        $error = 'Terlalu banyak percobaan login. Coba lagi dalam ' .
            $remainingSeconds . ' detik.';
    } else {

        $username = trim($_POST['username'] ?? '');

        $password = $_POST['password'] ?? '';


        if (verifyAdminCredentials($username, $password)) {

            loginAdmin($username);


            header('Location: index.php');

            exit;
        }


        $attempts = (int) ($_SESSION['login_attempts'] ?? 0) + 1;

        if ($attempts >= $maxLoginAttempts) {
            $_SESSION['login_attempts'] = 0;
            $_SESSION['login_locked_until'] = time() + $lockDuration;

            $error = 'Terlalu banyak percobaan login. Coba lagi dalam ' .
                $lockDuration . ' detik.';
        } else {
            $_SESSION['login_attempts'] = $attempts;
            $remainingAttempts = $maxLoginAttempts - $attempts;

            $error = 'Username atau password salah. Sisa percobaan: ' .
                $remainingAttempts . '.';
        }

        usleep(random_int(250000, 500000));
    }
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
        Login - Sistem Report Absensi
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


        html,
        body {

            margin: 0;

            padding: 0;

            min-height: 100%;

        }


        /*
        |--------------------------------------------------------------------------
        | BODY
        |--------------------------------------------------------------------------
        |
        | 
        |
        */

        body {

            min-height: 100vh;

            font-family:
                Arial,
                Helvetica,
                sans-serif;
            
            background_image url:("pt_graha_segara_cover.jpe");  
            background:
                linear-gradient(
                    135deg,
                    #0f172a 0%,
                    #1e3a8a 50%,
                    #2563eb 100%
                );

            display: flex;

            align-items: center;

            justify-content: center;

            padding: 20px;

        }


        /*
        |--------------------------------------------------------------------------
        | CONTAINER
        |--------------------------------------------------------------------------
        */

        .container {

            width: 100%;

            max-width: 430px;

        }


        /*
        |--------------------------------------------------------------------------
        | HEADER
        |--------------------------------------------------------------------------
        */

        .header {

            text-align: center;

            color: white;

            margin-bottom: 30px;

        }


        .header .icon {

            width: 80px;

            height: 80px;

            margin:
                0
                auto
                18px;

            border-radius: 20px;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 40px;

            background:
                rgba(
                    255,
                    255,
                    255,
                    .12
                );

            border:
                1px
                solid
                rgba(
                    255,
                    255,
                    255,
                    .15
                );

        }


        .header h1 {

            margin: 0;

            font-size: 30px;

            font-weight: bold;

        }


        .header p {

            margin:
                10px
                0
                0;

            color: #cbd5e1;

            font-size: 15px;

            line-height: 1.5;

        }


        /*
        |--------------------------------------------------------------------------
        | LOGIN CARD
        |--------------------------------------------------------------------------
        */

        .login-card {

            width: 100%;

            background: white;

            border-radius: 18px;

            padding:
                32px
                30px;

            box-shadow:
                0
                15px
                35px
                rgba(
                    0,
                    0,
                    0,
                    .20
                );

        }


        /*
        |--------------------------------------------------------------------------
        | CARD TITLE
        |--------------------------------------------------------------------------
        */

        .card-title {

            margin-bottom: 25px;

        }


        .card-title h2 {

            margin:
                0
                0
                7px;

            color: #1e293b;

            font-size: 22px;

        }


        .card-title p {

            margin: 0;

            color: #64748b;

            font-size: 13px;

            line-height: 1.5;

        }


        /*
        |--------------------------------------------------------------------------
        | FORM
        |--------------------------------------------------------------------------
        */

        .form-group {

            margin-bottom: 18px;

        }


        .form-group label {

            display: block;

            margin-bottom: 7px;

            color: #475569;

            font-size: 13px;

            font-weight: bold;

        }


        /*
        |--------------------------------------------------------------------------
        | INPUT WRAPPER
        |--------------------------------------------------------------------------
        */

        .input-wrapper {

            position: relative;

        }


        .input-icon {

            position: absolute;

            left: 13px;

            top: 50%;

            transform:
                translateY(-50%);

            font-size: 17px;

            pointer-events: none;

        }


        .form-group input {

            width: 100%;

            height: 45px;

            padding:
                10px
                13px
                10px
                43px;

            border:
                1px
                solid
                #cbd5e1;

            border-radius: 9px;

            outline: none;

            color: #1e293b;

            background: #fff;

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            font-size: 14px;

            transition:
                border-color .2s,
                box-shadow .2s;

        }


        .form-group input::placeholder {

            color: #94a3b8;

        }


        .form-group input:focus {

            border-color: #2563eb;

            box-shadow:
                0
                0
                0
                3px
                rgba(
                    37,
                    99,
                    235,
                    .12
                );

        }


        /*
        |--------------------------------------------------------------------------
        | PASSWORD BUTTON
        |--------------------------------------------------------------------------
        */

        .password-wrapper input {

            padding-right: 45px;

        }


        .toggle-password {

            position: absolute;

            right: 8px;

            top: 50%;

            transform:
                translateY(-50%);

            width: 34px;

            height: 34px;

            border: none;

            background: transparent;

            border-radius: 6px;

            cursor: pointer;

            font-size: 16px;

        }


        .toggle-password:hover {

            background: #f1f5f9;

        }


        /*
        |--------------------------------------------------------------------------
        | ERROR
        |--------------------------------------------------------------------------
        */

        .error {

            margin-bottom: 20px;

            padding:
                11px
                13px;

            border:
                1px
                solid
                #fecaca;

            border-radius: 8px;

            background: #fee2e2;

            color: #991b1b;

            font-size: 13px;

            line-height: 1.5;

        }


        /*
        |--------------------------------------------------------------------------
        | LOGIN BUTTON
        |--------------------------------------------------------------------------
        */

        .btn-login {

            width: 100%;

            height: 46px;

            margin-top: 4px;

            border: none;

            border-radius: 9px;

            background: #2563eb;

            color: white;

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            font-size: 14px;

            font-weight: bold;

            cursor: pointer;

            transition:
                background .2s,
                transform .15s,
                box-shadow .2s;

        }


        .btn-login:hover {

            background: #1d4ed8;

            box-shadow:
                0
                7px
                18px
                rgba(
                    37,
                    99,
                    235,
                    .25
                );

        }


        .btn-login:active {

            transform: scale(.99);

        }


        /*
        |--------------------------------------------------------------------------
        | FOOTER
        |--------------------------------------------------------------------------
        */

        .footer {

            margin-top: 25px;

            text-align: center;

            color: #cbd5e1;

            font-size: 12px;

        }


        /*
        |--------------------------------------------------------------------------
        | TABLET
        |--------------------------------------------------------------------------
        */

        @media(max-width: 650px) {

            body {

                padding:
                    20px
                    16px;

                align-items: center;

            }


            .container {

                max-width: 420px;

            }


            .header {

                margin-bottom: 24px;

            }


            .header .icon {

                width: 68px;

                height: 68px;

                border-radius: 17px;

                font-size: 34px;

                margin-bottom: 15px;

            }


            .header h1 {

                font-size: 24px;

            }


            .header p {

                font-size: 14px;

            }


            .login-card {

                padding:
                    27px
                    22px;

                border-radius: 16px;

            }


            .card-title h2 {

                font-size: 20px;

            }

        }


        /*
        |--------------------------------------------------------------------------
        | MOBILE KECIL
        |--------------------------------------------------------------------------
        */

        @media(max-width: 400px) {

            body {

                padding:
                    16px
                    12px;

            }


            .header {

                margin-bottom: 20px;

            }


            .header .icon {

                width: 60px;

                height: 60px;

                font-size: 30px;

            }


            .header h1 {

                font-size: 21px;

            }


            .header p {

                font-size: 13px;

            }


            .login-card {

                padding:
                    24px
                    18px;

                border-radius: 14px;

            }


            .card-title {

                margin-bottom: 20px;

            }


            .card-title h2 {

                font-size: 19px;

            }


            .form-group input {

                height: 44px;

                font-size: 16px;

                /*
                |--------------------------------------------------------------------------
                | IMPORTANT MOBILE
                |--------------------------------------------------------------------------
                |
                | 16px mencegah beberapa browser mobile
                | otomatis zoom ketika input difokuskan.
                |
                */

            }


            .btn-login {

                height: 45px;

                font-size: 14px;

            }


            .footer {

                margin-top: 18px;

            }

        }


        /*
        |--------------------------------------------------------------------------
        | MOBILE LANDSCAPE / LAYAR PENDEK
        |--------------------------------------------------------------------------
        */

        @media(max-height: 650px) {

            body {

                align-items: flex-start;

            }


            .container {

                margin:
                    20px
                    auto;

            }


            .header {

                margin-bottom: 18px;

            }


            .header .icon {

                width: 55px;

                height: 55px;

                font-size: 28px;

                margin-bottom: 10px;

            }


            .header h1 {

                font-size: 22px;

            }


            .login-card {

                padding:
                    22px
                    24px;

            }

        }

</style>

    <link rel="stylesheet" href="assets/app.css?v=6">
    <script src="assets/app.js?v=1" defer></script>

</head>


<body class="page-login page-transition">


    <div class="container">


        <!-- ======================================================
             HEADER
             ====================================================== -->

        <div class="header">

            <div class="icon">

                📊

            </div>


            <h1>

                SISTEM REPORT ABSENSI

            </h1>


            <p>

                Silakan login untuk mengakses
                sistem report absensi

            </p>

        </div>



        <!-- ======================================================
             LOGIN CARD
             ====================================================== -->

        <div class="login-card">


            <div class="card-title">

                <h2>

                    Login

                </h2>

            </div>



            <?php if ($error !== ''): ?>

                <div class="error">

                    <?= htmlspecialchars(
                        $error
                    ) ?>

                </div>

            <?php endif; ?>



            <form method="POST">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>"
                >


                <!-- ==================================================
                     USERNAME
                     ================================================== -->

                <div class="form-group">

                    <label for="username">

                        Username

                    </label>


                    <div class="input-wrapper">

                        <span class="input-icon">

                            👤

                        </span>


                        <input
                            type="text"
                            id="username"
                            name="username"
                            placeholder="Masukkan username"
                            value="<?= htmlspecialchars(
                                $_POST['username'] ?? ''
                            ) ?>"
                            autocomplete="username"
                            required
                            autofocus
                        >

                    </div>

                </div>



                <!-- ==================================================
                     PASSWORD
                     ================================================== -->

                <div class="form-group">

                    <label for="password">

                        Password

                    </label>


                    <div
                        class="
                            input-wrapper
                            password-wrapper
                        "
                    >

                        <span class="input-icon">

                            🔒

                        </span>


                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Masukkan password"
                            autocomplete="current-password"
                            required
                        >


                        <button
                            type="button"
                            class="toggle-password"
                            id="togglePassword"
                            aria-label="Tampilkan password"
                        >

                            👁

                        </button>

                    </div>

                </div>



                <!-- ==================================================
                     BUTTON LOGIN
                     ================================================== -->

                <button
                    type="submit"
                    class="btn-login"
                >

                    Login

                </button>


            </form>


        </div>



        <!-- ======================================================
             FOOTER
             ====================================================== -->

        <div class="footer">

            Sistem Report Absensi

        </div>


    </div>



    <!-- ==========================================================
         SHOW / HIDE PASSWORD
         ========================================================== -->

    <script>

        const passwordInput =
            document.getElementById(
                'password'
            );


        const togglePassword =
            document.getElementById(
                'togglePassword'
            );


        togglePassword.addEventListener(
            'click',
            function () {

                const isPassword =
                    passwordInput.type ===
                    'password';


                passwordInput.type =
                    isPassword
                        ? 'text'
                        : 'password';


                togglePassword.textContent =
                    isPassword
                        ? '🙈'
                        : '👁';


                togglePassword.setAttribute(

                    'aria-label',

                    isPassword
                        ? 'Sembunyikan password'
                        : 'Tampilkan password'

                );

            }
        );

    </script>


</body>

</html>
