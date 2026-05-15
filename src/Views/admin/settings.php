<h4 class="fw-bold mb-4">Settings</h4>

<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <form method="POST" action="/admin/settings">
                    <?= \App\Core\Csrf::field() ?>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Site Name</label>
                        <input type="text" name="site_name" class="form-control"
                               value="<?= e($settings['site_name'] ?? \App\Core\Config::get('app.name', 'URL Shortener')) ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Default Redirect Type</label>
                        <select name="default_redirect_type" class="form-select">
                            <option value="302" <?= ($settings['default_redirect_type'] ?? '302') === '302' ? 'selected' : '' ?>>302 – Temporary</option>
                            <option value="301" <?= ($settings['default_redirect_type'] ?? '302') === '301' ? 'selected' : '' ?>>301 – Permanent</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Allow Registration</label>
                        <select name="allow_registration" class="form-select">
                            <option value="0" <?= ($settings['allow_registration'] ?? '0') === '0' ? 'selected' : '' ?>>No</option>
                            <option value="1" <?= ($settings['allow_registration'] ?? '0') === '1' ? 'selected' : '' ?>>Yes</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i>Save Settings
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
