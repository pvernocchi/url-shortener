<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="text-center mb-4">
            <h1 class="display-6 fw-bold"><i class="bi bi-link-45deg text-primary"></i> URL Shortener</h1>
            <p class="text-muted">Step 1 of 2 – Environment Check</p>
        </div>

        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-clipboard-check me-2"></i>Environment Requirements</h5>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Requirement</th>
                            <th>Value</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($checks as $check): ?>
                        <tr>
                            <td><?= e($check['label']) ?></td>
                            <td><code><?= e($check['value']) ?></code></td>
                            <td class="text-center">
                                <?php if ($check['pass']): ?>
                                    <i class="bi bi-check-circle-fill text-success fs-5"></i>
                                <?php else: ?>
                                    <i class="bi bi-x-circle-fill text-danger fs-5"></i>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php $allPass = !in_array(false, array_column($checks, 'pass'), true); ?>

        <?php if (!$allPass): ?>
        <div class="alert alert-danger mt-3">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <strong>Some requirements are not met.</strong> Please fix the issues above before proceeding.
            <ul class="mt-2 mb-0">
                <?php foreach ($checks as $check): ?>
                    <?php if (!$check['pass']): ?>
                    <li><?= e($check['label']) ?> – check your server configuration or file permissions.</li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php else: ?>
        <div class="alert alert-success mt-3">
            <i class="bi bi-check-circle-fill me-2"></i>
            All requirements met! Click <strong>Continue</strong> to proceed.
        </div>
        <?php endif; ?>

        <form method="POST" action="/install/step1" class="mt-3">
            <?= \App\Core\Csrf::field() ?>
            <button type="submit" class="btn btn-primary btn-lg" <?= !$allPass ? 'disabled' : '' ?>>
                Continue <i class="bi bi-arrow-right ms-1"></i>
            </button>
        </form>
    </div>
</div>
