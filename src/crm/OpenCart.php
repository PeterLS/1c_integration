<?php


namespace PeterLS\crm;

use PDO;

class OpenCart implements CRM {
  private $db = NULL;
  private $stock_statuses = ['true' => 7, 'false' => 5];
  private $default_language_id = 1;

  public function __construct(array $db_params) {
    $this->db = new PDO("mysql:host={$db_params['host']};dbname={$db_params['name']}", $db_params['user'], $db_params['password']);
    $this->default_language_id = $this->getDefaultLanguage();
  }

  public function __destruct() {
    $this->db = NULL;
  }

  /**
   * @param $model - модель продукта
   * @param array $data - столбцы, которые нужно вернуть; по умолчанию все
   * @return array
   */
  public function getProductData($model, array $data = []): array {
    $STH = $this->db->prepare("SELECT *, product_id AS id, sku AS guid, quantity AS stock FROM oc_product WHERE model = :model LIMIT 1");
    $STH->execute([':model' => $model]);
    $row = ($STH->fetchAll(PDO::FETCH_ASSOC));
    if (empty($row)) {
      return [];
    }
    else {
      $row = $row[0];
    }

    if (empty($data)) {
      return $row;
    }
    else {
      $result = [];
      foreach ($row as $k => $v) {
        if (in_array($k, $data)) {
          $result[$k] = $v;
        }
      }
      return $result;
    }
  }

  public function updateProduct(int $id, array $data) {
    $product_data = [];
    foreach ($data as $k => $v) {
      switch ($k) {
        case 'guid':
          $product_data['sku'] = $v;
          break;
        case 'stock':
          $product_data['quantity'] = intval($v);
          break;
        case 'isactive':
          $product_data['status'] = ($v == 'true' ? 1 : 0);
          break;
        case 'price':
          $product_data[$k] = $v;
          break;
        case 'image':
          $image = explode('/image/', $data[$k]);
          $product_data[$k] = $image[count($image) - 1];
          unset($image);
          break;
      }
    }
    if (!empty($product_data)) {
      if (isset($product_data['quantity'])) {
        $product_data['stock_status_id'] = $this->getStockStatus($product_data['quantity']);
      }
      $product_data['date_available'] = date('Y-m-d');
      $product_data['date_modified'] = date('Y-m-d H:i:s');

      $sql = "UPDATE oc_product SET ";
      $count = 1;
      foreach ($product_data as $k => $v) {
        $sql .= "`$k` = :$k ";
        if ($count == count($product_data)) {
          $sql .= ' ';
        }
        else {
          $sql .= ', ';
        }
        $count++;
      }
      $sql .= "WHERE product_id = :product_id";

      $product_data['product_id'] = $id;
      $STH = $this->db->prepare($sql);
      $STH->execute($product_data);
    }
    unset($product_data, $sql);

    $product_description_data = [];
    if (isset($data['name'])) {
      $product_description_data['name'] = addslashes($data['name']);
    }
    if (isset($data['description'])) {
      $product_description_data['description'] = addslashes($data['description']);
    }

    if (!empty($product_description_data)) {
      $sql = "UPDATE oc_product_description SET ";
      foreach ($product_description_data as $k => $v) {
        $sql .= "`$k` = :$k ";
      }
      $sql .= "WHERE product_id = :product_id AND language_id = :language_id";

      $product_description_data['product_id'] = $id;
      $product_description_data['language_id'] = $this->default_language_id;
      $STH = $this->db->prepare($sql)->execute($product_description_data);
    }
    unset($product_description_data);

    if (!empty($data['filters'])) {
      $STH = $this->db->prepare("INSERT IGNORE INTO oc_product_filter VALUES (:product_id, :filter_id)");
      foreach ($data['filters'] as $filter_id) {
        $STH->execute([':product_id' => $id, ':filter_id' => $filter_id]);
      }
    }

    if (!empty($data['main_category_id'])) {
      $STH = $this->db->prepare("INSERT IGNORE INTO oc_product_to_category (product_id, category_id) VALUES (:product_id, :main_category_id)");
      $STH->execute([
        ':product_id' => $id, ':main_category_id' => $data['main_category_id']
      ]);
    }
  }

  public function addProduct(array $data) {
    $image = explode('/image/', $data['image']);
    $data['image'] = $image[count($image) - 1];
    unset($image);
    $STH = $this->db->prepare("INSERT INTO oc_product SET model = :model, sku = :sku, upc = '', ean = '', jan = '', isbn = '', mpn = '', location = '', quantity = :quantity, stock_status_id = :stock_status_id, image = :image, manufacturer_id = 1, price = :price, tax_class_id = 0, status = 1, date_added = current_date, date_modified = current_date, date_available = current_date");
    $STH->execute([':model' => $data['code'], ':sku' => $data['guid'], ':quantity' => $data['stock'], ':stock_status_id' => $this->getStockStatus($data['stock']), ':image' => $data['image'], ':price' => $data['price']]);
    $product_id = $this->db->lastInsertId('product_id');

    $STH = $this->db->prepare("INSERT INTO oc_product_description SET product_id = :product_id, language_id = :language_id, `name` = :product_name, `description` = :product_description, tag = '', meta_title = '', meta_description = '', meta_keyword = ''");
    $STH->execute([':product_id' => $product_id, ':language_id' => $this->default_language_id, ':product_name' => $data['name'], ':product_description' => $data['description']]);

    $STH = $this->db->prepare("INSERT INTO oc_product_to_category SET product_id = :product_id, category_id = :category_id");
    $STH->execute([
      ':product_id' => $product_id, ':category_id' => $data['main_category_id']
    ]);

    if (!empty($data['filters'])) {
      $STH = $this->db->prepare("INSERT INTO oc_product_filter VALUES (:product_id, :filter_id)");
      foreach ($data['filters'] as $filter_id) {
        $STH->execute([':product_id' => $product_id, ':filter_id' => $filter_id]);
      }
    }

    $STH = $this->db->prepare("INSERT INTO oc_product_to_store SET product_id = :product_id, store_id = 0");
    $STH->execute([':product_id' => $product_id]);
  }

  /**
   * @param string $name - имя категории
   * @param int $parent_id - ID родительской категории или 0
   * @param bool $add_if_empty - добавить, если категория не существует
   * @return int
   */
  public function getCategoryId(string $name, int $parent_id = 0, bool $add_if_empty = FALSE): int {
    $STH = $this->db->prepare("SELECT cd.category_id AS id FROM oc_category c, oc_category_description cd WHERE cd.category_id = c.category_id AND LOWER(cd.name) = LOWER(:category_name) AND c.parent_id = :parent_id LIMIT 1");
    $STH->execute([':category_name' => $name, ':parent_id' => $parent_id]);
    $row = $STH->fetchAll(PDO::FETCH_ASSOC);
    if (empty($row)) {
      if ($add_if_empty) {
        $STH = $this->db->prepare("INSERT INTO oc_category (image, parent_id, top, `column`, `status`, date_added, date_modified) VALUES ('', :parent_id, 1, 1, 1, CURRENT_TIME, CURRENT_TIME)");
        $STH->execute([':parent_id' => $parent_id]);
        $category_id = $this->db->lastInsertId('category_id');
        $STH = $this->db->prepare("INSERT INTO oc_category_description SET category_id = :category_id, language_id = :language_id, `name` = :category_name, description = '', meta_title = '', meta_description = '', meta_keyword = ''");
        $STH->execute([':category_id' => $category_id, ':category_name' => $name, ':language_id' => $this->default_language_id]);

        $STH = $this->db->prepare("INSERT INTO oc_category_to_store SET category_id = :category_id, store_id = 0");
        $STH->execute([':category_id' => $category_id]);

        $level = 0;
        $STH1 = $this->db->prepare("INSERT INTO oc_category_path SET category_id = :category_id, path_id = :path_id, `level` = :level_data");
        $STH = $this->db->prepare("SELECT path_id FROM oc_category_path WHERE category_id = :parent_id ORDER BY `level`");
        $STH->execute(['parent_id' => $parent_id]);
        while ($row = $STH->fetch(PDO::FETCH_ASSOC)) {
          $STH1->execute([
            ':category_id' => $category_id, ':path_id' => $row['path_id'], ':level_data' => $level
          ]);
          $level++;
        }
        $STH1->execute([
          ':category_id' => $category_id, ':path_id' => $category_id, ':level_data' => $level
        ]);
        unset($STH1, $level, $row);

        $STH = $this->db->prepare("INSERT IGNORE INTO oc_seo_url SET store_id = 0, language_id = :language_id, `query` = :query_data, `keyword` = :keyword_data");
        $STH->execute([
          ':language_id' => $this->default_language_id, ':query_data' => 'category_id=' . $category_id, ':keyword_data' => $this->generateSeoUrl($name)
        ]);

        return $category_id;
      }
      else {
        return 0;
      }
    }
    else {
      return intval($row[0]['id']);
    }
  }

  /**
   * @param string $filter_group_name - название группы фильтров
   * @param string $filter_name - название фильтра
   * @param bool $add_if_empty - добавлять группу/фильтр если отсутствует
   * @return int
   */
  public function getFilterId(string $filter_group_name, string $filter_name, bool $add_if_empty = FALSE): int {
    $STH = $this->db->prepare("SELECT filter_group_id FROM oc_filter_group_description WHERE `name` = :filter_group_name LIMIT 1");
    $STH->execute([':filter_group_name' => $filter_group_name]);
    $filter_group = $STH->fetchAll(PDO::FETCH_ASSOC);
    if (empty($filter_group)) {
      if ($add_if_empty) {
        $this->db->exec("INSERT INTO oc_filter_group VALUES (NULL, 0)");
        $last_insert_id = $this->db->lastInsertId('filter_group_id');
        $STH = $this->db->prepare("INSERT INTO oc_filter_group_description VALUES (:filter_group_id, :language_id, :filter_name)");
        $STH->execute([':filter_group_id' => $last_insert_id, ':filter_name' => $filter_name, ':language_id' => $this->default_language_id]);
        $filter_group_id = $this->db->lastInsertId('filter_group_id');
      }
      else {
        return 0;
      }
    }
    else {
      $filter_group_id = $filter_group[0]['$filter_group_id'];
    }

    $STH = $this->db->prepare("SELECT filter_id FROM oc_filter_description WHERE name = :filter_name AND filter_group_id = :filter_group_id LIMIT 1");
    $STH->execute([':filter_name' => $filter_name, ':filter_group_id' => $filter_group_id]);
    $filter = $STH->fetchAll(PDO::FETCH_ASSOC);
    if (empty($filter)) {
      if ($add_if_empty) {
        $STH = $this->db->prepare("INSERT INTO oc_filter (filter_group_id, sort_order) VALUES (:filter_group_id, 0)");
        $STH->execute([':filter_group_id' => $filter_group_id]);
        $filter_id = $this->db->lastInsertId('filter_id');
        $STH = $this->db->prepare("INSERT INTO oc_filter_description VALUES (:filter_id, :language_id, :filter_group_id, :filter_name)");
        $STH->execute([':filter_id' => $filter_id, ':filter_group_id' => $filter_group_id, ':filter_name' => $filter_name, ':language_id' => $this->default_language_id]);

        return $filter_id;
      }
      else {
        return 0;
      }
    }
    else {
      return $filter[0]['filter_id'];
    }
  }

  /**
   * @return int - язык по умолчанию
   */
  private function getDefaultLanguage(): int {
    return intval($this->db->query("SELECT language_id FROM oc_language ORDER BY sort_order LIMIT 1")->fetchColumn());
  }

  /**
   * @param int $default_language_id
   */
  public function setDefaultLanguageId(int $default_language_id) {
    $this->default_language_id = $default_language_id;
  }

  /**
   * @param array $stock_statuses ['true' => int (в наличии), 'false' => int (не в наличии)]
   */
  public function setStockStatuses(array $stock_statuses) {
    if (isset($stock_statuses['true'], $stock_statuses['false']) && is_int($stock_statuses['true']) && is_int($stock_statuses['false'])) {
      $this->stock_statuses = $stock_statuses;
    }
  }

  /**
   * @param int $quantity
   * @return int
   */
  private function getStockStatus(int $quantity): int {
    if ($quantity > 0) {
      return $this->stock_statuses['true'];
    }
    else {
      return $this->stock_statuses['false'];
    }
  }

  private function generateSeoUrl(string $name): string {
    $name = strip_tags($name); // убираем HTML-теги
    $name = str_replace(["\n", "\r"], " ", $name); // убираем перевод каретки
    $name = preg_replace("/\s+/", ' ', $name); // удаляем повторяющие пробелы
    $name = trim($name); // убираем пробелы в начале и конце строки
    $name = function_exists('mb_strtolower') ? mb_strtolower($name) : strtolower($name); // переводим строку в нижний регистр (иногда надо задать локаль)
    $name = strtr($name, [
      'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e', 'ж' => 'j', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't',
      'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'c', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'shch', 'ы' => 'y', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya', 'ъ' => '', 'ь' => ''
    ]);
    $name = preg_replace("/[^0-9a-z-_ ]/i", "", $name); // очищаем строку от недопустимых символов
    $name = str_replace(" ", "-", $name); // заменяем пробелы знаком минус
    return $name; // возвращаем результат
  }

  public function getOrders(int $start_date, int $end_date): array {
    $STH = $this->db->prepare("SELECT (IF((o.shipping_code = 'pickup.pickup'), 'Самовывоз', concat(o.shipping_country, ' ', o.shipping_city, ' ', o.shipping_address_1, ' ', o.shipping_address_2))) shipping,
          o.date_added,
          o.order_id id,
          o.comment,
          (IF((c.code IS NOT NULL), c.discount, '')) coupon,
          (IF((o.order_status_id = :paid_status_id), 1, 0)) paid,
          concat(o.firstname, ' ', o.lastname) customer_name,
          o.telephone,
          o.email
        FROM oc_order o
          LEFT JOIN oc_coupon_history ch ON o.order_id = ch.order_id
          LEFT JOIN oc_coupon c ON c.coupon_id = ch.coupon_id
        WHERE cast(o.date_added AS DATE) BETWEEN :start_date AND :end_date");
    $STH->execute([
      ':paid_status_id' => 9, ':start_date' => date('Y-m-d', $start_date), ':end_date' => date('Y-m-d', $end_date)
    ]);
    $orders = $STH->fetchAll(PDO::FETCH_ASSOC);
    $STH = $this->db->prepare("SELECT p.sku guid, p.model code, op.quantity, op.price, total FROM oc_order_product op, oc_product p WHERE order_id = :order_id AND op.product_id = p.product_id");
    foreach ($orders as &$order) {
      $STH->execute([':order_id' => $order['id']]);
      $products = $STH->fetchAll(PDO::FETCH_ASSOC);
      if (!empty($order['coupon'])) {
        $order['coupon'] = floatval($order['coupon']);
        foreach ($products as &$product) {
          $product['price'] = $product['price'] - $product['price'] / 100 * $order['coupon'];
          $product['discount'] = $product['total'] / 100 * $order['coupon'];
          $product['total'] -= $product['discount'];
        }
      }
      $order['products'] = $products;
    }

    return $orders;
  }
}