<h4 class="fw-bold mb-4">Dashboard</h4>

<div class="row g-4 mb-4">
    <div class="col-sm-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-4">
                <div class="fs-1 fw-bold text-primary"><?= (int)($totalLinks ?? 0) ?></div>
                <div class="text-muted">Total Links</div>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-4">
                <div class="fs-1 fw-bold text-success"><?= (int)($totalClicks ?? 0) ?></div>
                <div class="text-muted">Total Clicks</div>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-4">
                <div class="fs-1 fw-bold text-info"><?= (int)($activeLinks ?? 0) ?></div>
                <div class="text-muted">Active Links</div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0">Recent Links</h6>
        <a href="/admin/links/create" class="btn btn-sm btn-primary">
            <i class="bi bi-plus-circle me-1"></i>New Link
        </a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Short Code</th>
                    <th>Original URL</th>
                    <th class="text-end">Clicks</th>
                    <th>Created</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($recentLinks)): ?>
                    <?php foreach ($recentLinks as $link): ?>
                    <tr>
                        <td>
                            <a href="/<?= e($link['short_code']) ?>" target="_blank" class="fw-semibold">
                                <?= e($link['short_code']) ?>
                            </a>
                        </td>
                        <td>
                            <span class="truncate d-inline-block text-muted" title="<?= e($link['original_url']) ?>">
                                <?= e($link['original_url']) ?>
                            </span>
                        </td>
                        <td class="text-end"><?= (int)$link['click_count'] ?></td>
                        <td class="text-muted small"><?= e(date('M j, Y', strtotime($link['created_at']))) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="4" class="text-center text-muted py-4">No links yet. <a href="/admin/links/create">Create one!</a></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if (!empty($recentLinks)): ?>
    <div class="card-footer text-end">
        <a href="/admin/links" class="text-decoration-none small">View all links →</a>
    </div>
    <?php endif; ?>
</div>
