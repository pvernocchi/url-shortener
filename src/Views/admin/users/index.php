<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0">Users</h4>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <h6 class="fw-semibold mb-3">Invite User</h6>
        <form method="POST" action="/admin/users/invite" class="row g-2">
            <?= \App\Core\Csrf::field() ?>
            <div class="col-md-4">
                <input type="text" name="name" class="form-control" placeholder="Name" maxlength="100" required>
            </div>
            <div class="col-md-5">
                <input type="email" name="email" class="form-control" placeholder="Email" maxlength="255" required>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary w-100">Send Invitation</button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($users)): ?>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?= e($user['name']) ?></td>
                            <td><?= e($user['email']) ?></td>
                            <td><span class="badge <?= $user['role'] === 'admin' ? 'bg-primary' : 'bg-secondary' ?>"><?= e($user['role']) ?></span></td>
                            <td><?= e($user['status']) ?></td>
                            <td class="text-end">
                                <a href="/admin/users/<?= (int)$user['id'] ?>/edit" class="btn btn-sm btn-outline-secondary">Edit</a>
                                <?php if (($user['role'] ?? 'user') !== 'admin'): ?>
                                    <form method="POST" action="/admin/users/<?= (int)$user['id'] ?>/promote" class="d-inline">
                                        <?= \App\Core\Csrf::field() ?>
                                        <button type="submit" class="btn btn-sm btn-outline-primary">
                                            Promote to Admin
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <?php if ((int)$user['id'] !== (int)($currentUserId ?? 0)): ?>
                                    <form method="POST" action="/admin/users/<?= (int)$user['id'] ?>/delete" class="d-inline"
                                          onsubmit="return confirm('Delete this user? Their links will be kept but unassigned.');">
                                        <?= \App\Core\Csrf::field() ?>
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">No users found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
