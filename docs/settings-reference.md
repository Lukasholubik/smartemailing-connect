# SmartEmailing Connect – Reference nastavení

Všechna nastavení pluginu jsou uložena jako WordPress options s prefixem `smec_`.  
Přístup vždy přes `SMEC_Settings` (nikdy `get_option` napřímo v business logice).

---

## smec_api_settings

```php
[
    'username'  => 'user@example.com',   // e-mail do SmartEmailing
    'api_key'   => 'xxxx',               // API klíč
    'base_url'  => 'https://app.smartemailing.cz/api/v3/',
    'enabled'   => 0|1,
]
```

---

## smec_webtracking_settings

```php
[
    'enabled'        => 0|1,
    'guid'           => 'tracking-guid-ze-SE',
    'position'       => 'head'|'footer',
    'exclude_admins' => 0|1,
    'exclude_roles'  => ['editor', 'author'],   // pole slugů rolí
    'excluded_pages' => [1, 2, 3],              // post ID
    'custom_code'    => '<script>...</script>', // jen pro adminy s unfiltered_html
]
```

---

## smec_general_settings

```php
[
    'debug'               => 0|1,
    'delete_on_uninstall' => 0|1,
    'log_retention_days'  => 30,   // 1–365
    'modules'             => [
        'webtracking'  => 1,
        'forms'        => 1,
        'woocommerce'  => 1,
        'reading_time' => 1,
        'gtm'          => 1,
    ],
]
```

---

## smec_form_mappings

Pole mappingů – každý mapping je pole:

```php
[
    'id'                   => 'smec_map_abc123def456',  // unikátní ID
    'name'                 => 'Popis mappingu',
    'enabled'              => 1,
    'form_type'            => 'cf7'|'elementor'|'fluent'|'wpforms'|'registration',
    'form_id'              => '42'|'*',  // '*' = všechny formuláře daného typu
    'list_id'              => 5,         // ID listu v SmartEmailing
    'contact_status'       => 'confirmed'|'unconfirmed',
    'system_fields'        => [
        // klíč = SE systémové pole, hodnota = source + value
        'emailaddress' => ['source' => 'form_field', 'value' => 'email'],
        'name'         => ['source' => 'form_field', 'value' => 'first_name'],
        'surname'      => ['source' => 'static',     'value' => 'Novák'],
        'cellphone'    => ['source' => 'placeholder', 'value' => '__UTM_SOURCE__'],
        // povolená systémová pole: emailaddress, name, surname, cellphone,
        //                          birthday, nameday, salution
    ],
    'custom_field_mapping' => [
        ['field_id' => 15, 'source' => 'form_field', 'value' => 'company'],
        ['field_id' => 18, 'source' => 'static',     'value' => 'web'],
    ],
    'tags'                 => [
        ['source' => 'static',     'value' => 'web_form'],
        ['source' => 'form_field', 'value' => 'kategorie'],
    ],
    'conditions'           => [
        // AND logika – všechny podmínky musí platit
        ['field' => 'souhlas', 'operator' => '==',        'value' => 'yes'],
        ['field' => 'jmeno',   'operator' => 'not_empty',  'value' => ''],
        // operátory: == | != | contains | not_empty | empty
    ],
    'consent_field'        => 'souhlas_checkbox',  // pole formuláře = souhlas
    'created_at'           => '2026-06-08T10:30:00+00:00',
    'updated_at'           => '2026-06-08T10:30:00+00:00',
]
```

**Zdroje hodnot (`source`):**
- `form_field` – hodnota z odeslaného formulářového pole
- `static` – pevně zadaná hodnota
- `placeholder` – viz seznam placeholderů níže

---

## smec_rt_presets (Reading Time presety)

Pole presetů – každý preset je pole:

```php
[
    'id'            => 'smec_rt_abc12345',
    'name'          => 'Výchozí',
    'slug'          => 'default',          // pro shortcode: preset="default"
    'wpm'           => 200,                // slov za minutu
    'label'         => 'Doba čtení:',
    'suffix'        => 'min',
    'show_icon'     => 1,
    'icon'          => 'clock'|'custom',
    'custom_svg'    => '<svg>...</svg>',
    'auto_insert'   => 1,
    'auto_position' => 'before'|'after',   // před/za obsah
    'post_types'    => ['post', 'page'],
    'wrapper_tag'   => 'span'|'div'|'p'|'strong',
    'css_class'     => 'my-class',
    'display'       => 'inline'|'block',
    'color'         => '#333333',
    'icon_color'    => '#666666',
    'font_size'     => '16px',
    'font_weight'   => '600',
    'padding'       => '8px 12px',
    'border_radius' => '4px',
    'background'    => '#f5f5f5',
    'text_align'    => 'left',
    'icon_size'     => '18px',
    'custom_css'    => '.smec-reading-time { margin: 10px 0; }',
    'created_at'    => '2026-06-08T10:30:00+00:00',
    'updated_at'    => '2026-06-08T10:30:00+00:00',
]
```

**CSS selektory:**
- Kontejner: `.smec-reading-time.smec-rt-{slug}`
- Ikona: `.smec-rt-{slug} .smec-rt-icon`
- Auto-insert wrapper: `.smec-reading-time-auto`

---

## smec_woocommerce_settings

```php
[
    'enabled'              => 0|1,
    'list_id'              => 7,
    'require_optin'        => 1,
    'optin_label'          => 'Přihlásit se k odběru newsletteru',
    'import_on_status'     => ['processing', 'completed'],
    'status'               => 'confirmed'|'unconfirmed',
    'field_mapping'        => [
        'name'     => ['source' => 'form_field', 'value' => 'first_name'],
        'surname'  => ['source' => 'form_field', 'value' => 'last_name'],
        'cellphone'=> ['source' => 'form_field', 'value' => 'phone'],
    ],
    'custom_field_mapping' => [
        ['field_id' => 20, 'source' => 'order_field', 'value' => 'order_total'],
    ],
    // order_field hodnoty: order_total, order_number, order_date,
    //                      order_status, payment_method
    'tags'                 => ['woocommerce', 'shop'],
]
```

**Order meta klíč pro opt-in:** `_smec_newsletter_optin`

---

## smec_gtm_settings

```php
[
    'enabled'        => 0|1,
    'container_id'   => 'GTM-XXXXXXX',  // uloženo vždy jako čisté ID (ne celý snippet)
    'exclude_admins' => 0|1,            // GTM se nevloží administrátorům
    'exclude_roles'  => ['editor'],     // pole slugů rolí, jimž se GTM nevloží
]
```

Injekce: `<head>` přes `wp_head` (priorita 1), `<body>` přes `wp_body_open` (priorita 1), fallback do `wp_footer`.

---

## smec_notification_settings

```php
[
    'enabled'        => 1,
    'email'          => ['admin@example.com'],  // pole e-mailů
    'slack_webhook'  => 'https://hooks.slack.com/services/...',
    'webhook_url'    => 'https://example.com/webhook',  // SSRF ochrana
    'cooldown_hours' => 24,
    'events'         => [
        'api_error_streak'  => 1,
        'import_failures'   => 1,
        'cron_stopped'      => 1,
        'import_silence'    => 1,
        'webtrack_silence'  => 0,
    ],
    'thresholds'     => [
        'api_error_streak'  => 3,   // počet po sobě jdoucích chyb
        'import_failures'   => 5,   // počet failed položek ve frontě
        'cron_stopped'      => 6,   // hodiny bez cronu
        'import_silence'    => 48,  // hodiny bez importu
        'webtrack_silence'  => 72,  // hodiny bez tracking injekce
    ],
]
```

---

## smec_ignored_forms

```php
[
    'cf7|42' => [   // klíč = "type|form_id"
        'type'       => 'cf7',
        'form_id'    => '42',
        'reason'     => 'Starý formulář',
        'ignored_at' => '2026-06-08T10:30:00+00:00',
    ]
]
```

---

## smec_notifier_state (interní)

```php
[
    'cron_last_run'                  => 1717935000,  // unix timestamp
    'api_error_count'                => 2,
    'webtrack_last_inject'           => 1717934500,
    'last_notification_{event_name}' => 1717920000,  // kdy byl naposledy alert
]
```

---

## smec_db_version

String – aktuální verze pluginu, slouží pro detekci potřebných DB upgradů.

---

## Tranzienty (cache)

| Klíč | TTL | Obsah |
|---|---|---|
| `smec_lists_cache` | 600 s (10 min) | Pole kontaktních listů z API |
| `smec_customfields_cache` | 600 s (10 min) | Pole vlastních polí z API |
| `smec_form_scan` | 300 s (5 min) | Výsledky form scanneru |

Smazání cache: AJAX `smec_refresh_cache`.

---

## Placeholdery (v mapping hodnotách)

Použij jako `source: placeholder` a `value: __PLACEHOLDER__`:

| Placeholder | Hodnota |
|---|---|
| `__FORM_NAME__` | Název mappingu |
| `__REFERRER__` | HTTP_REFERER |
| `__CURRENT_URL__` | REQUEST_URI |
| `__PAGE_TITLE__` | Název aktuální stránky |
| `__POST_ID__` | ID aktuálního postu |
| `__POST_TITLE__` | Titulek aktuálního postu |
| `__DATE_TIME__` | Y-m-d H:i:s |
| `__DATE__` | Y-m-d |
| `__TIME__` | H:i:s |
| `__SITE_URL__` | URL webu |
| `__SITE_NAME__` | Název webu |
| `__USER_ID__` | ID přihlášeného uživatele |
| `__USER_EMAIL__` | E-mail přihlášeného uživatele |
| `__UTM_SOURCE__` | utm_source z URL |
| `__UTM_MEDIUM__` | utm_medium z URL |
| `__UTM_CAMPAIGN__` | utm_campaign z URL |
| `__UTM_CONTENT__` | utm_content z URL |
| `__UTM_TERM__` | utm_term z URL |

---

## AJAX akce (prefix `wp_ajax_smec_`)

| Akce | Funkce |
|---|---|
| `smec_test_api` | Test API spojení |
| `smec_save_api` | Uložení API nastavení |
| `smec_save_webtracking` | Uložení webtracking nastavení |
| `smec_fetch_lists` | Načtení listů z API |
| `smec_fetch_customfields` | Načtení vlastních polí z API |
| `smec_create_list` | Vytvoření nového listu v SE |
| `smec_get_mappings` | Načtení form mappingů |
| `smec_save_mapping` | Uložení/update mappingu |
| `smec_delete_mapping` | Smazání mappingu |
| `smec_duplicate_mapping` | Klonování mappingu |
| `smec_toggle_mapping` | Zapnutí/vypnutí mappingu |
| `smec_test_contact` | Odeslání testovacího kontaktu |
| `smec_get_logs` | Načtení logů |
| `smec_clear_logs` | Smazání logů |
| `smec_save_general_settings` | Obecná nastavení |
| `smec_save_reading_time` | Uložení reading time nastavení |
| `smec_get_rt_presets` | Načtení presetů doby čtení |
| `smec_save_rt_preset` | Uložení/update presetu |
| `smec_delete_rt_preset` | Smazání presetu |
| `smec_duplicate_rt_preset` | Klonování presetu |
| `smec_save_woocommerce` | WooCommerce nastavení |
| `smec_export_settings` | Export nastavení do JSON |
| `smec_import_settings` | Import nastavení z JSON |
| `smec_process_queue_now` | Manuální zpracování fronty |
| `smec_clear_queue` | Smazání fronty |
| `smec_retry_failed_queue` | Zopakování failed položek |
| `smec_refresh_cache` | Smazání API cache |
| `smec_run_diagnostics` | Spuštění health checks |
| `smec_deactivate_plugin` | Deaktivace konfliktního pluginu |
| `smec_dismiss_notice` | Skrytí diagnostické notifikace |
| `smec_scan_forms` | Přeskenování formulářů |
| `smec_ignore_form` | Přidání formuláře do ignore listu |
| `smec_unignore_form` | Odebrání z ignore listu |
| `smec_save_notifications` | Uložení notifikačních nastavení |
| `smec_test_notification` | Odeslání testovacího alertu |
| `smec_reset_notifier_state` | Reset čítačů chyb |
| `smec_test_webtracking` | Test webtracking injekce na homepage |
| `smec_save_gtm` | Uložení GTM nastavení |
| `smec_test_gtm` | Test GTM injekce na homepage |
| `smec_create_customfield` | Vytvoření nového vlastního pole v SE |
| `smec_get_chart_data` | Data pro grafy aktivity |
| `smec_get_form_monitor_stats` | Statistiky monitoru formulářů |
| `smec_get_form_monthly` | Měsíční statistiky konkrétního formuláře |

---

*Poslední aktualizace: 2026-06-08*
