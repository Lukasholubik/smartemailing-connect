# Jak vydat novou verzi pluginu

## Rychlý postup (2 minuty)

### 1. Změň verzi na dvou místech

**`smartemailing-connect.php`** – řádek `define( 'SMEC_VERSION', '...' )`:
```
define( 'SMEC_VERSION', '1.1.0' );
```

**Hlavička pluginu** (nahoře ve stejném souboru):
```
 * Version: 1.1.0
```

### 2. Zapsat changelog

Do `CHANGELOG.md` přidej sekci:
```
## [1.1.0] – 2026-06-15
### Přidáno
- Nová funkce XY
### Opraveno
- Bug v mapování polí
```

### 3. Commit a push

```bash
git add .
git commit -m "Release v1.1.0 – popis změn"
git push origin main
```

### 4. Vytvořit GitHub Release (plugin-update-checker to detekuje)

Na GitHubu: **Releases → Draft a new release**
- Tag: `v1.1.0`
- Title: `SmartEmailing Connect 1.1.0`
- Description: zkopíruj z CHANGELOG.md
- Klik **Publish release**

### Výsledek

WordPress na klientských webech zobrazí do ~12 hodin (nebo při manuálním refreshi):
> „Dostupná aktualizace: SmartEmailing Connect 1.1.0"

Kliknutím se plugin aktualizuje automaticky jako každý jiný plugin.

---

## Private repository (GitHub Token)

Pokud je repo private, přidej do `wp-config.php` na klientském webu:

```php
define( 'SMEC_GITHUB_TOKEN', 'ghp_tvujPersonalAccessToken' );
```

A odkomentuj řádek v `smartemailing-connect.php`:
```php
$smec_update_checker->setAuthentication( defined( 'SMEC_GITHUB_TOKEN' ) ? SMEC_GITHUB_TOKEN : '' );
```

Token vytvoříš na GitHubu: **Settings → Developer settings → Personal access tokens → Fine-grained tokens**
(oprávnění: pouze `Contents: Read`)

---

## Alternativa: sledovat větev místo releases

Pokud nechceš tvořit GitHub releases, lze sledovat větev přímo:
```php
$smec_update_checker->setBranch( 'main' );
```
WordPress pak detekuje změnu kdykoliv pushneš commit na `main`.
(Méně přehledné, doporučujeme releases.)

---

## Verzovací schéma

```
MAJOR.MINOR.PATCH
1.0.0 → 1.0.1  bugfix
1.0.1 → 1.1.0  nová funkce (zpětně kompatibilní)
1.1.0 → 2.0.0  breaking change (pozor u klientů!)
```
