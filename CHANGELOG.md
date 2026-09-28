# Changelog

Toutes les évolutions notables de Rocket Place. Format [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/), versions [SemVer](https://semver.org/lang/fr/).

## [Non publié]

### Retiré
- Ménage déplacé dans Rocket Clean (fayouz/rocket-clean) : entités `CleaningTask`/`CleaningChecklistItem` (migration supprimant `cleaning_task` et `cleaning_checklist_item`), contrôleurs, lien secret `/m/<jeton>`, e-mails Rocket Mailer (`ROCKET_MAILER_*`), pages et onglet Ménage, indicateurs du tableau de bord, données de démo. Les API `/api/places`, `/api/stock-items`, `/api/stock-levels` et documents restent, utilisées par Rocket Clean.

### Ajouté
- Ménage par lieu (`CleaningTask`) : fenêtre, statut, personne attribuée, checklist recopiée depuis le modèle du lieu, notes, photos avant/après/dégât rangées dans le dossier Rocket Cloud du lieu, relevés de stock (mettent à jour `StockLevel`). Création manuelle ou par une application (ex. PMS après un départ) avec `externalRef` idempotente, unique par lieu. Page « Ménage » pour téléphone (ménages du jour et en retard), onglet « Ménage » d'un lieu (planification, checklist), tableau de bord (ménages du jour, en retard), données de démo.
- Ménage : vignettes des photos et agrandissement ; choix de la personne parmi les comptes (`GET /api/cleaning-assignees`, admin) ; **lien secret sans compte** `/m/<jeton>` limité à un ménage (jeton HMAC 128 bits, expire le lendemain de l'échéance, copie/régénération/révocation par l'admin, `/api/public/cleaning/{token}` limité en débit, `no-store`/`noindex`) ; **e-mails via Rocket Mailer** (`MailerClient`, repli démo sans réseau, mode suite par jeton Rocket Auth) : attribution avec le lien, ménages en retard (8 h) et bilan du jour (20 h) aux administrateurs, désactivables (`/api/cleaning-settings`). Variables `ROCKET_MAILER_URL`, `ROCKET_MAILER_TOKEN`, `ROCKET_MAILER_MAILBOX`, `ROCKET_MAILER_SENDER`. Migration : `cleaning_task.link_salt`.
- Documentation : pages d'usage et d'API réécrites pour Rocket Place (les pages héritées du PMS — réservations, timeline, `/api/properties` — sont retirées).
- Mode suite documenté (Rocket Auth : connexion, sélecteur d'applications, déconnexion ; variables `ROCKET_AUTH_*`, `ROCKET_PUBLIC_URL`, `ROCKET_INTERNAL_URL`).
- Documents : en mode suite, Rocket Place appelle Rocket Cloud avec un jeton Rocket Auth (client credentials, audience `rocket-cloud`) ; `ROCKET_CLOUD_TOKEN` reste le repli.
- Les jetons Rocket Auth d'un client lié à une application (ex. `rocket-pms`) ont accès aux routes métier comme un jeton `rpl_…`.

## [0.1.0] - 2026-09-28

### Ajouté
- Lieux (`Place`) : nom, adresse, couleur repère, coordonnées, créés/renommés via l'API (admin).
- Domotique : catalogue de plugins intégré (Homey, Home Assistant, Nuki, Rocket Cloud, Service web), connecteurs par lieu (plusieurs autorisés, ex. deux Homey), onglet « Domotique » en lecture seule (administration : ajout/modification/suppression/test), secrets uniquement en noms de variables `.env` préfixées `CONNECTOR_`. Page Administration → Plugins.
- Serrures : état, batterie, historique, lien serrure → lieu (administration), synchronisation depuis le compte Nuki historique (`POST /api/locks/sync`) ; un lieu peut router l'état et/ou l'écriture de code vers un connecteur.
- Autorisations d'accès (`AccessGrant`) génériques : planifiées (code réservé), envoyées sur la serrure (action explicite), révoquées ; `externalRef` libre pour un client (ex. PMS) qui veut rattacher une autorisation à sa propre réservation.
- Documents par lieu dans Rocket Cloud (dossier créé à la demande, contrôle d'appartenance à l'arborescence du lieu).
- Stock de consommables/équipement : catalogue global d'articles (nom, ASIN Amazon, quantité de réassort, abonnement) et niveau (OK/Bas/Vide) suivi par lieu.
- Tableau de bord (nombre de lieux/serrures, alertes de stock, prochaines autorisations d'accès) et état des services Nuki et Homey.
- Mode démo sans jeton Nuki ni compte Rocket Cloud.
