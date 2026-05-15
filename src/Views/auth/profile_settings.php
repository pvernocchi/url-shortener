<h4 class="fw-bold mb-4">Profile Settings</h4>

<div class="row">
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <form method="POST" action="/admin/profile">
                    <?= \App\Core\Csrf::field() ?>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Display Name</label>
                        <input
                            type="text"
                            name="name"
                            class="form-control"
                            maxlength="100"
                            required
                            value="<?= e($user['name'] ?? '') ?>"
                        >
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Email</label>
                        <input type="email" class="form-control" value="<?= e($user['email'] ?? '') ?>" readonly>
                        <div class="form-text">Email changes are not available from this page.</div>
                    </div>

                    <hr class="my-4">
                    <h6 class="fw-semibold mb-3">Change Password</h6>
                    <p class="text-muted small">Leave both password fields empty to keep your current password.</p>

                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <input type="password" name="password" class="form-control" minlength="8" autocomplete="new-password">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Confirm New Password</label>
                        <input type="password" name="password_confirm" class="form-control" minlength="8" autocomplete="new-password">
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i>Save Profile
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
