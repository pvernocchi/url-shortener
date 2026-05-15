<div class="row justify-content-center">
    <div class="col-lg-9">
        <div class="text-center mb-4">
            <h1 class="display-6 fw-bold"><i class="bi bi-link-45deg text-primary"></i> URL Shortener</h1>
            <p class="text-muted">Step 2 of 2 – Configuration</p>
        </div>

        <form method="POST" action="/install/step2">
            <?= \App\Core\Csrf::field() ?>

            <!-- App Settings -->
            <div class="card shadow-sm mb-4">
                <div class="card-header"><h6 class="mb-0"><i class="bi bi-app me-2"></i>Application Settings</h6></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">App Name</label>
                            <input type="text" name="app_name" class="form-control" value="URL Shortener" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">App URL <span class="text-danger">*</span></label>
                            <input type="url" name="app_url" class="form-control"
                                   placeholder="https://yourdomain.com" required>
                            <div class="form-text">Full URL with https://, no trailing slash.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">App Secret Key</label>
                            <input type="text" name="app_secret" class="form-control font-monospace"
                                   value="<?= e($appSecret ?? '') ?>" required>
                            <div class="form-text">Used for security. Keep this secret.</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Database Settings -->
            <div class="card shadow-sm mb-4">
                <div class="card-header"><h6 class="mb-0"><i class="bi bi-database me-2"></i>Database Settings</h6></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">DB Host</label>
                            <input type="text" name="db_host" class="form-control" value="localhost">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold">Port</label>
                            <input type="number" name="db_port" class="form-control" value="3306">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Table Prefix</label>
                            <input type="text" name="db_prefix" class="form-control" value="us_">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Database Name <span class="text-danger">*</span></label>
                            <input type="text" name="db_name" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">DB Username</label>
                            <input type="text" name="db_user" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">DB Password</label>
                            <input type="password" name="db_pass" class="form-control">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Admin Account -->
            <div class="card shadow-sm mb-4">
                <div class="card-header"><h6 class="mb-0"><i class="bi bi-person-lock me-2"></i>Admin Account</h6></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Name</label>
                            <input type="text" name="admin_name" class="form-control" value="Admin" required>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
                            <input type="email" name="admin_email" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Password <span class="text-danger">*</span></label>
                            <input type="password" name="admin_pass" class="form-control" minlength="8" required>
                            <div class="form-text">At least 8 characters.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Confirm Password <span class="text-danger">*</span></label>
                            <input type="password" name="admin_pass2" class="form-control" minlength="8" required>
                        </div>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-success btn-lg">
                <i class="bi bi-rocket-takeoff me-1"></i>Install Now
            </button>
            <a href="/install" class="btn btn-outline-secondary btn-lg ms-2">Back</a>
        </form>
    </div>
</div>
