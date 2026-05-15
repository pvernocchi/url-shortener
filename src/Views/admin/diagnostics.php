<h4 class="fw-bold mb-4">Diagnostics</h4>

<div class="row g-4">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Application</h6>
                <?php if (($versionsMatch ?? false) === true): ?>
                    <span class="badge bg-success">In Sync</span>
                <?php else: ?>
                    <span class="badge bg-warning text-dark">Upgrade Available</span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <table class="table table-sm mb-3">
                    <tr><th>Code Version</th><td><?= e($codeVersion ?? '') ?></td></tr>
                    <tr><th>DB Version</th><td><?= e(($dbVersion ?? '') !== '' ? $dbVersion : 'Not recorded') ?></td></tr>
                    <tr><th>Last Migration</th><td><?= e(($lastMigration ?? '') !== '' ? $lastMigration : 'None') ?></td></tr>
                </table>
                <form method="POST" action="/admin/upgrade" class="d-inline-block">
                    <?= \App\Core\Csrf::field() ?>
                    <button type="submit" class="btn <?= ($versionsMatch ?? false) ? 'btn-outline-secondary' : 'btn-primary' ?>">
                        <i class="bi bi-arrow-repeat me-1"></i>Run pending migrations
                    </button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-header"><h6 class="mb-0">PHP Environment</h6></div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tr><th>PHP Version</th><td><?= e($phpVersion ?? PHP_VERSION) ?></td></tr>
                    <tr><th>Memory Limit</th><td><?= e(ini_get('memory_limit')) ?></td></tr>
                    <tr><th>Max Upload</th><td><?= e(ini_get('upload_max_filesize')) ?></td></tr>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-header"><h6 class="mb-0">Directory Permissions</h6></div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <?php foreach ($writableDirs ?? [] as $dir => $writable): ?>
                    <tr>
                        <td><code><?= e($dir) ?></code></td>
                        <td>
                            <?php if ($writable): ?>
                                <span class="badge bg-success">Writable</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Not Writable</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>
    </div>
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header"><h6 class="mb-0">Loaded Extensions</h6></div>
            <div class="card-body">
                <div class="d-flex flex-wrap gap-1">
                    <?php foreach (array_slice($extensions ?? [], 0, 50) as $ext): ?>
                    <span class="badge bg-light text-dark border"><?= e($ext) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>
