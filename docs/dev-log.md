# SmartEmailing Connect – Vývojový deník

> **Tento soubor je první co číst.** Každá změna přibyde sem – co bylo uděláno, proč a kde v kódu.
> Nové záznamy přidávej **na začátek** (nejnovější nahoře).

---

## Workflow spolupráce

### Git & verzování
- **Repo:** `https://github.com/Lukasholubik/smartemailing-connect/`
- **Push příkaz:** Napíše-li uživatel **"push"** (nebo "pošli", "pushni"), provedu bez dalšího ptaní:
  1. Bezpečnostní a penetrační audit změněného kódu (viz sekce níže)
  2. Opravím všechny nalezené problémy
  3. `git add` + `git commit` + `git push`
- **Vybízení k pushování:** Sám aktivně připomenu push po větší ucelené změně nebo sérii úprav – nikdy nenechám kód dlouho jen lokálně.
- **Commit zprávy:** Stručně popisují co a proč (česky nebo anglicky dle kontextu), ne jak.
- **Po každé změně:** Záznam do tohoto `dev-log.md` (datum, soubory, co, proč).

### Verzování a GitHub Release

Při každém bumpu verze provést **celý tento postup** (Plugin Update Checker vyžaduje GitHub Release, ne jen tag):

1. Bump `Version:` v hlavičce `smartemailing-connect.php` + `define('SMEC_VERSION', ...)`
2. Aktualizovat `CHANGELOG.md`
3. `git add` + `git commit` (`chore: bump version to X.Y.Z`)
4. `git tag vX.Y.Z`
5. `git push origin main --tags`
6. Vytvořit **GitHub Release** na `https://github.com/Lukasholubik/smartemailing-connect/releases/new?tag=vX.Y.Z`
   - Title: `SmartEmailing Connect X.Y.Z`
   - Body: changelog změn dané verze
   - Publikovat (ne Draft!)
7. Ověřit: `GET https://api.github.com/repos/Lukasholubik/smartemailing-connect/releases/latest`

**Po release – vynutit kontrolu na live webu:**
`https://domena.cz/wp-admin/update-core.php?force-check=1`

### Větve (branching strategie)
- **`main`** = stabilní CORE kód – vždy funkční, vždy prošel bezpečnostním auditem.
- **Feature větve** = při každé nové funkci nebo úpravě, která by mohla narušit chod:
  - Pojmenování: `feature/nazev-funkce`, `fix/nazev-opravy`, `refactor/nazev`
  - Větev se merguje do `main` až po otestování a bezpečnostním auditu
  - Pokud se funkce nepovede → prostý `git checkout main`, větev smažeme
- **Kdy vytvořit větev:** Vždy, když přidáváme novou funkci nebo měníme existující logiku. Pro drobné opravy textu/CSS stačí přímý commit do `main`.

### Bezpečnostní audit před každým pushem
Před každým `git push` automaticky provedu kontrolu:
- **Injection:** SQL injection (přímé dotazy bez `$wpdb->prepare()`), XSS (výstup bez `esc_*`), command injection
- **Auth & capabilities:** Každý AJAX handler a REST endpoint má `check_ajax_referer()` / `verify_nonce` + `current_user_can()`
- **Citlivá data:** Žádný API klíč, heslo, secret nesmí být v kódu nebo logu v plaintextu
- **SSRF:** URL validace pro webhooku a externí požadavky
- **Sanitizace vstupů:** Všechny `$_POST`, `$_GET`, `$_REQUEST` hodnoty sanitizovány před použitím
- **Escapování výstupů:** HTML kontext `esc_html()`, atributy `esc_attr()`, URL `esc_url()`
- **Otevřené přesměrování:** `wp_safe_redirect()` místo `wp_redirect()` kde hrozí manipulace
- Pokud najdu problém → opravím **před** pushem, zapíši do dev-logu

### CSS
- **Framework: Tailwind CSS** – veškeré nové styly píšu v Tailwindu.
- Žádné vlastní CSS třídy pokud to Tailwind zvládne utility třídami.
- Inline `style=""` jen pro dynamické hodnoty (CSS custom properties z nastavení).

---

## Šablona záznamu

```
### RRRR-MM-DD – Stručný popis změny

**Soubory:** `includes/XYZ.php`, `templates/admin/page-xyz.php`
**Co bylo uděláno:** ...
**Proč:** ...
**Pozor na:** ... (volitelné – upozornění, side-effecty, TODO)
```

---

## Záznamy

### 2026-06-08 – GTM modul – JavaScript implementace

**Soubory:** `assets/admin/js/admin.js`  
**Co bylo uděláno:** Přidána funkce `initGtmPage()` a registrace v init bloku (`#smec-save-gtm`).  
Funkce obsahuje:
- **Live preview Container ID** – při psaní do textarea (`#gtm-container`) okamžitě extrahuje Container ID (přímý formát `GTM-XXXXXX` i celý snippet) a zobrazuje ho pod polem.
- **Uložení nastavení** (`#smec-save-gtm`) – odesílá `enabled`, `container_id`, `exclude_admins`, `exclude_roles[]` přes AJAX `smec_save_gtm`, po úspěchu aktualizuje zobrazené Container ID.
- **Test GTM** (`#smec-test-gtm`) – AJAX `smec_test_gtm`, výsledek zobrazí checklistem (✅/❌) a hinty stejně jako webtracking test.

**Stav GTM modulu (kompletní):**
- `includes/GTM/GTM.php` – injekce `<head>` skriptu + noscript (`wp_body_open` + `wp_footer` fallback), vyloučení adminů/rolí, extrakce Container ID ze snippetu
- `includes/Settings.php` – `get_gtm()` / `save_gtm()`, GTM v `modules` array
- `includes/Plugin.php` – GTM registrace jako modul
- `includes/Admin/Admin.php` – `page_gtm()`, AJAX handlery `ajax_save_gtm()` + `ajax_test_gtm()`
- `templates/admin/page-gtm.php` – admin UI s formulářem, návod, info o injekci
- `assets/admin/js/admin.js` – `initGtmPage()` s live preview, save, test

**Proč:** GTM JavaScript chyběl – tlačítka Uložit a Otestovat nefungovala.

---

### 2026-06-08 – Oprava: Vytvořit vlastní pole nefungovalo

**Soubory:** `includes/Admin/Admin.php`, `assets/admin/js/admin.js`  
**Co bylo uděláno:** Tlačítko „Vytvořit" pro vlastní pole na stránce Seznamy & Pole nemělo žádnou implementaci – ani JS handler ani PHP AJAX metodu. Přidán handler `#smec-create-field` do `initListsPage()` a nová metoda `ajax_create_customfield()` v Admin.php. Po vytvoření se automaticky smaže cache vlastních polí.  
**Proč:** Funkce byla vizuálně v šabloně (tlačítko existovalo), ale kód za ní nikdy nebyl implementován.

---

### 2026-06-08 – Moduly: aktivace/deaktivace záložek v menu

**Soubory:** `includes/Admin/Admin.php`, `assets/admin/js/admin.js`  
**Co bylo uděláno:** Záložky Webtracking, Formuláře, WooCommerce, Doba čtení se nyní zobrazují/skrývají dle stavu modulu v Nastavení. Přepínání modulů se ukládá okamžitě při toggling (bez nutnosti klikat na Uložit) a stránka se po 600ms automaticky refreshuje, aby se menu aktualizovalo.  
**Záložky vždy viditelné:** Přehled, API připojení, Seznamy & Pole, Logy, Notifikace, Nastavení.

---

### 2026-06-08 – Oprava false-positive varování „Nedávná chyba API"

**Soubory:** `includes/Diagnostics/Diagnostics.php`, `includes/Logging/Logger.php`  
**Co bylo uděláno:** Diagnostika zobrazovala varování o chybě API i když API mezitím začalo fungovat. Přidána metoda `has_api_success_after($datetime)` do Loggeru. V `check_api()` se varování zobrazí jen pokud: chyba nastala < 1h ago **A** od té doby neproběhlo žádné úspěšné API volání. Ověřeno: po opravě endpointu `contactlists` proběhlo 20 úspěšných volání → varování se nyní nezobrazí.  
**Proč:** Stará chyba (404 na `contact-lists`) byla opravena, ale diagnostika to nerozpoznala.

---

### 2026-06-08 – Statistiky odesláno v monitoru formulářů

**Soubory:** `includes/Forms/FormManager.php`, `includes/Logging/Logger.php`, `includes/Admin/Admin.php`, `templates/admin/page-overview.php`, `assets/admin/js/admin.js`, `assets/admin/css/admin.css`  
**Co bylo uděláno:** Přidán sloupec „Odesláno" do tabulky monitoru formulářů. Přepínač 30 dní / 12 měsíců. Kliknutím na číslo otevře modal s měsíčním sloupcovým grafem (Chart.js). Doplněny klíče `form_id` a `mapping_name` do import logu v FormManager – nutné pro párování statistik s formuláři.  
**Pozor na:** Historické logy (před touto úpravou) nemají `form_id` v kontextu – pro ně se bude zobrazovat 0. Data se naplní od teď pro nové importy.

---

### 2026-06-08 – Grafy aktivity na přehledu

**Soubory:** `includes/Logging/Logger.php`, `includes/Admin/Admin.php`, `templates/admin/page-overview.php`, `assets/admin/js/admin.js`, `assets/admin/css/admin.css`  
**Co bylo uděláno:** Přidány grafy na stránku Přehled. Přepínač 30 dní / 12 měsíců. Graf „API volání & Importy" (čarový, 3 série: API volání / Importy kontaktů / Chyby). Graf „Kontakty dle formuláře" (horizontální sloupcový, rozpad dle mapping name). Chart.js 4.4.0 načítán z CDN (jen na přehledové stránce). Data z tabulky `smec_logs` přes nový AJAX handler `smec_get_chart_data`.  
**Pozor na:** Graf „dle formuláře" závisí na klíči `mapping_name` v context JSON importních logů. Pokud FormManager neloguje tento klíč, ukáže se „Jiný". Doplnit logging v FormManager při budoucích úpravách.

---

### 2026-06-08 – Průvodce propojením formulářů

**Soubory:** `templates/admin/page-forms.php`, `assets/admin/js/admin.js`, `assets/admin/css/admin.css`  
**Co bylo uděláno:** Nahrazena minimální nápověda plnohodnotným průvodcem rozděleným do 6 kroků + přehledem placeholderů. Průvodce je sbalitelný (toggle ▼/▶). Každá záložka editoru (Základní, Systémová pole, Vlastní pole, Štítky & Podmínky, Test) má vlastní sekci s tabulkami a příklady.  
**Proč:** Admin nevěděl co vyplnit do jednotlivých polí (zejm. klíče polí formuláře, typy zdrojů, placeholdery).

---

### 2026-06-08 – Oprava endpointu contact-lists → contactlists

**Soubory:** `includes/Api/ApiService.php`  
**Co bylo uděláno:** Opraveny oba výskyty špatného endpointu `contact-lists` na `contactlists` (bez pomlčky) – v `get_contact_lists()` i `create_contact_list()`.  
**Proč:** SmartEmailing API v3 používá konvenci bez pomlček (stejně jako `customfields`). Endpoint `contact-lists` vracel HTML 404 stránku „SmartEmailing 2.1". Diagnostikováno z DB logů `smec_logs` + ověřeno přímým voláním API.  
**Pozor na:** Pokud by SE v budoucnu přidalo další endpointy, vždy ověřit pojmenování – konvence je camelCase/nohyphen (viz `contactlists`, `customfields`, `import`).

---

### 2026-06-08 – Tlačítko „Otestovat webtracking"

**Soubory:** `includes/Admin/Admin.php`, `templates/admin/page-webtracking.php`, `assets/admin/js/admin.js`, `assets/admin/css/admin.css`  
**Co bylo uděláno:** Přidáno tlačítko „Otestovat webtracking" na stránce Webtracking v adminu. Server-side AJAX handler stáhne homepage přes `wp_remote_get()` a zkontroluje přítomnost tracking skriptu a GUID ve zdrojovém kódu. Výsledek zobrazí checklistem (✅/❌) a případnými nápovědami při varování.  
**Proč:** Admin potřeboval jednoduché ověření, zda webtracking skutečně běží – bez nutnosti ručně prohlížet zdrojový kód stránky.  
**Pozor na:** Test vždy načítá homepage jako nepřihlášený uživatel. Pokud je aktivní caching plugin, může vidět cachovanou verzi bez tracking skriptu – to je pak false negative, ne chyba nastavení.

---

### 2026-06-08 – Vytvoření dokumentační složky

**Soubory:** `docs/overview.md`, `docs/settings-reference.md`, `docs/dev-log.md`  
**Co bylo uděláno:** Zřízena složka `docs/` s přehledem architektury pluginu, referencí všech option klíčů a tímto vývojovým deníkem.  
**Proč:** Zajistit kontinuitu mezi session – rychlé zorientování bez nutnosti znovu procházet zdrojový kód.

---

### 2026-05-29 – Verze 1.0.0 – Počáteční vytvoření pluginu

**Verze:** 1.0.0 → 1.0.2  
**Co bylo vytvořeno:**
- API připojení SmartEmailing (Basic Auth, ping test)
- Webtracking – automatické vkládání tracking skriptu s vyloučeními (role, stránky, admini)
- Správa kontaktních listů a vlastních polí (načítání z API, tvorba nových)
- Propojení formulářů: Elementor Pro, CF7, Fluent Forms, WPForms, WP Registrace
- WooCommerce: opt-in checkbox na pokladně, import zákazníků na status objednávky
- Modul Doba čtení: presety, shortcode `[smart_reading_time]`, Gutenberg blok, auto-insert
- Diagnostický systém – detekce výpadků s popisem a řešením
- Form Scanner – přehled všech formulářů na webu vs. napojení na SE
- Notifikační systém – e-mail, Slack webhook, generic webhook, cooldown
- Queue/retry mechanismus (hourly cron, max 3 pokusy)
- Logy do DB (`smec_logs`), retence, export
- Export/import nastavení jako JSON
- Plugin Update Checker (GitHub releases)
- Grou.cz admin skupina (vizuální seskupení v menu)

**Architektura:**
- Prefix options: `smec_`
- Prefix tříd: `SMEC_`
- DB tabulky: `{prefix}smec_logs`, `{prefix}smec_queue`
- Nonce: `smec_admin_nonce`

---
