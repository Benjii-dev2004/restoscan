<?php
/**
 * app/controllers/SuperAdminController.php
 * Centre de controle de RESTOSCAN - reserve au proprietaire
 */

require_once APP_PATH . '/models/SuperAdmin.php';
require_once APP_PATH . '/models/Restaurant.php';
require_once APP_PATH . '/models/User.php';
require_once APP_PATH . '/models/Setting.php';
require_once APP_PATH . '/models/SuperAdminLog.php';
require_once APP_PATH . '/models/LoginAttempt.php';

class SuperAdminController extends Controller {

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

        // Brute force persistant (BDD, scope 'superadmin' pour ne pas melanger avec users)
        $ip       = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $attempts = new LoginAttempt();

        $remainingSecs = $attempts->getLockoutRemaining($ip, 'superadmin');
        if ($remainingSecs > 0) {
            $rem = (int) ceil($remainingSecs / 60);
            $_SESSION['sa_login_error'] = "Trop de tentatives. Reessayez dans {$rem} minute(s).";
            $this->redirect('/superadmin/login');
        }

        $email    = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL) ?? '';
        $password = $_POST['password'] ?? '';

        $sa = (new SuperAdmin())->authenticate($email, $password);
        if (!$sa) {
            $count = $attempts->registerFailure($ip, 'superadmin');
            if ($count >= $attempts->maxAttempts()) {
                $_SESSION['sa_login_error'] = 'Compte bloque pendant 15 minutes.';
            } else {
                $_SESSION['sa_login_error'] = 'Email ou mot de passe incorrect.';
            }
            $this->redirect('/superadmin/login');
        }

        $attempts->resetForIp($ip, 'superadmin');
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
        (new SuperAdminLog())->log(
            (int) $_SESSION['superadmin']['id'],
            'create',
            $rid,
            "Cree restaurant \"{$nom}\" (admin: {$adminEmail}, formule: {$formule}, duree: {$duree} mois)"
        );
        $_SESSION['sa_flash_success'] = "Restaurant \"{$nom}\" cree. URL a partager au gerant : {$loginUrl} (admin: {$adminEmail})";
        $this->redirect('/superadmin/dashboard');
    }

    public function restaurantExtend(string $id): void {
        $this->requireSuperAdmin();
        if (!$this->validateCsrf()) $this->redirect('/superadmin/dashboard');

        $months = max(1, min(60, (int) ($_POST['months'] ?? 1)));
        (new Restaurant())->extendSubscription((int) $id, $months);
        (new SuperAdminLog())->log(
            (int) $_SESSION['superadmin']['id'],
            'extend',
            (int) $id,
            "Prolongation de {$months} mois"
        );
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
            (new SuperAdminLog())->log(
                (int) $_SESSION['superadmin']['id'],
                $newStatut === 'suspendu' ? 'suspend' : 'activate',
                (int) $id,
                "Statut change vers \"{$newStatut}\""
            );
            $_SESSION['sa_flash_success'] = "Statut bascule vers \"{$newStatut}\".";
        }
        $this->redirect('/superadmin/dashboard');
    }

    public function restaurantDelete(string $id): void {
        $this->requireSuperAdmin();
        if (!$this->validateCsrf()) $this->redirect('/superadmin/dashboard');
        $r = (new Restaurant())->findByIdGlobal((int) $id);
        $nom = $r['nom'] ?? '(inconnu)';
        (new Restaurant())->deleteById((int) $id);
        (new SuperAdminLog())->log(
            (int) $_SESSION['superadmin']['id'],
            'delete',
            (int) $id,
            "Suppression definitive de \"{$nom}\""
        );
        $_SESSION['sa_flash_success'] = 'Restaurant supprime.';
        $this->redirect('/superadmin/dashboard');
    }

    /** POST /superadmin/restaurant/impersonate/{id} */
    public function restaurantImpersonate(string $id): void {
        $this->requireSuperAdmin();
        if (!$this->validateCsrf()) $this->redirect('/superadmin/dashboard');

        $rid   = (int) $id;
        $resto = (new Restaurant())->findByIdGlobal($rid);
        if (!$resto) {
            $_SESSION['sa_flash_error'] = 'Restaurant introuvable.';
            $this->redirect('/superadmin/dashboard');
        }

        // Trouver le premier admin du restaurant
        $admin = (new User($rid))->findFirstAdmin();
        if (!$admin) {
            $_SESSION['sa_flash_error'] = 'Pas d admin dans ce restaurant.';
            $this->redirect('/superadmin/dashboard');
        }

        // Mode impersonation : on garde la session SA et on ouvre une session user
        $_SESSION['impersonating'] = true;
        $_SESSION['user'] = [
            'id'            => (int) $admin['id'],
            'restaurant_id' => $rid,
            'nom'           => $admin['nom'],
            'email'         => $admin['email'],
            'role'          => 'admin',
        ];

        (new SuperAdminLog())->log(
            (int) $_SESSION['superadmin']['id'],
            'impersonate',
            $rid,
            "Connexion en tant que admin \"{$admin['email']}\""
        );

        header('Location: ' . BASE_URL . '/r/' . $resto['slug'] . '/admin/dashboard');
        exit;
    }

    /** GET /superadmin/stop-impersonation - revenir au super-admin */
    public function stopImpersonation(): void {
        if (empty($_SESSION['impersonating']) || empty($_SESSION['superadmin'])) {
            $this->redirect('/superadmin/login');
        }
        unset($_SESSION['user'], $_SESSION['impersonating']);
        header('Location: ' . BASE_URL . '/superadmin/dashboard');
        exit;
    }

    /** GET /superadmin/logs */
    public function logs(): void {
        $this->requireSuperAdmin();
        $logModel = new SuperAdminLog();
        $this->render('superadmin/logs', [
            'logs' => $logModel->getRecent(200),
            'sa'   => $_SESSION['superadmin'],
        ], 'superadmin');
    }

    /** GET /superadmin/health - dashboard sante serveur */
    public function health(): void {
        $this->requireSuperAdmin();
        require_once ROOT_PATH . '/core/ErrorHandler.php';

        $health = [
            'db_ok'          => $this->checkDb(),
            'db_size_mb'     => $this->dbSizeMb(),
            'errors_today'   => ErrorHandler::todayCount(),
            'errors_recent'  => ErrorHandler::recentErrors(20),
            'cron_last'      => $this->cronLastRun(),
            'inactive_restos'=> $this->inactiveRestaurants(),
            'disk_logs_kb'   => $this->dirSizeKb(ROOT_PATH . '/logs'),
            'disk_qr_kb'     => $this->dirSizeKb(ROOT_PATH . '/qrcodes'),
            'disk_img_kb'    => $this->dirSizeKb(ROOT_PATH . '/public/img/menu'),
            'php_version'    => PHP_VERSION,
        ];

        $this->render('superadmin/health', [
            'h'  => $health,
            'sa' => $_SESSION['superadmin'],
        ], 'superadmin');
    }

    private function checkDb(): bool {
        try {
            $stmt = Database::getInstance()->query("SELECT 1");
            return $stmt && (int) $stmt->fetchColumn() === 1;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function dbSizeMb(): float {
        try {
            $row = Database::getInstance()->query(
                "SELECT SUM(data_length + index_length) AS sz
                 FROM information_schema.tables
                 WHERE table_schema = DATABASE()"
            )->fetch();
            return round(((int) ($row['sz'] ?? 0)) / 1024 / 1024, 2);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function cronLastRun(): ?string {
        $log = ROOT_PATH . '/logs/cron.log';
        if (!file_exists($log)) return null;
        $size = filesize($log);
        if ($size <= 0) return null;
        // Lire les derniers 1000 octets
        $h = @fopen($log, 'r');
        if (!$h) return null;
        fseek($h, max(0, $size - 1000));
        $tail = fread($h, 1000);
        fclose($h);
        if (preg_match_all('/\[([0-9\- :]+)\]/', $tail, $m)) {
            return end($m[1]);
        }
        return null;
    }

    private function inactiveRestaurants(): array {
        try {
            return Database::getInstance()->query(
                "SELECT r.id, r.nom, r.slug,
                        MAX(c.created_at) AS last_order
                 FROM restaurants r
                 LEFT JOIN commandes c ON c.restaurant_id = r.id
                 WHERE r.statut = 'actif'
                 GROUP BY r.id
                 HAVING last_order IS NULL
                     OR last_order < DATE_SUB(NOW(), INTERVAL 24 HOUR)
                 ORDER BY last_order ASC"
            )->fetchAll();
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function dirSizeKb(string $dir): int {
        if (!is_dir($dir)) return 0;
        $size = 0;
        foreach (glob($dir . '/*') ?: [] as $f) {
            if (is_file($f)) $size += filesize($f);
        }
        return (int) round($size / 1024);
    }

    // --- Helpers -------------------------------------------------------------

    private function requireSuperAdmin(): void {
        if (!isset($_SESSION['superadmin'])) {
            $this->redirect('/superadmin/login');
        }
    }
}
