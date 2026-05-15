<h4 class="fw-bold mb-4">Email Settings (SMTP)</h4>

<div class="row justify-content-center">
    <div class="col-lg-8">

        <!-- SMTP Configuration -->
        <div class="card shadow-sm mb-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-envelope-gear me-2"></i>SMTP Configuration</h6>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="/admin/settings/email">
                    <?= \App\Core\Csrf::field() ?>

                    <div class="row g-3 mb-3">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">SMTP Host</label>
                            <input type="text" name="smtp_host" class="form-control"
                                   value="<?= e($settings['smtp_host'] ?? '') ?>"
                                   placeholder="smtp.example.com">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Port</label>
                            <input type="number" name="smtp_port" class="form-control"
                                   value="<?= e($settings['smtp_port'] ?? '587') ?>"
                                   min="1" max="65535" placeholder="587">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Encryption</label>
                        <select name="smtp_encryption" class="form-select">
                            <?php
                            $enc = $settings['smtp_encryption'] ?? 'tls';
                            foreach (['tls' => 'STARTTLS (port 587)', 'ssl' => 'SSL / TLS (port 465)', 'none' => 'None (plaintext, port 25)'] as $val => $label):
                            ?>
                            <option value="<?= e($val) ?>" <?= $enc === $val ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">STARTTLS is recommended for most providers.</div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Username</label>
                            <input type="text" name="smtp_username" class="form-control"
                                   autocomplete="username"
                                   value="<?= e($settings['smtp_username'] ?? '') ?>"
                                   placeholder="user@example.com">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Password</label>
                            <input type="password" name="smtp_password" class="form-control"
                                   autocomplete="current-password"
                                   placeholder="Leave blank to keep current password">
                            <div class="form-text">Leave blank to keep the saved password unchanged.</div>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">From Address</label>
                            <input type="email" name="smtp_from_address" class="form-control"
                                   value="<?= e($settings['smtp_from_address'] ?? '') ?>"
                                   placeholder="noreply@example.com">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">From Name</label>
                            <input type="text" name="smtp_from_name" class="form-control"
                                   value="<?= e($settings['smtp_from_name'] ?? '') ?>"
                                   placeholder="URL Shortener">
                        </div>
                    </div>

                    <div class="mb-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="smtp_logging"
                                   id="smtp_logging" value="1"
                                   <?= ($settings['smtp_logging'] ?? '0') === '1' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="smtp_logging">
                                Enable SMTP logging <span class="text-muted">(writes conversation to <code>storage/logs/smtp.log</code>)</span>
                            </label>
                        </div>
                        <div class="form-text text-warning">
                            <i class="bi bi-exclamation-triangle me-1"></i>Enable only for troubleshooting — log may contain credentials.
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i>Save Email Settings
                    </button>
                    <a href="/admin/settings" class="btn btn-outline-secondary ms-2">General Settings</a>
                </form>
            </div>
        </div>

        <!-- Send Test Email -->
        <div class="card shadow-sm">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-send me-2"></i>Send Test Email</h6>
            </div>
            <div class="card-body p-4">
                <p class="text-muted mb-3">Send a test message to verify your SMTP settings are working correctly.</p>
                <form method="POST" action="/admin/settings/email/test">
                    <?= \App\Core\Csrf::field() ?>
                    <div class="row g-2 align-items-end">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Recipient Address</label>
                            <input type="email" name="test_email_to" class="form-control"
                                   placeholder="you@example.com" required>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-outline-primary w-100">
                                <i class="bi bi-envelope me-1"></i>Send Test Email
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

    </div>
</div>
