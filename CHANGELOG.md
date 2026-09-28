# Changelog

Toutes les évolutions notables de Rocket Place. Format [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/), versions [SemVer](https://semver.org/lang/fr/).

## [Non publié]

### Ajouté
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
