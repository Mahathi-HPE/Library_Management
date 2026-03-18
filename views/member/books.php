<div class="d-flex justify-content-between align-items-center mb-3">
    <h3>All Books</h3>
    <div class="text-end">
        <p class="mb-0"><small class="text-muted">Monthly limit: <strong><?= (int) $borrowedThisMonth ?>/7</strong> | Remaining: <strong><?= (int) $remainingThisMonth ?></strong></small></p>
        <a href="index.php?c=member&a=dashboard" class="btn btn-secondary">Back</a>
    </div>
</div>

<?php if (!empty($message)): ?>
    <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="get" class="row g-2 mb-3">
    <input type="hidden" name="c" value="member">
    <input type="hidden" name="a" value="books">
    <div class="col-md-8">
        <input type="text" class="form-control" name="search" value="<?= htmlspecialchars($search ?? '') ?>" placeholder="Search by title or author">
    </div>
    <div class="col-md-4">
        <button class="btn btn-primary w-100">Search</button>
    </div>
</form>

<div class="table-responsive">
    <table class="table table-bordered table-striped align-middle">
        <thead>
        <tr>
            <th>Title</th>
            <th>Author Name</th>
            <th>Price</th>
            <th>Pub Date</th>
            <th>Available Copies</th>
            <th>Borrow</th>
        </tr>
        </thead>
        <tbody>
        <?php if (empty($books)): ?>
            <tr><td colspan="6" class="text-center">No available books found.</td></tr>
        <?php else: ?>
            <?php foreach ($books as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['Title']) ?></td>
                    <td><?= htmlspecialchars($row['AuthName'] ?? '') ?></td>
                    <td><?= number_format((float) $row['Price'], 2) ?></td>
                    <td><?= htmlspecialchars($row['PubDate']) ?></td>
                    <td><?= (int) $row['AvailableCopies'] ?></td>
                    <td>
                        <form method="post" action="index.php?c=member&a=requestBook" class="d-flex gap-2 align-items-center">
                            <input type="hidden" name="bid" value="<?= (int) $row['Bid'] ?>">
                            <input
                                type="number"
                                name="quantity"
                                class="form-control form-control-sm"
                                min="1"
                                max="<?= (int) $row['AvailableCopies'] ?>"
                                value="1"
                                style="width: 90px;"
                                required
                                <?= (int) $row['AvailableCopies'] <= 0 ? 'disabled' : '' ?>
                            >
                            <button class="btn btn-sm btn-success" <?= (int) $row['AvailableCopies'] <= 0 ? 'disabled' : '' ?>>Request</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>
