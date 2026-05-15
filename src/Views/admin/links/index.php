<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0">Links</h4>
    <a href="/admin/links/create" class="btn btn-primary">
        <i class="bi bi-plus-circle me-1"></i>New Link
    </a>
</div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Short URL</th>
                    <th>Original URL</th>
                    <th class="text-end">Clicks</th>
                    <th class="text-center">Active</th>
                    <th>Created</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($links)): ?>
                    <?php foreach ($links as $link): ?>
                    <tr>
                        <td>
                            <a href="/<?= e($link['short_code']) ?>" target="_blank" class="fw-semibold">
                                <?= e($link['short_code']) ?>
                            </a>
                            <?php if ($link['redirect_type'] == 301): ?>
                            <span class="badge bg-secondary ms-1">301</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="truncate d-inline-block text-muted" title="<?= e($link['original_url']) ?>">
                                <?= e(mb_strimwidth($link['original_url'], 0, 60, '…')) ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <a href="/admin/links/<?= (int)$link['id'] ?>/analytics">
                                <?= (int)$link['click_count'] ?>
                            </a>
                        </td>
                        <td class="text-center">
                            <form method="POST" action="/admin/links/<?= (int)$link['id'] ?>/toggle" class="d-inline">
                                <?= \App\Core\Csrf::field() ?>
                                <button type="submit" class="btn btn-sm <?= $link['is_active'] ? 'btn-success' : 'btn-outline-secondary' ?> px-2 py-0">
                                    <?= $link['is_active'] ? 'ON' : 'OFF' ?>
                                </button>
                            </form>
                        </td>
                        <td class="text-muted small"><?= e(date('M j, Y', strtotime($link['created_at']))) ?></td>
                        <td class="text-end">
                            <a href="/admin/links/<?= (int)$link['id'] ?>/edit" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form method="POST" action="/admin/links/<?= (int)$link['id'] ?>/delete"
                                  class="d-inline"
                                  onsubmit="return confirm('Delete this link?')">
                                <?= \App\Core\Csrf::field() ?>
                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">
                        No links yet. <a href="/admin/links/create">Create your first link!</a>
                    </td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if (($totalPages ?? 1) > 1): ?>
    <div class="card-footer">
        <nav>
            <ul class="pagination pagination-sm mb-0 justify-content-center">
                <?php for ($p = 1; $p <= ($totalPages ?? 1); $p++): ?>
                <li class="page-item <?= $p === ($page ?? 1) ? 'active' : '' ?>">
                    <a class="page-link" href="/admin/links?page=<?= $p ?>"><?= $p ?></a>
                </li>
                <?php endfor; ?>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>
