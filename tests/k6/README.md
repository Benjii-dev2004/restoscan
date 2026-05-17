# 🚀 Tests de charge RESTOSCAN — k6

3 scénarios pour valider que l'app tient en conditions réelles d'un restaurant plein.

## Prérequis

1. **k6 installé** (https://k6.io/docs/get-started/installation/)
2. **Un QR token valide** : récupère-le depuis phpMyAdmin
   ```sql
   SELECT qr_token FROM tables LIMIT 1;
   ```

## Configuration

Tous les scripts lisent l'URL via la variable d'environnement `BASE_URL` :

```powershell
$env:BASE_URL="https://restoscan.alwaysdata.net"
$env:QR_TOKEN="ton_token_de_64_caracteres"
```

## Scénarios

### 1️⃣ menu_load.js — Rush midi simulé

100 clients qui scannent le QR au même moment et browsent le menu.

```powershell
k6 run tests/k6/menu_load.js
```

**Durée** : ~5 minutes
**Objectif** : valider que le menu reste fluide à 100 clients concurrents
**Seuils** :
- 95% des requêtes < 1s
- Taux d'erreur < 1%

### 2️⃣ order_burst.js — Pic de commandes

Vérifie que le rate limit fonctionne (envoie 30 commandes en 1 min, doit en rejeter ~10).

```powershell
k6 run tests/k6/order_burst.js
```

**Durée** : ~2 minutes
**Objectif** : valider la protection anti-spam

### 3️⃣ sustained.js — Service de 30 minutes

Trafic continu sur 30 min (commandes + consultations menu + polling status).
Simule un vrai service du soir.

```powershell
k6 run tests/k6/sustained.js
```

**Durée** : 30 minutes
**Objectif** : valider qu'il n'y a pas de fuite mémoire, latence stable

## Interprétation des résultats

Après chaque run, k6 affiche :

```
✓ http_req_duration..............: avg=234ms min=89ms med=212ms max=2.1s p(95)=890ms
✓ http_req_failed................: 0.12% ✓ 12 ✗ 9988
✓ checks........................: 99.88% ✓ 9988 ✗ 12
```

- **http_req_duration p(95)** : 95% des requêtes finissent en < cette valeur. Objectif < 1s
- **http_req_failed** : % d'erreurs. Objectif < 1%
- **checks** : assertions personnalisées (status 200, contenu attendu, etc.)

## Bon à savoir

- Tu es **bloqué par le rate limit** de l'app (20 commandes/min/IP). C'est attendu et même testé par `order_burst.js`.
- Pour tester sans rate limit, soit tu testes depuis plusieurs machines/IPs, soit tu commentes temporairement le rate limit dans `OrderController::doCreate`.
