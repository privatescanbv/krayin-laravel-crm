---
name: grafana-dashboards
description: How to read/edit Grafana dashboards and panels for this repo (local Grafana at localhost:3000, Loki at localhost:3100, the "stack-monitor" logging dashboard) via the HTTP API, test LogQL queries, and push changes to acc/prod with grafana:sync-dashboard. Use whenever asked to add/change a Grafana panel or log query, check whether logs (Laravel, Traefik access log, n8n, Keycloak, ...) reach Loki/Grafana, or sync a Grafana dashboard between environments.
---

# Grafana dashboards (this repo)

Logging stack: container stdout → **promtail** → **Loki** → **Grafana**. The main
dashboard is `stack-monitor` ("Stack monitoring overview"). Work through the API,
not the browser; everything below was hit while adding the "Traefik access log" panel.

## Access (local)

| What | From the host | From the `crm` container |
|---|---|---|
| Grafana | `http://localhost:3000` | `http://grafana:3000` |
| Loki | `http://localhost:3100` | `http://loki:3100` |

Grafana needs auth (anonymous and `admin:admin` both give 401). Read the admin
credentials from the container **without printing them**:

```bash
U=$(docker exec grafana printenv GF_SECURITY_ADMIN_USER)
P=$(docker exec grafana printenv GF_SECURITY_ADMIN_PASSWORD)
curl -s -u "$U:$P" http://localhost:3000/api/dashboards/uid/stack-monitor > /tmp/dash.json
```

Inside the app the same environment is `config('services.grafana.environments.local')`
(`GRAFANA_LOCAL_URL/USER/PASSWORD` in `.env`). Never `grep`/`cat` `.env` without masking
secrets: it also holds Metabase API keys and passwords.

## The dashboard is NOT provisioned

- The live dashboard lives in Grafana's own DB (volume `privatescan_crm_grafana-data`).
- `.docker/grafana/dashboard.json` is only an **export**; it drifts. Its version and
  layout differed from live, and live had no N8N/SuiteCRM panels while the file did.
  Editing the file changes nothing in Grafana.
- So: **edit live via the API**, then, if the repo copy should follow, apply the
  same change to `.docker/grafana/dashboard.json` (keep its 4-space indent; it may be
  wrapped in `{"dashboard": ...}`). Don't blindly overwrite live with the file:
  people rearrange panels in the UI.

## Editing a dashboard

1. `GET /api/dashboards/uid/<uid>` → `{dashboard, meta}`.
2. Change `dashboard.panels` (Python or PHP; find panels by **title**, and never assume
   a title exists in live just because it's in the file; fall back to "first panel of
   `type: logs`" as a template).
3. Save:
   ```bash
   # payload: {"dashboard": <dashboard>, "folderUid": meta.folderUid, "overwrite": false, "message": "..."}
   curl -s -u "$U:$P" -H 'Content-Type: application/json' -X POST \
     http://localhost:3000/api/dashboards/db --data-binary @/tmp/save.json
   ```
   Keep `overwrite: false` and the `version` from step 1: Grafana then rejects the save
   if someone changed the dashboard in between, instead of silently clobbering it.
4. Verify by re-reading it (`version` went up, panel titles and `gridPos` as expected).

Panel facts:
- Grid is **24 columns** wide; `gridPos: {h, w, x, y}`. To insert a full-width row at
  `y`, add 8 (panel height) to every panel with `gridPos.y >= y`.
- New panel `id` = max existing id + 1. Copy `datasource`, `options` and
  `pluginVersion` from an existing logs panel.
- Panel queries are **hard-coded** (`{service="crm"}`, `{service="forms"}`, ...). Only
  the bottom panel "🔎 Logs: $service ($level)" follows the `$service` / `$level`
  variables (`$level` "All" = `.*`, which also matches lines without `level_name`).
- **Check datasource uids against `GET /api/datasources`.** Panels and variables kept
  pointing at uids of a Loki datasource that had been recreated (`Data source not
  found`: panel stays empty, variables never load), while panels without a `datasource`
  silently used the default. Point every panel, target and variable at the real uid;
  `grafana:sync-dashboard` remaps real uids per environment, but leaves unknown ones as
  they are. The uid differs per environment, so never hard-code it in instructions for acc/prod.
- **Metric queries must not fail on non-JSON lines.** `count_over_time({...} | json ...)`
  returns `pipeline error: 'JSONParserErr'` for the whole panel as soon as one line in
  the window isn't JSON (Metabase, Traefik startup, php-fpm). Filter on stream labels
  instead (`{level_name="ERROR"}` needs no `| json`), or add `| __error__=""` after `| json`.
  Log panels don't have this problem: bad lines are just shown with an `__error__` label.
- Use `[$__auto]` for timeseries and `[$__range]` with `queryType: instant` for stat/pie,
  so panels follow the dashboard's time picker; don't put fixed ranges in titles.
- Verify a saved dashboard through Grafana itself: `POST /api/ds/query` with each panel's
  target (substitute `$service`/`$level` yourself). This catches datasource errors that
  testing the LogQL on Loki directly won't.

## Labels in Loki (from `.docker/config/promtail/promtail-config.yml`)

- Every container: `container`, `service` (compose service name), `project`, `stream`;
  `app` only for crm|forms|n8n|mysql|traefik|suitecrm|phpmyadmin|keycloak.
- `level_name` / `channel` only exist for Laravel's JSON log lines. Other services get
  `level_name` only when a line matches one of promtail's regexes. **Traefik access-log
  lines get no `level_name`**, so they never show up under the `$level` variable.
- Traefik access log (`--accesslog=true --accesslog.format=json` in all three compose
  files): `{service="traefik"} | json` gives `DownstreamStatus`, `RequestMethod`,
  `RequestHost`, `RequestPath`, `Duration` (**nanoseconds**), `ClientHost`, `RouterName`, ...

## Test LogQL before putting it in a panel

Query Loki directly (no auth locally). It returns the formatted lines, so you can see
exactly what the panel will show:

```bash
S=$(($(date +%s)-900))000000000   # last 15 min, in ns
curl -s -G http://localhost:3100/loki/api/v1/query_range \
  --data-urlencode 'query={service="traefik"} | json | line_format "{{.DownstreamStatus}} {{.RequestMethod}} {{.RequestHost}}{{.RequestPath}} {{div .Duration 1000000}}ms"' \
  --data-urlencode "start=$S" --data-urlencode 'limit=5' \
  | python3 -c 'import json,sys; d=json.load(sys.stdin); print(d["status"], d.get("error","")); [print(v[1]) for r in d["data"]["result"] for v in r["values"]]'
```

No lines? Generate traffic first (`curl -sk https://crm.local.privatescan.nl/admin/login`)
and check the container really logs (`docker logs --since 1m traefik`). A changed
compose `command:` only applies after the container is recreated (`docker inspect
<name> --format '{{json .Args}}'` shows what's running).

## Pushing to acc / prod

```bash
docker exec crm php artisan grafana:sync-dashboard --source local --target acc --dashboard stack-monitor --dry-run
docker exec crm php artisan grafana:sync-dashboard --source local --target prod --dashboard stack-monitor
```

- acc = `https://logs.dev.privatescan.nl`, prod = `https://logs.privatescan.nl`
  (`GRAFANA_<ENV>_URL` + `GRAFANA_<ENV>_TOKEN` in `.env`; see `config/services.php` →
  `services.grafana` and `app/Console/Commands/SyncGrafanaDashboard.php`).
- The target dashboard is **overwritten**; always run `--dry-run` first: it shows the
  target version and who last updated it. If someone edited it on prod, ask before syncing.
- Datasources are matched by name, else the single datasource of the same type; no match
  → that dashboard is skipped and the command exits non-zero.
- Promtail on acc/prod uses `/home/apps/docker/promtail/config.yml` on the server, **not**
  the repo's config, and the labels differ: there, streams only have `app, container,
  project, service, service_name, stream`. **No `level_name`/`channel` labels.** So never
  select on `{level_name=...}`; parse it: `| json | __error__="" | level_name=~"..."`
  (works with and without the label; locally it then skips non-JSON error lines).
- Laravel's app channel is the APP_ENV: `local`, `staging` (acc), `production` (prod).
  Filter with `channel=~"php-fpm|local|staging|production"`, not one env's name.
- After a sync, verify **on the target**: run each panel's target through the target's
  own `POST /api/ds/query` (token from `config('services.grafana.environments.<env>')`),
  and inspect `GET /api/datasources/uid/<uid>/resources/labels` when a panel is empty.
  An empty Traefik panel on prod means the access-log flag isn't deployed there yet.

## Related

- `.docker/config/promtail/promtail-config.yml`: local labels/pipeline.
- `docker-compose.yml` / `-acc.yml` / `-prod.yml`: Traefik access-log flags, Grafana/Loki services.
- `docs/modules/Development/pages/dev.adoc` → "Grafana-dashboards synchroniseren".
- `.claude/skills/metabase-dashboards/SKILL.md`: the Metabase counterpart.
