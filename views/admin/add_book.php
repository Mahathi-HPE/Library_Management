<div class="d-flex justify-content-between align-items-center mb-3">
    <h3>Add New Book</h3>
    <a href="index.php?c=admin&a=dashboard" class="btn btn-secondary">Back</a>
</div>

<?php if (!empty($message)): ?>
    <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="post" action="index.php?c=admin&a=addBook" class="card card-body">
    <div class="mb-3">
        <label class="form-label">Title</label>
        <input type="text" name="title" class="form-control" required>
    </div>
    <div class="mb-3">
        <label class="form-label">Author Names (comma separated)</label>
        <input type="text" name="authors" class="form-control" placeholder="Author 1, Author 2" required>
    </div>
    <div class="mb-3">
        <label class="form-label">Author Locations (comma separated, same order)</label>
        <input type="text" name="author_locations" class="form-control" placeholder="Location 1, Location 2">
    </div>
    <div class="mb-3">
        <label class="form-label">Author Emails (comma separated, same order)</label>
        <input type="text" name="author_emails" class="form-control" placeholder="author1@mail.com, author2@mail.com">
    </div>
    <div class="mb-3">
        <label class="form-label">Price</label>
        <input type="number" name="price" class="form-control" min="1" step="0.01" required>
    </div>
    <div class="mb-3">
        <label class="form-label">Pub Date</label>
        <input type="date" name="pubdate" class="form-control" required>
    </div>
    <div class="mb-3">
        <label class="form-label">Number of Copies</label>
        <input type="number" name="copies" class="form-control" min="1" value="1" required>
    </div>
    <button class="btn btn-primary">Add Book</button>
</form>
