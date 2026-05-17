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
            'statut'     => $_GET['statut']     ?? '',
            'date_start' => $_GET['date_start'] ?? '',
            'date_end'   => $_GET['date_end']   ?? '',
            'table_id'   => (int) ($_GET['table_id'] ?? 0),
        ];
        $page = max(1, (int) ($_GET['page'] ?? 1));

        $result = $orderModel->getHistoryPaginated($filters, $page, 50);

        $this->render('admin/orders', [
            'orders'    => $result['orders'],
            'total'     => $result['total'],
            'page'      => $result['page'],
            'pages'     => $result['pages'],
            'tables'    => $tableModel->findAll('numero ASC'),
            'filters'   => $filters,
            'user'      => $_SESSION['user'],
            'app_name'  => $this->getAppName($rid),
        ], 'admin');
    }

    // --- Rapports / Analytics ------------------------------------------------

    public function reports(): void {
        $this->requireAuth('admin');
        $rid = $this->currentRestaurantId();

        // Periode : presets + custom
        $preset = $_GET['preset'] ?? 'month';
        [$start, $end] = $this->resolveDatePreset($preset, $_GET['from'] ?? '', $_GET['to'] ?? '');

        // Determiner le grouping optimal selon la duree
        $duration = strtotime($end) - strtotime($start);
        $grouping = match(true) {
            $duration <= 60 * 86400        => 'day',    // < 60 jours
            $duration <= 730 * 86400       => 'month',  // < 2 ans
            default                        => 'year',
        };

        $orderModel = new Order($rid);
        $stats     = $orderModel->getStatsForPeriod($start, $end);
        $evolution = $orderModel->getRevenueByBucket($start, $end, $grouping);
        $topDishes = $orderModel->getTopDishesForPeriod($start, $end, 10);
        $hourly    = $orderModel->getHourlyDistribution($start, $end);
        $byTable   = $orderModel->getStatsByTable($start, $end);

        $this->render('admin/reports', [
            'stats'     => $stats,
            'evolution' => $evolution,
            'topDishes' => $topDishes,
            'hourly'    => $hourly,
            'byTable'   => $byTable,
            'grouping'  => $grouping,
            'preset'    => $preset,
            'start'     => $start,
            'end'       => $end,
            'user'      => $_SESSION['user'],
            'app_name'  => $this->getAppName($rid),
        ], 'admin');
    }

    /** GET /admin/reports/export - telechargement CSV */
    public function reportsExport(): void {
        $this->requireAuth('admin');
        $rid = $this->currentRestaurantId();

        $preset = $_GET['preset'] ?? 'month';
        [$start, $end] = $this->resolveDatePreset($preset, $_GET['from'] ?? '', $_GET['to'] ?? '');

        $orderModel = new Order($rid);
        $orders = $orderModel->getOrdersForExport($start, $end);

        $filename = 'rapport_' . substr($start, 0, 10) . '_au_' . substr($end, 0, 10) . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store');

        $out = fopen('php://output', 'w');
        // BOM UTF-8 pour Excel
        fwrite($out, "\xEF\xBB\xBF");
        // Entetes
        fputcsv($out, ['ID', 'Date', 'Table', 'Statut', 'Total', 'Notes', 'Articles'], ';');
        foreach ($orders as $o) {
            fputcsv($out, [
                $o['id'],
                $o['created_at'],
                $o['table_numero'],
                $o['statut'],
                $o['total'],
                $o['notes'] ?? '',
                $o['items'] ?? '',
            ], ';');
        }
        fclose($out);
        exit;
    }

    /**
     * Resoudre un preset de periode en [start, end] datetimes MySQL.
     * Presets : today, week, month, last_month, q3m, year, last_year, 5years, custom
     */
    private function resolveDatePreset(string $preset, string $from = '', string $to = ''): array {
        $now = time();
        switch ($preset) {
            case 'today':
                $start = date('Y-m-d 00:00:00');
                $end   = date('Y-m-d 23:59:59');
                break;
            case 'week':
                $start = date('Y-m-d 00:00:00', strtotime('-6 days'));
                $end   = date('Y-m-d 23:59:59');
                break;
            case 'last_month':
                $start = date('Y-m-01 00:00:00', strtotime('first day of last month'));
                $end   = date('Y-m-t 23:59:59',  strtotime('last day of last month'));
                break;
            case 'q3m':
                $start = date('Y-m-d 00:00:00', strtotime('-3 months'));
                $end   = date('Y-m-d 23:59:59');
                break;
            case 'year':
                $start = date('Y-01-01 00:00:00');
                $end   = date('Y-12-31 23:59:59');
                break;
            case 'last_year':
                $y = (int) date('Y') - 1;
                $start = "{$y}-01-01 00:00:00";
                $end   = "{$y}-12-31 23:59:59";
                break;
            case '5years':
                $y = (int) date('Y') - 4;
                $start = "{$y}-01-01 00:00:00";
                $end   = date('Y-12-31 23:59:59');
                break;
            case 'custom':
                $start = ($from && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from))
                    ? $from . ' 00:00:00'
                    : date('Y-m-01 00:00:00');
                $end   = ($to && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))
                    ? $to . ' 23:59:59'
                    : date('Y-m-d 23:59:59');
                break;
            case 'month':
            default:
                $start = date('Y-m-01 00:00:00');
                $end   = date('Y-m-d 23:59:59');
        }
        return [$start, $end];
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
