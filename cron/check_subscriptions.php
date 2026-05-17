<?php
/**
 * cron/check_subscriptions.php
 *
 * A executer quotidiennement (recommande : 1 fois par jour vers 8h).
 *
 * Sur Alwaysdata :
 *   Panel > Crons > Nouveau cron
 *   Commande : /usr/bin/php ~/www/cron/check_subscriptions.php
 *   Frequence : Daily a 08:00
 *
 * En local :
 *   php cron/check_subscriptions.php
 */

// Garde-fou : ne tourner qu en CLI ou avec une cle de securite
if (PHP_SAPI !== 'cli') {
    // Si appele en HTTP, exiger une cle dans l URL (?key=...)
    $key = $_GET['key'] ?? '';
    $expected = defined('CRON_KEY') ? CRON_KEY : '';
    if (!$expected || !hash_equals($expected, $key)) {
        http_response_code(403);
        exit('Forbidden');
    }
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/Context.php';
require_once __DIR__ . '/../core/Model.php';
require_once __DIR__ . '/../core/Mailer.php';
require_once __DIR__ . '/../app/models/Restaurant.php';

$rmodel = new Restaurant();
$mailer = new Mailer();
$restos = $rmodel->listAll();

$expired      = 0;
$emails30     = 0;
$emails7      = 0;
$emailsExpire = 0;

foreach ($restos as $r) {
    if (empty($r['abonnement_fin'])) continue;

    $fin      = strtotime($r['abonnement_fin']);
    $now      = time();
    $daysLeft = (int) floor(($fin - $now) / 86400);

    // 1) Marquer comme expire si depasse
    if ($daysLeft < 0 && $r['statut'] === 'actif') {
        $rmodel->setStatut((int) $r['id'], 'expire');
        $expired++;
    }

    // Sauter ceux qui n ont pas d email gerant
    if (empty($r['gerant_email'])) continue;

    // 2) Email J-30 : entre J-30 et J-8, une seule fois
    if ($daysLeft <= 30 && $daysLeft > 7 && empty($r['email_30j_sent'])
        && $r['statut'] === 'actif') {
        if ($mailer->sendExpirationWarning($r, $daysLeft)) {
            $rmodel->markEmailSent((int) $r['id'], 'email_30j_sent');
            $emails30++;
        }
    }

    // 3) Email J-7 : entre J-7 et J-1, une seule fois
    if ($daysLeft <= 7 && $daysLeft > 0 && empty($r['email_7j_sent'])
        && $r['statut'] === 'actif') {
        if ($mailer->sendExpirationWarning($r, $daysLeft)) {
            $rmodel->markEmailSent((int) $r['id'], 'email_7j_sent');
            $emails7++;
        }
    }

    // 4) Email J-0 / expire : si depasse, une seule fois
    if ($daysLeft <= 0 && empty($r['email_expire_sent'])) {
        if ($mailer->sendExpirationNotice($r)) {
            $rmodel->markEmailSent((int) $r['id'], 'email_expire_sent');
            $emailsExpire++;
        }
    }
}

$log = sprintf(
    "[%s] Cron OK : %d expires marques | emails J-30: %d | J-7: %d | expire: %d\n",
    date('Y-m-d H:i:s'), $expired, $emails30, $emails7, $emailsExpire
);

// Log dans logs/cron.log
$logFile = ROOT_PATH . '/logs/cron.log';
@file_put_contents($logFile, $log, FILE_APPEND);
echo $log;
