**Example** 1c.php in the root of the site:

`<?php
 require_once __DIR__ . '/vendor/autoload.php';
 
 use PeterLS\Integration\Integration;
 
 define('AUTH_KEY', '123');
 
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
 }`