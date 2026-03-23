# Releasing a new version

## Krok po kroku

1. **Zmień wersję** w `important-cta.php` (linia `Version:` i stała `ICTA_VERSION`)

2. **Commit i push do `main`**
   ```bash
   git add .
   git commit -m "Release v1.1.0"
   git push origin main
   ```

3. **Utwórz GitHub Release**
   ```bash
   gh release create v1.1.0 --title "v1.1.0" --notes "Opis zmian"
   ```
   Lub przez UI: GitHub → Releases → Draft a new release → tag `v1.1.0`

4. **WordPress wykryje update automatycznie** — pojawi się w Dashboard → Aktualizacje

## Ważne

- Tag musi mieć prefix `v` (np. `v1.1.0` nie `1.1.0`)
- Wersja w `important-cta.php` musi być wyższa niż poprzednia
- Plugin-update-checker sprawdza GitHub Releases, nie tagi bez release

## Numery wersji (SemVer)

- `1.0.x` — bugfixy, bez nowych funkcji
- `1.x.0` — nowe funkcje, wsteczna kompatybilność
- `x.0.0` — breaking changes
