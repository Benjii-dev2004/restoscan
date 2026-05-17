# Cron RESTOSCAN

## check_subscriptions.php

Script a executer **quotidiennement** pour :
- Marquer comme `expire` les restaurants dont l abonnement est depasse
- Envoyer les emails J-30 / J-7 / J-0 au gerant de chaque restaurant

### Configuration Alwaysdata

1. Aller dans **Panel > Avancé > Crons**
2. Cliquer **"Ajouter un cron"**
3. Renseigner :
   - **Type** : `Web` ou `Commande`
   - **Commande** (mode Commande) :
     ```
     /usr/bin/php ~/www/cron/check_subscriptions.php
     ```
   - **URL** (mode Web — alternatif) :
     ```
     https://restoscan.alwaysdata.net/cron/check_subscriptions.php?key=VOTRE_CLE_SECRETE
     ```
   - **Fréquence** : `Daily` à `08:00`
4. Sauvegarder

### Test manuel

Sur le serveur (via SSH) :
```bash
php ~/www/cron/check_subscriptions.php
```

Sortie attendue :
```
[2026-05-17 08:00:00] Cron OK : 0 expires marques | emails J-30: 0 | J-7: 0 | expire: 0
```

Les logs sont aussi ecrits dans `logs/cron.log`.

### Securite (mode Web)

Si tu utilises le mode Web (URL HTTP), definis une cle dans `config/config.local.php` :
```php
define('CRON_KEY', 'une-cle-tres-longue-et-aleatoire');
```

Sans cle valide, l URL `/cron/check_subscriptions.php?key=...` renvoie 403.

En mode CLI (recommande), la cle n est pas necessaire.
