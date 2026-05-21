# 🔌 Intégration Oracle Simphony STS Gen2

> Ce document résume le plan d'intégration Oracle pour RESTOSCAN.
> Le document source détaillé : `RESTOSCAN_Modifications_Oracle.docx`

---

## 🎯 Objectif business

Permettre à RESTOSCAN d'être l'**interface client moderne** pour les grands hôtels
qui utilisent **Oracle Micros Simphony** (Radisson, Pullman, Onomo, etc.).

- L'hôtel garde son POS Micros existant
- RESTOSCAN devient le canal "client scan QR → commande"
- Tout est synchronisé via l'API officielle Oracle Simphony STS Gen2

---

## 🏗️ Architecture cible

```
┌─────────────────────┐         ┌──────────────────┐
│   CLIENT (mobile)   │         │  CUISINE / POS   │
│   Scanne le QR      │         │  Micros déjà     │
│   Voit RESTOSCAN    │         │  en place        │
└──────────┬──────────┘         └────────▲─────────┘
           │                             │
           ▼                             │
┌─────────────────────┐                  │
│  RESTOSCAN BACKEND  │                  │
│  (PHP - mode oracle)│                  │
└──────────┬──────────┘                  │
           │  HTTPS / REST API           │
           ▼                             │
┌────────────────────────────────────────┴─────────┐
│         ORACLE SIMPHONY CLOUD (STS Gen2)         │
│  • Stocke le menu officiel                       │
│  • Reçoit les commandes                          │
│  • Les transmet au POS physique du restaurant    │
└──────────────────────────────────────────────────┘
```

---

## 🔄 Mode hybride

RESTOSCAN fonctionne dans **2 modes** selon le restaurant client :

| Mode | Quand | Source de données |
|---|---|---|
| `standalone` (défaut) | Petits restos sans Micros | BDD MySQL locale |
| `oracle` | Grands hôtels avec Micros | API Oracle Simphony |

Le mode est stocké dans `restaurants.mode_integration`.

---

## 📦 12 modifications planifiées (par phase)

### Phase 1 — Fondations ✅ (en cours)
- ✅ **01** Configuration `oracle_config.php` (template + `.gitignore`)
- ✅ **02** Colonnes Oracle sur table `restaurants`
- ✅ **12** Table `oracle_logs` + modèle `OracleLog`
- ✅ Mock Oracle API local (`mock-oracle/`)

### Phase 2 — Authentification
- ⏳ **03** `OracleAuthService` (OAuth2 PKCE complet)
- ⏳ **04** Cron `refresh_tokens.php` + vérification auto

### Phase 3 — Service API
- ⏳ **05** `OracleApiService` (toutes les méthodes : menus, checks, taxes...)

### Phase 4 — Intégration menu
- ⏳ **06** Refonte `MenuController` avec branchement Oracle
- ⏳ **08** Cache local `menu_cache`
- ⏳ **11** Strategy Pattern (`MenuProvider` / `OrderProvider`)

### Phase 5 — Intégration commandes
- ⏳ **07** Refonte `OrderController` (calculator + createCheck)

### Phase 6 — Notifications temps réel
- ⏳ **09** Endpoint webhook `/webhooks/oracle/notifications`
- ⏳ **10** Cron `reconcile_orders.php`

### Phase 7 — Tests & déploiement
- ⏳ Tests d'intégration avec sandbox Oracle
- ⏳ Tests de charge
- ⏳ Documentation déploiement

**Durée estimée totale** : 4-6 semaines à plein temps.

---

## 🔐 Sécurité

| Donnée | Protection |
|---|---|
| `oracle_api_password` | Chiffré AES-256-CBC en BDD (`oracle_api_password_enc`) |
| Clé de chiffrement | Dans `oracle_config.php` (jamais en BDD, jamais committée) |
| `id_token` / `refresh_token` | En BDD (sensibles mais durée de vie courte) |
| Logs Oracle | Sanitization automatique des champs sensibles (`OracleLog::sanitize()`) |

---

## 🧪 Tester avec le mock

Voir [`mock-oracle/README.md`](mock-oracle/README.md).

Résumé :
```bash
# Lancer le mock
php -S localhost:8081 -t mock-oracle/

# Dans oracle_config.php :
define('ORACLE_API_BASE_URL', 'http://localhost:8081');
```

---

## 📚 Référence API Oracle

- Documentation officielle : https://docs.oracle.com/cd/F22513_01/index.html
- Endpoints STS Gen2 : https://api.simphony.oracleindustry.com
- OAuth2 PKCE : flow standard RFC 7636
