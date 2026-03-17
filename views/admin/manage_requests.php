<div class="d-flex justify-content-between align-items-center mb-3">
    <h3>Manage Borrow Requests</h3>
    <a href="index.php?c=admin&a=dashboard" class="btn btn-secondary">Back</a>
</div>

<?php if (!empty($message)): ?>
    <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="table-responsive">
    <table class="table table-bordered table-striped align-middle">
        <thead>
        <tr>
            <th>Title</th>
            <th>Member</th>
            <th>Request ID</th>
            <th>Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php if (empty($requests)): ?>
            <tr><td colspan="4" class="text-center">No pending requests.</td></tr>
        <?php else: ?>
            <?php foreach ($requests as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['Title']) ?></td>
                    <td><?= htmlspecialchars($row['MemName']) ?></td>
                    <td>#<?= (int) $row['BorrowId'] ?></td>
                    <td>
                        <form method="post" action="index.php?c=admin&a=approveRequest" class="d-inline">
                            <input type="hidden" name="borrow_id" value="<?= (int) $row['BorrowId'] ?>">
                            <button class="btn btn-sm btn-success">Approve</button>
                        </form>
                        <form method="post" action="index.php?c=admin&a=rejectRequest" class="d-inline">
                            <input type="hidden" name="borrow_id" value="<?= (int) $row['BorrowId'] ?>">
                            <button class="btn btn-sm btn-danger">Reject</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>