# Kiliba Connector Module - Installation Guide

## Composer installation
1. Get dependency: `composer require kiliba/module-connector`
2. Enable module: `php bin/magento module:enable Kiliba_Connector`
3. Upgrade database: `php bin/magento setup:upgrade`
4. Re-run compile command: `php bin/magento setup:di:compile`
5. Update static files: `php bin/magento setup:static-content:deploy`
6. Clean cache: `php bin/magento cache:flush`

## Zip Installation
1. In app/code create folder Kiliba/Connector
2. Unzip content in that folder
3. Open command line and go to the magento root directory
4. Enable module: `php bin/magento module:enable Kiliba_Connector`
5. Upgrade database: `php bin/magento setup:upgrade`
6. Re-run compile command: `php bin/magento setup:di:compile`
7. Update static files: `php bin/magento setup:static-content:deploy`
8. Clean cache: `php bin/magento cache:flush`

## Points de contrôle Kiliba

Les échanges attendus par Kiliba sont couverts par les appels suivants :

- **Onboarding** : `Helper/KilibaCaller::checkBeforeStartSync()` envoie un `POST` vers `https://backend-api.production-api.kiliba.eu/external_api/checkformmagento` avec `id_account`, `url_shop`, `token`, `locale` et `url_logo` pour valider le CMS, refuser les doublons de boutique et déclencher la synchronisation côté Kiliba.
- **Présence du module** : `POST /rest/all/V1/kiliba-connector/debug?token=...&accountId=...` renvoie un tableau dont le premier élément contient `version`, confirmant l’installation du module.
- **Récupération de données** : `POST /rest/all/V1/kiliba-connector/pullDatas` attend `model`, `limit`, `offset`, `token` et `accountId` et retourne un tableau où le premier objet expose `results` (ainsi que `total_size`, `model`, `limit`, `offset`).
- **Guests newsletter** : `POST /rest/all/V1/kiliba-connector/pullDatas?model=customers_guest` retourne les inscrits à la newsletter dont `customer_id = 0` (uniquement l’adresse e-mail et les métadonnées d’inscription / statut).
- **Popups Kiliba** :
  - Configuration : `POST /rest/all/V1/kiliba-connector/popup/{popupType}/configuration` prend `token`, `accountId`, `config` (JSON) et retourne `success: true` en première position si l’enregistrement réussit.
  - Activation : `POST /rest/all/V1/kiliba-connector/popup/{popupType}/activation` prend `token`, `accountId`, `activation` (timestamp Unix ou `0`) et retourne `success: true`.
  - Upload d’image : `POST /rest/all/V1/kiliba-connector/uploadImage` accepte soit un champ multipart `image`, soit un champ `image` en base64 (optionnellement accompagné d’un `mimeType`) et répond avec `success: true` et `relative_path` vers le fichier stocké.
