<div class="row justify-content-center align-items-center" style="min-height:80vh;">
    <div class="col-sm-10 col-md-6 col-lg-4">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h5 class="card-title mb-3 fw-semibold">Verify your sign-in</h5>

                <?php if ($mfaType === 'totp'): ?>
                <p class="text-muted small">
                    Enter the 6-digit code from your authenticator app for
                    <strong><?= e($pendingEmail ?? '') ?></strong>.
                </p>
                <form method="POST" action="/login/mfa">
                    <?= \App\Core\Csrf::field() ?>
                    <div class="mb-3">
                        <label class="form-label">Authentication code</label>
                        <input type="text" name="mfa_code" class="form-control" maxlength="6" inputmode="numeric" required autofocus>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-shield-check me-1"></i>Verify
                    </button>
                </form>
                <?php endif; ?>

                <?php if ($mfaType === 'webauthn' || ($hasWebAuthn ?? false)): ?>
                <?php if ($mfaType === 'totp'): ?><hr class="my-3"><?php endif; ?>
                <p class="text-muted small <?= $mfaType === 'webauthn' ? '' : 'mt-2' ?>">
                    <?= $mfaType === 'webauthn' ? 'Signing in as <strong>' . e($pendingEmail ?? '') . '</strong>.' : 'Or use a security key.' ?>
                </p>
                <button type="button" id="webauthn-btn" class="btn btn-outline-primary w-100">
                    <i class="bi bi-key me-1"></i>Use Security Key / Device
                </button>
                <div id="webauthn-status" class="mt-2 d-none"></div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<?php if (($mfaType === 'webauthn') || ($hasWebAuthn ?? false)): ?>
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

    function showStatus(el, cls, msg) {
        el.className = 'mt-2 alert alert-' + cls;
        el.textContent = msg;
        el.classList.remove('d-none');
    }

    var btn    = document.getElementById('webauthn-btn');
    var status = document.getElementById('webauthn-status');

    btn.addEventListener('click', function () {
        btn.disabled = true;
        showStatus(status, 'secondary', 'Requesting challenge…');

        fetch('/login/webauthn/challenge')
            .then(function (r) { return r.json(); })
            .then(function (opts) {
                showStatus(status, 'secondary', 'Waiting for authenticator…');
                opts.challenge = b64urlDecode(opts.challenge);
                if (opts.allowCredentials) {
                    opts.allowCredentials = opts.allowCredentials.map(function (c) {
                        return { type: c.type, id: b64urlDecode(c.id), transports: c.transports || [] };
                    });
                }
                return navigator.credentials.get({ publicKey: opts });
            })
            .then(function (cred) {
                showStatus(status, 'secondary', 'Verifying with server…');
                var body = {
                    id: b64url(cred.rawId),
                    rawId: b64url(cred.rawId),
                    type: cred.type,
                    response: {
                        clientDataJSON:    b64url(cred.response.clientDataJSON),
                        authenticatorData: b64url(cred.response.authenticatorData),
                        signature:         b64url(cred.response.signature),
                        userHandle:        cred.response.userHandle ? b64url(cred.response.userHandle) : null,
                    }
                };
                return fetch('/login/webauthn/verify', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(body)
                }).then(function (r) { return r.json(); });
            })
            .then(function (result) {
                if (result.success && result.redirect) {
                    showStatus(status, 'success', '✓ Verified! Redirecting…');
                    window.location.href = result.redirect;
                } else {
                    showStatus(status, 'danger', 'Error: ' + (result.error || 'Verification failed'));
                    btn.disabled = false;
                }
            })
            .catch(function (err) {
                showStatus(status, 'danger', 'Error: ' + err.message);
                btn.disabled = false;
            });
    });
}());
</script>
<?php endif; ?>
