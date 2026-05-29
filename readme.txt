=== SmartEmailing Connect ===
Contributors: holubik
Tags: smartemailing, email marketing, forms, webtracking, woocommerce
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPL-2.0+

Univerzální WordPress konektor pro SmartEmailing – API integrace, webtracking, propojení formulářů, WooCommerce a modul doby čtení.

== Popis ==

SmartEmailing Connect umožňuje propojit jakýkoliv WordPress web se SmartEmailingem bez nutnosti programování.

**Funkce:**
* API připojení se SmartEmailingem (Basic Auth)
* Automatické vkládání webtracking kódu
* Grafické mapování formulářových polí na SmartEmailing bez programování
* Podpora: Elementor Pro Forms, Contact Form 7, Fluent Forms, WPForms, WP Registrace
* Správa seznamů kontaktů a vlastních polí přímo z WP administrace
* WooCommerce integrace (opt-in checkbox, import zákazníků)
* Modul Doba čtení s plnou vizuální konfigurací
* Logování API volání a retry fronta při chybách
* Export/import nastavení

== Instalace ==

1. Nahrajte složku `smartemailing-connect` do `/wp-content/plugins/`
2. Aktivujte plugin přes menu Pluginy → Aktivovat
3. Přejděte do SmartEmailing → API připojení a zadejte přihlašovací údaje
4. Otestujte připojení tlačítkem "Otestovat připojení"
5. Nastavte webtracking (SmartEmailing → Webtracking) – GUID najdete v SE pod Nastavení → Zdroje dat
6. Načtěte seznamy (SmartEmailing → Seznamy & Pole) a pak přidejte propojení formuláře

== Jak získat API klíč ==

1. Přihlaste se do SmartEmailingu
2. Přejděte do Nastavení → API
3. Vygenerujte nebo zkopírujte API klíč
4. Zadejte klíč a přihlašovací e-mail do pluginu

== Jak získat webtracking GUID ==

1. Přihlaste se do SmartEmailingu
2. Přejděte do Nastavení → Zdroje dat → Webtracking
3. Zkopírujte GUID identifikátor ze skriptu

== Jak napojit formulář ==

1. Přejděte do SmartEmailing → Formuláře
2. Klikněte na "+ Přidat propojení"
3. Vyberte typ formuláře (Elementor, CF7, atd.)
4. Zadejte název/ID formuláře
5. Vyberte cílový seznam kontaktů
6. Na záložce "Systémová pole" namapujte e-mail a další pole
7. Uložte a otestujte odesláním testovacího kontaktu

== Shortcode doby čtení ==

`[smart_reading_time]` – zobrazí dobu čtení aktuálního příspěvku

Parametry:
* `post_id=""` – ID příspěvku (výchozí: aktuální)
* `label=""` – vlastní popisek
* `suffix=""` – vlastní suffix (min, minut...)
* `icon="1"` – zobrazit ikonu (1/0)
* `class=""` – CSS třída

== Konflikty s oficiálním SmartEmailing pluginem ==

Pokud je aktivní oficiální plugin SmartEmailing, plugin zobrazí varování v administraci.
Hrozí duplicitní webtracking. Doporučujeme deaktivovat oficiální plugin a používat jen SmartEmailing Connect.

== Čtení logů ==

Přejděte do SmartEmailing → Logy pro zobrazení API volání, importů a chyb.
Debug režim zapnete v SmartEmailing → Nastavení.

== Changelog ==

= 1.0.0 =
* První vydání
