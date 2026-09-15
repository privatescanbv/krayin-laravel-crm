---
name: metabase-dashboards
description: How to read/build/edit Metabase dashboards and cards for this repo (local instance at reports.local.privatescan.nl) via the app's own API client — no browser login exists. Use whenever asked to create, adjust, or inspect a Metabase dashboard/question/chart, add filters, wire drill-down/click-through, or debug why a Metabase card errors.
---

# Metabase dashboards (this repo)

There is **no known Metabase browser login** for the local instance. Don't try to log
into `https://reports.local.privatescan.nl` — go straight to the API via this repo's
own Metabase client. Everything below was learned the hard way building the "Leads
per maand" and "Verloren leads" dashboards; follow it and you'll skip the mistakes.

## Access

```php
docker compose exec -T crm php artisan tinker --execute="
\$c = app(App\Services\Metabase\MetabaseEnvironments::class)->resolve('dev');
// \$c is a MetabaseClient — createCard, updateCard, getCard, listCards,
// createDashboard, updateDashboard, getDashboard, listDatabases, getDatabaseMetadata.
"
```

- The local instance's configured environment name is **`dev`**, not `local`
  (`METABASE_DEV_URL=http://metabase:3000` internally, same instance as
  `reports.local.privatescan.nl` externally — see `.env` and
  `config/services.php` → `services.metabase`).
- Auth is `x-api-key`, already wired in `MetabaseClient` — you don't touch it directly
  except when you need a raw `Http::` call (see "Running a card's query" below).
- `App\Services\Metabase\MetabaseEnvironments::resolve('dev')` throws if
  `METABASE_DEV_API_KEY` isn't set — it's already in `.env` for local dev.
- `php artisan metabase:sync-dashboard` (`SyncMetabaseDashboard` command) copies an
  **existing** dashboard between environments. It is not for building a new dashboard
  from scratch — for that, use the client directly as below.

## Finding the analytics database & field ids

The CRM's own `analytics` schema (see `database/analytics/*.sql`) is registered in
Metabase as database id **3** (`crm_analytics`). Native SQL cards need this id but not
field ids; **field-filter** template tags (dashboard filters bound to a real column —
see below) need a field id, which you get from:

```php
\$meta = \$c->getDatabaseMetadata(3);
foreach (\$meta['tables'] as \$t) {
    if (\$t['name'] === 'fact_leads') {
        foreach (\$t['fields'] as \$f) { echo \$f['id'] . ' => ' . \$f['name'] . PHP_EOL; }
    }
}
```

Field ids are stable but not memorized here on purpose — always look them up fresh;
they shift when columns are added (Metabase's own schema sync runs periodically and
picks up new columns automatically, but the id assigned is whatever Metabase gives it).

## Building/editing a native SQL card

```php
\$card = \$client->createCard([
    'name' => 'Leads per maand — won/lost',
    'description' => '...',
    'display' => 'bar', // table | bar | line | combo | scalar | pivot ...
    'visualization_settings' => $settings ?: new stdClass, // NEVER an empty [] — see gotchas
    'dataset_query' => [
        'database' => 3,
        'type' => 'native',
        'native' => ['query' => $sql, 'template-tags' => $templateTags],
    ],
    'parameters' => $parameters, // only needed if the card uses {{tags}}
    'collection_id' => null,
]);
// $client->updateCard($id, [...]) — PARTIAL updates work; send only changed keys
// (e.g. just 'display' + 'visualization_settings' to restyle a chart without
// touching its query).
```

### Two kinds of template tags

**Plain variable** (free value, e.g. clicked-through from another card):
```php
'lost_reason' => ['id' => $uuid, 'name' => 'lost_reason', 'display-name' => 'Lost reason', 'type' => 'text', 'required' => false],
```
SQL: `[[AND lost_reason = {{lost_reason}}]]`

**Field filter / dimension** (dashboard dropdown/date-picker bound to a real column —
this is what you want for shared dashboard filters):
```php
'afdeling' => ['id' => $uuid, 'name' => 'afdeling', 'display-name' => 'Afdeling', 'type' => 'dimension', 'dimension' => ['field', $fieldId, null], 'widget-type' => 'string/=', 'default' => null],
```
SQL: `[[AND {{afdeling}}]]` (no `column =` — Metabase generates the whole predicate).
Useful `widget-type`s: `string/=` (dropdown of distinct values), `date/all-options`
(free date range), `date/month-year` (**gives the native ‹ month › stepper UI** — use
this whenever someone asks to "page through months").

## Wiring a dashboard filter to multiple cards

```php
$dashboard = $client->createDashboard(['name' => '...', 'description' => '...', 'collection_id' => null]);

$parameters = [
    ['id' => $paramUuid, 'name' => 'Afdeling', 'slug' => 'afdeling', 'type' => 'string/=', 'sectionId' => 'string'],
    // 'default' => '2026-09' works for date/month-year to pre-select a month
];

$dashcards = [[
    'id' => -1, // negative placeholder = "create new"; reuse the real id to update in place
    'card_id' => $cardId,
    'row' => 0, 'col' => 0, 'size_x' => 24, 'size_y' => 6, // grid is 24 COLUMNS WIDE, not 12 (see gotchas)
    'series' => [],
    'parameter_mappings' => [
        ['parameter_id' => $paramUuid, 'card_id' => $cardId, 'target' => ['dimension', ['template-tag', 'afdeling']]],
    ],
    'visualization_settings' => new stdClass,
]];

$client->updateDashboard($dashboard['id'], ['name' => '...', 'description' => '...', 'parameters' => $parameters, 'dashcards' => $dashcards]);
```

## Click-through / drill-down between cards

Set on the **dashcard's** `visualization_settings.click_behavior` (not the card itself):

```php
'visualization_settings' => [
    'click_behavior' => [
        'type' => 'link', 'linkType' => 'question', 'targetId' => $detailCardId,
        'parameterMapping' => [
            // clicked column -> target card's PLAIN VARIABLE tag:
            'some-key' => ['id' => 'some-key', 'source' => ['type' => 'column', 'id' => 'lost_reason', 'name' => 'lost_reason'], 'target' => ['type' => 'variable', 'id' => 'lost_reason']],
            // clicked column -> target card's FIELD-FILTER/dimension tag:
            'other-key' => ['id' => 'other-key', 'source' => ['type' => 'column', 'id' => 'bron', 'name' => 'bron'], 'target' => ['type' => 'dimension', 'id' => ['dimension', ['template-tag', 'leadbron']]]],
        ],
    ],
],
```

## Running a card's query directly (verification without a browser)

There's no UI login, so verify by executing the card's query over the API instead of
looking at it:

```php
$apiKey = config('services.metabase.environments.dev.api_key');
$resp = Illuminate\Support\Facades\Http::baseUrl($client->baseUrl().'/api')
    ->withHeaders(['x-api-key' => $apiKey])->acceptJson()->asJson()
    ->post("/card/{$cardId}/query", ['parameters' => []]); // MUST send ['parameters' => []], not [] — see gotchas
$rows = $resp->json()['data']['rows'] ?? null;
```
To simulate a dashboard filter being set, pass a real parameter in that array, e.g.
`['type' => 'string/=', 'target' => ['dimension', ['template-tag', 'afdeling']], 'value' => ['Herniapoli']]`.

## Gotchas (all hit while building the leads dashboards — don't repeat these)

1. **Don't alias the base table when a card uses field-filter tags.** Metabase
   generates the filter predicate as `` `realtablename`.`column` `` — the *actual*
   table name from its metadata, not your query's alias. `FROM analytics.fact_leads f
   ... WHERE {{afdeling}}` breaks with `Unknown column 'fact_leads.afdeling'` the
   moment the filter is used, even though the unfiltered query runs fine. Either don't
   alias the table at all (`FROM analytics.fact_leads LEFT JOIN analytics.dim_user u
   ON u.user_sk = fact_leads.verkoper_sk`), or don't put field-filter tags on queries
   that need an alias for a self-join.
2. **Don't round-trip `getCard()` into `updateCard()`.** `getCard()` returns the
   MBQL5 "lib" internal representation (`lib/type`, `stages`, …). Sending that back
   as-is 400s with `MBQL 4 keys like :type, :query, or :native are not allowed in MBQL
   5 queries with :lib/type`. Always build the `dataset_query` payload fresh (native
   SQL string + your own `template-tags` array) rather than editing what `getCard`
   gave you.
3. **Empty arrays are not empty objects in the Metabase API.** PHP's `[]` serializes
   to JSON `[]`, and Metabase rejects that for anything it expects as a map (e.g. an
   empty `visualization_settings`, or the `POST /card/{id}/query` body needs the key
   `parameters` present as `['parameters' => []]`, not an omitted/empty top-level
   array) with `"invalid type, received: ()"` or `"Value must be a map"`. Use `new
   stdClass` for an intentionally-empty settings object.
4. **The dashboard grid is 24 columns wide** in the Metabase version running here
   (v0.62), not the 12 you might expect from older Metabase docs. `size_x: 12` is
   half-width, not full-width.
5. **A full `updateDashboard()` call overwrites the whole `dashcards` layout**,
   including any manual repositioning a person did in the UI. If someone may have
   dragged cards around, `getDashboard()` first and preserve `row`/`col`/`size_x`/
   `size_y` — or just ask before doing a scripted rebuild of an existing dashboard's
   layout.
6. **One-off scripts belong at the repo root, not `storage/app/`.** `storage/app` is
   bind-mounted from a *different* local folder (`crm_storage/`) than the rest of the
   repo (which mounts to `/usr/share/nginx/html`), so a file you `Write` under
   `storage/app/foo.php` is invisible inside the container. Write scratch scripts to
   the repo root and `require '/usr/share/nginx/html/foo.php'` from tinker, then
   delete the file when done.
7. There are no Metabase UI credentials anywhere in `.env`/docs — don't try to log in
   via `claude-in-chrome`/Playwright. Do everything through the API and verify with
   direct card-query calls as above.

## Related

- `app/Services/Metabase/MetabaseClient.php` — the thin API wrapper used above.
- `app/Services/Metabase/DashboardSyncService.php` — cross-environment dashboard sync
  (`metabase:sync-dashboard`), good reference for payload shapes for cards/dashboards/
  click_behavior/parameters even though its own use case is different.
- `database/analytics/*.sql` — the source schema these dashboards query
  (`analytics.fact_leads`, `analytics.fact_orders`, `analytics.dim_user`, …).
