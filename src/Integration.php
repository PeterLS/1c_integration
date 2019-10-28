<?php


namespace PeterLS\Integration;

use Exception;
use SimpleXMLElement;
use ZipArchive;

class Integration {
  private $oc = NULL;
  private $auth_key = NULL;

  private $export_dir = NULL;
  private $import_dir = NULL;
  private $image_dir = NULL;
  private $default_image = '';
  private $default_pirce_type = 'Розничная';

  private $db_params = ['host' => 'localhost', 'name' => '', 'user' => 'root', 'password' => '', 'port' => 0];

  private $errors = [];

  /**
   * @return bool
   */
  public function startImport() { //from 1C
    if (!$this->checkSettingsBeforeImport()) {
      return false;
    }

    $zip_file = $this->getLastFile($this->import_dir, 'zip');

    if ($zip_file !== false) {
      $zip = new ZipArchive;
      $sh_zip = $zip->open($zip_file);
      if ($sh_zip === true) {
        if ($zip->extractTo($this->image_dir) === TRUE) {
          $zip->close();
          unlink($zip_file);

          $xml_file = $this->getLastFile($this->import_dir, 'xml');
          if ($xml_file !== false) {
            return $this->load($xml_file);
          } else {
            return false;
          }
        } else {
          $zip->close();
          unlink($zip_file);
          $this->setError('Не удалось распаковать архив. Пожалуйста, попробуйте сделать выгрузку снова.');
          return false;
        }
      } else {
        unlink($zip_file);
        $this->setError('Не удалось открыть архив. Пожалуйста, попробуйте сделать выгрузку снова.');
        return false;
      }
    }

    return false;
  }

  private function load($xml_file) {
    ini_set("memory_limit", "512M");
    ini_set("max_execution_time", 36000);

    $contents = file_get_contents($xml_file);
    $xml = new SimpleXMLElement($contents);

    if (!empty($xml->Goods->Item)) {
      $all_images = $this->getImages();
      foreach ($xml->Goods->Item as $item) {
        foreach ($item->attributes() as $k => $v) {
          $product[$k] = (string)$v;
        }
        $product['image'] = $this->default_image;

        $image_sn = explode('-', $product['image'])[1] * 1;
        if (isset($all_images[$image_sn])) {
          if ($product['isactive'] == 'false') {
            unlink($this->image_dir . $all_images[$image_sn]);
          } else {
            $product['image'] = $this->image_dir . $all_images[$image_sn];
          }
          unset($all_images[$image_sn]);
        }

        $product_data = $this->oc->getProductData($product['code'], ['id', 'status', 'price', 'sku', 'quantity', 'price']);

        if ($product["isactive"] == "false" && empty($product_data['status'])) {
          continue;
        }

        $product['filters'] = [];
        foreach ($item->filters->filter as $filter) {
          $filter = $filter->attributes();
          $product['filters'][] = $this->oc->getFilterId($filter['filtername'], $filter['filtervalue'], true);
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

        $categories = explode('/', $product['category']);
        $product['main_category_id'] = $parent_category_id = 0;
        foreach ($categories as $category) {
          $category = trim($category);
          if (!empty($category)) {
            $category_id = $this->oc->getCategoryId($category, $parent_category_id, true);
            $product['main_category_id'] = $category_id;
            $parent_category_id = $category_id;
          }
        }

        if (empty($product_data['id'])) {
          $this->oc->addProduct($product);
        } else {
          //удалим не измененные поля из массива
          $this->checkFields($product, $product_data);

          $this->oc->updateProduct($product_data['id'], $product);
        }
      }
    }

    $this->setXmlSuccess();
  }

  private function checkFields(array &$new_data, array $old_data) {
    if (empty($product['filters'])) {
      unset($product['filters']);
    }
    if ($new_data['isactive'] == 'true' && !empty($old_data['status'])) {
      unset($new_data['isactive']);
    }
    if ($new_data['guid'] == $old_data['sku']) {
      unset($new_data['guid']);
    }
    if (intval($new_data['stock']) == $old_data['quantity']) {
      unset($new_data['stock']);
    }
    if ($new_data['price'] == $old_data['price']) {
      unset($new_data['price']);
    }
  }

  /**
   * @return array
   */
  private function getImages(): array {
    $fn = [];
    $open_dir = opendir($this->image_dir);
    if (($open_dir = opendir($this->image_dir)) !== false) {
      while (($filename = readdir($open_dir)) !== false) {
        if ($filename == '.' || $filename == '..' || $filename == 'current') {
          continue;
        }

        $sn = explode('-', $filename);;
        if (isset($sn[1])) {
          $new_name = $sn[1];
          rename($this->image_dir . $filename, $this->image_dir . $new_name);
        } else {
          $new_name = $filename;
        }

        $fname = explode('.', $new_name);
        if (file_exists($this->image_dir . $fname[0] . '.jpg') && file_exists($this->image_dir . $fname[0] . '.png')) {
          if (filectime($this->image_dir . $fname[0] . '.jpg') > filectime($this->image_dir . $fname[0] . '.png')) {
            unlink($this->image_dir . $fname[0] . '.png');
            $fn[$fname[0] * 1] = $fname[0] . '.jpg';
          } else {
            unlink($this->image_dir . $fname[0] . '.jpg');
            $fn[$fname[0] * 1] = $fname[0] . '.png';
          }
        } else {
          $fn[$fname[0] * 1] = $new_name;
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
      return true;
    } else {
      return false;
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
      return true;
    } catch (Exception $e) {
      $this->setError($e);
      return false;
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
    if ($open_dir === false) {
      $this->setError('Невозможно открыть директорию ' . $dir);
      return false;
    } else {
      while (($filename = readdir($open_dir) !== false)) {
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
          return false;
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
      return false;
    } else {
      return true;
    }
  }
}