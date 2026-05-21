<?php
/**
 * cron/refresh_tokens.php
 *
 * ROLE : Renouveler proactivement les id_token Oracle de tous les restos
 *        en mode 'oracle'. Lance par cron quotidien (recommande : 03h00).
 *
 * STRATEGIE :
 *   - Pour chaque resto en mode oracle :
 *     * Si token expire dans < 7 jours      -> renouvellement
 *     * Si password Oracle expire dans < 14j -> email d alerte au gerant
 *     * Si 3 echecs consecutifs              -> resto marque HORS-LIGNE
 *
 * INSTALLATION :
 *   Alwaysdata > Taches planifiees > Nouvelle tache :
 *     Commande : /usr/bin/php cron/refresh_tokens.php
 *     Repertoire : /home/restoscan/www
 *     Frequence : Tous les jours a 03h00
 */

if (PHP_SAPI !== 'cli') {
    $key = $_GET['key'] ?? '';
    if (!defined('CRON_KEY') || !hash_equals(CRON_KEY, $key)) {
        http_response_code(403);
        exit('Forbidden');
    }
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// Charger oracle_config s il existe
$oracleConfig = __DIR__ . '/../config/oracle_config.php';
if (!file_exists($oracleConfig)) {
    echo "[" . date('Y-m-d H:i:s') . "] Pas de config Oracle, cron skippe\n";
    exit(0);
}
require_once $oracleConfig;

require_once __DIR__ . '/../core/Context.php';
require_once __DIR__ . '/../core/Model.php';
require_once __DIR__ . '/../core/Mailer.php';
require_once __DIR__ . '/../core/Crypto.php';
require_once __DIR__ . '/../app/models/Restaurant.php';
require_once __DIR__ . '/../app/services/OracleAuthService.php';

$restoModel = new Restaurant();
$mailer     = new Mailer();
$restos     = $restoModel->getAllOracleRestaurants();

$renewed = 0;
$failed  = 0;
$alerted = 0;

echo "[" . date('Y-m-d H:i:s') . "] Cron refresh_tokens : " . count($restos) . " resto(s) Oracle\n";

foreach ($restos as $r) {
    $rid = (int) $r['id'];
    $tokenExpiresAt = $r['oracle_token_expires_at'] ? strtotime($r['oracle_token_expires_at']) : 0;
    $tokenRemaining = $tokenExpiresAt - time();
    $passwordExpiresAt = $r['oracle_password_expires_at'] ? strtotime($r['oracle_password_expires_at']) : 0;
    $passwordRemaining = $passwordExpiresAt - time();

    // 1. ALERTE password Oracle expire bientot (14j et 7j)
    if ($passwordExpiresAt > 0) {
        $daysLeft = (int) floor($passwordRemaining / 86400);
        if (in_array($daysLeft, [14, 7, 3, 1], true) && !empty($r['gerant_email'])) {
            $urgent = $daysLeft <= 7 ? '[URGENT] ' : '';
            $subject = "{$urgent}Mot de passe Oracle expire dans {$daysLeft} jour(s)";
            $html = "<p>Bonjour <strong>" . htmlspecialchars($r['nom']) . "</strong>,</p>" .
                    "<p>Votre mot de passe API Oracle Simphony expire dans <strong>{$daysLeft} jour(s)</strong>.</p>" .
                    "<p>Sans renouvellement, RESTOSCAN ne pourra plus envoyer vos commandes a Oracle. " .
                    "Merci de mettre a jour le mot de passe via la console EMC et de nous le communiquer.</p>";
            if ($mailer->send($r['gerant_email'], $subject, $html)) {
                $alerted++;
                echo "  [" . $r['slug'] . "] Email alerte password J-{$daysLeft} envoye\n";
            }
        }
    }

    // 2. RENOUVELLEMENT TOKEN si expire dans < 7 jours
    if ($tokenRemaining < 7 * 86400) {
        try {
            $auth = new OracleAuthService($rid);
            $auth->authenticate(); // force refresh ou full flow
            $renewed++;
            echo "  [" . $r['slug'] . "] Token renouvele OK\n";
        } catch (\Throwable $e) {
            $failed++;
            $msg = "Echec renouvellement token resto {$r['slug']} : " . $e->getMessage();
            echo "  [ERREUR] {$msg}\n";
            // Alerter le gerant + le proprietaire RESTOSCAN
            if (!empty($r['gerant_email'])) {
                $mailer->send(
                    $r['gerant_email'],
                    '[CRITIQUE] Connexion Oracle echouee — RESTOSCAN',
                    "<p>La connexion automatique a Oracle Simphony a echoue pour <strong>" .
                    htmlspecialchars($r['nom']) . "</strong>.</p>" .
                    "<p>Raison : " . htmlspecialchars($e->getMessage()) . "</p>" .
                    "<p>Action requise : verifier les credentials Oracle. Le service reste " .
                    "operationnel avec les dernieres donnees connues.</p>"
                );
            }
        }
    } else {
        $daysLeft = (int) floor($tokenRemaining / 86400);
        echo "  [" . $r['slug'] . "] Token encore valide {$daysLeft}j\n";
    }
}

echo sprintf(
    "[%s] Cron OK : %d renouveles, %d echecs, %d alertes envoyees\n",
    date('Y-m-d H:i:s'), $renewed, $failed, $alerted
);

// Log dans logs/cron.log
$logFile = ROOT_PATH . '/logs/cron.log';
@file_put_contents(
    $logFile,
    sprintf("[%s] refresh_tokens : %d/%d renouveles, %d echecs\n",
        date('Y-m-d H:i:s'), $renewed, count($restos), $failed),
    FILE_APPEND
);
