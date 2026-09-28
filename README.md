# Rocket Place

Gestion de **lieux** (logements, locaux, terrains…), utilisable sans aucun PMS : **lieux** (nom, adresse, couleur, coordonnées), **connecteurs/domotique** pluggables (Homey, Home Assistant, Nuki, Rocket Cloud, service web), **serrures connectées** (état, batterie, historique) avec des **autorisations d'accès** génériques (code clavier temporaire, planifié puis envoyé sur action explicite), **documents** par lieu dans Rocket Cloud, un **stock de consommables/équipement** par lieu et le **ménage** (tâches par lieu, checklist, photos, relevés de stock ; créables par un PMS). Brique du Middleware Rocket, sur le socle [rocket-core](https://github.com/fayouz/rocket-core).

| Dossier | Stack |
|---|---|
| `backend/` | Symfony 8.1, API Platform, Doctrine (PostgreSQL), rocket-core (`rocket/core-bundle`) |
| `frontend/` | Nuxt 4, Nuxt UI 4, layer `@rocket/core` |
| `docs/` | Documentation (Nuxt UI + Nuxt Content), changelog sur `/changelog` |

Le socle commun (comptes, LDAP, SSO / Rocket Auth, applications externes, tableau de bord, mises à jour, modes autonome et suite) vient de rocket-core : ce dépôt ne contient que le métier.

## Démarrage rapide

```bash
docker compose up -d --build
```

- Application : http://localhost:3900 (configuration initiale : création de l'administrateur)
- API + OpenAPI : http://localhost:8900/api/docs
- Démo complète : `docker compose -f compose.yaml -f compose.demo.yaml up -d --build` (voir [demo/README.md](demo/README.md))

Sans `nuki.api_token` (coffre), l'application tourne sur des **données de démo** (deux lieux fictifs et leurs serrures) : rien n'est lu ni écrit chez Nuki.

### Développement sans Docker

```bash
# backend (PHP 8.4, PostgreSQL)
cd backend && composer install --ignore-platform-req=ext-ldap
php bin/console lexik:jwt:generate-keypair
php bin/console doctrine:migrations:migrate
echo 'MESSENGER_TRANSPORT_DSN=sync://' >> .env.local
php -S 127.0.0.1:8900 -t public
php bin/phpunit

# frontend
cd frontend && npm install && NUXT_PUBLIC_API_BASE=http://localhost:8900 npm run dev -- --port 3900
```

## Configuration

| Variable | Rôle |
|---|---|
| `nuki.api_token` (coffre) | Jeton de l'API web Nuki (compte historique) ; le droit `smartlock.auth` est nécessaire pour créer les codes. Vide : démo. |
| `ROCKET_CLOUD_URL` (jeton : secret `rocket.cloud.token`) | Compte Rocket Cloud historique pour les documents. Vide : démo. |
| `ROCKET_AUTH_URL`, `ROCKET_AUTH_INTERNAL_URL`, `ROCKET_AUTH_CLIENT_ID` (`rocket-place`), `ROCKET_AUTH_CLIENT_SECRET`, `ROCKET_AUTH_ADMIN_GROUP`, `ROCKET_PUBLIC_URL`, `ROCKET_INTERNAL_URL` | Mode suite (voir ci-dessous). `ROCKET_AUTH_URL` vide : mode autonome, inchangé. |
| `CONNECTOR_*` | Transition seulement : anciennes variables des secrets de connecteurs, à importer avec `app:secrets:migrate-env` (secret `connector_*`). |

## Mode suite (Rocket Auth)

Avec `ROCKET_AUTH_URL`, Rocket Place rejoint la suite Rocket (mécanisme de rocket-core) : connexion par Rocket Auth uniquement, sélecteur des applications et « Mon compte » dans le menu, déconnexion propagée (RP-initiated logout et back-channel logout sur `ROCKET_INTERNAL_URL`). Rocket Auth doit déclarer le client `rocket-place` (redirect `<interface>/auth/callback`, retour de déconnexion `<interface>/login?logged_out=1`).

Appels entre applications (client credentials, `Rocket\Core\Suite\ServiceTokenProvider`) :

- **Place → Rocket Cloud** (documents) : jeton Rocket Auth d'audience `rocket-cloud` à la place de `rocket.cloud.token` (coffre), qui reste le repli (mode autonome ou Rocket Auth injoignable). Dans Rocket Cloud, un administrateur lie une application au client `rocket-place` (champ « Client Rocket Auth »).
- **Rocket PMS → Place** : Place accepte les jetons Rocket Auth d'audience `rocket-place` émis pour un client lié à une application (Administration → Applications, champ « Client Rocket Auth », ex. `rocket-pms`). Ils donnent les mêmes droits qu'un jeton `rpl_…` : routes métier ouvertes par `PlaceScopeGuardListener` / `PlaceAccessVoter`.

Environnement complet (Auth, Cloud, Place, PMS) : `compose.suite.yaml` de rocket-pms.

## Fonctionnalités (v0.1)

- **Lieux** : créés/renommés via l'API (admin), couleur repère, adresse, coordonnées.
- **Connecteurs/domotique** : catalogue de plugins (Homey, Home Assistant, Nuki, Rocket Cloud, service web) configurables par lieu, plusieurs par lieu ; onglet « Domotique » en lecture seule (cartes d'info).
- **Serrures** : état, batterie, historique, rattachées à un lieu ; un lieu peut router l'état et/ou l'écriture de code vers un connecteur (Home Assistant, Homey, Nuki secondaire) ou garder le compte Nuki historique.
- **Autorisations d'accès** (`AccessGrant`) : génériques (pas liées à un PMS), avec une référence externe libre optionnelle (ex. un id de réservation côté client) ; cycle **planifiée → envoyée (code écrit sur la serrure, action explicite) → révoquée**.
- **Documents** par lieu dans Rocket Cloud (dossier créé à la demande, contrôle d'appartenance à l'arborescence du lieu).
- **Stock** de consommables/équipement : catalogue global d'articles (nom, ASIN Amazon, quantité de réassort, abonnement), niveau (OK/Bas/Vide) suivi par lieu.
- **Ménage** : tâches par lieu (fenêtre, statut, personne attribuée), checklist recopiée du modèle du lieu, photos avant/après/dégât dans Rocket Cloud, relevés de stock ; page téléphone « Mes ménages du jour » ; création idempotente par une application via `externalRef` (`POST /api/places/{id}/cleanings`) ; lien secret sans compte `/m/<jeton>` limité au ménage ; e-mails via Rocket Mailer (attribution, retards, bilan du jour ; `ROCKET_MAILER_URL`, secret `rocket.mailer.token`, `ROCKET_MAILER_MAILBOX`, `ROCKET_MAILER_SENDER`, démo sans réseau).
- **Tableau de bord** : nombre de lieux/serrures, alertes de stock, ménages du jour et en retard, prochaines autorisations d'accès ; état des services Nuki et Homey.
- **API** pour les applications externes (jeton `rpl_…`), par exemple un PMS côté client.

## Images Docker

Publiées par la CI (workflow réutilisable `docker-images.yml` de rocket-core) **uniquement** sur tag `vX.Y.Z` et lancement manuel (Actions → CI → Run workflow) :

| Image | Contenu |
| --- | --- |
| `ghcr.io/fayouz/rocket-place-api` | API Symfony + worker (FrankenPHP Alpine, `composer --no-dev`, opcache, cible `prod` de `backend/Dockerfile`) |
| `ghcr.io/fayouz/rocket-place-front` | Front Nuxt (`.output` seul, `node:22-alpine`, utilisateur `node`, cible `prod` de `frontend/Dockerfile`) |

- Tags : `vX.Y.Z`, `X.Y.Z`, `X.Y`, `latest` (dernier tag) et `sha-<commit>` ; multi-arch `linux/amd64` + `linux/arm64` ; labels OCI (source, version, révision), SBOM et provenance.
- Sur les PR et branches : build `linux/amd64` de validation + tests de fumée, jamais poussé.
- Le dépôt est privé : les images sont **privées** (visibilité par défaut, à garder). Plan GitHub Free : 500 Mo de stockage et 1 Go/mois de transfert pour les paquets privés (au-delà : facturé ou bloqué) — supprimer les anciennes versions (`sha-…`) et ne publier que sur tag. Pour tirer les images : `docker login ghcr.io` avec un jeton `read:packages`.
- Exemple de déploiement : [`compose.prod.yaml`](compose.prod.yaml) (base, API, worker, front, labels Traefik en commentaire).

## Gitflow

`main` : production ; `develop` : intégration ; `feature/*` → `develop` (section `[Non publié]` du [CHANGELOG](CHANGELOG.md)) ; `release/*` et `hotfix/*` → `main`, puis tag `vX.Y.Z` créé depuis GitHub.

## Secrets des intégrations (coffre)

Les jetons et clés des intégrations sont gardés **chiffrés en base** dans le coffre de rocket-core (Administration → **Secrets**), plus dans le `.env`. Le code les lit par `App\Secrets\IntegrationSecrets` ; l'API ne renvoie jamais leur valeur (aperçu masqué `••••1234`). Seule la clé maîtresse `ROCKET_SECRETS_KEY` (`php bin/console rocket:secrets:generate-key`) reste dans l'environnement : la sauvegarder hors de la base.

| Ancienne variable | Secret du coffre |
|---|---|
| `NUKI_API_TOKEN` | `nuki.api_token` |
| `CONNECTOR_X` (connecteurs, champ `secretVar`) | `connector_x` (champ `secret` du connecteur, converti par la migration Doctrine) |
| `ROCKET_CLOUD_TOKEN` | `rocket.cloud.token` |
| `ROCKET_MAILER_TOKEN` | `rocket.mailer.token` |

Migration d'une instance existante :

1. `php bin/console rocket:secrets:generate-key` → `ROCKET_SECRETS_KEY` dans `.env.local` (ou l'environnement du conteneur) ; `php bin/console doctrine:migrations:migrate`.
2. `php bin/console app:secrets:migrate-env --dry-run` puis `php bin/console app:secrets:migrate-env` : importe les variables ci-dessus sous leur nom de secret (idempotent, `--overwrite` pour remplacer).
3. Retirer ces variables du `.env.local` / de l'environnement. Pendant la transition, une variable encore présente sert de repli (avertissement « deprecated » dans les journaux).

