<?php


namespace PeterLS\Integration;

use Exception;
use PclZip;
use SimpleXMLElement;

class Integration {
  private $oc = NULL;
  private $auth_key = NULL;
  private $shell_start = FALSE;
  private $uri_delimeter = ''; // for example: proftomsk.ru in URI /var/www/proftomsk/data/www/proftomsk.ru/image/catalog/import

  private $export_dir = NULL;
  private $import_dir = NULL;
  private $image_dir = NULL;
  private $default_image = '';
  private $default_pirce_type = 'Розничная';

  private $db_params = ['host' => 'localhost', 'name' => '', 'user' => 'root', 'password' => '', 'port' => 0];
  private $unlink_files = TRUE;

  private $errors = [];

  /**
   * @param string $auth_key
   * @param string $xml_file_name
   * @param string $zip_file_name
   * @return bool|void
   */
  public function startImport(string $auth_key, string $xml_file_name = '', string $zip_file_name = '') { //from 1C
    if (!$this->checkSettingsBeforeImport() || !$this->checkAuthKey($auth_key)) {
      return FALSE;
    }

    if (empty($zip_file_name)) {
      $zip_file = $this->getLastFile($this->import_dir, 'zip');
    } else {
      if (file_exists($this->import_dir . '/' . $zip_file_name . '.zip')) {
        $zip_file = $this->import_dir . '/' . $zip_file_name . '.zip';
      } else {
        $zip_file = FALSE;
      }
    }

    if ($zip_file !== FALSE) {
      require_once __DIR__ . "/libs/pclzip.lib.php";
      $archive = new PclZip($zip_file);
      $result = $archive->extract(PCLZIP_OPT_PATH, $this->image_dir);
      if ($result == 0) {
        $this->setError('Не удалось распаковать архив. Пожалуйста, попробуйте сделать выгрузку снова.');
        return FALSE;
      }
      if ($this->unlink_files) {
        unlink($zip_file);
      }
    }

    if (empty($xml_file_name)) {
      $xml_file = $this->getLastFile($this->import_dir, 'xml');
    } else {
      if (file_exists($this->import_dir . '/' . $xml_file_name . '.xml')) {
        $xml_file = $this->import_dir . '/' . $xml_file_name . '.xml';
      } else {
        $xml_file = FALSE;
      }
    }

    if ($xml_file !== FALSE) {
      $this->load($xml_file);
    } else {
      return FALSE;
    }
  }

  /**
   * @param string $auth_key
   * @param int|NULL $start_date - метка Unix
   * @param int|NULL $end_date - метка Unix
   * @return bool|void
   */
  public function getOrders(string $auth_key, int $start_date = NULL, int $end_date = NULL) {
    if (!$this->checkAuthKey($auth_key)) {
      return FALSE;
    }

    if (is_null($start_date)) {
      $start_date = strtotime(date('Y-m-d 00:00:00'));
    }
    if (is_null($end_date)) {
      $end_date = strtotime(date('Y-m-d 23:59:59'));
    }

    if ($start_date > $end_date) {
      $temp = $end_date;
      $end_date = $start_date;
      $start_date = $temp;
      unset($temp);
    }

    $orders = $this->oc->getOrders($start_date, $end_date);
    $xmlstr = '<?xml version="1.0" encoding="UTF-8"?>';
    $xmlstr .= '<Orders>';

    foreach ($orders as $order) {
      $paid = empty($order['paid']) ? 'false' : 'true';
      $xmlstr .= '<Order Delivery="' . $order['shipping'] . '" Data="' . $order['date_added'] . '" Number="' . $order['id'] . '" Description="' . $order['comment'] . '" Coupon="' . $order['coupon'] . '" Paid="' . $paid . '">';
      $xmlstr .= '<Client FIO="' . $order['customer_name'] . '" Phone="' . $order['telephone'] . '" Email="' . $order['email'] . '" />';

      $xmlstr .= '<Goods>';
      foreach ($order['products'] as $product) {
        $xmlstr .= '<Item Guid="' . $product['guid'] . '" Code="' . $product['code'] . '" Quantity="' . $product['quantity'] . '" Price="' . $product['price'] . '" Summ="' . $product['total'] . '" Discont="' . $product['discount'] . '" />';
      }
      $xmlstr .= '</Goods>';
      $xmlstr .= '</Order>';
    }

    $xmlstr .= '</Orders>';

    echo $this->printXML($xmlstr);
  }

  /**
   * @param string $auth_key
   * @return bool|void
   */
  public function importUsers(string $auth_key) {
    if (!$this->checkAuthKey($auth_key)) {
      return FALSE;
    }

    $xml_file = $this->getLastFile($this->import_dir, 'xml');
    if ($xml_file !== FALSE) {
      $this->loadUsers($xml_file);
    } else {
      return FALSE;
    }
  }

  public function quantityUpdate(array $data): bool {
    if (empty($data['guid']) || !isset($data['count']) || empty($data['h'])) {
      $this->setXmlError('Не указаны обязательные параметры');
      return FALSE;
    }
    if ($data['h'] != md5('guid=' . $data['guid'] . '&count=' . $data['count'] . $this->auth_key)) {
      $this->setXmlError('Не верный ключ авторизации');
      return FALSE;
    }

    $this->oc->updateProduct($data['guid'], ['stock' => $data['count']]);
    return TRUE;
  }


  /**
   * @param $phone
   * @return mixed|string|null
   */
  private function formattedPhone($phone) {
    $phone = (string)$phone;
    $phone = str_replace('(', '', $phone);
    $phone = str_replace(')', '', $phone);
    $phone = str_replace('-', '', $phone);
    $phone = preg_replace('/\s+/i', '', $phone);

    if (preg_match('/^8\d{10}$/i', $phone)) {
      return '+7' . substr($phone, 1);
    } elseif (preg_match('/^7\d{10}$/i', $phone)) {
      return '+' . $phone;
    } elseif (preg_match('/^\+7\d{10}$/i', $phone)) {
      return $phone;
    }

    return NULL;
  }

  private function loadUsers(string $xml_file) {
    ini_set("memory_limit", "512M");
    ini_set("max_execution_time", 36000);

    $contents = file_get_contents($xml_file);
    $xml = new SimpleXMLElement($contents);
    unset($contents);
    if ($this->unlink_files) {
      unlink($xml_file);
    }

    foreach ($xml->Users->Item as $item) {
      foreach ($item->attributes() as $k => $v) {
        if (in_array($k, ['firstname', 'lastname', 'telephone', 'email', 'date_added'])) {
          $user[$k] = (string)$v;
        } elseif (in_array($k, ['sale'])) {
          $user[$k] = floatval($v);
        }
      }

      if (empty($user['telephone'])) {
        $user['telephone'] = NULL;
      } else {
        $user['telephone'] = $this->formattedPhone($user['telephone']);
      }
      if (empty($user['email']) || !filter_var($user['email'], FILTER_VALIDATE_EMAIL)) {
        $user['email'] = NULL;
      }
      if (is_null($user['email']) && is_null($user['telephone'])) {
        continue;
      }
      if (empty($user['date_added'])) {
        $user['date_added'] = time();
      } else {
        $user['date_added'] = strtotime($user['date_added']);
      }
      if (empty($user['firstname'])) {
        $user['firstname'] = '';
      }
      if (empty($user['lastname'])) {
        $user['lastname'] = '';
      }
      if (empty($user['sale'])) {
        $user['sale'] = 0;
      }

      $this->oc->updateUser($user, TRUE);
    }

    $this->setXmlSuccess();
  }

  private function load($xml_file) {
    ini_set("memory_limit", "512M");
    ini_set("max_execution_time", 36000);

    $contents = file_get_contents($xml_file);
    $xml = new SimpleXMLElement($contents);
    unset($contents);
    if ($this->unlink_files) {
      unlink($xml_file);
    }

    if (!empty($xml->Goods->Item)) {
      $all_images = $this->getImages();
      foreach ($xml->Goods->Item as $item) {
        foreach ($item->attributes() as $k => $v) {
          $product[$k] = (string)$v;
        }

        if (empty($product['guid'])) {
          continue;
        }

        $product['image'] = $this->default_image;

        if (isset($all_images[$product['code']])) {
          if ($product['isactive'] == 'false') {
            unlink($all_images[$product['code']]);
          } else {
            if ($this->shell_start) {
              $product['image'] = explode($this->uri_delimeter, $all_images[$product['code']])[1];
            } else {
              $product['image'] = $all_images[$product['code']];
            }
          }
          unset($all_images[$product['code']]);
        }

        $product_data = $this->oc->getProductData($product['guid'], ['id', 'status', 'price', 'stock', 'manufacturer_id']);

        if ($product["isactive"] == "false" && empty($product_data['status'])) {
          continue;
        }

        $product['description'] = htmlspecialchars(str_replace('$', '<br/>', $product['description']));
        $product['filters'] = [];
        foreach ($item->filters->filter as $filter) {
          $filter = $filter->attributes();
          $product['filters'][] = $this->oc->getFilterId($filter['filtername'], $filter['filtervalue'], TRUE);
        }

        $product['price'] = 0;
        if ($product['showprice'] == 'true') {
          foreach ($item->Prices->price as $price) {
            $price = $price->attributes();
            if ($price['pricename'] == $this->default_pirce_type) {
              $product['price'] = floatval($price['pricevalue']);
            }
          }
        }

        //производитель
        if (!empty($product['mnfc'])) {
          $product['manufacturer_id'] = $this->oc->getManufacturerId($product['mnfc'], TRUE);
        } else {
          $product['manufacturer_id'] = 0;
        }

        $categories = explode('/', $product['category']);
        $product['main_category_id'] = $parent_category_id = 0;
        foreach ($categories as $category) {
          $category = trim($category);
          if (!empty($category)) {
            $category_id = $this->oc->getCategoryId($category, $parent_category_id, TRUE);
            $product['main_category_id'] = $category_id;
            $parent_category_id = $category_id;
          }
        }

        if (empty($product_data)) {
          $this->oc->addProduct($product);
        } else {
          //удалим не измененные поля из массива
          $this->checkFields($product, $product_data);
          $this->oc->updateProduct($product['guid'], $product);
        }
      }
    }

    $this->setXmlSuccess();
  }

  private function checkFields(array &$new_data, array $old_data) {
    if (empty($new_data['filters'])) {
      unset($new_data['filters']);
    }
    if ($new_data['isactive'] == 'true' && !empty($old_data['status'])) {
      unset($new_data['isactive']);
    }
    if ($new_data['stock'] == $old_data['stock']) {
      unset($new_data['stock']);
    }
    if ($new_data['price'] == $old_data['price']) {
      unset($new_data['price']);
    }
    if ($new_data['manufacturer_id'] == $old_data['manufacturer_id']) {
      unset($new_data['manufacturer_id']);
    }
  }

  /**
   * @return array
   */
  private function getImages(): array {
    $fn = [];
    if (($open_dir = opendir($this->image_dir)) !== FALSE) {
      while (($filename = readdir($open_dir)) !== FALSE) {
        if ($filename == '.' || $filename == '..' || $filename == 'current') {
          continue;
        }

        $new_name = $filename;
        $fname = explode('.', $new_name);
        if (file_exists($this->image_dir . '/' . $fname[0] . '.jpg') && file_exists($this->image_dir . '/' . $fname[0] . '.png')) {
          if (filectime($this->image_dir . '/' . $fname[0] . '.jpg') > filectime($this->image_dir . '/' . $fname[0] . '.png')) {
            unlink($this->image_dir . '/' . $fname[0] . '.png');
            $fn[$fname[0]] = $this->image_dir . '/' . $fname[0] . '.jpg';
          } else {
            unlink($this->image_dir . '/' . $fname[0] . '.jpg');
            $fn[$fname[0]] = $this->image_dir . '/' . $fname[0] . '.png';
          }
        } else {
          $fn[$fname[0]] = $this->image_dir . '/' . $new_name;
        }
      }
    }

    return $fn;
  }

  /**
   * @param array $db_params
   * @return bool
   */
  public function setDbParams(array $db_params) {
    if (!empty($db_params['host']) && !empty($db_params['name']) && !empty($db_params['user']) && isset($db_params['password'])) {
      $this->db_params = $db_params;
      return TRUE;
    } else {
      return FALSE;
    }
  }

  /**
   * @param string $oc
   * @return bool
   */
  public function setOc(string $oc): bool {
    try {
      $oc = 'PeterLS\\crm\\' . $oc;
      $this->oc = new $oc($this->db_params);
      return TRUE;
    } catch (Exception $e) {
      $this->setError($e);
      return FALSE;
    }
  }

  /**
   * @param string $export_dir
   */
  public function setExportDir(string $export_dir) {
    $this->export_dir = $this->replaceSlashes($export_dir);
  }

  /**
   * @param string $import_dir
   */
  public function setImportDir(string $import_dir) {
    $this->import_dir = $this->replaceSlashes($import_dir);
  }

  /**
   * @param string $auth_key
   */
  public function setAuthKey(string $auth_key) {
    $this->auth_key = $auth_key;
  }

  private function checkAuthKey(string $auth_key): bool {
    if ($auth_key === $this->auth_key) {
      return TRUE;
    } else {
      $this->setError('Неверный ключ авторизации');
      return FALSE;
    }
  }

  /**
   * @param string $image_dir
   */
  public function setDirImage(string $image_dir) {
    $this->image_dir = $image_dir;
  }

  /**
   * @param string $default_image
   */
  public function setDefaultImage(string $default_image) {
    $this->default_image = $default_image;
  }

  /**
   * @param string $default_pirce_type
   */
  public function setDefaultPirceType(string $default_pirce_type) {
    $this->default_pirce_type = $default_pirce_type;
  }

  /**
   * @param string $dir
   * @param string $file_type
   * @return bool|mixed
   */
  private function getLastFile(string $dir, string $file_type) {
    $lm = $fn = [];
    $dir = $this->replaceSlashes($dir);

    $open_dir = opendir($dir);
    if ($open_dir === FALSE) {
      $this->setError('Невозможно открыть директорию ' . $dir);
      return FALSE;
    } else {
      while (($filename = readdir($open_dir)) !== FALSE) {
        if ($filename == '.' || $filename == '..' || $filename == 'current') {
          continue;
        }

        $ext = explode('.', $filename);

        if ($ext[1] == $file_type) {
          $lastModified = filemtime("{$dir}/{$filename}");
          $lm[] = $lastModified;
          $fn[] = $filename;
        }
      }

      if (!empty($fn)) {
        array_multisort($lm, SORT_NUMERIC, SORT_ASC, $fn);
        $last_index = count($lm) - 1;

        return $dir . '/' . $fn[$last_index];
      } else {
        if ($file_type === 'zip') {
          $this->setError('Отсутствует ZIP-архив.');
          return FALSE;
        } else {
          $this->setXmlError('Отсутствует файл выгрузки');
        }
      }
    }
  }

  private function replaceSlashes($path, $separator = DIRECTORY_SEPARATOR) {
    return str_replace('/', $separator, $path);
  }

  /**
   * @param string $error
   */
  private function setError(string $error) {
    $this->errors[] = $error;
  }

  /**
   * @return array
   */
  public function getErrors(): array {
    return $this->errors;
  }

  /**
   * @param string $string
   */
  private function setXmlError(string $string) {
    $string = str_replace('"', '', $string);
    echo $this->printXML('<?xml version="1.0" encoding="UTF-8"?><error descr="' . $string . '">1</error>');
    exit();
  }

  private function setXmlSuccess() {
    echo $this->printXML('<?xml version="1.0" encoding="UTF-8"?><error descr="">0</error>');
    exit();
  }

  private function printXML(string $data): string {
    $xml_class = new SimpleXMLElement($data);
    return $xml_class->asXML();
  }

  /**
   * @return bool
   */
  private function checkSettingsBeforeImport(): bool {
    if (empty($this->import_dir) || empty($this->image_dir) || is_null($this->oc)) {
      $this->setError('Некорректные настройки модуля импорта.');
      return FALSE;
    } else {
      return TRUE;
    }
  }

  /**
   * @param bool $unlink_files
   */
  public function setUnlinkFiles(bool $unlink_files) {
    $this->unlink_files = $unlink_files;
  }

  /**
   * @param bool $shell_start
   */
  public function setShellStart(bool $shell_start) {
    $this->shell_start = $shell_start;
  }

  /**
   * @param string $uri_delimeter
   */
  public function setUriDelimeter(string $uri_delimeter) {
    $this->uri_delimeter = $uri_delimeter;
  }
}