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
- **Suivi des ouvertures** : les pulls `customer` et `customers_guest` exposent `pixel_tracking_status`, `pixel_tracking_updated_at`, `pixel_tracking_source` et `pixel_tracking_policy_version`. `POST /rest/all/V1/kiliba-connector/setCustomerEmailOpenTracking` permet de mettre ces valeurs à jour sur les attributs client configurés et les colonnes newsletter compatibles.
  L’appel accepte `id_customer` ou `email`, ainsi que `status`, `updated_at`, `source` et `policy_version`, en plus des paramètres d’authentification habituels `accountId` et `token`. Les codes d’attributs client se configurent dans le back-office ou via `setConfig` avec les clés `KILIBA_CUSTOMER_PIXEL_TRACKING_*_FIELD`.
- **Popups Kiliba** :
  - Configuration : `POST /rest/all/V1/kiliba-connector/popup/{popupType}/configuration` prend `token`, `accountId`, `config` (JSON) et retourne `success: true` en première position si l’enregistrement réussit.
  - Activation : `POST /rest/all/V1/kiliba-connector/popup/{popupType}/activation` prend `token`, `accountId`, `activation` (timestamp Unix ou `0`) et retourne `success: true`.
  - Upload d’image : `POST /rest/all/V1/kiliba-connector/uploadImage` accepte soit un champ multipart `image`, soit un champ `image` en base64 (optionnellement accompagné d’un `mimeType`) et répond avec `success: true` et `relative_path` vers le fichier stocké.

## Suivi des ouvertures des emails

Le connecteur `2.8.24` ou supérieur peut synchroniser la décision de suivi des ouvertures stockée par Magento. Cette information est distincte de l’abonnement newsletter et du consentement à recevoir des communications marketing.

### Attributs client

Le connecteur lit des attributs client Magento existants. Il ne les crée pas automatiquement, car leur création, leur type et leur alimentation dépendent du parcours de consentement de la boutique.

| Champ envoyé à Kiliba | Code d’attribut par défaut | Type Magento recommandé | Obligatoire |
| --- | --- | --- | --- |
| `pixel_tracking_status` | `pixel_tracking_status` | `varchar` ou `text` | Oui pour transmettre une décision explicite |
| `pixel_tracking_updated_at` | `pixel_tracking_updated_at` | `datetime`, `varchar` ou `text` | Non |
| `pixel_tracking_source` | `pixel_tracking_source` | `varchar` ou `text` | Non |
| `pixel_tracking_policy_version` | `pixel_tracking_policy_version` | `varchar` ou `text` | Non |

Chaque code peut être remplacé au scope **Website** dans `Stores > Configuration > Kiliba > Connector`. Une valeur vide dans la configuration réactive le code par défaut. Un code invalide ou un attribut inexistant est ignoré et produit une valeur `null` dans le pull.

Les mêmes mappings peuvent être configurés à distance avec l’endpoint `setConfig` et les clés suivantes :

- `KILIBA_CUSTOMER_PIXEL_TRACKING_STATUS_FIELD`
- `KILIBA_CUSTOMER_PIXEL_TRACKING_UPDATED_AT_FIELD`
- `KILIBA_CUSTOMER_PIXEL_TRACKING_SOURCE_FIELD`
- `KILIBA_CUSTOMER_PIXEL_TRACKING_POLICY_VERSION_FIELD`

L’endpoint `debug` retourne les mappings effectivement résolus dans `customerPixelTrackingFields`, ce qui permet de contrôler le website associé sans exposer les valeurs des clients.

### Valeurs de statut acceptées

`pixel_tracking_status` est une valeur texte, jamais un booléen ni `0`/`1` :

| Valeur | Signification |
| --- | --- |
| `unknown` | Aucune décision exploitable n’est connue. |
| `legacy_not_opposed` | L’email d’information Kiliba a été livré et le contact ne s’est pas opposé. Ce statut n’est pas un consentement explicite. |
| `consented` | Le contact a explicitement accepté le suivi des ouvertures. |
| `refused` | Le contact a explicitement refusé le suivi. |
| `withdrawn` | Le contact a retiré un consentement précédemment donné. |

Pour un nouveau contact dont Magento transmet une décision explicite, utiliser `consented`, `refused` ou `withdrawn`. `legacy_not_opposed` est normalement écrit par Kiliba après le scénario d’information historique et ne doit pas être utilisé par le formulaire marchand pour simuler un consentement.

`pixel_tracking_updated_at` accepte une date reconnue par PHP. Une date ISO 8601 avec fuseau, par exemple `2026-09-15T10:30:00Z`, est recommandée. Le connecteur la normalise en UTC et l’adapte si l’attribut ou la colonne Magento est de type `datetime`, `timestamp` ou `date`.

### Abonnés newsletter sans compte client

Pour les abonnés invités, le connecteur lit les colonnes optionnelles suivantes dans `newsletter_subscriber` lorsqu’elles existent :

- `pixel_tracking_status`
- `pixel_tracking_updated_at`
- `pixel_tracking_source`
- `pixel_tracking_policy_version`

Ces noms de colonnes ne sont pas configurables. Une mise à jour effectuée par le connecteur actualise aussi `change_status_at`, afin que le pull incrémental `customers_guest` reprenne la ligne.

### Mise à jour par API

L’endpoint authentifié accepte un identifiant client ou une adresse email. Si les deux sont fournis, ils doivent désigner le même client du website associé au compte Kiliba.

```bash
curl --request POST \
  "https://magento.example/rest/all/V1/kiliba-connector/setCustomerEmailOpenTracking" \
  --data-urlencode "accountId=KILIBA_ACCOUNT_ID" \
  --data-urlencode "token=MAGENTO_CONNECTOR_TOKEN" \
  --data-urlencode "id_customer=123" \
  --data-urlencode "status=consented" \
  --data-urlencode "updated_at=2026-09-15T10:30:00Z" \
  --data-urlencode "source=checkout_consent" \
  --data-urlencode "policy_version=2026-04"
```

Champs acceptés :

- `id_customer` ou `email` pour identifier le contact ;
- `status`, `updated_at`, `source` et `policy_version` pour les valeurs à modifier ;
- `accountId` et `token` pour authentifier l’appel et résoudre le website.

Une mise à jour client sauvegardée par Magento actualise `customer_entity.updated_at`. Le pull incrémental `customer` peut donc récupérer le changement sans resynchronisation complète. Après la première installation ou l’ajout d’attributs déjà alimentés, lancer néanmoins une synchronisation complète des clients pour reprendre l’historique.
