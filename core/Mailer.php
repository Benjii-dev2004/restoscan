<?php
/**
 * core/Mailer.php
 * Helper d envoi d emails via la fonction PHP mail() (relai SMTP de l hote).
 * Configurable via constantes MAIL_FROM_EMAIL et MAIL_FROM_NAME definies
 * dans config.local.php (production) ou config.php (defaults).
 */

class Mailer {

    private string $fromEmail;
    private string $fromName;

    public function __construct() {
        $this->fromEmail = defined('MAIL_FROM_EMAIL')
            ? MAIL_FROM_EMAIL
            : 'no-reply@restoscan.alwaysdata.net';
        $this->fromName  = defined('MAIL_FROM_NAME')
            ? MAIL_FROM_NAME
            : 'RESTOSCAN';
    }

    /** Envoyer un email HTML. Retourne true si mail() reussit. */
    public function send(string $to, string $subject, string $htmlBody): bool {
        if (!$to || !filter_var($to, FILTER_VALIDATE_EMAIL)) return false;

        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $this->fromName . ' <' . $this->fromEmail . '>',
            'Reply-To: ' . $this->fromEmail,
            'X-Mailer: RESTOSCAN',
        ];

        // En dev local : ne pas envoyer reellement (eviter le spam SMTP)
        if (defined('ENV') && ENV === 'development') {
            error_log("[MAILER DEV] To: {$to} | Subject: {$subject}");
            return true;
        }

        return @mail($to, $subject, $htmlBody, implode("\r\n", $headers));
    }

    /** Email d alerte d expiration (J-30, J-7) */
    public function sendExpirationWarning(array $resto, int $daysLeft): bool {
        if (empty($resto['gerant_email'])) return false;

        $nom    = htmlspecialchars($resto['nom']);
        $finStr = date('d/m/Y', strtotime($resto['abonnement_fin']));
        $urgent = $daysLeft <= 7 ? 'URGENT — ' : '';
        $subject = "{$urgent}Votre abonnement expire dans {$daysLeft} jours";

        $html = $this->wrapTemplate(
            "Abonnement expire dans {$daysLeft} jours",
            "<p>Bonjour <strong>{$nom}</strong>,</p>
             <p>Votre abonnement a la plateforme expire dans <strong>{$daysLeft} jours</strong> ({$finStr}).</p>
             <p>Pour eviter toute interruption de service, merci de prendre contact avec nous au plus vite pour renouveler.</p>
             <p>Une fois la date depassee, vos employes ne pourront plus se connecter et les clients ne pourront plus passer commande.</p>
             <hr style=\"border:none;border-top:1px solid #e5e7eb;margin:1.5rem 0\">
             <p style=\"color:#6b7280;font-size:.9em\">Cet email est envoye automatiquement. Pour repondre, contactez directement l equipe RESTOSCAN.</p>"
        );

        return $this->send($resto['gerant_email'], $subject, $html);
    }

    /** Email J-0 : abonnement expire */
    public function sendExpirationNotice(array $resto): bool {
        if (empty($resto['gerant_email'])) return false;

        $nom    = htmlspecialchars($resto['nom']);
        $finStr = date('d/m/Y', strtotime($resto['abonnement_fin']));
        $subject = 'Votre abonnement a expire — Acces suspendu';

        $html = $this->wrapTemplate(
            'Abonnement expire',
            "<p>Bonjour <strong>{$nom}</strong>,</p>
             <p>Votre abonnement a expire le <strong>{$finStr}</strong>.</p>
             <p>L acces a votre interface est desormais bloque pour l ensemble de vos employes, et les clients ne peuvent plus passer commande.</p>
             <p><strong>Contactez-nous au plus vite pour renouveler et retablir le service.</strong></p>
             <hr style=\"border:none;border-top:1px solid #e5e7eb;margin:1.5rem 0\">
             <p style=\"color:#6b7280;font-size:.9em\">Vos donnees sont conservees. Apres renouvellement, tout reprend exactement la ou vous l avez laisse.</p>"
        );

        return $this->send($resto['gerant_email'], $subject, $html);
    }

    /** Template HTML de base */
    private function wrapTemplate(string $title, string $bodyHtml): string {
        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>{$title}</title>
</head>
<body style="margin:0;padding:0;font-family:Arial,sans-serif;background:#f3f4f6">
<table cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#f3f4f6;padding:2rem 1rem">
<tr><td align="center">
<table cellpadding="0" cellspacing="0" border="0" width="600" style="background:white;border-radius:12px;overflow:hidden;max-width:600px">
<tr><td style="background:#e85d04;padding:1.5rem;color:white;text-align:center">
<h1 style="margin:0;font-size:1.5rem">{$title}</h1>
</td></tr>
<tr><td style="padding:2rem;color:#1f2937;line-height:1.6">
{$bodyHtml}
</td></tr>
<tr><td style="background:#f9fafb;padding:1rem;text-align:center;color:#9ca3af;font-size:.85rem">
RESTOSCAN — Solution de commande digitale pour restaurants
</td></tr>
</table>
</td></tr>
</table>
</body>
</html>
HTML;
    }
}
