<div class="d-flex justify-content-between align-items-center mb-3">
    <h3>Return Books</h3>
    <a href="index.php?c=member&a=dashboard" class="btn btn-secondary">Back</a>
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
            <th>Bdate</th>
            <th>Price</th>
            <th>Author Name</th>
            <th>Copies</th>
            <th>Return</th>
        </tr>
        </thead>
        <tbody>
        <?php if (empty($rows)): ?>
            <tr><td colspan="6" class="text-center">No borrowed books available to return.</td></tr>
        <?php else: ?>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['Title']) ?></td>
                    <td><?= htmlspecialchars((string) ($row['Bdate'] ?? '')) ?></td>
                    <td><?= number_format((float) $row['Price'], 2) ?></td>
                    <td><?= htmlspecialchars($row['AuthName'] ?? '') ?></td>
                    <td><?= (int) $row['Copies'] ?></td>
                    <td>
                        <form method="post" action="index.php?c=member&a=requestReturn" class="d-flex gap-2 align-items-center">
                            <input type="hidden" name="bid" value="<?= (int) $row['Bid'] ?>">
                            <input
                                type="number"
                                name="quantity"
                                class="form-control form-control-sm"
                                min="1"
                                max="<?= (int) $row['Copies'] ?>"
                                value="1"
                                style="width: 90px;"
                                required
                            >
                            <button class="btn btn-sm btn-warning">Request Return</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>