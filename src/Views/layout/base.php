<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title ?? 'URL Shortener') ?> – <?= e(\App\Core\Config::get('app.name', 'URL Shortener')) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
          crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background: #f8f9fa; }
        .sidebar { min-height: 100vh; background: #212529; }
        .sidebar .nav-link { color: #adb5bd; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { color: #fff; background: rgba(255,255,255,.1); border-radius: .375rem; }
        .navbar-brand { font-weight: 700; letter-spacing: -.5px; }
        .truncate { max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; display: inline-block; }
    </style>
</head>
<body>
<?php $isLoggedIn = \App\Core\Session::has('user_id'); ?>
<?php if ($isLoggedIn): ?>
<div class="d-flex">
    <!-- Sidebar -->
    <nav class="sidebar d-none d-md-flex flex-column p-3" style="width:230px; min-width:230px;">
        <a class="navbar-brand text-white mb-4 ps-2" href="/admin">
            <i class="bi bi-link-45deg"></i> URL Shortener
        </a>
        <ul class="nav flex-column gap-1">
            <li class="nav-item">
                <a class="nav-link px-3 py-2" href="/admin"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a>
            </li>
            <li class="nav-item">
                <a class="nav-link px-3 py-2" href="/admin/links"><i class="bi bi-link me-2"></i>Links</a>
            </li>
            <li class="nav-item">
                <a class="nav-link px-3 py-2" href="/admin/links/create"><i class="bi bi-plus-circle me-2"></i>New Link</a>
            </li>
            <?php if (\App\Core\App::isAdmin()): ?>
            <li class="nav-item">
                <a class="nav-link px-3 py-2" href="/admin/settings"><i class="bi bi-gear me-2"></i>Settings</a>
            </li>
            <li class="nav-item">
                <a class="nav-link px-3 py-2" href="/admin/backup"><i class="bi bi-download me-2"></i>Backup</a>
            </li>
            <li class="nav-item">
                <a class="nav-link px-3 py-2" href="/admin/diagnostics"><i class="bi bi-clipboard-pulse me-2"></i>Diagnostics</a>
            </li>
            <?php endif; ?>
        </ul>
        <div class="mt-auto">
            <div class="text-muted small px-3 mb-2"><?= e(\App\Core\Session::get('user_name', '')) ?></div>
            <form method="POST" action="/logout">
                <?= \App\Core\Csrf::field() ?>
                <button type="submit" class="btn btn-outline-secondary btn-sm w-100">
                    <i class="bi bi-box-arrow-right me-1"></i>Logout
                </button>
            </form>
        </div>
    </nav>
    <!-- Main -->
    <div class="flex-grow-1 p-4" style="min-width:0;">
        <!-- Mobile nav -->
        <nav class="navbar navbar-light bg-white border-bottom mb-4 d-md-none rounded">
            <div class="container-fluid">
                <a class="navbar-brand" href="/admin"><i class="bi bi-link-45deg"></i> URL Shortener</a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mobileNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="mobileNav">
                    <ul class="navbar-nav">
                        <li class="nav-item"><a class="nav-link" href="/admin">Dashboard</a></li>
                        <li class="nav-item"><a class="nav-link" href="/admin/links">Links</a></li>
                        <li class="nav-item"><a class="nav-link" href="/admin/links/create">New Link</a></li>
                        <?php if (\App\Core\App::isAdmin()): ?>
                        <li class="nav-item"><a class="nav-link" href="/admin/settings">Settings</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </nav>

        <?php $flash_success = \App\Core\Session::flash('success'); ?>
        <?php $flash_error = \App\Core\Session::flash('error'); ?>
        <?php $flash_errors = \App\Core\Session::flash('errors'); ?>
        <?php if ($flash_success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= e($flash_success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        <?php if ($flash_error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= e($flash_error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        <?php if ($flash_errors && is_array($flash_errors)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <ul class="mb-0">
                <?php foreach ($flash_errors as $err): ?>
                <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?= $content ?? '' ?>
    </div>
</div>
<?php else: ?>
<div class="container py-5">
    <?php $flash_success = \App\Core\Session::flash('success'); ?>
    <?php $flash_error = \App\Core\Session::flash('error'); ?>
    <?php $flash_errors = \App\Core\Session::flash('errors'); ?>
    <?php if ($flash_success): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <?= e($flash_success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    <?php if ($flash_error): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <?= e($flash_error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    <?php if ($flash_errors && is_array($flash_errors)): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <ul class="mb-0">
            <?php foreach ($flash_errors as $err): ?>
            <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    <?= $content ?? '' ?>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc4s9bIOgUxi8T/jzmfLFLbBCd5uUpN5m1L9b2rVJvj7"
        crossorigin="anonymous"></script>
</body>
</html>
