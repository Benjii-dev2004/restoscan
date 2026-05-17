# 🛡️ AUDIT DE SÉCURITÉ RESTOSCAN

> Audit complet réalisé en plusieurs phases.

---

## 📋 Phase A — Scans automatiques

| Scan | Note | Statut |
|---|---|---|
| **SSL Labs** | A+ | ✅ Parfait |
| **SecurityHeaders.com** | A | ✅ (cap par `unsafe-inline` CSP — accepté) |
| **PageSpeed Insights** (menu client) | 95+/90+/100/90 | ✅ Après optimisations |
| **Mozilla Observatory** | n/a | ⏸ Bloqué par 404 sur `/` (acceptable) |
| **OWASP ZAP** | À lancer | ⏳ Manuel par l'utilisateur |

---

## 🔓 Phase B1 — Tests offensifs manuels

### Légende
- 🟢 **Sécurisé** — l'attaque échoue
- 🟡 **Mitigé** — l'attaque échoue mais l'implémentation pourrait être renforcée
- 🔴 **VULNÉRABLE** — fix immédiat requis (**TOUS CORRIGÉS**)

---

### 1. SQL Injection — 🟢

Toutes les requêtes utilisent PDO préparées. Les interpolations résiduelles (`{$whereClause}`, `{$orderBy}`, `{$column}`) sont protégées par regex ou whitelist.

### 2. XSS Stocké — 🟢

Toutes les sorties HTML utilisent `View::e()` ou `htmlspecialchars()`. Les variables sans échappement (`$content`, `$pc`, `$dark`) proviennent de sources sécurisées (rendu interne, validation hex).

### 3. XSS Reflété — 🟢

Aucun paramètre URL n'est affiché directement sans échappement.

### 4. CSRF — 🟢

24/24 endpoints POST validés via `validateCsrf()` ou `validateCsrfAjax()`.
Seul `/order/create` n'a pas de CSRF (volontaire — endpoint public sans session).

### 5. Cross-tenant (lecture) — 🟢

Tous les modèles scopent par `restaurant_id`. `requireAuth()` vérifie que le slug URL matche `$_SESSION['user']['restaurant_id']` et détruit la session si mismatch.

### 6. Order tampering — 🟢

Prix recalculés serveur depuis la BDD, quantité bornée `max(1, min(50, qty))`.

### 7. File upload bypass — 🟢

MIME via `finfo_file()`, extension whitelist, nom aléatoire `bin2hex(random_bytes(8))`, taille max 2 Mo. Défense en profondeur ajoutée (`.htaccess` désactive PHP dans le dossier upload).

### 8. Path traversal — 🟢

Le QR token est matché par regex `([^/]+)` — les `/` sont impossibles. Recherche en BDD préparée.

### 9. IDOR — 🟢

Toutes les opérations `findById`/`delete`/`update` sont scopées par `restaurant_id` au niveau modèle.

### 10. Privilege escalation — 🟢

`requireAuth('role')` empêche un user `cuisine` d'accéder à `/admin`.

### 11. Authentication bypass — 🟢

Toutes les méthodes sensibles appellent `requireAuth()`. Pas d'endpoint oublié.

### 12. Information disclosure — 🟢

`display_errors = 0` en prod. Logs dans `logs/` protégés par `.htaccess`. `/migrations/` et `/cron/` également protégés.

### 13. Race conditions — 🟡

Transaction PDO autour de la création de commande. Pas d'incohérence possible. Une race théorique sur le compteur brute-force a été éliminée en passant en BDD.

### 14. Mass assignment — 🟢 (après fix)

Tous les champs sont extraits explicitement. **Fix** : `categorie_id` est désormais validé contre le restaurant_id courant (avant : un admin pouvait pointer vers une catégorie d'un autre resto, fuite mineure du nom de la catégorie).

### 15. Subscription bypass — 🟢

`checkSubscription()` appelée dans `requireAuth()`. Endpoints publics (`/menu`, `/order`) vérifient `restaurant_statut` et `abonnement_fin`.

### 16. Brute force bypass — 🟢 (après fix)

**Fix HIGH** : le compteur était en `$_SESSION` (bypassable en effaçant le cookie). Migration vers une table `login_attempts` persistante indexée par IP+scope.

### 17. Session security — 🟢

`httponly`, `secure` (HTTPS), `samesite=Lax`, `session_regenerate_id(true)` après login.

### 18. Open redirect — 🟢

Toutes les redirections utilisent des paths hardcodés. Aucune entrée utilisateur dans `redirect()`.

### 19. Slug enumeration — 🟡 (acceptable)

Possible de tester `/r/marc/` puis `/r/paul/` pour détecter quels restos existent. Aucune donnée fuitée — juste l'existence. Acceptable.

### 20. Rate limiting `/order/create` — 🟡 (à venir)

Pas de rate limit sur la création de commande. Si un QR token fuit, un attaquant peut spammer la cuisine. **Non corrigé** — à ajouter dans une itération future.

---

## ✅ Fixes appliqués (Phase B1)

| ID | Sévérité | Description | Statut |
|---|---|---|---|
| F1 | 🔴 HIGH | Brute force bypass via cookie clearing | ✅ Fixé (table `login_attempts`) |
| F2 | 🟡 MED  | Cross-tenant FK leak via `categorie_id` | ✅ Fixé (validation explicite) |
| F3 | 🟢 LOW  | `/migrations/` accessible HTTP | ✅ `.htaccess` deny all |
| F4 | 🟢 LOW  | PHP exécutable dans `/public/img/menu/` | ✅ `.htaccess` désactive PHP |
| F5 | 🟢 LOW  | Rate limit absent sur `/order/create` | ⏳ Phase B2 (à venir) |

---

## 🎯 Verdict Phase B1

**L'app est solide.** Aucune vulnérabilité critique exploitable n'a été trouvée après corrections. Le code suit les bonnes pratiques PHP modernes (PDO, échappement systématique, CSRF, validation serveur).

**Phase B2 (suite recommandée)** :
- Scan OWASP ZAP automatique
- Rate limiting public sur `/order/create`
- Migration `unsafe-inline` CSP vers nonces (passer SecurityHeaders A → A+)
- Tests de charge (Phase D)
