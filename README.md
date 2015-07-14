## Antareja EPP - WHMCS .ID EPP Module

- - - -

About | Description
------------ | -------------
Stable tag | 0.9.0
License | GPLv2 or later
License URI | http://www.gnu.org/licenses/gpl-2.0.html

### Description

This is an opensource .ID EPP registrar module for WHMCS. Forked from [WHMCS-Coza-EPP](https://gitlab.devlabs.linuxassist.net/awit-whmcs/whmcs-coza-epp/wikis/home).

### Installation

If you have any troubles during installation please contact us at registrar<at>isi.co.id.

#### Setting up the registrar module

1. Put `antareja` folder to /modules/registrars/.
2. Configure server address and default nameserver in antarejaconfig.php.
3. Enter your registrar login details and certificate path (this is the full server-side filesystem path to your .pem file).
4. Save settings.

#### Setting up the antarejasync.php script

Edit your crontab and include something like this:

```php
15 9 * * * /usr/bin/php -q /var/www/whmcs/modules/registrars/antareja/antarejasync.php
```