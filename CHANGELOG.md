# Changelog
All notable changes to this project will be documented in this file.

## [2.8.15] - 18/06/2026
- Bump `@kiliba-codebase/cms-popup` to `1.0.46`.
- Restore popup info mode default wordings on storefront popups after the shared popup runtime release.

## [2.8.14] - 15/06/2026
- Bump `@kiliba-codebase/cms-popup` to `1.0.45`.
- Add popup birthday collection and save-only signup support on Magento storefront popups.

## [2.8.13] - 05/06/2026
- Bump `@kiliba-codebase/cms-popup` to `1.0.44`.
- Add popup V2 info mode, reopen launcher, and per-field typography controls (`fontSize`, `lineHeight`) in storefront popups.

## [2.8.12] - 04/06/2026
- Bump `@kiliba-codebase/cms-popup` to `1.0.41`.
- Add popup locale support for `nl`, `pt`, `nb` and `sv` in storefront popups.

## [2.8.11] - 29/05/2026
- Guard cart webhook date formatting against missing quote timestamps to avoid PHP 8.2 `strtotime(null)` failures during checkout saves.
- Guard order webhook date formatting against missing order timestamps to avoid PHP 8.2 `strtotime(null)` failures during early save events.
- Skip early order webhook saves until Magento exposes a real status or state.
- Fallback webhook authentication token lookup from website scope to default scope so legacy single-token Magento setups can send live cart and order webhooks again.

## [2.8.10] - 26/05/2026
- Bump `@kiliba-codebase/cms-popup` to `1.0.40` to restore click handling on mobile popups displayed in corner mode.

## [2.8.9] - 22/05/2026
- Bump `@kiliba-codebase/cms-popup` to `1.0.39` to restore visible popup consent controls on themes that hide native checkbox and radio inputs.

## [2.8.8] - 21/05/2026
- Bump `@kiliba-codebase/cms-popup` to `1.0.36` to fix the remaining popup visual regressions on legacy and centered V2 image-top layouts.

## [2.8.7] - 21/05/2026
- Bump `@kiliba-codebase/cms-popup` to `1.0.35` to restore the historical legacy popup rendering after module updates.

## [2.8.6] - 20/05/2026
- Resolve popup preview payloads through a short-lived Kiliba token instead of a long query string, avoiding `URI Too Long` failures on large V2 previews.

## [2.8.5] - 12/05/2026
- Add native Magento wishlist pull data (`pullDatas?model=wishlist`) for the Kiliba wishlist scenario.

## [2.8.4] - 07/04/2026
- Add translate for configurable products

## [2.8.3] - 03/02/2026
- Add SMS optin for pop-up registrations

## [2.8.2] - 20/01/2026
- Fix db schema padding on popup subscribe column to restore setup:upgrade in developer mode.
- Expose order promo codes in API export/schema.

## [2.8.1] - 22/12/2025
- Ajoute la collecte `customers_guest` pour synchroniser les abonnés newsletter invités (email uniquement, statut, métadonnées).

## [2.8.0] - 10/12/2025
- Push cart and order webhooks to Kiliba webhook endpoint
- Remove enhanced mode for carts
- Ping Kiliba on module setup:upgrade (apply data patch)
## [2.7.1] - 09/12/2025
- Add pop-up registrations pull data.
## [2.7.0] - 28/11/2025
- Add **promo code first purchase** pop-up.
## [2.6.3] - 27/10/2025
- Fix `auto_raw` bundle mode
## [2.6.2] - 23/09/2025
- Delete unused legacy SyncInterface
## [2.6.1] - 22/09/2025
- PHP 8.4 compatibility
## [2.6.0] - 09/07/2025
- Add catalog rules synchronization
## [2.5.0] - 04/03/2025
- Compatibility with frontend themes without jQuery or RequireJS
## [2.4.2] - 11/02/2025
- Bundle price mode: auto_raw (take prices as displayed on Magento)
## [2.4.1] - 06/02/2025
- Add overridable method to compute product prices
## [2.4.0] - 27/01/2025
- Happy new year 2025
- Add enhanced mode for quotes
## [2.3.0] - 10/09/2024
- Add translations for product name and description
- Add customer groups synchronization
## [2.2.9] - 12/08/2024
- Add bundle products in products synchronization (requires plugin to custom prices)
## [2.2.8] - 07/08/2024
- Add security when instantiating salable quantity class (check MSI enabled)
- Force products order by ID
## [2.2.7] - 05/08/2024
- Add security when instantiating salable quantity class
## [2.2.6] - 30/05/2024
- Set token scope to website
- Use stronger PHP methods for generating token
## [2.2.5] - 25/04/2024
- Get products SKU
## [2.2.4] - 15/04/2024
- Add promo code code to promo code label and description
## [2.2.3] - 23/03/2024
- Add currency to debug endpoint
## [2.2.2] - 30/01/2024
- Add Kiliba config menu to ACL
- Enhance properties returned by Collect::pullDatas() for custom plugins
## [2.2.1] - 14/12/2023
- Handle multi sources salable quantity
## [2.2.0] - 08/11/2023
- Track guests for carts and visits
## [2.1.5] - 27/10/2023
- Get all product images
## [2.1.4] - 06/10/2023
- Migrate to new Kiliba API (backend-api.production-api.kiliba.eu/external_api)
## [2.1.3] - 05/10/2023
- List categories of products
- PullDatas endpoint: added model category
- PullDatas endpoint: added models priceRules and coupons
## [2.1.2] - 27/07/2023
- Fix compatibility PHP 8.2
## [2.1.1] - 27/07/2023
- Fix quote items gathering
- Add guest checkout
## [2.1.0] - 11/05/2023
- Compatibility Magento 2.2
- Fix discount to use website
## [2.0.8] - 11/05/2023
- Get store locale from config (including config file)
## [2.0.7] - 10/05/2023
- Fix customers listing (filter by website ID)
## [2.0.6] - 28/03/2023
- Fix compatibility PHP7.3
## [2.0.5] - 23/03/2023
- MultiStore: Fixed a bug that prevented some visits from being reported
- MultiStore: Fixed incorrect price return
## [2.0.0] - 01/02/2023
