<div class="row justify-content-center align-items-center" style="min-height:80vh;">
    <div class="col-sm-10 col-md-6 col-lg-4">
        <div class="text-center mb-4">
            <h2 class="fw-bold"><i class="bi bi-link-45deg text-primary"></i>
                <?= e(\App\Core\Config::get('app.name', 'URL Shortener')) ?>
            </h2>
        </div>
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h5 class="card-title mb-4 fw-semibold">Sign in</h5>
                <form method="POST" action="/login">
                    <?= \App\Core\Csrf::field() ?>
                    <div class="mb-3">
                        <label class="form-label">Email address</label>
                        <input type="email" name="email" class="form-control" autofocus required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-box-arrow-in-right me-1"></i>Login
                    </button>
                </form>
                <div class="text-center mt-3">
                    <a href="/signup" class="text-decoration-none">Create account</a>
                </div>
            </div>
        </div>
    </div>
</div>
