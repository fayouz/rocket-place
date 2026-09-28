# Environnement de démo

## Dans GitHub Codespaces (rien à installer)

1. Sur GitHub, ouvre le dépôt, choisis la branche qui contient la démo, puis **Code → Codespaces → Create codespace on …**.
2. Attends la fin de la commande de démarrage dans le terminal (5 à 10 minutes au premier lancement, le temps de construire les images). Elle affiche les URLs de la démo.
3. Dans l'onglet **Ports**, ouvre « Rocket Place » (3900) ou « Documentation et changelog » (3901).

> ⚠️ Les mots de passe de démo sont publics. Arrête le codespace quand tu as fini (menu Codespaces → *Stop codespace*).

Pour relancer la démo à la main : `bash demo/codespaces/start.sh`.

## En local

Pré-requis : Docker avec Compose v2.24 ou plus récent.

```bash
docker compose -f compose.yaml -f compose.demo.yaml up -d --build
```

Le service `demo-seed` prépare la base, charge les données de démo et synchronise l'annuaire LDAP, puis s'arrête : `docker compose -f compose.yaml -f compose.demo.yaml logs -f demo-seed`.

| Adresse | Contenu |
|---|---|
| http://localhost:3900 | Rocket Place |
| http://localhost:3901 | Documentation, et le changelog sur `/changelog` |
| http://localhost:8900/api/docs | Documentation de l'API |

La démo contient deux lieux fictifs (« Le port », « Les vignes »), leurs serrures, un connecteur Homey de démo, un catalogue de stock et une autorisation d'accès planifiée. Aucun jeton Nuki ni compte Rocket Cloud : rien n'est envoyé.

## Comptes

| Compte | Mot de passe | Type |
|---|---|---|
| `admin@example.org` | `demo-admin-password` | local, administrateur |
| `alice@example.org` | `demo-alice-password` | local |
| `marie.martin@example.org` | `password` | LDAP, administratrice via le groupe `rocket-admins` |
| `jean.dupont@example.org` | `password` | LDAP |

## Scénarios à tester

1. Connectez-vous avec `alice@example.org`.
2. **Lieux → Le port** : onglet **Serrures**, la serrure et son autorisation d'accès planifiée ; onglet **Domotique**, le connecteur Homey de démo.
3. Onglet **Stock** : niveaux OK/Bas/Vide des articles suivis.
4. En administrateur : « Créer sur la serrure » sur l'autorisation d'accès (rejeté en démo, aucun jeton Nuki).
