<?php
/**
 * app/controllers/AuthController.php
 * Controleur MVC - authentification des utilisateurs (multi-tenant)
 */

require_once APP_PATH . '/models/User.php';
require_once APP_PATH . '/models/Restaurant.php';

class AuthController extends Controller {

    private const MAX_ATTEMPTS = 5;
    private const LOCKOUT_SECS = 900;

    public function loginForm(): void {
        if (isset($_SESSION['user'])) {
            $this->redirect('/');
        }
        $this->generateCsrf();
        $this->render('auth/login', [
            'error'    => $_SESSION['login_error'] ?? '',
            'app_name' => APP_NAME,
        ]);
        unset($_SESSION['login_error']);
    }

    public function login(): void {
        if (!$this->validateCsrf()) {
            $_SESSION['login_error'] = 'Requete invalide.';
            $this->redirect('/auth/login');
        }

        // SEC-19 : protection brute force par IP
        $ip       = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $lockKey  = 'bf_lock_'  . md5($ip);
        $countKey = 'bf_count_' . md5($ip);
        $timeKey  = 'bf_time_'  . md5($ip);

        if (!empty($_SESSION[$lockKey])) {
            $elapsed = time() - ($_SESSION[$timeKey] ?? 0);
            if ($elapsed < self::LOCKOUT_SECS) {
                $remaining = (int) ceil((self::LOCKOUT_SECS - $elapsed) / 60);
                $_SESSION['login_error'] = "Trop de tentatives. Reessayez dans {$remaining} minute(s).";
                $this->redirect('/auth/login');
            }
            unset($_SESSION[$lockKey], $_SESSION[$countKey], $_SESSION[$timeKey]);
        }

        $email    = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL) ?? '';
        $password = $_POST['password'] ?? '';

        if (!$email || !$password) {
            $_SESSION['login_error'] = 'Veuillez remplir tous les champs.';
            $this->redirect('/auth/login');
        }

        $userModel = new User();
        $user      = $userModel->authenticate($email, $password);

        if (!$user) {
            $_SESSION[$countKey] = ($_SESSION[$countKey] ?? 0) + 1;
            $_SESSION[$timeKey]  = time();
            if ($_SESSION[$countKey] >= self::MAX_ATTEMPTS) {
                $_SESSION[$lockKey] = true;
                $_SESSION['login_error'] = 'Trop de tentatives. Compte bloque pendant 15 minutes.';
            } else {
                $remaining = self::MAX_ATTEMPTS - $_SESSION[$countKey];
                $_SESSION['login_error'] = "Email ou mot de passe incorrect. ({$remaining} tentative(s) restante(s))";
            }
            $this->redirect('/auth/login');
        }

        // Verifier que le restaurant existe et est utilisable
        $restoModel = new Restaurant();
        $resto      = $restoModel->findByIdGlobal((int) $user['restaurant_id']);
        if (!$resto) {
            $_SESSION['login_error'] = 'Compte invalide (restaurant introuvable).';
            $this->redirect('/auth/login');
        }

        unset($_SESSION[$lockKey], $_SESSION[$countKey], $_SESSION[$timeKey]);
        session_regenerate_id(true);

        $_SESSION['user'] = [
            'id'            => (int) $user['id'],
            'restaurant_id' => (int) $user['restaurant_id'],
            'nom'           => $user['nom'],
            'email'         => $user['email'],
            'role'          => $user['role'],
        ];

        $this->redirect('/');
    }

    public function logout(): void {
        if (!$this->validateCsrf()) {
            $this->redirect('/');
        }
        session_destroy();
        $this->redirect('/auth/login');
    }
}
