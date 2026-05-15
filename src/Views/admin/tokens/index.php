<?php $newTokenPlaintext = \App\Core\Session::flash('new_token_plaintext'); ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0">API Tokens</h4>
    <a href="/admin/tokens/create" class="btn btn-primary">
        <i class="bi bi-plus-circle me-1"></i>New Token
    </a>
</div>

<?php if ($newTokenPlaintext): ?>
<div class="alert alert-success alert-dismissible fade show">
    <div class="fw-semibold mb-1">Copy this token now. For security, it will not be shown again.</div>
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <code id="newTokenValue"><?= e($newTokenPlaintext) ?></code>
        <button type="button" class="btn btn-sm btn-outline-success" onclick="copyNewToken()">Copy</button>
    </div>
    <small id="copyTokenStatus" class="d-block mt-2 text-muted"></small>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<script>
function copyNewToken() {
    const token = document.getElementById('newTokenValue')?.textContent || '';
    const status = document.getElementById('copyTokenStatus');
    if (!token) return;
    navigator.clipboard.writeText(token).then(() => {
        if (status) status.textContent = 'Token copied to clipboard.';
    }).catch(() => {
        if (status) status.textContent = 'Unable to copy automatically. Please copy the token manually.';
    });
}
</script>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Name</th>
                    <th>Owner</th>
                    <th>Scopes</th>
                    <th>Created</th>
                    <th>Last Used</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($tokens)): ?>
                    <?php foreach ($tokens as $token): ?>
                    <tr>
                        <td class="fw-semibold"><?= e($token['name']) ?></td>
                        <td>
                            <div><?= e($token['user_email'] ?? 'Unknown user') ?></div>
                            <?php if (!empty($token['user_name'])): ?>
                            <small class="text-muted"><?= e($token['user_name']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php foreach (explode(',', (string)$token['scopes']) as $scope): ?>
                                <?php $cleanScope = trim($scope); ?>
                                <?php if ($cleanScope !== ''): ?>
                                <span class="badge bg-secondary"><?= e($cleanScope) ?></span>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </td>
                        <td class="text-muted small"><?= !empty($token['created_at']) ? e(date('M j, Y H:i', strtotime((string)$token['created_at']))) : '—' ?></td>
                        <td class="text-muted small"><?= !empty($token['last_used_at']) ? e(date('M j, Y H:i', strtotime((string)$token['last_used_at']))) : 'Never' ?></td>
                        <td>
                            <?php if (empty($token['revoked_at'])): ?>
                            <span class="badge bg-success">Active</span>
                            <?php else: ?>
                            <span class="badge bg-secondary">Revoked</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <?php if (empty($token['revoked_at'])): ?>
                            <form method="POST" action="/admin/tokens/<?= (int)$token['id'] ?>/revoke" class="d-inline">
                                <?= \App\Core\Csrf::field() ?>
                                <button type="submit" class="btn btn-sm btn-outline-warning">Revoke</button>
                            </form>
                            <?php endif; ?>
                            <form method="POST" action="/admin/tokens/<?= (int)$token['id'] ?>/delete"
                                  class="d-inline"
                                  onsubmit="return confirm('Delete this token permanently?')">
                                <?= \App\Core\Csrf::field() ?>
                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">
                            No API tokens yet. <a href="/admin/tokens/create">Create your first token.</a>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
