# 🎭 Mock Oracle Simphony STS Gen2

Serveur PHP simulant l'API Oracle Simphony pour les tests locaux.

---

## 🚀 Lancer le mock

Depuis la racine du projet :

```bash
php -S localhost:8081 -t mock-oracle/
```

Tu vois :
```
[Sun Oct  5 12:00:00 2026] PHP 8.1 Development Server (http://localhost:8081) started
```

---

## ✅ Tester rapidement

### Le mock répond ?

```bash
curl http://localhost:8081/mock/state
```

→ Renvoie l'état JSON interne.

### Login OAuth2

```bash
curl -X POST http://localhost:8081/oidc-provider/v1/oauth2/signin ^
  -H "Content-Type: application/json" ^
  -d "{\"username\":\"admin\",\"password\":\"test\"}"
```

→ Renvoie `{"authorization_code":"authcode_xxxxxx"}`

### Échanger le code contre un token

```bash
curl -X POST http://localhost:8081/oidc-provider/v1/oauth2/token ^
  -H "Content-Type: application/json" ^
  -d "{\"grant_type\":\"authorization_code\",\"code\":\"authcode_xxxxxx\"}"
```

→ Renvoie `{"id_token":"...", "refresh_token":"...", "expires_in":3600}`

### Récupérer un menu (authentifié)

```bash
curl http://localhost:8081/api/v1/menus/menu-001 ^
  -H "Authorization: Bearer idtoken_xxxxxxx"
```

---

## 🧪 Endpoints de contrôle (tests de résilience)

| Endpoint | Effet |
|---|---|
| `GET /mock/state` | Voir l'état interne (tokens, checks, simulations actives) |
| `POST /mock/reset` | Reset complet (vide tokens et checks) |
| `POST /mock/expire-token` | Expire tous les tokens (test refresh) |
| `POST /mock/fail/5` | Les 5 prochaines requêtes renverront `500` (test retry) |
| `POST /mock/slow/2000` | Ajoute 2000 ms de latence aux prochaines requêtes |

Exemple :
```bash
# Simuler 3 pannes Oracle
curl -X POST http://localhost:8081/mock/fail/3

# Simuler Oracle lent (3s)
curl -X POST http://localhost:8081/mock/slow/3000

# Remettre normal
curl -X POST http://localhost:8081/mock/slow/0
```

---

## 📁 Fichiers

| Fichier | Rôle |
|---|---|
| `index.php` | Routeur principal (un seul fichier, tout est dedans) |
| `data/state.json` | État persistant entre requêtes (auto-créé) |
| `data/menu.json` | Fixture menu (optionnel, sinon menu par défaut) |

---

## 🔌 Configurer RESTOSCAN pour utiliser le mock

Dans `config/oracle_config.php` :

```php
define('ORACLE_API_BASE_URL', 'http://localhost:8081');
define('ORACLE_AUTH_URL',     'http://localhost:8081');
```

---

## ⚠️ Limites du mock

- Ne reproduit pas la cryptographie complète OAuth2 PKCE (juste le flow)
- Pas de webhook sortant (à simuler avec curl manuel)
- Les fixtures sont volontairement minimales
- Pas de rate limiting

Le mock est là pour valider l'**intégration code** RESTOSCAN, pas pour certifier la compatibilité Oracle réelle.

Pour la certification finale, il faudra accès au **sandbox Oracle officiel** (compte Oracle Industry).
