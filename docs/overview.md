# SmartEmailing Connect – Přehled pluginu

> **Rychlý průvodce pro navigaci v kódu.** Při každém novém úkolu nejprve nahlédni do `dev-log.md`, pak sem.

---

## Základní info

| Položka | Hodnota |
|---|---|
| Verze | 1.0.2 |
| Prefix option klíčů | `smec_` |
| Prefix tříd | `SMEC_` |
| DB tabulky | `{prefix}smec_logs`, `{prefix}smec_queue` |
| Nonce action | `smec_admin_nonce` |
| GitHub repo | `https://github.com/Lukasholubik/smartemailing-connect/` |
| Auto-updater | Plugin Update Checker v5.5 (vendor/) |

---

## Struktura složek

```
smartemailing-connect/
├── smartemailing-connect.php   ← bootstrap, konstanty (SMEC_VERSION, SMEC_PLUGIN_DIR…)
├── uninstall.php               ← smaže tabulky a options pokud je nastaveno delete_on_uninstall
├── CHANGELOG.md                ← oficiální changelog verzí pluginu
├── docs/                       ← TATO SLOŽKA – vývojová dokumentace
│   ├── overview.md             ← tento soubor
│   ├── settings-reference.md   ← všechny option klíče a jejich struktury
│   └── dev-log.md              ← deník změn ze společných session
│
├── includes/
│   ├── Plugin.php              ← orchestrátor, inicializuje všechny moduly
│   ├── Settings.php            ← čte/zapisuje všechny WordPress options
│   ├── Activator.php           ← aktivace/deaktivace, tvorba tabulek, crony
│   ├── grou-admin-group.php    ← vizuální seskupení Grou.cz pluginů v menu
│   │
│   ├── Admin/Admin.php         ← registrace admin stránek, AJAX handlery, load šablon
│   ├── Api/ApiService.php      ← HTTP klient pro SmartEmailing API (Basic Auth)
│   ├── Database/Database.php   ← helper pro názvy tabulek
│   ├── Logging/Logger.php      ← zápis logů do DB, sanitizace kontextu
│   ├── Queue/Queue.php         ← retry fronta pro selhané importy
│   ├── Webtracking/Webtracking.php      ← injektování SE tracking skriptu
│   ├── Notifications/Notifier.php       ← hlídání zdraví systému, odesílání alertů
│   ├── Diagnostics/Diagnostics.php      ← zdravotní kontroly
│   │
│   ├── Forms/
│   │   ├── FormManager.php         ← zachytí odeslání formuláře, najde mappingy
│   │   ├── FormScanner.php         ← detekuje formuláře na webu
│   │   ├── MappingProcessor.php    ← převod dat formuláře → SE kontakt, resolvuje placeholdery
│   │   └── Integrations/
│   │       ├── ElementorForms.php
│   │       ├── ContactForm7.php
│   │       ├── FluentForms.php
│   │       ├── WPForms.php
│   │       └── NativeRegistration.php
│   │
│   ├── WooCommerce/WooCommerceIntegration.php  ← opt-in checkbox, import na status objednávky
│   └── ReadingTime/ReadingTime.php             ← výpočet, shortcode, Gutenberg blok, auto-insert
│
├── templates/admin/
│   ├── page-overview.php       ← dashboard, diagnostika, přehled fronty
│   ├── page-api.php            ← API přihlašovací údaje, test spojení
│   ├── page-webtracking.php    ← GUID, pozice skriptu, vyloučení
│   ├── page-lists.php          ← seznam kontaktů a vlastní pole ze SE
│   ├── page-forms.php          ← správa mappingů formulářů
│   ├── page-woocommerce.php    ← WooCommerce nastavení
│   ├── page-reading-time.php   ← presety doby čtení
│   ├── page-logs.php           ← logy + správa fronty
│   ├── page-notifications.php  ← e-mail/Slack/webhook alerty
│   └── page-settings.php       ← obecná nastavení, export/import
│
└── assets/
    ├── admin/css/admin.css         ← admin UI styling
    ├── admin/js/admin.js           ← admin AJAX, CRUD mappingů, export/import
    └── frontend/css/reading-time.css  ← styling .smec-reading-time
```

---

## Třídy a jejich zodpovědnost

| Třída | Soubor | Co dělá |
|---|---|---|
| `SMEC_Plugin` | Plugin.php | Inicializuje a propojuje všechny moduly |
| `SMEC_Settings` | Settings.php | Jediný přístupový bod pro čtení/zápis options |
| `SMEC_ApiService` | ApiService.php | `ping()`, `get_contact_lists()`, `import_contacts()` atd. |
| `SMEC_Logger` | Logger.php | `log($type, $level, $msg, $context)` – zapisuje do DB |
| `SMEC_Queue` | Queue.php | Retry mechanismus – `add()`, `process()` |
| `SMEC_Webtracking` | Webtracking.php | Hooked na `wp_head`/`wp_footer`, injektuje SE skript |
| `SMEC_FormManager` | FormManager.php | `handle_submission($type, $id, $fields, $ctx)` |
| `SMEC_FormScanner` | FormScanner.php | Detekce formulářů (cachováno 5 min) |
| `SMEC_MappingProcessor` | MappingProcessor.php | Resolvuje placeholdery, buduje kontaktní pole |
| `SMEC_ReadingTime` | ReadingTime.php | Shortcode `[smart_reading_time]`, Gutenberg blok, auto-insert |
| `SMEC_WooCommerceIntegration` | WooCommerceIntegration.php | Opt-in checkbox + import zákazníků |
| `SMEC_Notifier` | Notifier.php | Hourly cron – kontrola zdraví + odesílání alertů |
| `SMEC_Diagnostics` | Diagnostics.php | `run()` – vrací pole issues |
| `SMEC_Admin` | Admin.php | Registrace admin stránek, všechny `wp_ajax_smec_*` handlery |
| `SMEC_Activator` | Activator.php | `activate()` / `deactivate()` – tabulky, crony, výchozí hodnoty |
| `SMEC_Database` | Database.php | Helper: `logs_table()`, `queue_table()` |

---

## Klíčové WordPress hooky

### Actions
| Hook | Kdo | Co |
|---|---|---|
| `wpcf7_mail_sent` | ContactForm7.php | Import CF7 submission |
| `elementor_pro/forms/new_record` | ElementorForms.php | Import Elementor submission |
| `fluentform/submission_inserted` | FluentForms.php | Import Fluent Forms submission |
| `wpforms_process_complete` | WPForms.php | Import WPForms submission |
| `user_register` | NativeRegistration.php | Import WP registrace |
| `woocommerce_checkout_after_terms_and_conditions` | WooCommerceIntegration.php | Vloží opt-in checkbox |
| `woocommerce_order_status_{status}` | WooCommerceIntegration.php | Import zákazníka |
| `the_content` (priority 10) | ReadingTime.php | Auto-insert doby čtení |
| `wp_head` (priority 50) | ReadingTime.php | Dynamic CSS presetů |
| `smec_process_queue` (hourly) | Queue.php | Zpracuje retry frontu |
| `smec_clean_logs` (daily) | Logger.php | Smaže staré logy |
| `smec_check_notifications` (hourly) | Notifier.php | Health check + alerty |

### AJAX (vše prefix `wp_ajax_smec_`)
Kompletní seznam viz `settings-reference.md` nebo Admin.php.

---

## Shortcode & Gutenberg blok

- Shortcode: `[smart_reading_time preset="default" post_id="123"]`
- Gutenberg blok: `smartemailing-connect/reading-time`

---

## Bezpečnost (přehled)

- Všechny AJAX handlery: `check_ajax_referer('smec_admin_nonce')` + `current_user_can('manage_options')`
- Sanitizace vstupů: `sanitize_text_field`, `esc_url_raw`, `sanitize_hex_color` atd.
- Výstup vždy přes `esc_html()` / `esc_attr()` / `esc_url()`
- API klíč v logu vždy redaktován jako `[REDACTED]`
- Webhoook URL – SSRF ochrana (zakázáno localhost, privátní IP)
- SVG – sanitizace whitelist tagů

---

## SmartEmailing API

- **Endpoint:** `https://app.smartemailing.cz/api/v3/`
- **Auth:** HTTP Basic Auth (username:api_key)
- **Timeout:** 15 s
- **Retry chyby:** 429, 500, 502, 503, 504
- **Retry strategie:** ihned → 5 min → 30 min (max 3 pokusy, pak `failed`)

### Klíčové metody ApiService
- `ping()` – test spojení
- `get_contact_lists()` – seznam listů (kešováno 10 min)
- `get_custom_fields()` – vlastní pole (kešováno 10 min)
- `create_contact_list($name)` – vytvoří nový list
- `import_contacts($contacts, $settings)` – hlavní import

---

## Cache (tranzienty)

| Klíč | TTL | Co cachuje |
|---|---|---|
| `smec_lists_cache` | 10 min | Kontaktní listy z API |
| `smec_customfields_cache` | 10 min | Vlastní pole z API |
| `smec_form_scan` | 5 min | Výsledky skenování formulářů |

---

## Integrace formulářů – tok dat

```
Form submit
  → Integration hook (CF7/Elementor/…)
  → FormManager::handle_submission(type, id, fields, context)
  → najde aktivní mappingy pro daný type+id
  → MappingProcessor::process(mapping, fields)
       → resolvuje placeholdery
       → builduje SE kontaktní pole
  → ApiService::import_contacts()
       → úspěch: log info
       → retryable chyba: Queue::add() → retry cron
       → jiná chyba: log error
```

---

*Tento soubor popisuje stav pluginu ke dni **2026-06-08**. Aktuální změny viz `dev-log.md`.*
