<div class="d-flex justify-content-between align-items-center mb-3">
    <h3>Currently Borrowed Books</h3>
    <a href="index.php?c=member&a=dashboard" class="btn btn-secondary">Back</a>
</div>

<div class="table-responsive">
    <table class="table table-bordered table-striped">
        <thead>
        <tr>
            <th>Title</th>
            <th>Bdate</th>
            <th>Price</th>
            <th>Author Name</th>
            <th>Copies</th>
            <th>Fine</th>
            <th>Fine Status</th>
        </tr>
        </thead>
        <tbody>
        <?php if (empty($rows)): ?>
            <tr><td colspan="7" class="text-center">No current borrowed books.</td></tr>
        <?php else: ?>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['Title']) ?></td>
                    <td><?= htmlspecialchars((string) ($row['Bdate'] ?? '')) ?></td>
                    <td><?= number_format((float) $row['Price'], 2) ?></td>
                    <td><?= htmlspecialchars($row['AuthName'] ?? '') ?></td>
                    <td><?= (int) $row['Copies'] ?></td>
                    <td><?= number_format((float) $row['Fine'], 2) ?></td>
                    <td><?= htmlspecialchars($row['FineStatus']) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>
