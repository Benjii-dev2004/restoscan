<?php
/**
 * app/controllers/AuthController.php
 * Authentification slug-aware (chaque restaurant a son URL de login)
 */

require_once APP_PATH . '/models/User.php';
require_once APP_PATH . '/models/Restaurant.php';
require_once APP_PATH . '/models/Setting.php';

class AuthController extends Controller {

    private const MAX_ATTEMPTS = 5;
    private const LOCKOUT_SECS = 900;

    public function loginForm(): void {
        // Refuser l acces sans contexte restaurant
        if (!Context::hasContext()) {
            http_response_code(404);
            require_once APP_PATH . '/views/errors/404.php';
            exit;
        }

        if (isset($_SESSION['user'])) {
            $this->redirect('/');
        }
        $this->generateCsrf();

        // Branding : nom + logo du restaurant
        $settings = (new Setting(Context::id()))->getAll();

        $this->render('auth/login', [
            'error'        => $_SESSION['login_error'] ?? '',
            'app_name'     => Context::name(),
            'logo'         => $settings['logo']               ?? '',
            'slogan'       => $settings['slogan']             ?? '',
            'primary'      => $settings['couleur_principale'] ?? '#e85d04',
            'restaurant'   => Context::restaurant(),
        ]);
        unset($_SESSION['login_error']);
    }

    public function login(): void {
        if (!Context::hasContext()) {
            http_response_code(404);
            exit;
        }
        if (!$this->validateCsrf()) {
            $_SESSION['login_error'] = 'Requete invalide.';
            $this->redirect('/auth/login');
        }

        // Brute force par IP
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

        // Verifier que l user appartient bien au restaurant du slug
        if ((int) $user['restaurant_id'] !== Context::id()) {
            $_SESSION['login_error'] = 'Cet identifiant ne correspond pas a ce restaurant.';
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
        $slug = Context::slug();
        session_destroy();
        if ($slug) {
            header('Location: ' . BASE_URL . '/r/' . $slug . '/auth/login');
        } else {
            header('Location: ' . BASE_URL . '/');
        }
        exit;
    }
}
