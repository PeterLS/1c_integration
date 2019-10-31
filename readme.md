##Example 1c.php in the root of the site:

```php
require_once __DIR__ . '/vendor/autoload.php';

use PeterLS\Integration\Integration;

define('AUTH_KEY', '123'); //your auth key

if (!isset($_GET['autoload'])) {
  $_GET['autoload'] = '';
}
$int = new Integration();
require_once __DIR__ . '/config_db.php';
$int->setDbParams(['host' => DB_HOSTNAME, 'name' => DB_DATABASE, 'user' => DB_USERNAME, 'password' => DB_PASSWORD]);
$int->setAuthKey(AUTH_KEY);
$int->setOc('opencart');
$int->setDirImage(__DIR__ . '/image/catalog/import');
$int->setImportDir(__DIR__ . '/import_files');

if (!empty($_GET['action'])) {
  if ($_GET['action'] == 'import') {
    $int->startImport($_GET['autoload']);
  } elseif ($_GET['action'] == 'get_orders') {
    $start_date = empty($_GET['date_from']) ? NULL : strtotime($_GET['date_from']);
    $end_date = empty($_GET['date_to']) ? NULL : strtotime($_GET['date_to']);
    $int->getOrders($_GET['autoload'], $start_date, $end_date);
  }
}
if(!empty($int->getErrors()) && !empty($_GET['debug'])) {
  var_dump($int->getErrors());
}
```

##Then, URL addresses:

- For import from 1C to Site: http://site.ru/1c.php?autoload=123&action=import
- For export orders from Site to 1C: http://site.ru/1c.php?autoload=123&action=get_orders.<br/>
If parameters date_from and date_to are not sent, system will return a list of orders for today.<br/>
Optional parameters can be added:
    1. date_from (today default)
    2. date_to (today default)