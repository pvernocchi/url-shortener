<div class="row justify-content-center align-items-center" style="min-height:80vh;">
    <div class="col-sm-10 col-md-6 col-lg-5">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h5 class="card-title mb-4 fw-semibold">Create account</h5>
                <form method="POST" action="/signup">
                    <?= \App\Core\Csrf::field() ?>
                    <div class="mb-3">
                        <label for="signup_name" class="form-label">Name</label>
                        <input id="signup_name" type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="signup_email" class="form-label">Email address</label>
                        <input id="signup_email" type="email" name="email" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        Request invitation
                    </button>
                </form>
                <div class="text-center mt-3">
                    <a href="/login" class="text-decoration-none">Back to sign in</a>
                </div>
            </div>
        </div>
    </div>
</div>
