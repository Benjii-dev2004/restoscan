<?php
/**
 * app/controllers/AuthController.php
 * Authentification slug-aware (chaque restaurant a son URL de login)
 */

require_once APP_PATH . '/models/User.php';
require_once APP_PATH . '/models/Restaurant.php';
require_once APP_PATH . '/models/Setting.php';
require_once APP_PATH . '/models/LoginAttempt.php';

class AuthController extends Controller {

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

        // Brute force PERSISTANT (BDD, pas session : resiste au cookie clearing)
        $ip       = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $attempts = new LoginAttempt();

        $remainingSecs = $attempts->getLockoutRemaining($ip, 'user');
        if ($remainingSecs > 0) {
            $remainingMin = (int) ceil($remainingSecs / 60);
            $_SESSION['login_error'] = "Trop de tentatives. Reessayez dans {$remainingMin} minute(s).";
            $this->redirect('/auth/login');
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
            $count = $attempts->registerFailure($ip, 'user');
            if ($count >= $attempts->maxAttempts()) {
                $_SESSION['login_error'] = 'Trop de tentatives. Compte bloque pendant 15 minutes.';
            } else {
                $remaining = $attempts->maxAttempts() - $count;
                $_SESSION['login_error'] = "Email ou mot de passe incorrect. ({$remaining} tentative(s) restante(s))";
            }
            $this->redirect('/auth/login');
        }

        // Verifier que l user appartient bien au restaurant du slug
        if ((int) $user['restaurant_id'] !== Context::id()) {
            $_SESSION['login_error'] = 'Cet identifiant ne correspond pas a ce restaurant.';
            $this->redirect('/auth/login');
        }

        $attempts->resetForIp($ip, 'user');
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
        // Si on est en mode impersonation : retour au super-admin sans tuer la session SA
        if (!empty($_SESSION['impersonating']) && !empty($_SESSION['superadmin'])) {
            unset($_SESSION['user'], $_SESSION['impersonating']);
            header('Location: ' . BASE_URL . '/superadmin/dashboard');
            exit;
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
