# Releasing a new version

## Krok po kroku

1. **Zmień wersję** w `important-cta.php`:
   - Linia `Version: X.Y.Z` w komentarzu nagłówkowym
   - Stała `ICTA_VERSION` — musi być identyczna

2. **Commit i push do `main`**
   ```bash
   git add .
   git commit -m "Release v3.1.0"
   git push origin main
   ```

3. **Utwórz GitHub Release**
   ```bash
   gh release create v3.1.0 --title "v3.1.0" --notes "Opis zmian"
   ```

4. **WordPress wykryje update automatycznie** (plugin-update-checker sprawdza GitHub Releases)

## Deploy na serwer (natychmiastowy)

Jeśli nie chcesz czekać na auto-update:

```bash
cd /path/to/important-cta
zip -r /tmp/important-cta.zip . --exclude "*.git*"
scp /tmp/important-cta.zip root@65.21.75.39:/tmp/

ssh root@65.21.75.39 "
  docker cp /tmp/important-cta.zip wordpress-gkwos4s8c0k0gkcwow48g0cg:/tmp/
  docker exec wordpress-gkwos4s8c0k0gkcwow48g0cg bash -c '
    rm -rf /tmp/icta-tmp
    unzip -q /tmp/important-cta.zip -d /tmp/icta-tmp
    rm -rf /var/www/html/wp-content/plugins/important-cta
    mkdir -p /var/www/html/wp-content/plugins/important-cta
    cp -r /tmp/icta-tmp/assets /tmp/icta-tmp/includes /tmp/icta-tmp/templates \
          /tmp/icta-tmp/vendor /tmp/icta-tmp/important-cta.php \
          /tmp/icta-tmp/RELEASING.md /tmp/icta-tmp/CLAUDE.md \
          /var/www/html/wp-content/plugins/important-cta/
    echo Done
  '
"
```

**UWAGA:** Nigdy `unzip -d important-cta` — tworzy zagnieżdżoną strukturę. Zawsze temp dir + copy.

## Numery wersji (SemVer)

- `x.y.Z` — bugfixy, bez nowych funkcji
- `x.Y.0` — nowe funkcje, wsteczna kompatybilność
- `X.0.0` — breaking changes

## Ważne

- Tag musi mieć prefix `v` (np. `v3.1.0` nie `3.1.0`)
- Wersja w `important-cta.php` musi być wyższa niż poprzednia
- Auto-updater: `lukelocksmith/important-cta` na GitHub
- Po zmianie CSS/JS: ICTA_VERSION jest cache-busterem, więc bump = odświeżenie
