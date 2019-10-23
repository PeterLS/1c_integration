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

  private function load($xml_file): bool {
    ini_set("memory_limit", "512M");
    ini_set("max_execution_time", 36000);

    $contents = file_get_contents($xml_file);
    $xml = new SimpleXMLElement($contents);

    if (empty($xml->Goods->Item)) {

    } else {
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

        $product_data = $this->oc->getProductData($product['code'], ['id', 'status', 'manufacturer_id']);

        if ($product["isactive"] == "false" && empty($product_data['status'])) {
          continue;
        }

        $product['filters'] = [];
        foreach($item->filters->filter as $filter) {
          $filter = $filter->attributes();
          $product['filters'][(string)$filter['filtername']] = (string)$filter['filtervalue'];
        }

        foreach($item->Prices->price as $price) {
          $price = $price->attributes();
          $product[(string)$price['pricename']] = (string)$price['pricevalue'];
        }

        $categories = explode('/',$product['category']);
        $product_categories = [];
        $main_category = $parent_category_id = 0;
        foreach ($categories as $category) {
          $category = trim($category);
          if (!empty($category)) {
            $category_id = $this->oc->getCategoryId($category, $parent_category_id, true);
            $product_categories[] = $category;
            $main_category = $category_id;
            $parent_category_id = $category_id;
          }
        }
      }
    }


    return true;
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
      $this->oc = new $oc();
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
    $this->export_dir = $export_dir;
  }

  /**
   * @param string $import_dir
   */
  public function setImportDir(string $import_dir) {
    $this->import_dir = $import_dir;
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
   * @param string $dir
   * @param string $file_type
   * @return bool|mixed
   */
  private function getLastFile(string $dir, string $file_type) {
    $lm = $fn = [];

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
          $lastModified = filemtime("{$dir}{$filename}");
          $lm[] = $lastModified;
          $fn[] = $filename;
        }
      }

      if (!empty($fn)) {
        array_multisort($lm, SORT_NUMERIC, SORT_ASC, $fn);
        $last_index = count($lm) - 1;

        return $dir . $fn[$last_index];
      } else {
        if ($file_type === 'zip') {
          return false;
        } else {
          $this->setXmlError('Отсутствует файл выгрузки');
        }
      }
    }
  }

  /**
   * @param string $error
   */
  private function setError(string $error) {
    $this->errors[] = $error;
  }

  /**
   * @param string $string
   */
  private function setXmlError(string $string) {
    $string = str_replace('"', '', $string);
    $xml_str = '<?xml version="1.0" encoding="UTF-8"?><error descr="' . $string . '">1</error>';
    $xml_class = new SimpleXMLElement($xml_str);
    echo $xml_class->asXML();
    exit();
  }

  /**
   * @return bool
   */
  private function checkSettingsBeforeImport(): bool {
    if (empty($this->import_dir) || empty($this->dir_image) || is_null($this->oc)) {
      $this->setError('Некорректные настройки модуля импорта.');
      return false;
    } else {
      return true;
    }
  }
}