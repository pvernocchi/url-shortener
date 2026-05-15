<h4 class="fw-bold mb-4">Security Settings</h4>

<div class="row">
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h6 class="fw-semibold mb-3">TOTP Authenticator</h6>

                <?php if (!($totpAllowed ?? false)): ?>
                    <div class="alert alert-warning mb-0">
                        TOTP is disabled by administrator policy.
                    </div>
                <?php elseif ($totpEnabled ?? false): ?>
                    <p class="text-muted">Your account currently requires an authenticator code during login.</p>
                    <form method="POST" action="/admin/security">
                        <?= \App\Core\Csrf::field() ?>
                        <button type="submit" name="action" value="disable_totp" class="btn btn-outline-danger">
                            Disable TOTP
                        </button>
                    </form>
                <?php else: ?>
                    <p class="text-muted">Enable TOTP with Google Authenticator or Microsoft Authenticator.</p>
                    <div class="alert alert-secondary">
                        <div class="fw-semibold mb-1">TOTP Secret</div>
                        <code class="fs-6"><?= e($totpSecret ?? '') ?></code>
                        <div class="small text-muted mt-2">Provisioning URI:</div>
                        <code class="small"><?= e($totpProvisioningUri ?? '') ?></code>
                    </div>
                    <form method="POST" action="/admin/security">
                        <?= \App\Core\Csrf::field() ?>
                        <div class="mb-3">
                            <label class="form-label">Authentication code</label>
                            <input type="text" name="mfa_code" class="form-control" maxlength="6" inputmode="numeric" required>
                        </div>
                        <button type="submit" name="action" value="enable_totp" class="btn btn-primary">
                            Enable TOTP
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
