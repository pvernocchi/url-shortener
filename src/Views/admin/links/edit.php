<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="fw-bold mb-0">Edit Link</h4>
            <a href="/admin/links" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Back
            </a>
        </div>

        <div class="card shadow-sm">
            <div class="card-body p-4">
                <form method="POST" action="/admin/links/<?= (int)($link['id'] ?? 0) ?>">
                    <?= \App\Core\Csrf::field() ?>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Short Code</label>
                        <div class="input-group">
                            <span class="input-group-text text-muted"><?= e(rtrim(\App\Core\Config::get('app.url', ''), '/')) ?>/</span>
                            <input type="text" class="form-control bg-light" value="<?= e($link['short_code'] ?? '') ?>" readonly>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Original URL <span class="text-danger">*</span></label>
                        <input type="url" name="original_url" class="form-control"
                               value="<?= e($link['original_url'] ?? '') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Title</label>
                        <input type="text" name="title" class="form-control"
                               value="<?= e($link['title'] ?? '') ?>">
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Redirect Type</label>
                            <select name="redirect_type" class="form-select">
                                <option value="302" <?= ($link['redirect_type'] ?? 302) == 302 ? 'selected' : '' ?>>302 – Temporary</option>
                                <option value="301" <?= ($link['redirect_type'] ?? 302) == 301 ? 'selected' : '' ?>>301 – Permanent</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Expires At</label>
                            <input type="datetime-local" name="expires_at" class="form-control"
                                   value="<?= !empty($link['expires_at']) ? e(date('Y-m-d\TH:i', strtotime($link['expires_at']))) : '' ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Max Clicks</label>
                            <input type="number" name="max_clicks" class="form-control"
                                   value="<?= e($link['max_clicks'] ?? '') ?>" placeholder="Unlimited" min="1">
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_active" id="isActive"
                                   <?= ($link['is_active'] ?? 1) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="isActive">Link is active</label>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i>Save Changes
                    </button>
                    <a href="/admin/links" class="btn btn-outline-secondary ms-2">Cancel</a>
                </form>
            </div>
        </div>
    </div>
</div>
