<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h3 class="mb-3">Login</h3>

                <?php if (!empty($message)): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
                <?php endif; ?>
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="post" action="index.php?c=auth&a=authenticate">
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Login As</label>
                        <select name="role" class="form-select" required>
                            <option value="User">Member</option>
                            <option value="Admin">Admin</option>
                        </select>
                    </div>
                    <button class="btn btn-primary w-100" type="submit">Login</button>
                </form>

                <div class="text-center mt-3">
                    <span class="text-muted">New user?</span>
                    <a href="index.php?c=auth&a=register">Register here</a>
                </div>
            </div>
        </div>
    </div>
</div>

