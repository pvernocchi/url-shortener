<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="fw-bold mb-0">Create API Token</h4>
            <a href="/admin/tokens" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Back
            </a>
        </div>

        <div class="card shadow-sm">
            <div class="card-body p-4">
                <form method="POST" action="/admin/tokens">
                    <?= \App\Core\Csrf::field() ?>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Token Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required maxlength="100" placeholder="e.g. Copilot Extension">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Scopes</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="scopes[]" value="read" id="scopeRead" checked>
                            <label class="form-check-label" for="scopeRead">read</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="scopes[]" value="write" id="scopeWrite" checked>
                            <label class="form-check-label" for="scopeWrite">write</label>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Owner User</label>
                        <select name="user_id" class="form-select">
                            <option value="">Current admin</option>
                            <?php foreach (($users ?? []) as $user): ?>
                            <option value="<?= (int)$user['id'] ?>" <?= (int)$user['id'] === (int)($currentUserId ?? 0) ? 'selected' : '' ?>>
                                <?= e($user['email']) ?><?= !empty($user['name']) ? ' (' . e($user['name']) . ')' : '' ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-key me-1"></i>Create Token
                    </button>
                    <a href="/admin/tokens" class="btn btn-outline-secondary ms-2">Cancel</a>
                </form>
            </div>
        </div>
    </div>
</div>

