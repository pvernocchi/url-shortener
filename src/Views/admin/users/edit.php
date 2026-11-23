<?php $isSelf = (int)$user['id'] === (int)($currentUserId ?? 0); ?>
<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="fw-bold mb-0">Edit User</h4>
            <a href="/admin/users" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Back
            </a>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-body p-4">
                <h6 class="fw-semibold mb-3">Account Details</h6>
                <form method="POST" action="/admin/users/<?= (int)$user['id'] ?>">
                    <?= \App\Core\Csrf::field() ?>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Name</label>
                        <input type="text" name="name" class="form-control" maxlength="100" value="<?= e($user['name']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Email</label>
                        <input type="email" name="email" class="form-control" maxlength="255" value="<?= e($user['email']) ?>" required>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Role</label>
                            <select name="role" class="form-select" <?= $isSelf ? 'disabled' : '' ?>>
                                <?php foreach (['user', 'admin'] as $role): ?>
                                    <option value="<?= $role ?>" <?= $user['role'] === $role ? 'selected' : '' ?>><?= $role ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Status</label>
                            <select name="status" class="form-select" <?= $isSelf ? 'disabled' : '' ?>>
                                <?php foreach (['active', 'suspended'] as $status): ?>
                                    <option value="<?= $status ?>" <?= $user['status'] === $status ? 'selected' : '' ?>><?= $status ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <?php if ($isSelf): ?>
                        <p class="text-muted small">You cannot change your own role or status.</p>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </form>
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-body p-4">
                <h6 class="fw-semibold mb-3">Reset Password</h6>
                <form method="POST" action="/admin/users/<?= (int)$user['id'] ?>/password" autocomplete="off">
                    <?= \App\Core\Csrf::field() ?>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <input type="password" name="password" class="form-control" placeholder="New password" minlength="8" autocomplete="new-password" required>
                        </div>
                        <div class="col-md-6">
                            <input type="password" name="password_confirm" class="form-control" placeholder="Confirm password" minlength="8" autocomplete="new-password" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-warning">Reset Password</button>
                </form>
            </div>
        </div>

        <?php if (!$isSelf): ?>
            <div class="card shadow-sm border-danger">
                <div class="card-body p-4">
                    <h6 class="fw-semibold text-danger mb-3">Delete Account</h6>
                    <p class="text-muted small">This permanently removes the user, their API tokens and security keys. Their links are kept but unassigned.</p>
                    <form method="POST" action="/admin/users/<?= (int)$user['id'] ?>/delete"
                          onsubmit="return confirm('Delete this user permanently?');">
                        <?= \App\Core\Csrf::field() ?>
                        <button type="submit" class="btn btn-danger">Delete User</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
