<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="fw-bold mb-0">Create New Link</h4>
            <a href="/admin/links" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Back
            </a>
        </div>

        <div class="card shadow-sm">
            <div class="card-body p-4">
                <form method="POST" action="/admin/links">
                    <?= \App\Core\Csrf::field() ?>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Original URL <span class="text-danger">*</span></label>
                        <input type="url" name="original_url" class="form-control"
                               placeholder="https://example.com/very/long/url" required autofocus>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Custom Alias <span class="text-muted fw-normal">(optional)</span></label>
                        <div class="input-group">
                            <span class="input-group-text text-muted"><?= e(rtrim(\App\Core\Config::get('app.url', ''), '/')) ?>/</span>
                            <input type="text" name="custom_code" class="form-control"
                                   placeholder="my-link" pattern="[a-zA-Z0-9_-]{3,64}">
                        </div>
                        <div class="form-text">3-64 characters: letters, numbers, hyphens, underscores.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Title <span class="text-muted fw-normal">(optional)</span></label>
                        <input type="text" name="title" class="form-control" placeholder="My link title">
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Redirect Type</label>
                            <select name="redirect_type" class="form-select">
                                <option value="302">302 – Temporary</option>
                                <option value="301">301 – Permanent</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Expires At</label>
                            <input type="datetime-local" name="expires_at" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Max Clicks</label>
                            <input type="number" name="max_clicks" class="form-control"
                                   placeholder="Unlimited" min="1">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-plus-circle me-1"></i>Create Link
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
