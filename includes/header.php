<?php
/**
 * Shared HTML <head> tag — opened on every internal view page.
 * Closed by footer.php (which closes </main> </div.main-area> </div.app-wrapper> </body> </html>)
 */
$pageTitle    = $pageTitle    ?? 'Dashboard';
$extraCss     = $extraCss     ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= htmlspecialchars($pageTitle) ?> — <?= APP_NAME ?></title>

    <!-- Bootstrap 5 (layout grid only — we override all visuals) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">

    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Apple-style Design System -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css?v=<?= time() ?>">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/pagination.css?v=<?= time() ?>">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/print.css?v=<?= time() ?>" media="print">

    <script>
        /**
         * Global Portability Constant
         * Normalized with a trailing slash for reliable path concatenation
         */
        const BASE = '<?= rtrim(BASE_URL, "/") ?>/';
    </script>

    <!-- CSRF Token (read by Api wrapper in main.js) -->
    <meta name="csrf-token" content="<?= getCsrfToken() ?>">

    <?php foreach ($extraCss as $css): ?>
        <link rel="stylesheet" href="<?= htmlspecialchars($css) ?>">
    <?php endforeach; ?>

    <!-- Bootstrap 5 JS (modals, dropdowns) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Chart.js 4 -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>

    <!-- SweetAlert2 (Rich notification feedback) -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Yazzies Shared Utilities (Api, Format, Toast, Modal, Form, etc.) -->
    <!-- MUST load here so inline view scripts can use Api.get(), Format.peso(), etc. -->
    <script src="<?= BASE_URL ?>/assets/js/main.js?v=<?= time() ?>"></script>

    <style>
        /* Force Apple font stack even when Bootstrap sets its own */
        body, html {
            font-family: -apple-system, 'SF Pro Text', 'SF Pro Display', 'Helvetica Neue', 'Inter', Arial, sans-serif !important;
        }

        /* Reset Bootstrap form-control so our styles win */
        .form-control:focus { box-shadow: none; }
    </style>
</head>
<body>
<div class="app-wrapper">
