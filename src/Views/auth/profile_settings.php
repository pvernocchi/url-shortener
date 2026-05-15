<h4 class="fw-bold mb-4">Profile Settings</h4>

<div class="row">
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <form method="POST" action="/admin/profile">
                    <?= \App\Core\Csrf::field() ?>

                    <div class="mb-3">
                        <label for="profile_name" class="form-label fw-semibold">Display Name</label>
                        <input
                            id="profile_name"
                            type="text"
                            name="name"
                            class="form-control"
                            maxlength="100"
                            required
                            value="<?= e($user['name'] ?? '') ?>"
                        >
                    </div>

                    <div class="mb-3">
                        <label for="profile_email" class="form-label fw-semibold">Email</label>
                        <input
                            id="profile_email"
                            type="email"
                            class="form-control"
                            value="<?= e($user['email'] ?? '') ?>"
                            readonly
                            aria-describedby="profile_email_help"
                        >
                        <div id="profile_email_help" class="form-text">Email changes are not available from this page.</div>
                    </div>

                    <hr class="my-4">
                    <h6 class="fw-semibold mb-3">Change Password</h6>
                    <p id="profile_password_help" class="text-muted small">Leave both password fields empty to keep your current password.</p>

                    <div class="mb-3">
                        <label for="profile_password" class="form-label">Password</label>
                        <input
                            id="profile_password"
                            type="password"
                            name="password"
                            class="form-control"
                            minlength="<?= (int)($minPasswordLength ?? 8) ?>"
                            autocomplete="new-password"
                            aria-describedby="profile_password_help"
                        >
                    </div>

                    <div class="mb-3">
                        <label for="profile_password_confirm" class="form-label">Confirm Password</label>
                        <input
                            id="profile_password_confirm"
                            type="password"
                            name="password_confirm"
                            class="form-control"
                            minlength="<?= (int)($minPasswordLength ?? 8) ?>"
                            autocomplete="new-password"
                            aria-describedby="profile_password_help"
                        >
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i>Save Profile
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
