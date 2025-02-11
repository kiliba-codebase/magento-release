# Changelog
All notable changes to this project will be documented in this file.

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
