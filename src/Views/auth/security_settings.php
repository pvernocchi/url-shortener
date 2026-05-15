<h4 class="fw-bold mb-4">Security Settings</h4>

<div class="row g-4">
    <div class="col-lg-8">

        <!-- TOTP Authenticator -->
        <div class="card shadow-sm mb-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-phone me-2"></i>TOTP Authenticator</h6>
            </div>
            <div class="card-body p-4">
                <?php if (!($totpAllowed ?? false)): ?>
                    <div class="alert alert-warning mb-0">
                        TOTP is disabled by administrator policy.
                        <?php if (($totpEnabled ?? false) || ($totpConfigured ?? false)): ?>
                            Your current MFA preference is preserved and will become active again when re-enabled globally.
                        <?php endif; ?>
                    </div>
                <?php elseif ($totpEnabled ?? false): ?>
                    <p class="text-muted">Your account currently requires an authenticator code during login.</p>
                    <form method="POST" action="/admin/security">
                        <?= \App\Core\Csrf::field() ?>
                        <button type="submit" name="action" value="disable_totp" class="btn btn-outline-danger">
                            <i class="bi bi-x-circle me-1"></i>Disable TOTP
                        </button>
                    </form>
                <?php else: ?>
                    <p class="text-muted">Enable TOTP with Google Authenticator, Microsoft Authenticator, or any TOTP app.</p>

                    <div class="mb-3 text-center">
                        <div id="totp-qr" class="d-inline-block"></div>
                        <div class="small text-muted mt-1">Scan with your authenticator app</div>
                    </div>

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
                            <i class="bi bi-check2-circle me-1"></i>Enable TOTP
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- Windows Hello / Platform Authenticator -->
        <div class="card shadow-sm mb-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-laptop me-2"></i>Windows Hello / Touch ID</h6>
            </div>
            <div class="card-body p-4">
                <?php if (!($webauthnPlatformAllowed ?? false)): ?>
                    <div class="alert alert-warning mb-0">
                        Platform authenticators (Windows Hello, Touch ID) are disabled by administrator policy.
                    </div>
                <?php else: ?>
                    <p class="text-muted">Use your device's built-in biometric or PIN (Windows Hello, Touch ID, Face ID) as a second factor.</p>

                    <?php
                    $platformCreds = array_filter($webauthnCredentials ?? [], fn($c) => $c['credential_type'] === 'platform');
                    ?>

                    <?php if (!empty($platformCreds)): ?>
                    <div class="list-group mb-3">
                        <?php foreach ($platformCreds as $cred): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <i class="bi bi-laptop me-2 text-muted"></i>
                                <strong><?= e($cred['name'] ?? 'Platform Key') ?></strong>
                                <span class="text-muted small ms-2">Registered <?= e(date('M j, Y', strtotime($cred['created_at']))) ?></span>
                            </div>
                            <form method="POST" action="/admin/security" class="ms-2">
                                <?= \App\Core\Csrf::field() ?>
                                <input type="hidden" name="action" value="delete_webauthn">
                                <input type="hidden" name="credential_id" value="<?= (int)$cred['id'] ?>">
                                <button type="submit" class="btn btn-outline-danger btn-sm"
                                        onclick="return confirm('Remove this device?')">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <button type="button" class="btn btn-primary" id="register-platform-btn">
                        <i class="bi bi-plus-circle me-1"></i>Register Device
                    </button>
                    <div id="platform-status" class="mt-2 d-none"></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- YubiKey / Security Key -->
        <div class="card shadow-sm mb-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-usb-symbol me-2"></i>YubiKey / Security Key</h6>
            </div>
            <div class="card-body p-4">
                <?php if (!($webauthnYubikeyAllowed ?? false)): ?>
                    <div class="alert alert-warning mb-0">
                        Security keys (YubiKey, FIDO2 key) are disabled by administrator policy.
                    </div>
                <?php else: ?>
                    <p class="text-muted">Use a hardware security key (YubiKey, FIDO2 compatible) as a second factor.</p>

                    <?php
                    $keyCreds = array_filter($webauthnCredentials ?? [], fn($c) => $c['credential_type'] === 'security_key');
                    ?>

                    <?php if (!empty($keyCreds)): ?>
                    <div class="list-group mb-3">
                        <?php foreach ($keyCreds as $cred): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <i class="bi bi-key me-2 text-muted"></i>
                                <strong><?= e($cred['name'] ?? 'Security Key') ?></strong>
                                <span class="text-muted small ms-2">Registered <?= e(date('M j, Y', strtotime($cred['created_at']))) ?></span>
                            </div>
                            <form method="POST" action="/admin/security" class="ms-2">
                                <?= \App\Core\Csrf::field() ?>
                                <input type="hidden" name="action" value="delete_webauthn">
                                <input type="hidden" name="credential_id" value="<?= (int)$cred['id'] ?>">
                                <button type="submit" class="btn btn-outline-danger btn-sm"
                                        onclick="return confirm('Remove this security key?')">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <button type="button" class="btn btn-primary" id="register-key-btn">
                        <i class="bi bi-plus-circle me-1"></i>Register Security Key
                    </button>
                    <div id="key-status" class="mt-2 d-none"></div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<?php if (($totpAllowed ?? false) && !($totpEnabled ?? false) && ($totpProvisioningUri ?? '') !== ''): ?>
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"
        integrity="sha384-OLBgp1GsljhM2TJ+sbHjaiH9txEUvgdDTAzHv2P24donTt6/529l+9Ua0vFImLlb"
        crossorigin="anonymous"></script>
<script>
(function () {
    var uri = <?= json_encode($totpProvisioningUri ?? '') ?>;
    var el  = document.getElementById('totp-qr');
    if (el && uri) {
        new QRCode(el, { text: uri, width: 200, height: 200, correctLevel: QRCode.CorrectLevel.M });
    }
}());
</script>
<?php endif; ?>

<?php if (($webauthnPlatformAllowed ?? false) || ($webauthnYubikeyAllowed ?? false)): ?>
<script>
(function () {
    function b64url(buffer) {
        var bytes = new Uint8Array(buffer);
        var binary = '';
        for (var i = 0; i < bytes.byteLength; i++) binary += String.fromCharCode(bytes[i]);
        return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
    }

    function b64urlDecode(str) {
        str = str.replace(/-/g, '+').replace(/_/g, '/');
        while (str.length % 4) str += '=';
        var bin = atob(str);
        var buf = new Uint8Array(bin.length);
        for (var i = 0; i < bin.length; i++) buf[i] = bin.charCodeAt(i);
        return buf.buffer;
    }

    function prepareOptions(opts) {
        opts.challenge = b64urlDecode(opts.challenge);
        if (opts.user) opts.user.id = b64urlDecode(opts.user.id);
        if (opts.excludeCredentials) {
            opts.excludeCredentials = opts.excludeCredentials.map(function (c) {
                return { type: c.type, id: b64urlDecode(c.id), transports: c.transports || [] };
            });
        }
        return opts;
    }

    function showStatus(el, cls, msg) {
        el.className = 'mt-2 alert alert-' + cls;
        el.textContent = msg;
        el.classList.remove('d-none');
    }

    function registerWebAuthn(type, btnId, statusId) {
        var btn    = document.getElementById(btnId);
        var status = document.getElementById(statusId);
        if (!btn) return;

        btn.addEventListener('click', function () {
            btn.disabled = true;
            showStatus(status, 'secondary', 'Requesting challenge…');

            fetch('/admin/security/webauthn/challenge?type=' + type)
                .then(function (r) { return r.json(); })
                .then(function (opts) {
                    showStatus(status, 'secondary', 'Waiting for authenticator…');
                    return navigator.credentials.create({ publicKey: prepareOptions(opts) });
                })
                .then(function (cred) {
                    showStatus(status, 'secondary', 'Verifying with server…');
                    var body = {
                        id:   b64url(cred.rawId),
                        rawId: b64url(cred.rawId),
                        type: cred.type,
                        response: {
                            clientDataJSON:    b64url(cred.response.clientDataJSON),
                            attestationObject: b64url(cred.response.attestationObject),
                        }
                    };
                    return fetch('/admin/security/webauthn/register', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(body)
                    }).then(function (r) { return r.json(); });
                })
                .then(function (result) {
                    if (result.success) {
                        showStatus(status, 'success', '✓ ' + result.message);
                        setTimeout(function () { location.reload(); }, 1200);
                    } else {
                        showStatus(status, 'danger', 'Error: ' + (result.error || 'Unknown error'));
                        btn.disabled = false;
                    }
                })
                .catch(function (err) {
                    showStatus(status, 'danger', 'Error: ' + err.message);
                    btn.disabled = false;
                });
        });
    }

    <?php if ($webauthnPlatformAllowed ?? false): ?>
    registerWebAuthn('platform', 'register-platform-btn', 'platform-status');
    <?php endif; ?>
    <?php if ($webauthnYubikeyAllowed ?? false): ?>
    registerWebAuthn('security_key', 'register-key-btn', 'key-status');
    <?php endif; ?>
}());
</script>
<?php endif; ?>

