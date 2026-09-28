#!/usr/bin/env bash
# Demo scenarios of rocket-place, checked by the CI (reusable workflow brick-demo.yml of rocket-core) once the demo stack
# (compose.yaml + compose.demo.yaml) is up. Run with "bash -e" from the repository root; environment:
# COMPOSE (docker compose -f compose.yaml -f compose.demo.yaml), FRONT, DOCS (demo front and docs URLs), APP_VERSION.
# Locally: COMPOSE="docker compose -f compose.yaml -f compose.demo.yaml" FRONT=http://localhost:3900 DOCS=http://localhost:3901 bash -e .github/demo-scenarios.sh
set -x
# Seeded accounts: the first-run setup is closed
curl -fsS $FRONT/api/setup | jq -e '.required == false'
# Local account (through the front's same-origin /api proxy, as in Codespaces)
ALICE=$(curl -fsS -X POST $FRONT/api/auth/login -H 'Content-Type: application/json' \
  -d '{"email":"alice@example.org","password":"demo-alice-password"}' | jq -r .token)
# LDAP account, admin through its directory group
TOKEN=$(curl -fsS -X POST $FRONT/api/auth/login -H 'Content-Type: application/json' \
  -d '{"email":"marie.martin@example.org","password":"password"}' | jq -r .token)
curl -fsS $FRONT/api/me -H "Authorization: Bearer $TOKEN" | jq -e '.roles | index("ROLE_ADMIN")'
# Version of the images, shown by the API and the interface; no one-click update without UPDATER_TOKEN
curl -fsS $FRONT/api/system/version -H "Authorization: Bearer $TOKEN" | jq -e '.version == "0.0.0-ci"'
curl -fsS $FRONT/api/system/update -H "Authorization: Bearer $TOKEN" | jq -e '.current.release == "0.0.0-ci" and .method == "manual" and .methods.docker.configured == false'
curl -fsS $FRONT/login | grep -q 'appVersion:"v0.0.0-ci"'
# LDAP settings (environment until saved): the connection test finds the directory's users
curl -fsS $FRONT/api/ldap/config -H "Authorization: Bearer $TOKEN" | jq -e '.source == "environment" and .enabled'
curl -fsS -X POST $FRONT/api/ldap/test -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' -d '{}' \
  | jq -e '.ok and .count >= 2'
# Documentation site, with the changelog
curl -fsS $DOCS/changelog | grep -q 'Dernière version'
# Network health checks (also run by the worker's scheduler): the real OpenLDAP
curl -fsS -X POST $FRONT/api/health/check -H "Authorization: Bearer $TOKEN" > health.json
jq -e '[.services[] | select(.id == "ldap") | .status] == ["operational"]' health.json || { jq . health.json; exit 1; }
$COMPOSE exec -T api php bin/console app:health:check
# Dashboard: the whole platform and service details for admins
curl -fsS $FRONT/api/dashboard -H "Authorization: Bearer $TOKEN" \
  | jq -e '.scope == "platform" and (.daily | length) == 30 and ([.health.services[] | select(.id == "database" or .id == "ldap") | .status] == ["operational", "operational"])'
# An application acts as a user, never as an administrator
DEMO_TOKEN=$(grep -o 'rpl_demo_[a-z_]*' compose.demo.yaml | head -1)
curl -fsS $FRONT/api/me -H "Authorization: Bearer $DEMO_TOKEN" -H 'X-Impersonate-User: admin@example.org' \
  | jq -e '.user.email == "admin@example.org" and (.roles | index("ROLE_ADMIN") | not)'
# Rocket Place: the demo places, their locks and the planned access grant, plus the stock catalogue
PORT=$(curl -fsS $FRONT/api/places -H "Authorization: Bearer $ALICE" -H "Accept: application/json" | jq -r '.[] | select(.name == "Le port") | .id')
curl -fsS $FRONT/api/places/$PORT/locks -H "Authorization: Bearer $ALICE" | jq -e '.demo and ([.locks[].id] == [90001])'
curl -fsS $FRONT/api/places/$PORT/access-grants -H "Authorization: Bearer $ALICE" | jq -e '([.[] | select(.externalRef == "demo-1")] | length) == 1'
curl -fsS $FRONT/api/stock-items -H "Authorization: Bearer $ALICE" | jq -e '(length) >= 4'
