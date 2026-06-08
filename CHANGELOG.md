# Changelog – SmartEmailing Connect

Všechny výrazné změny jsou dokumentovány v tomto souboru.
Formát dle [Keep a Changelog](https://keepachangelog.com/cs/1.0.0/).

## [1.2.0] – 2026-06-08

### Přidáno
- Modul Google Tag Manager – vložení GTM kódu přímo do webu zadáním Container ID (nebo celého GTM snippetu)
- GTM script vložen do `<head>` (priorita 1), noscript iframe ihned za `<body>` (wp_body_open) s fallbackem do patičky
- Možnost vyloučit administrátory a konkrétní role z načítání GTM
- Tlačítko „Otestovat GTM" – server-side ověření přítomnosti GTM kódu na homepage
- GTM modul přidán do sekce Moduly v Nastavení (lze zapnout/vypnout)

## [1.1.0] – 2026-06-08

### Přidáno
- Průvodce propojením formulářů – krok po kroku s příklady pro každou záložku
- Grafy aktivity na přehledu (Chart.js) – API volání, importy, chyby za 30 dní nebo 12 měsíců
- Sloupec „Odesláno" v monitoru formulářů s přepínačem 30d/12m a měsíčním drill-down grafem
- Tlačítko „Otestovat webtracking" – server-side ověření přítomnosti skriptu na homepage
- Auto-refresh seznamů a vlastních polí při otevření stránky Seznamy & Pole
- Záložky v admin menu se skrývají/zobrazují dle stavu modulu v Nastavení
- Přepínání modulů se ukládá okamžitě bez nutnosti klikat na Uložit

### Opraveno
- API endpoint `contact-lists` → `contactlists` (správná konvence SE API v3)
- Tlačítko „Vytvořit vlastní pole" – chyběl AJAX handler, kliknutí nedělalo nic
- Duplicitní tabulky na stránce Seznamy & Pole po načtení ze SmartEmailingu
- False-positive varování „Nedávná chyba API" když API mezitím začalo fungovat
- `form_id` a `mapping_name` přidány do import logu pro správné párování statistik

## [1.0.0] – 2026-05-29

### Přidáno
- API připojení SmartEmailing (Basic Auth, ping test)
- Webtracking – automatické vkládání tracking skriptu s vyloučeními
- Správa seznamů kontaktů a vlastních polí ze SmartEmailing API
- Propojení formulářů: Elementor Pro, Contact Form 7, Fluent Forms, WPForms, WP Registrace
- WooCommerce integrace – opt-in checkbox, import zákazníků
- Modul Doba čtení – presety, vlastní SVG ikona, shortcode, Gutenberg blok
- Diagnostický systém – detekce výpadků s doporučenými řešeními
- Monitor formulářů – přehled všech formulářů na webu vs. napojení
- Notifikační systém – e-mail, Slack webhook, generic webhook
- Queue/retry mechanismus pro selhané importy
- Logy a diagnostika
- Export/import nastavení
- Grou.cz admin skupina
