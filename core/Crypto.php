<?php
/**
 * core/Crypto.php
 *
 * ROLE : Chiffrement/dechiffrement AES-256-CBC des donnees sensibles
 *        (notamment oracle_api_password_enc en BDD).
 *
 * USAGE :
 *   $encrypted = Crypto::encrypt('mon_mot_de_passe');
 *   $plain     = Crypto::decrypt($encrypted);
 *
 * SECURITE :
 *   - La cle vient de ORACLE_ENCRYPTION_KEY (jamais en BDD)
 *   - IV (16 octets) genere aleatoirement a chaque chiffrement
 *   - Format de sortie : base64(IV || ciphertext)
 *   - Pour masquer dans les logs : Crypto::mask($token) → "***xxxx"
 */

class Crypto {

    private const CIPHER = 'aes-256-cbc';

    /** Derive la cle 32 octets depuis ORACLE_ENCRYPTION_KEY */
    private static function key(): string {
        if (!defined('ORACLE_ENCRYPTION_KEY') || !ORACLE_ENCRYPTION_KEY) {
            throw new \RuntimeException('ORACLE_ENCRYPTION_KEY non defini dans oracle_config.php');
        }
        // SHA-256 pour avoir une cle de 32 octets, meme si la constante n est pas hex 64
        return hash('sha256', ORACLE_ENCRYPTION_KEY, true);
    }

    /** Chiffrer une chaine en AES-256-CBC. Retourne base64(IV || ciphertext). */
    public static function encrypt(string $plaintext): string {
        $key = self::key();
        $iv  = random_bytes(16);
        $ct  = openssl_encrypt($plaintext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv);
        if ($ct === false) {
            throw new \RuntimeException('Echec du chiffrement : ' . openssl_error_string());
        }
        return base64_encode($iv . $ct);
    }

    /** Dechiffrer une chaine produite par encrypt(). */
    public static function decrypt(string $encoded): string {
        $key  = self::key();
        $data = base64_decode($encoded, true);
        if ($data === false || strlen($data) < 17) {
            throw new \RuntimeException('Format chiffre invalide');
        }
        $iv = substr($data, 0, 16);
        $ct = substr($data, 16);
        $pt = openssl_decrypt($ct, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv);
        if ($pt === false) {
            throw new \RuntimeException('Echec du dechiffrement (cle changee ?)');
        }
        return $pt;
    }

    /**
     * Masquer une valeur sensible pour l affichage dans les logs.
     * Garde uniquement les 4 derniers caracteres.
     */
    public static function mask(?string $value): string {
        if ($value === null || $value === '')   return '(vide)';
        if (strlen($value) <= 8)                return '***';
        return '***' . substr($value, -4);
    }

    /** Generer une cle de chiffrement aleatoire (a copier dans oracle_config.php) */
    public static function generateKey(): string {
        return bin2hex(random_bytes(32));
    }
}
