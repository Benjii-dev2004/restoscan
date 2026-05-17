<?php
/**
 * app/controllers/AdminController.php
 * Controleur MVC - back-office administration (scope restaurant)
 */

require_once APP_PATH . '/models/Table.php';
require_once APP_PATH . '/models/Category.php';
require_once APP_PATH . '/models/MenuItem.php';
require_once APP_PATH . '/models/Order.php';
require_once APP_PATH . '/models/User.php';
require_once APP_PATH . '/models/Setting.php';

class AdminController extends Controller {

    // --- Dashboard -----------------------------------------------------------

    public function dashboard(): void {
        $this->requireAuth('admin');
        $rid = $this->currentRestaurantId();

        $orderModel  = new Order($rid);
        $menuModel   = new MenuItem($rid);
        $tableModel  = new Table($rid);

        $tableModel->syncStatuts();

        $stats     = $orderModel->getTodayStats();
        $topItems  = $menuModel->getTopItems(3);
        $tables    = $tableModel->getAllWithStatus();

        $this->render('admin/dashboard', [
            'stats'    => $stats,
            'topItems' => $topItems,
            'tables'   => $tables,
            'user'     => $_SESSION['user'],
            'app_name' => $this->getAppName($rid),
        ], 'admin');
    }

    // --- Menu ----------------------------------------------------------------

    public function menuList(): void {
        $this->requireAuth('admin');
        $rid = $this->currentRestaurantId();

        $menuModel     = new MenuItem($rid);
        $categoryModel = new Category($rid);

        $this->generateCsrf();
        $this->render('admin/menu', [
            'items'      => $menuModel->getAllForAdmin(),
            'categories' => $categoryModel->findAll(),
            'user'       => $_SESSION['user'],
            'app_name'   => $this->getAppName($rid),
        ], 'admin');
    }

    public function menuAdd(): void {
        $this->requireAuth('admin');
        if (!$this->validateCsrf()) $this->redirect('/admin/menu');
        $rid = $this->currentRestaurantId();

        $data = [
            'categorie_id'      => (int) ($_POST['categorie_id'] ?? 0),
            'nom'               => $this->sanitize($_POST['nom'] ?? ''),
            'description'       => $this->sanitize($_POST['description'] ?? ''),
            'prix'              => (float) ($_POST['prix'] ?? 0),
            'temps_preparation' => (int) ($_POST['temps_preparation'] ?? 15),
            'image'             => '',
        ];

        if (!empty($_FILES['image']['tmp_name'])) {
            $data['image'] = $this->handleImageUpload($_FILES['image']);
        }

        if ($data['nom'] && $data['prix'] > 0 && $data['categorie_id']) {
            $menuModel = new MenuItem($rid);
            $menuModel->create($data);
        }
        $this->redirect('/admin/menu');
    }

    public function menuEdit(string $id): void {
        $this->requireAuth('admin');
        if (!$this->validateCsrf()) $this->redirect('/admin/menu');
        $rid = $this->currentRestaurantId();

        $menuModel = new MenuItem($rid);
        $item      = $menuModel->findById((int) $id);
        if (!$item) $this->redirect('/admin/menu');

        $data = [
            'categorie_id'      => (int) ($_POST['categorie_id'] ?? $item['categorie_id']),
            'nom'               => $this->sanitize($_POST['nom'] ?? $item['nom']),
            'description'       => $this->sanitize($_POST['description'] ?? $item['description']),
            'prix'              => (float) ($_POST['prix'] ?? $item['prix']),
            'temps_preparation' => (int) ($_POST['temps_preparation'] ?? $item['temps_preparation']),
            'disponible'        => (int) ($_POST['disponible'] ?? $item['disponible']),
            'image'             => $item['image'],
        ];

        if (!empty($_FILES['image']['tmp_name'])) {
            $data['image'] = $this->handleImageUpload($_FILES['image']);
        }

        $menuModel->update((int) $id, $data);
        $this->redirect('/admin/menu');
    }

    public function menuDelete(string $id): void {
        $this->requireAuth('admin');
        if (!$this->validateCsrf()) $this->redirect('/admin/menu');
        $menuModel = new MenuItem($this->currentRestaurantId());
        $menuModel->delete((int) $id);
        $this->redirect('/admin/menu');
    }

    public function menuToggle(string $id): void {
        $this->requireAuth('admin');
        if (!$this->validateCsrfAjax()) {
            $this->json(['error' => 'Token CSRF invalide.'], 403);
        }
        $menuModel = new MenuItem($this->currentRestaurantId());
        $menuModel->toggleAvailability((int) $id);
        $this->json(['success' => true]);
    }

    // --- Tables --------------------------------------------------------------

    public function tablesList(): void {
        $this->requireAuth('admin');
        $rid = $this->currentRestaurantId();
        $tableModel = new Table($rid);
        $this->generateCsrf();

        $currentHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $onLocalhost = in_array(strtolower($currentHost), ['localhost', '127.0.0.1', '::1'], true);
        $lanIp       = $onLocalhost ? $this->resolveQrHost($rid) : null;
        if ($lanIp === $currentHost) $lanIp = null;

        $this->render('admin/tables', [
            'tables'      => $tableModel->getAllWithStatus(),
            'user'        => $_SESSION['user'],
            'app_name'    => $this->getAppName($rid),
            'onLocalhost' => $onLocalhost,
            'lanIp'       => $lanIp,
        ], 'admin');
    }

    public function tablesQrCacheClear(): void {
        $this->requireAuth('admin');
        if (!$this->validateCsrf()) $this->redirect('/admin/tables');
        foreach (glob(QRCODE_PATH . '/*.png') ?: [] as $file) {
            @unlink($file);
        }
        $this->redirect('/admin/tables');
    }

    public function tablesAdd(): void {
        $this->requireAuth('admin');
        if (!$this->validateCsrf()) $this->redirect('/admin/tables');

        $numero   = (int) ($_POST['numero']   ?? 0);
        $capacite = (int) ($_POST['capacite'] ?? 4);

        if ($numero > 0) {
            $tableModel = new Table($this->currentRestaurantId());
            $tableModel->create($numero, $capacite);
        }
        $this->redirect('/admin/tables');
    }

    public function tablesDelete(string $id): void {
        $this->requireAuth('admin');
        if (!$this->validateCsrf()) $this->redirect('/admin/tables');
        $tableModel = new Table($this->currentRestaurantId());
        if ($tableModel->hasActiveOrders((int) $id)) {
            $_SESSION['flash_error'] = 'Impossible de supprimer une table avec des commandes actives.';
            $this->redirect('/admin/tables');
        }
        $tableModel->delete((int) $id);
        $this->redirect('/admin/tables');
    }

    public function tablesQrcode(string $id): void {
        $this->requireAuth('admin');
        $rid = $this->currentRestaurantId();

        $tableModel = new Table($rid);
        $table      = $tableModel->findById((int) $id);
        if (!$table) $this->redirect('/admin/tables');

        $url = BASE_URL . '/menu/' . $table['qr_token'];
        $filename = 'table_' . $rid . '_' . $table['numero'] . '_qr.png';
        $filepath = QRCODE_PATH . '/' . $filename;
        $this->generateQrCode($url, $filepath);

        header('Content-Type: image/png');
        header('Content-Disposition: attachment; filename="qrcode_table_' . $table['numero'] . '.png"');
        readfile($filepath);
        exit;
    }

    private function resolveQrHost(int $rid): string {
        $saved = (new Setting($rid))->get('ip_locale', '');
        if ($saved && filter_var($saved, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return $saved;
        }

        $currentHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $onLocal     = in_array(strtolower($currentHost), ['localhost', '127.0.0.1', '::1'], true);
        if (!$onLocal) return $currentHost;

        if (function_exists('socket_create')) {
            $sock = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
            if ($sock !== false) {
                @socket_connect($sock, '8.8.8.8', 80);
                @socket_getsockname($sock, $detected);
                @socket_close($sock);
                if (!empty($detected) && $detected !== '0.0.0.0' && $detected !== '127.0.0.1') {
                    return $detected;
                }
            }
        }
        return $currentHost;
    }

    // --- Commandes -----------------------------------------------------------

    public function ordersList(): void {
        $this->requireAuth('admin');
        $rid = $this->currentRestaurantId();

        $orderModel = new Order($rid);
        $tableModel = new Table($rid);

        $filters = [
            'statut'   => $_GET['statut']   ?? '',
            'date'     => $_GET['date']     ?? '',
            'table_id' => (int) ($_GET['table_id'] ?? 0),
        ];

        $this->render('admin/orders', [
            'orders'   => $orderModel->getHistory($filters),
            'tables'   => $tableModel->findAll('numero ASC'),
            'filters'  => $filters,
            'user'     => $_SESSION['user'],
            'app_name' => $this->getAppName($rid),
        ], 'admin');
    }

    // --- Utilisateurs --------------------------------------------------------

    public function usersList(): void {
        $this->requireAuth('admin');
        $rid = $this->currentRestaurantId();
        $userModel = new User($rid);
        $this->generateCsrf();
        $this->render('admin/users', [
            'users'    => $userModel->findAll(),
            'user'     => $_SESSION['user'],
            'app_name' => $this->getAppName($rid),
        ], 'admin');
    }

    public function usersAdd(): void {
        $this->requireAuth('admin');
        if (!$this->validateCsrf()) $this->redirect('/admin/users');

        $nom      = $this->sanitize($_POST['nom']      ?? '');
        $email    = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL) ?? '';
        $password = $_POST['password'] ?? '';
        $role     = $_POST['role']     ?? '';

        $allowed = ['admin', 'cuisine', 'serveur'];
        if ($nom && $email && strlen($password) >= 8 && in_array($role, $allowed, true)) {
            $userModel = new User($this->currentRestaurantId());
            $userModel->create($nom, $email, $password, $role);
        }
        $this->redirect('/admin/users');
    }

    public function usersDelete(string $id): void {
        $this->requireAuth('admin');
        if (!$this->validateCsrf()) $this->redirect('/admin/users');
        if ((int) $id === (int) $_SESSION['user']['id']) {
            $this->redirect('/admin/users');
        }
        $userModel = new User($this->currentRestaurantId());
        $userModel->delete((int) $id);
        $this->redirect('/admin/users');
    }

    // --- Categories ----------------------------------------------------------

    public function categoriesList(): void {
        $this->requireAuth('admin');
        $rid = $this->currentRestaurantId();
        $categoryModel = new Category($rid);
        $this->generateCsrf();
        $this->render('admin/categories', [
            'categories' => $categoryModel->getAllWithCount(),
            'user'       => $_SESSION['user'],
            'app_name'   => $this->getAppName($rid),
        ], 'admin');
    }

    public function categoriesAdd(): void {
        $this->requireAuth('admin');
        if (!$this->validateCsrf()) $this->redirect('/admin/categories');

        $nom   = $this->sanitize($_POST['nom']   ?? '');
        $ordre = (int) ($_POST['ordre']           ?? 0);
        $icone = $this->sanitize($_POST['icone']  ?? 'utensils');

        if ($nom) {
            $categoryModel = new Category($this->currentRestaurantId());
            $categoryModel->create($nom, $ordre, $icone);
        }
        $this->redirect('/admin/categories');
    }

    public function categoriesDelete(string $id): void {
        $this->requireAuth('admin');
        if (!$this->validateCsrf()) $this->redirect('/admin/categories');
        $categoryModel = new Category($this->currentRestaurantId());
        if ($categoryModel->hasItems((int) $id)) {
            $_SESSION['flash_error'] = 'Impossible de supprimer une categorie qui contient des plats.';
            $this->redirect('/admin/categories');
        }
        $categoryModel->delete((int) $id);
        $this->redirect('/admin/categories');
    }

    // --- Parametres ----------------------------------------------------------

    public function settingsForm(): void {
        $this->requireAuth('admin');
        $rid = $this->currentRestaurantId();
        $this->generateCsrf();
        $settingModel = new Setting($rid);
        $this->render('admin/settings', [
            'settings' => $settingModel->getAll(),
            'success'  => $_SESSION['settings_saved'] ?? false,
            'user'     => $_SESSION['user'],
            'app_name' => $this->getAppName($rid),
        ], 'admin');
        unset($_SESSION['settings_saved']);
    }

    public function settingsDetectIp(): void {
        $this->requireAuth('admin');
        $ip = null;

        if (function_exists('socket_create')) {
            $sock = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
            if ($sock !== false) {
                @socket_connect($sock, '8.8.8.8', 80);
                @socket_getsockname($sock, $addr);
                @socket_close($sock);
                if (!empty($addr) && $addr !== '0.0.0.0' && $addr !== '127.0.0.1') {
                    $ip = $addr;
                }
            }
        }

        if (!$ip) {
            $resolved = gethostbyname(gethostname());
            if ($resolved && $resolved !== gethostname() && $resolved !== '127.0.0.1') {
                $ip = $resolved;
            }
        }

        $this->json(['ip' => $ip]);
    }

    public function settingsSave(): void {
        $this->requireAuth('admin');
        if (!$this->validateCsrf()) $this->redirect('/admin/settings');
        $rid = $this->currentRestaurantId();

        $settingModel = new Setting($rid);
        $settingModel->set('nom_restaurant',     $this->sanitize($_POST['nom_restaurant'] ?? ''));
        $settingModel->set('slogan',             $this->sanitize($_POST['slogan'] ?? ''));
        $settingModel->set('couleur_principale', $this->sanitize($_POST['couleur_principale'] ?? '#e85d04'));
        $settingModel->set('devise',             $this->sanitize($_POST['devise'] ?? 'FCFA'));

        $ipLocale = trim($_POST['ip_locale'] ?? '');
        if (filter_var($ipLocale, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $settingModel->set('ip_locale', $ipLocale);
        } elseif ($ipLocale === '') {
            $settingModel->set('ip_locale', '');
        }

        if (!empty($_FILES['logo']['tmp_name'])) {
            $path = $this->handleImageUpload($_FILES['logo']);
            if ($path) $settingModel->set('logo', $path);
        }

        $_SESSION['settings_saved'] = true;
        $this->redirect('/admin/settings');
    }

    // --- Helpers -------------------------------------------------------------

    private array $restaurantNames = [];

    private function getAppName(int $rid): string {
        if (!isset($this->restaurantNames[$rid])) {
            $this->restaurantNames[$rid] = (new Setting($rid))->get('nom_restaurant', APP_NAME);
        }
        return $this->restaurantNames[$rid];
    }

    private function handleImageUpload(array $file): string {
        $allowedMime = ['image/jpeg', 'image/png', 'image/webp'];
        $allowedExt  = ['jpg', 'jpeg', 'png', 'webp'];
        $maxSize     = 2 * 1024 * 1024;
        $uploadDir   = PUBLIC_PATH . '/img/menu/';

        if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] > $maxSize) return '';

        $finfo    = finfo_open(FILEINFO_MIME_TYPE);
        $realMime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (!in_array($realMime, $allowedMime, true)) return '';

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt, true)) return '';

        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $filename = bin2hex(random_bytes(8)) . '.' . $ext;
        $dest     = $uploadDir . $filename;

        if (move_uploaded_file($file['tmp_name'], $dest)) {
            return 'img/menu/' . $filename;
        }
        return '';
    }

    private function generateQrCode(string $url, string $filepath): void {
        $vendorPath = ROOT_PATH . '/vendor/autoload.php';
        if (file_exists($vendorPath)) {
            require_once $vendorPath;
            if (class_exists('Endroid\\QrCode\\QrCode')) {
                $qrCode = new \Endroid\QrCode\QrCode($url);
                $qrCode->setSize(400);
                $qrCode->setMargin(20);
                $writer = new \Endroid\QrCode\Writer\PngWriter();
                $result = $writer->write($qrCode);
                $result->saveToFile($filepath);
                return;
            }
        }

        $apiUrl  = 'https://api.qrserver.com/v1/create-qr-code/?size=400x400&data=' . urlencode($url);
        $context = stream_context_create(['http' => ['timeout' => 10]]);
        $img     = @file_get_contents($apiUrl, false, $context);
        if ($img) file_put_contents($filepath, $img);
    }
}
