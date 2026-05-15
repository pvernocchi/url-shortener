<div class="row justify-content-center align-items-center" style="min-height:80vh;">
    <div class="col-sm-10 col-md-6 col-lg-5">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h5 class="card-title mb-4 fw-semibold">Set your password</h5>
                <p class="text-muted mb-4">
                    <?= e($name ?? '') ?> (<?= e($email ?? '') ?>)
                </p>
                <form method="POST" action="/signup/complete">
                    <?= \App\Core\Csrf::field() ?>
                    <input type="hidden" name="token" value="<?= e($token ?? '') ?>">
                    <div class="mb-3">
                        <label for="signup_password" class="form-label">Password</label>
                        <input id="signup_password" type="password" name="password" class="form-control" minlength="8" required>
                    </div>
                    <div class="mb-3">
                        <label for="signup_password_confirm" class="form-label">Confirm password</label>
                        <input id="signup_password_confirm" type="password" name="password_confirm" class="form-control" minlength="8" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        Create account
                    </button>
                </form>
                <div class="text-center mt-3">
                    <a href="/login" class="text-decoration-none">Back to sign in</a>
                </div>
            </div>
        </div>
    </div>
</div>
