<?php

require_once 'auth.php';

if (
    $_SERVER['REQUEST_METHOD'] !== 'POST' ||
    !csrfIsValid($_POST['csrf_token'] ?? null)
) {
    header('Location: index.php');
    exit;
}

logoutAdmin();

header('Location: login.php');
exit;
