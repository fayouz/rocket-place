# Rocket Place

Gestion de **lieux** (logements, locaux, terrains…), utilisable sans aucun PMS, sur la stack des briques Rocket. Socle commun : [rocket-core](https://github.com/fayouz/rocket-core) (bundle Symfony `rocket/core-bundle` + layer Nuxt `@rocket/core`), à lire avant de modifier les comptes, le SSO, les applications, le tableau de bord ou la mise en page : ce code n'est pas ici. Dérivé de rocket-pms (dont il reprend le socle domotique/serrures/documents), sans le PMS (pas de réservations Lodgify).

## Repères
- `app_id` `place`, jetons d'application `rpl_…`, ports front 3900 · api 8900 · docs 3901.
- Domaine : `Place` (lieu : nom, adresse, couleur, coordonnées, dossier Rocket Cloud), `SmartLock` (serrure → lieu), `AccessGrant` (autorisation d'accès générique : lieu, serrure, libellé, validité `DATETIMETZ`, code, statut planned/created/error/revoked, `externalRef` libre optionnelle), `StockItem`/`StockLevel` (catalogue global d'articles + niveau OK/Bas/Vide par lieu), `CleaningTask` (ménage : lieu, fenêtre `DATETIMETZ`, statut todo/in_progress/done/cancelled, assignee User, checklist/photos/relevés de stock en JSON, `externalRef` unique par lieu) + `CleaningChecklistItem` (modèle de checklist par lieu). `Controller/CleaningController` : planification = PLACE_MANAGE, exécution = personne attribuée (ou non attribué) ; photos via `DocumentProviderRegistry`.
- Intégrations : `Nuki/NukiClient` (+ `DemoNuki`), `Cloud/CloudClient` (+ `DemoCloud`) ; sans jeton : démo. Accès : `Code/AccessGrantService` (`plan` réserve un code unique sur la serrure, `send` l'écrit réellement — jamais en test ni sans action explicite —, `revoke` le retire au mieux). Tableau de bord : `Dashboard/PlacesSection`. Sondes : `Health/NukiProbe`, `Health/HomeyProbe`.
- Domotique et connecteurs pluggables : `Domotique/PluginRegistry` (catalogue code-défini : `HomeyPlugin`, `WebServicePlugin`, `NukiPlugin`, `RocketCloudPlugin`), entité `Connector` (plugin configuré pour un lieu, plusieurs par lieu, capacités déclarées par le plugin). Chaque capacité a son registre qui résout le connecteur du lieu puis retombe sur le compte global historique si absent : `Lock/LockProviderRegistry` (serrures), `Cloud/DocumentProviderRegistry` (documents, repli `ROCKET_CLOUD_URL`/`ROCKET_CLOUD_TOKEN`). Secrets = uniquement un nom de variable `.env` préfixée `CONNECTOR_` (`Domotique/SecretEnv`), jamais la valeur en base. `Controller/ConnectorController` (CRUD admin + test), `Controller/DomotiqueController` (lecture par lieu).
- Front : `pages/places/[id].vue` (onglets : Serrures, Domotique, Documents, Stock), `components/LocksInbox.vue`, `DomotiqueTab.vue`, `StockTab.vue`, `CleaningTab.vue` (+ `CleaningCard.vue`) ; `pages/menage.vue` (ménages du jour, téléphone) ; `pages/plugins.vue` (administration, catalogue).

## Vérifier avant de pousser
```bash
cd backend && php bin/console lint:container && php bin/console doctrine:schema:validate && php bin/phpunit
cd frontend && npm run lint && npm run typecheck
cd docs && npm run lint && npm run typecheck && npm run generate   # si docs/ a changé
```

## Pièges connus
- Création/suppression de codes sur les serrures : actions réelles chez Nuki (ou le connecteur), jamais en test ni sans clic de l'utilisateur (`AccessGrantController::send`/`revoke`).
- Dates des autorisations d'accès en `DATETIMETZ` (instants justes quel que soit le fuseau du serveur).
- API Platform répond en JSON-LD par défaut : envoyer `Accept: application/json`.
- Migrations : lancer d'abord celles du socle, puis `doctrine:migrations:diff`.
- Pas de Composer sur le Mac de Faez : `docker run --rm -v "$PWD":/app -w /app composer:2 install --ignore-platform-reqs`. Cache npm global en erreur de droits : `npm ci --cache <dossier temporaire>`.

## Feuille de route
v0.1 : lieux, connecteurs/domotique, serrures + autorisations d'accès génériques, documents Rocket Cloud, stock. Puis un PMS (ex. rocket-pms ou LoussaHousing) devient client de cette API (jeton `rpl_…`) pour piloter les autorisations d'accès depuis ses réservations.
