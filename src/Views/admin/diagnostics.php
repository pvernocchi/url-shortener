<h4 class="fw-bold mb-4">Diagnostics</h4>

<div class="row g-4">
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
