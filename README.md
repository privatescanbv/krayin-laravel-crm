# Lokale ontwikkelomgeving (PrivateScan)

Alles draait in Docker. Je hebt op de host **geen** PHP en **geen** Composer nodig.

## Eenmalige setup

1. **Toegang tot de private base image.** De crm-image bouwt op
   `ghcr.io/privatescanbv/dockerbase/php`, dus log in bij de GitHub registry met
   een personal access token met `read:packages`:

   ```bash
   docker login ghcr.io
   ```

   Zonder dit faalt de build met `denied` / `unauthorized`.

2. **`vendor/` aanmaken.** `start-dev.sh` gebruikt `./vendor/bin/sail`, dus die
   moet er eerst zijn. Omdat je nog geen werkende container hebt, bootstrap je
   dat met de officiele composer-image:

   ```bash
   docker run --rm -v "$PWD":/app -w /app \
     -v "$HOME/.cache/composer":/tmp/composer-home \
     -e COMPOSER_HOME=/tmp/composer-home -e COMPOSER_ALLOW_SUPERUSER=1 \
     composer:2 install --ignore-platform-reqs --no-scripts --no-interaction --prefer-dist
   ```

   Daarna gebruik je altijd `./vendor/bin/sail composer ...`.

3. **`.env` aanmaken.** `cp .env.example .env`. Lees de commentaarblokken bij de
   `DB_*`-variabelen: een aantal daarvan is verplicht voor de MySQL-init en de
   container crasht zonder die waardes.

4. **Hostnames in `/etc/hosts`.** Traefik routeert op hostname, dus deze moeten
   naar localhost wijzen:

   ```bash
   sudo tee -a /etc/hosts <<'HOSTS'
   127.0.0.1 crm.local.privatescan.nl
   127.0.0.1 sso.local.privatescan.nl
   127.0.0.1 phpmyadmin.local.privatescan.nl
   127.0.0.1 reports.local.privatescan.nl
   127.0.0.1 logs.local.privatescan.nl
   127.0.0.1 patient.local.privatescan.nl
   127.0.0.1 patientdev.local.privatescan.nl
   127.0.0.1 formsdev.local.privatescan.nl
   127.0.0.1 clinic.local.privatescan.nl
   HOSTS
   ```

5. **TLS-certificaten.** Certificaten staan **niet** in git: een cert is alleen
   vertrouwd op de machine waar de bijbehorende CA in de trust store staat, dus
   iedereen maakt zijn eigen set. `start-dev.sh` doet dit automatisch als
   `certs/` leeg is, of handmatig:

   ```bash
   ./scripts/generate-local-certs.sh
   ```

   De CA komt in `~/.local-dev-ca` en wordt **gedeeld met de andere
   PrivateScan-projecten** (o.a. clinic-portal). Je vertrouwt hem dus één keer,
   en daarna werken alle `*.local.privatescan.nl`-projecten:

   ```bash
   sudo security add-trusted-cert -d -r trustRoot \
     -k /Library/Keychains/System.keychain "$HOME/.local-dev-ca/rootCA.pem"
   ```

   Firefox heeft zijn eigen trust store: zet `security.enterprise_roots.enabled`
   op `true` in `about:config`.

6. **Starten en migreren.**

   ```bash
   ./start-dev.sh
   ./vendor/bin/sail artisan migrate
   ```

7. **Metabase** heeft eenmalig een eigen database nodig; zie het
   `METABASE_DB_PASSWORD`-blok in `.env.example` voor het SQL-commando.

## URLs

| Wat | URL |
| --- | --- |
| CRM admin | https://crm.local.privatescan.nl/admin/login (`admin@example.com` / `admin123`) |
| Keycloak SSO | https://sso.local.privatescan.nl |
| phpMyAdmin | https://phpmyadmin.local.privatescan.nl |
| Metabase | https://reports.local.privatescan.nl |
| Grafana | https://logs.local.privatescan.nl |
| Traefik dashboard | http://localhost:8089 |

## Veelvoorkomende problemen

- **`mysql_crm` crasht met exit 1.** Het init-script mist een verplichte env-var.
  Check `docker logs mysql_crm` op `env var is required`. MySQL draait zijn
  init-scripts alleen bij een lege datadir, dus na het repareren van `.env` moet
  het volume weg: `docker compose rm -sf mysql_crm && docker volume rm krayin-laravel-crm_crm-mysql`.
- **"Name or service not known" voor de database.** `DB_HOST` moet de
  compose-servicenaam zijn: `mysql_crm`.
- **Browser vertrouwt het certificaat niet.** Je gebruikt het cert van een
  collega, of je eigen CA staat nog niet in de trust store. Zie stap 5.
- **`brew install` compileert eindeloos of faalt.** Op macOS 14 bouwt Homebrew
  geen bottles meer en compileert alles from source. Je hebt voor dit project
  geen brew-packages nodig.

---

## Notes setup ai models

Setting up models on VPS.

sudo apt update
sudo apt install -y pipx
pipx ensurepath
pipx install huggingface_hub
hf auth login
hf models ls --search "medgemma gguf"
hf models ls --search "medgemma gguf" --format json
sudo chown -R $USER:$USER /home/apps/models
hf download unsloth/medgemma-27b-text-it-GGUF \
--local-dir /home/apps/models
hf download \
unsloth/medgemma-27b-text-it-GGUF \
--local-dir /home/apps/models \
--max-workers 1

![enter image description here](https://raw.githubusercontent.com/krayin/temp-media/master/dashboard.png)

## Topics

1. [Introduction](#introduction)
2. [Documentation](#documentation)
3. [Requirements](#requirements)
4. [Installation & Configuration](#installation-and-configuration)
4. [Docker Installation](https://devdocs.krayincrm.com/2.0/introduction/docker.html)
5. [License](#license)
6. [Security Vulnerabilities](#security-vulnerabilities)

### Introduction

[Krayin CRM](https://krayincrm.com) is a hand tailored CRM framework built on some of the hottest opensource technologies
such as [Laravel](https://laravel.com) (a [PHP](https://secure.php.net/) framework) and [Vue.js](https://vuejs.org)
a progressive Javascript framework.

**Free & Opensource Laravel CRM solution for SMEs and Enterprises for complete customer lifecycle management.**

**Read our documentation: [Krayin CRM Docs](https://devdocs.krayincrm.com/)**

**We also have a forum for any type of concerns, feature requests, or discussions. Please visit: [Krayin CRM Forums](https://forums.krayincrm.com/)**

# Visit our live [Demo](https://demo.krayincrm.com)

<a href="javascript:void();">
    <img class="flag-img" src="https://raw.githubusercontent.com/krayin/temp-media/master/visit-our-live-demo.png" alt="Chinese" width="100%">
</a>

It packs in lots of features that will allow your E-Commerce business to scale in no time:

-   Descriptive and Simple Admin Panel.
-   Admin Dashboard.
-   Custom Attributes.
-   Built on Modular Approach.
-   Email parsing via Sendgrid.
-   Check out [these features and more](https://krayincrm.com/features/).

**For Developers**:
Take advantage of two of the hottest frameworks used in this project -- Laravel and Vue.js -- both of which have been used in Krayin CRM.

### Documentation

#### Krayin Documentation [https://devdocs.krayincrm.com](https://devdocs.krayincrm.com)

### Requirements

-   **SERVER**: Apache 2 or NGINX.
-   **RAM**: 3 GB or higher.
-   **PHP**: 8.1 or higher
-   **For MySQL users**: 5.7.23 or higher.
-   **For MariaDB users**: 10.2.7 or Higher.
-   **Node**: 8.11.3 LTS or higher.
-   **Composer**: 2.5 or higher

### Installation and Configuration

##### Execute these commands below, in order

```
composer create-project
```

-   Find **.env** file in root directory and change the **APP_URL** param to your **domain**.

-   Also, Configure the **Mail** and **Database** parameters inside **.env** file.

```
php artisan krayin-crm:install
```

**To execute Krayin**:

##### On server:

Warning: Before going into production mode we recommend you uninstall developer dependencies.
In order to do that, run the command below:

> composer install --no-dev

```
Open the specified entry point in your hosts file in your browser or make an entry in hosts file if not done.
```

##### On local:

```
php artisan route:clear
php artisan serve
```


**How to log in as admin:**

> _http(s)://example.com/admin/login_

```
email:admin@example.com
password:admin123
```

### WhatsApp CRM Integration

[Krayin CRM WhatsApp](https://krayincrm.com/extensions/krayin-crm-whatsapp-extension/) Extension enables the store administrator to generate leads via their WhatsApp number.

![enter image description here](https://raw.githubusercontent.com/krayin/temp-media/master/krayin-crm-whatsapp-integration.png)

### VoIP CRM Integration

[Krayin CRM VoIP](https://krayincrm.com/extensions/krayin-crm-voip/) extension allows the user to make Trunk calls over a broadband Internet connection and the user can also perform Inbound routes.

![enter image description here](https://raw.githubusercontent.com/krayin/temp-media/master/krayin-voip.png)

### License

Krayin CRM is a fully open-source CRM framework which will always be free under the [MIT License](https://github.com/krayin/laravel-crm/blob/2.1/LICENSE).

### Security Vulnerabilities

Please don't disclose security vulnerabilities publicly. If you find any security vulnerability in Krayin CRM then please email us: sales@krayincrm.com.
