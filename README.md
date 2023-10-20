# Kiliba Connector Module - Installation Guide

## Zip Installation
1. In app/code create folder Kiliba/Connector
2. Unzip content in that folder
3. Open command line and go to the magento root directory
4. Enable module : php bin/magento module:enable Kiliba_Connector
5. Upgrade database : php bin/magento setup:upgrade
6. Re-run compile command : php bin/magento setup:di:compile
7. Update static files by: php bin/magento setup:static-content:deploy 
8. Clean cache: php bin/magento cache:flush
