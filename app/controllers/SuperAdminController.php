<?php
/**
 * app/controllers/SuperAdminController.php
 * Centre de controle de RESTOSCAN - reserve au proprietaire
 */

require_once APP_PATH . '/models/SuperAdmin.php';
require_once APP_PATH . '/models/Restaurant.php';
require_once APP_PATH . '/models/User.php';
require_once APP_PATH . '/models/Setting.php';

class SuperAdminController extends Controller {

    private const MAX_ATTEMPTS = 5;
    private const LOCKOUT_SECS = 900;

    // --- Auth ----------------------------------------------------------------

    public function loginForm(): void {
        if (isset($_SESSION['superadmin'])) {
            $this->redirect('/superadmin/dashboard');
        }
        $this->generateCsrf();
        $this->render('superadmin/login', [
            'error' => $_SESSION['sa_login_error'] ?? '',
        ]);
        unset($_SESSION['sa_login_error']);
    }

    public function login(): void {
        if (!$this->validateCsrf()) {
            $_SESSION['sa_login_error'] = 'Requete invalide.';
            $this->redirect('/superadmin/login');
        }

        $ip       = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $lockKey  = 'sa_bf_lock_'  . md5($ip);
        $countKey = 'sa_bf_count_' . md5($ip);
        $timeKey  = 'sa_bf_time_'  . md5($ip);

        if (!empty($_SESSION[$lockKey])) {
            $elapsed = time() - ($_SESSION[$timeKey] ?? 0);
            if ($elapsed < self::LOCKOUT_SECS) {
                $rem = (int) ceil((self::LOCKOUT_SECS - $elapsed) / 60);
                $_SESSION['sa_login_error'] = "Trop de tentatives. Reessayez dans {$rem} minute(s).";
                $this->redirect('/superadmin/login');
            }
            unset($_SESSION[$lockKey], $_SESSION[$countKey], $_SESSION[$timeKey]);
        }

        $email    = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL) ?? '';
        $password = $_POST['password'] ?? '';

        $sa = (new SuperAdmin())->authenticate($email, $password);
        if (!$sa) {
            $_SESSION[$countKey] = ($_SESSION[$countKey] ?? 0) + 1;
            $_SESSION[$timeKey]  = time();
            if ($_SESSION[$countKey] >= self::MAX_ATTEMPTS) {
                $_SESSION[$lockKey] = true;
                $_SESSION['sa_login_error'] = 'Compte bloque pendant 15 minutes.';
            } else {
                $_SESSION['sa_login_error'] = 'Email ou mot de passe incorrect.';
            }
            $this->redirect('/superadmin/login');
        }

        unset($_SESSION[$lockKey], $_SESSION[$countKey], $_SESSION[$timeKey]);
        session_regenerate_id(true);

        $_SESSION['superadmin'] = [
            'id'    => (int) $sa['id'],
            'nom'   => $sa['nom'],
            'email' => $sa['email'],
        ];
        $this->redirect('/superadmin/dashboard');
    }

    public function logout(): void {
        if (!$this->validateCsrf()) {
            $this->redirect('/superadmin/dashboard');
        }
        unset($_SESSION['superadmin']);
        $this->redirect('/superadmin/login');
    }

    // --- Dashboard -----------------------------------------------------------

    public function dashboard(): void {
        $this->requireSuperAdmin();
        $restoModel = new Restaurant();
        $this->generateCsrf();
        $this->render('superadmin/dashboard', [
            'stats'       => $restoModel->globalStats(),
            'restaurants' => $restoModel->listAllWithStats(),
            'sa'          => $_SESSION['superadmin'],
        ], 'superadmin');
    }

    // --- Restaurants CRUD ----------------------------------------------------

    public function restaurantNewForm(): void {
        $this->requireSuperAdmin();
        $this->generateCsrf();
        $this->render('superadmin/restaurant_new', [
            'sa' => $_SESSION['superadmin'],
        ], 'superadmin');
    }

    public function restaurantCreate(): void {
        $this->requireSuperAdmin();
        if (!$this->validateCsrf()) $this->redirect('/superadmin/dashboard');

        $nom         = $this->sanitize($_POST['nom']         ?? '');
        $email       = filter_input(INPUT_POST, 'gerant_email', FILTER_SANITIZE_EMAIL) ?? '';
        $telephone   = $this->sanitize($_POST['gerant_telephone'] ?? '');
        $formule     = $_POST['formule']  ?? 'starter';
        $duree       = (int) ($_POST['duree_mois'] ?? 1);
        $adminNom    = $this->sanitize($_POST['admin_nom']    ?? '');
        $adminEmail  = filter_input(INPUT_POST, 'admin_email', FILTER_SANITIZE_EMAIL) ?? '';
        $adminPass   = $_POST['admin_password'] ?? '';

        if (!$nom || !$adminNom || !$adminEmail || strlen($adminPass) < 8) {
            $_SESSION['sa_flash_error'] = 'Champs invalides (nom requis, mot de passe admin >= 8 caracteres).';
            $this->redirect('/superadmin/restaurant/new');
        }
        if (!in_array($formule, ['starter','pro','premium'], true)) $formule = 'starter';
        if ($duree < 1)  $duree = 1;
        if ($duree > 60) $duree = 60;

        $restoModel = new Restaurant();
        // Verifier email admin pas deja pris
        if ((new User())->findByEmailGlobal($adminEmail)) {
            $_SESSION['sa_flash_error'] = 'Cet email admin est deja utilise.';
            $this->redirect('/superadmin/restaurant/new');
        }

        $slug = $restoModel->generateUniqueSlug($nom);
        $now  = date('Y-m-d H:i:s');
        $fin  = date('Y-m-d H:i:s', strtotime("+{$duree} months"));

        $rid = $restoModel->create([
            'nom'              => $nom,
            'slug'             => $slug,
            'abonnement_debut' => $now,
            'abonnement_fin'   => $fin,
            'statut'           => 'actif',
            'formule'          => $formule,
            'gerant_email'     => $email ?: null,
            'gerant_telephone' => $telephone ?: null,
        ]);

        // Seed des settings par defaut
        (new Setting())->seedDefaults($rid, $nom);

        // Creer l admin local
        (new User())->createForRestaurant($rid, $adminNom, $adminEmail, $adminPass, 'admin');

        $loginUrl = BASE_URL . '/r/' . $slug . '/auth/login';
        $_SESSION['sa_flash_success'] = "Restaurant \"{$nom}\" cree. URL a partager au gerant : {$loginUrl} (admin: {$adminEmail})";
        $this->redirect('/superadmin/dashboard');
    }

    public function restaurantExtend(string $id): void {
        $this->requireSuperAdmin();
        if (!$this->validateCsrf()) $this->redirect('/superadmin/dashboard');

        $months = max(1, min(60, (int) ($_POST['months'] ?? 1)));
        (new Restaurant())->extendSubscription((int) $id, $months);
        $_SESSION['sa_flash_success'] = "Abonnement prolonge de {$months} mois.";
        $this->redirect('/superadmin/dashboard');
    }

    public function restaurantToggle(string $id): void {
        $this->requireSuperAdmin();
        if (!$this->validateCsrf()) $this->redirect('/superadmin/dashboard');

        $restoModel = new Restaurant();
        $r = $restoModel->findByIdGlobal((int) $id);
        if ($r) {
            $newStatut = $r['statut'] === 'actif' ? 'suspendu' : 'actif';
            $restoModel->setStatut((int) $id, $newStatut);
            $_SESSION['sa_flash_success'] = "Statut bascule vers \"{$newStatut}\".";
        }
        $this->redirect('/superadmin/dashboard');
    }

    public function restaurantDelete(string $id): void {
        $this->requireSuperAdmin();
        if (!$this->validateCsrf()) $this->redirect('/superadmin/dashboard');
        (new Restaurant())->deleteById((int) $id);
        $_SESSION['sa_flash_success'] = 'Restaurant supprime.';
        $this->redirect('/superadmin/dashboard');
    }

    // --- Helpers -------------------------------------------------------------

    private function requireSuperAdmin(): void {
        if (!isset($_SESSION['superadmin'])) {
            $this->redirect('/superadmin/login');
        }
    }
}
