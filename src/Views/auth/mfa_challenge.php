<div class="row justify-content-center align-items-center" style="min-height:80vh;">
    <div class="col-sm-10 col-md-6 col-lg-4">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h5 class="card-title mb-3 fw-semibold">Verify your sign-in</h5>
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
            </div>
        </div>
    </div>
</div>
