<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">Analytics</h4>
        <p class="text-muted mb-0">
            Short link: <a href="/<?= e($link['short_code'] ?? '') ?>" target="_blank"><?= e($link['short_code'] ?? '') ?></a>
        </p>
    </div>
    <a href="/admin/links" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
</div>

<div class="row g-4 mb-4">
    <div class="col-sm-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-4">
                <div class="fs-1 fw-bold text-primary"><?= (int)($totalClicks ?? 0) ?></div>
                <div class="text-muted">Total Clicks</div>
            </div>
        </div>
    </div>
    <div class="col-sm-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <p class="mb-1"><strong>Original URL:</strong></p>
                <p class="text-break text-muted small"><?= e($link['original_url'] ?? '') ?></p>
                <p class="mb-1"><strong>Created:</strong>
                    <?= e(date('M j, Y H:i', strtotime($link['created_at'] ?? 'now'))) ?>
                </p>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($daily)): ?>
<div class="card shadow-sm mb-4">
    <div class="card-header"><h6 class="mb-0">Clicks Over Time (30 days)</h6></div>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead class="table-light">
                <tr><th>Date</th><th class="text-end">Clicks</th></tr>
            </thead>
            <tbody>
                <?php foreach ($daily as $row): ?>
                <tr>
                    <td><?= e($row['day']) ?></td>
                    <td class="text-end"><?= (int)$row['cnt'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($referrers)): ?>
<div class="card shadow-sm mb-4">
    <div class="card-header"><h6 class="mb-0">Top Referrers</h6></div>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead class="table-light">
                <tr><th>Domain</th><th class="text-end">Clicks</th></tr>
            </thead>
            <tbody>
                <?php foreach ($referrers as $ref): ?>
                <tr>
                    <td><?= e($ref['referer_domain'] ?? '(direct)') ?></td>
                    <td class="text-end"><?= (int)$ref['cnt'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
