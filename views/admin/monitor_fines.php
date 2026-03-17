<div class="d-flex justify-content-between align-items-center mb-3">
    <h3>Monitor Fines</h3>
    <a href="index.php?c=admin&a=dashboard" class="btn btn-secondary">Back</a>
</div>

<div class="table-responsive">
    <table class="table table-bordered table-striped">
        <thead>
        <tr>
            <th>MemName</th>
            <th>Title</th>
            <th>Copies</th>
            <th>Bdate</th>
            <th>Fine</th>
            <th>Fine Status</th>
        </tr>
        </thead>
        <tbody>
        <?php if (empty($rows)): ?>
            <tr><td colspan="6" class="text-center">No fine records found.</td></tr>
        <?php else: ?>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['MemName']) ?></td>
                    <td><?= htmlspecialchars($row['Title']) ?></td>
                    <td><?= (int) ($row['Copies'] ?? 0) ?></td>
                    <td><?= htmlspecialchars((string) ($row['Bdate'] ?? '')) ?></td>
                    <td><?= number_format((float) $row['Fine'], 2) ?></td>
                    <td><?= htmlspecialchars($row['FineStatus']) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>
