<div class="d-flex justify-content-between align-items-center mb-3">
    <h3>My Requests</h3>
    <a href="index.php?c=member&a=dashboard" class="btn btn-secondary">Back</a>
</div>

<div class="table-responsive">
    <table class="table table-bordered table-striped align-middle">
        <thead>
        <tr>
            <th>Title</th>
            <th>Request ID</th>
            <th>Status</th>
        </tr>
        </thead>
        <tbody>
        <?php if (empty($requests)): ?>
            <tr><td colspan="3" class="text-center">No requests found.</td></tr>
        <?php else: ?>
            <?php foreach ($requests as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['Title']) ?></td>
                    <td>#<?= (int) $row['BorrowId'] ?></td>
                    <td>
                        <?php if ($row['Status'] === 'Pending'): ?>
                            <span class="badge bg-warning">Pending</span>
                        <?php elseif ($row['Status'] === 'Approved'): ?>
                            <span class="badge bg-success">Approved</span>
                        <?php elseif ($row['Status'] === 'Rejected'): ?>
                            <span class="badge bg-danger">Rejected</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>