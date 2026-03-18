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
        $mid = (int) (Auth::user()['mid'] ?? 0);
        $borrow = new Borrow();
        
        $this->render('member/books', [
            'title' => 'All Books',
            'books' => (new Book())->availableBooks($search ?: null),
            'search' => $search,
            'message' => $_SESSION['flash']['message'] ?? null,
            'error' => $_SESSION['flash']['error'] ?? null,
            'remainingThisMonth' => $borrow->remainingThisMonth($mid),
            'borrowedThisMonth' => $borrow->borrowedThisMonth($mid),
        ]);
        unset($_SESSION['flash']);
    }

    public function requestBook(): void
    {
        Auth::requireRole('User');
        $bid = (int) ($_POST['bid'] ?? 0);
        $mid = (int) (Auth::user()['mid'] ?? 0);
        $quantity = (int) ($_POST['quantity'] ?? 0);

        if ($bid <= 0 || $mid <= 0 || $quantity <= 0) {
            $_SESSION['flash']['error'] = 'Invalid request.';
        } else {
            $borrow = new Borrow();
            
            // Check availability before attempting request
            if (!$borrow->isAvailable($bid, 1)) {
                $_SESSION['flash']['error'] = 'No copies of this book are currently available.';
            } else {
                $availableCopies = $borrow->getAvailableCopiesCount($bid);
                $pendingRequests = $borrow->getPendingRequestCountForBook($bid, $mid);
                $borrowedThisMonth = $borrow->borrowedThisMonth($mid);
                $remainingThisMonth = $borrow->remainingThisMonth($mid);
                
                if ($quantity > $availableCopies) {
                    $_SESSION['flash']['error'] = "Only {$availableCopies} copy/copies available. You requested {$quantity}.";
                } elseif (($pendingRequests + $quantity) > $availableCopies) {
                    $_SESSION['flash']['error'] = "You have {$pendingRequests} pending request(s). Total would exceed {$availableCopies} available copy/copies.";
                } elseif ($quantity > $remainingThisMonth) {
                    $_SESSION['flash']['error'] = "You have borrowed/requested {$borrowedThisMonth} books this month. Only {$remainingThisMonth} more allowed (monthly limit: 7).";
                } elseif ($borrow->requestBook($bid, $mid, $quantity)) {
                    $_SESSION['flash']['message'] = 'Book request submitted successfully. Waiting for admin approval.';
                } else {
                    $_SESSION['flash']['error'] = 'Failed to submit request.';
                }
            }
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
            'rows' => (new Borrow())->currentReturnable((int) Auth::user()['mid']),
            'message' => $_SESSION['flash']['message'] ?? null,
            'error' => $_SESSION['flash']['error'] ?? null,
        ]);
        unset($_SESSION['flash']);
    }

    public function requestReturn(): void
    {
        Auth::requireRole('User');
        $bid = (int) ($_POST['bid'] ?? 0);
        $mid = (int) (Auth::user()['mid'] ?? 0);
        $quantity = (int) ($_POST['quantity'] ?? 0);

        if ($bid <= 0 || $mid <= 0 || $quantity <= 0) {
            $_SESSION['flash']['error'] = 'Invalid return request.';
        } elseif ((new Borrow())->requestReturnBookCopies($bid, $mid, $quantity)) {
            $_SESSION['flash']['message'] = 'Return request submitted successfully. Waiting for admin approval.';
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

    public function requests(): void
    {
        Auth::requireRole('User');
        $this->render('member/requests', [
            'title' => 'My Requests',
            'requests' => (new Borrow())->getBorrowRequestsByMember((int) Auth::user()['mid'])
        ]);
    }
}
