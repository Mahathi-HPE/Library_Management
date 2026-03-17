<?php
declare(strict_types=1);

class AuthController extends Controller
{
    public function login(): void
    {
        $this->redirectIfAuthenticated();
        $this->render('auth/login', [
            'title' => 'Login',
            'error' => $this->flash('error'),
            'message' => $this->flash('message'),
        ]);
        $this->clearFlash();
    }

    public function register(): void
    {
        $this->redirectIfAuthenticated();
        $this->render('auth/register', [
            'title' => 'Register',
            'error' => $this->flash('error'),
            'old' => $this->flash('old', []),
        ]);
        $this->clearFlash();
    }

    public function authenticate(): void
    {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $role = trim($_POST['role'] ?? '');

        if (!$username || !$password || !in_array($role, ['User', 'Admin'], true)) {
            $this->loginError('Please fill all login fields.');
        }

        $dbUser = (new User())->findByUsernameAndRole($username, $role);
        $isValid = $dbUser && (password_verify($password, $dbUser['Password']) || hash_equals($dbUser['Password'], $password));

        if (!$dbUser || !$isValid || $dbUser['RName'] !== $role) {
            $this->loginError('Invalid credentials.');
        }

        $user = ['uid' => (int) $dbUser['Uid'], 'username' => $dbUser['Username'], 'role' => $dbUser['RName']];

        if ($role === 'User') {
            $member = (new Member())->findByUser((int) $dbUser['Uid'], $dbUser['Username']);
            if (!$member) {
                $this->loginError('No member record found.');
            }
            $user['mid'] = (int) $member['Mid'];
            $user['mem_name'] = $member['MemName'];
        }

        Auth::login($user);
        $this->redirect($role === 'Admin' ? 'admin' : 'member', 'dashboard');
    }

    public function storeRegistration(): void
    {
        $name = trim($_POST['name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if ($name === '' || $username === '' || $password === '' || $location === '' || $email === '') {
            $this->registerError('Please fill all registration fields.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->registerError('Please enter a valid email address.');
        }

        $userModel = new User();
        $memberModel = new Member();

        if ($userModel->findByUsername($username)) {
            $this->registerError('Username already exists.');
        }

        if ($memberModel->findByEmail($email)) {
            $this->registerError('Email already exists.');
        }

        $db = Database::getInstance()->pdo();

        try {
            $db->beginTransaction();
            $uid = $userModel->create($username, $password);
            $mid = $memberModel->create($name, $email, $location);
            $rid = $userModel->ensureRole('User');
            $userModel->assignRole($uid, $rid);
            $memberModel->linkUser($uid, $mid);
            $db->commit();

            $_SESSION['flash']['message'] = 'Registration successful. You can now log in as a member.';
            $this->redirect('auth', 'login');
        } catch (Throwable $e) {
            $db->rollBack();
            $this->registerError('Registration failed. Please try again.');
        }
    }

    public function logout(): void
    {
        Auth::logout();
        session_destroy();
        $this->redirect('auth', 'login');
    }

    private function redirectIfAuthenticated(): void
    {
        if (Auth::check()) {
            $this->redirect(Auth::role() === 'Admin' ? 'admin' : 'member', 'dashboard');
        }
    }

    private function flash(string $key, mixed $default = null): mixed
    {
        return $_SESSION['flash'][$key] ?? $default;
    }

    private function clearFlash(): void
    {
        unset($_SESSION['flash']);
    }

    private function loginError(string $message): void
    {
        $_SESSION['flash']['error'] = $message;
        $this->redirect('auth', 'login');
    }

    private function registerError(string $message): void
    {
        $_SESSION['flash']['error'] = $message;
        $_SESSION['flash']['old'] = $_POST;
        $this->redirect('auth', 'register');
    }
}
