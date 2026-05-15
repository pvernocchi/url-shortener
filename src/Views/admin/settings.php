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

                    <hr class="my-4">
                    <h6 class="fw-semibold mb-3">Multi-factor Authentication (MFA)</h6>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Global MFA Policy</label>
                        <select name="mfa_policy" class="form-select">
                            <option value="optional" <?= ($settings['mfa_policy'] ?? 'optional') === 'optional' ? 'selected' : '' ?>>Optional</option>
                            <option value="required" <?= ($settings['mfa_policy'] ?? 'optional') === 'required' ? 'selected' : '' ?>>Required</option>
                        </select>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="mfa_allow_totp" id="mfa_allow_totp" value="1"
                               <?= ($settings['mfa_allow_totp'] ?? '1') === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="mfa_allow_totp">Allow TOTP (Google/Microsoft Authenticator)</label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="mfa_allow_webauthn_platform" id="mfa_allow_webauthn_platform" value="1"
                               <?= ($settings['mfa_allow_webauthn_platform'] ?? '1') === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="mfa_allow_webauthn_platform">Allow Windows TPM / Windows Hello (WebAuthn platform)</label>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="mfa_allow_webauthn_security_key" id="mfa_allow_webauthn_security_key" value="1"
                               <?= ($settings['mfa_allow_webauthn_security_key'] ?? '1') === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="mfa_allow_webauthn_security_key">Allow YubiKey (WebAuthn security key)</label>
                    </div>

                    <hr class="my-4">
                    <h6 class="fw-semibold mb-3">Login CAPTCHA</h6>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="captcha_enabled" id="captcha_enabled" value="1"
                               <?= ($settings['captcha_enabled'] ?? '0') === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="captcha_enabled">Enable CAPTCHA on login</label>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Provider</label>
                        <select name="captcha_provider" class="form-select">
                            <option value="recaptcha" <?= ($settings['captcha_provider'] ?? 'recaptcha') === 'recaptcha' ? 'selected' : '' ?>>Google reCAPTCHA</option>
                            <option value="turnstile" <?= ($settings['captcha_provider'] ?? 'recaptcha') === 'turnstile' ? 'selected' : '' ?>>Cloudflare Turnstile</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Site Key</label>
                        <input type="text" name="captcha_site_key" class="form-control"
                               value="<?= e($settings['captcha_site_key'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Secret Key</label>
                        <input type="password" name="captcha_secret_key" class="form-control"
                               value="<?= e($settings['captcha_secret_key'] ?? '') ?>">
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i>Save Settings
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
