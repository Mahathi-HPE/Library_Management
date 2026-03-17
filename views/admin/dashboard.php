<div class="d-flex justify-content-between align-items-center mb-3">
    <h3>Admin Dashboard</h3>
    <a href="index.php?c=auth&a=logout" class="btn btn-outline-danger">Logout</a>
</div>

<?php if (!empty($message)): ?>
    <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="list-group mb-4">
    <a href="index.php?c=admin&a=manageUsers" class="list-group-item list-group-item-action">Manage Users</a>
    <a href="index.php?c=admin&a=manageRequests" class="list-group-item list-group-item-action">Manage Borrow Requests</a>
    <a href="index.php?c=admin&a=manageReturns" class="list-group-item list-group-item-action">Manage Return Requests</a>
    <a href="index.php?c=admin&a=addBook" class="list-group-item list-group-item-action">Add New Book</a>
    <a href="index.php?c=admin&a=monitorFines" class="list-group-item list-group-item-action">Monitor Fines</a>
</div>
