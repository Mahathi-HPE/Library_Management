<?php
declare(strict_types=1);

class MemberController extends Controller
{
    public function dashboard(): void
    {
        Auth::requireRole('User');

        $this->render('member/dashboard', [
            'title' => 'Member Dashboard',
            'user' => Auth::user(),
        ]);
    }

    public function books(): void
    {
        Auth::requireRole('User');
        $search = trim($_GET['search'] ?? '');
        $this->render('member/books', [
            'title' => 'All Books',
            'books' => (new Book())->availableBooks($search ?: null),
            'search' => $search,
            'message' => $_SESSION['flash']['message'] ?? null,
            'error' => $_SESSION['flash']['error'] ?? null,
        ]);
        unset($_SESSION['flash']);
    }

    public function borrow(): void
    {
        Auth::requireRole('User');
        $bid = (int) ($_POST['bid'] ?? 0);
        $mid = (int) (Auth::user()['mid'] ?? 0);
        $quantity = (int) ($_POST['quantity'] ?? 0);
        $borrowModel = new Borrow();
        $remaining = $borrowModel->remainingThisMonth($mid);

        if ($bid <= 0 || $mid <= 0 || $quantity <= 0) {
            $_SESSION['flash']['error'] = 'Invalid borrow request.';
        } elseif (($borrowModel->getActiveBorrowCount($bid, $mid) + $quantity) > 3) {
            $_SESSION['flash']['error'] = 'You have reached the maximum limit of 3 copies for this book. Please return a copy before borrowing more.';
        } elseif (!$borrowModel->isAvailable($bid, $quantity)) {
            $_SESSION['flash']['error'] = 'Requested number of copies is not available.';
        } elseif ($quantity > $remaining) {
            $_SESSION['flash']['error'] = $remaining > 0
                ? 'Monthly limit is 5 copies. You can borrow only ' . $remaining . ' more this month.'
                : 'Monthly limit reached. You cannot borrow more than 5 copies this month.';
        } elseif ($borrowModel->borrowBook($bid, $mid, $quantity)) {
            $_SESSION['flash']['message'] = $quantity . ' cop' . ($quantity > 1 ? 'ies were' : 'y was') . ' borrowed successfully.';
        } else {
            $_SESSION['flash']['error'] = 'Requested number of copies is not available.';
        }
        $this->redirect('member', 'books');
    }

    public function current(): void
    {
        Auth::requireRole('User');
        $this->render('member/current', ['title' => 'Currently Borrowed', 'rows' => (new Borrow())->currentBorrowed((int) Auth::user()['mid'])]);
    }

    public function returns(): void
    {
        Auth::requireRole('User');
        $this->render('member/returns', [
            'title' => 'Return Books',
            'rows' => (new Borrow())->currentBorrowed((int) Auth::user()['mid']),
            'message' => $_SESSION['flash']['message'] ?? null,
            'error' => $_SESSION['flash']['error'] ?? null,
        ]);
        unset($_SESSION['flash']);
    }

    public function returnBooks(): void
    {
        Auth::requireRole('User');
        $bid = (int) ($_POST['bid'] ?? 0);
        $mid = (int) (Auth::user()['mid'] ?? 0);
        $quantity = (int) ($_POST['quantity'] ?? 0);

        if ($bid <= 0 || $mid <= 0 || $quantity <= 0) {
            $_SESSION['flash']['error'] = 'Invalid return request.';
        } elseif ((new Borrow())->returnBookCopies($bid, $mid, $quantity)) {
            $_SESSION['flash']['message'] = $quantity . ' cop' . ($quantity > 1 ? 'ies were' : 'y was') . ' returned successfully.';
        } else {
            $_SESSION['flash']['error'] = 'Requested number of copies cannot be returned.';
        }

        $this->redirect('member', 'returns');
    }

    public function history(): void
    {
        Auth::requireRole('User');
        $this->render('member/history', ['title' => 'Borrow History', 'rows' => (new Borrow())->history((int) Auth::user()['mid'])]);
    }
}
