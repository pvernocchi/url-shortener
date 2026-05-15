<div class="row justify-content-center align-items-center" style="min-height:80vh;">
    <div class="col-sm-10 col-md-8 col-lg-6">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h5 class="card-title mb-3 fw-semibold">Set up multi-factor authentication</h5>
                <p class="text-muted">
                    Add this TOTP secret in Google Authenticator or Microsoft Authenticator and enter the generated 6-digit code.
                </p>

                <div class="alert alert-secondary">
                    <div class="fw-semibold mb-1">TOTP Secret</div>
                    <code class="fs-6"><?= e($totpSecret ?? '') ?></code>
                    <div class="small text-muted mt-2">Provisioning URI:</div>
                    <code class="small"><?= e($totpProvisioningUri ?? '') ?></code>
                </div>

                <?php if (($allowWebauthnPlatform ?? false) || ($allowWebauthnYubikey ?? false)): ?>
                <div class="alert alert-info small">
                    WebAuthn options enabled by policy:
                    <?php if (($allowWebauthnPlatform ?? false)): ?> Windows Hello (TPM)<?php endif; ?>
                    <?php if (($allowWebauthnPlatform ?? false) && ($allowWebauthnYubikey ?? false)): ?>, <?php endif; ?>
                    <?php if (($allowWebauthnYubikey ?? false)): ?>YubiKey (security key)<?php endif; ?>.
                    TOTP enrollment is available in this release.
                </div>
                <?php endif; ?>

                <form method="POST" action="/login/mfa/setup">
                    <?= \App\Core\Csrf::field() ?>
                    <div class="mb-3">
                        <label class="form-label">Authentication code</label>
                        <input type="text" name="mfa_code" class="form-control" maxlength="6" inputmode="numeric" required autofocus>
                    </div>
                    <button type="submit" name="action" value="enable_totp" class="btn btn-primary">
                        <i class="bi bi-check2-circle me-1"></i>Enable TOTP
                    </button>
                    <?php if (!(bool)($mfaRequired ?? false)): ?>
                    <button type="submit" name="action" value="skip" class="btn btn-outline-secondary ms-2">
                        Skip for now
                    </button>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
</div>
