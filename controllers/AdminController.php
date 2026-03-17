<?php
declare(strict_types=1);

class AdminController extends Controller
{
    public function dashboard(): void
    {
        Auth::requireRole('Admin');

        $this->render('admin/dashboard', [
            'title' => 'Admin Dashboard',
            'message' => $_SESSION['flash']['message'] ?? null,
            'error' => $_SESSION['flash']['error'] ?? null,
        ]);

        unset($_SESSION['flash']);
    }

    public function manageUsers(): void
    {
        Auth::requireRole('Admin');
        $this->render('admin/manage_users', ['title' => 'Manage Users', 'rows' => (new Borrow())->adminManageUsersTable()]);
    }

    public function addBook(): void
    {
        Auth::requireRole('Admin');
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $title = trim($_POST['title'] ?? '');
            $authorsRaw = trim($_POST['authors'] ?? '');
            $locationsRaw = trim($_POST['author_locations'] ?? '');
            $emailsRaw = trim($_POST['author_emails'] ?? '');
            $authorNames = array_values(array_filter(array_map('trim', explode(',', $authorsRaw))));
            $authorLocations = $locationsRaw === ''
                ? []
                : array_values(array_map('trim', explode(',', $locationsRaw)));
            $authorEmails = $emailsRaw === ''
                ? []
                : array_values(array_map('trim', explode(',', $emailsRaw)));

            if (!empty($authorLocations) && count($authorLocations) !== count($authorNames)) {
                $_SESSION['flash']['error'] = 'Author locations must match the number of author names.';
                $this->render('admin/add_book', ['title' => 'Add New Book', 'message' => $_SESSION['flash']['message'] ?? null, 'error' => $_SESSION['flash']['error'] ?? null]);
                unset($_SESSION['flash']);
                return;
            }

            if (!empty($authorEmails) && count($authorEmails) !== count($authorNames)) {
                $_SESSION['flash']['error'] = 'Author emails must match the number of author names.';
                $this->render('admin/add_book', ['title' => 'Add New Book', 'message' => $_SESSION['flash']['message'] ?? null, 'error' => $_SESSION['flash']['error'] ?? null]);
                unset($_SESSION['flash']);
                return;
            }

            $authors = [];
            foreach ($authorNames as $index => $name) {
                if ($name === '') {
                    continue;
                }

                $email = $authorEmails[$index] ?? '';
                if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $_SESSION['flash']['error'] = 'Please enter valid author emails.';
                    $this->render('admin/add_book', ['title' => 'Add New Book', 'message' => $_SESSION['flash']['message'] ?? null, 'error' => $_SESSION['flash']['error'] ?? null]);
                    unset($_SESSION['flash']);
                    return;
                }

                $authors[] = [
                    'name' => $name,
                    'location' => $authorLocations[$index] ?? 'Unknown',
                    'email' => $email,
                ];
            }

            $authors = array_values(array_reduce($authors, function (array $carry, array $item): array {
                $key = strtolower($item['name']);
                if (!isset($carry[$key])) {
                    $carry[$key] = $item;
                }
                return $carry;
            }, []));

            $price = (float) ($_POST['price'] ?? 0);
            $pubDate = trim($_POST['pubdate'] ?? '');
            $copies = (int) ($_POST['copies'] ?? 0);
            if (!$title || empty($authors) || $price <= 0 || !$pubDate || $copies <= 0) {
                $_SESSION['flash']['error'] = 'All fields are required, and price and copies must be > 0.';
            } elseif ((new Book())->addBookWithAuthorsAndCopy($title, $authors, $price, $pubDate, $copies)) {
                $_SESSION['flash']['message'] = 'Book added successfully.';
                $this->redirect('admin', 'dashboard');
            } else {
                $_SESSION['flash']['error'] = 'Could not add book.';
            }
        }
        $this->render('admin/add_book', ['title' => 'Add New Book', 'message' => $_SESSION['flash']['message'] ?? null, 'error' => $_SESSION['flash']['error'] ?? null]);
        unset($_SESSION['flash']);
    }

    public function monitorFines(): void
    {
        Auth::requireRole('Admin');
        $this->render('admin/monitor_fines', ['title' => 'Monitor Fines', 'rows' => (new Borrow())->adminFineTable()]);
    }
}
