<?php


namespace PeterLS\crm;

use PDO;

class OpenCart implements CRM {
  private $db = NULL;

  public function __construct(array $db_params) {
    $this->db = new PDO("mysql:host={$db_params['host']};dbname={$db_params['name']}", $db_params['user'], $db_params['password']);
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
    $STH = $this->db->prepare("SELECT *, product_id AS id FROM oc_product WHERE model = :model LIMIT 1");
    $STH->execute([':model' => $model]);
    $row = ($STH->fetchAll(PDO::FETCH_ASSOC));
    if (empty($row)) {
      return [];
    } else {
      $row = $row[0];
    }

    if (empty($data)) {
      return $row;
    } else {
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
    // TODO: Implement updateProduct() method.
  }

  public function addProduct(array $data) {
    // TODO: Implement addProduct() method.
  }

  /**
   * @param string $name - имя категории
   * @param int $parent_id - ID родительской категории или 0
   * @param bool $add_if_empty - добавить, если категория не существует
   * @return int
   */
  public function getCategoryId(string $name, int $parent_id = 0, bool $add_if_empty = false): int {
    $STH = $this->db->prepare("SELECT cd.category_id AS id FROM oc_category c, oc_category_description cd WHERE cd.category_id = c.category_id AND LOWER(cd.name) = LOWER(:category_name) AND c.parent_id = :parent_id LIMIT 1");
    $STH->execute([
      ':category_name' => $name,
      ':parent_id' => $parent_id
    ]);
    $row = $STH->fetchAll(PDO::FETCH_ASSOC);
    if (empty($row)) {
      if ($add_if_empty) {
        $STH = $this->db->prepare("INSERT INTO oc_category (image, parent_id, top, `column`, `status`, date_added, date_modified) VALUES ('', :parent_id, 1, 1, 1, CURRENT_TIME, CURRENT_TIME)");
        $STH->execute([
          ':parent_id' => $parent_id
        ]);
        $last_insert_id = $this->db->lastInsertId('category_id');
        $STH = $this->db->prepare("INSERT INTO oc_category_description (category_id, language_id, `name`) VALUES (:category_id, :language_id, :category_name)");
        $STH->execute([
          ':category_id' => $last_insert_id,
          ':category_name' => $name,
          ':language_id' => $this->getDefaultLanguage()
        ]);

        return $last_insert_id;
      } else {
        return 0;
      }
    } else {
      return intval($row[0]['id']);
    }
  }

  /**
   * @param string $filter_group_name - название группы фильтров
   * @param string $filter_name - название фильтра
   * @param bool $add_if_empty - добавлять группу/фильтр если отсутствует
   * @return int
   */
  public function getFilterId(string $filter_group_name, string $filter_name, bool $add_if_empty = false): int {
    $STH = $this->db->prepare("SELECT filter_group_id FROM oc_filter_group_description WHERE `name` = :filter_group_name LIMIT 1");
    $STH->execute([
      ':filter_group_name' => $filter_group_name
    ]);
    $filter_group = $STH->fetchAll(PDO::FETCH_ASSOC);
    if (empty($filter_group)) {
      if ($add_if_empty) {
        $this->db->exec("INSERT INTO oc_filter_group VALUES (NULL, 0)");
        $last_insert_id = $this->db->lastInsertId('filter_group_id');
        $STH = $this->db->prepare("INSERT INTO oc_filter_group_description VALUES (:filter_group_id, :language_id, :filter_name)");
        $STH->execute([
          ':filter_group_id' => $last_insert_id,
          ':filter_name' => $filter_name,
          ':language_id' => $this->getDefaultLanguage()
        ]);
        $filter_group_id = $this->db->lastInsertId('filter_group_id');
      } else {
        return 0;
      }
    } else {
      $filter_group_id = $filter_group[0]['$filter_group_id'];
    }

    $STH = $this->db->prepare("SELECT filter_id FROM oc_filter_description WHERE name = :filter_name AND filter_group_id = :filter_group_id LIMIT 1");
    $STH->execute([
      ':filter_name' => $filter_name,
      ':filter_group_id' => $filter_group_id
    ]);
    $filter = $STH->fetchAll(PDO::FETCH_ASSOC);
    if (empty($filter)) {
      if ($add_if_empty) {
        $STH = $this->db->prepare("INSERT INTO oc_filter (filter_group_id, sort_order) VALUES (:filter_group_id, 0)");
        $STH->execute([
          ':filter_group_id' => $filter_group_id
        ]);
        $filter_id =  $this->db->lastInsertId('filter_id');
        $STH = $this->db->prepare("INSERT INTO oc_filter_description VALUES (:filter_id, :language_id, :filter_group_id, :filter_name)");
        $STH->execute([
          ':filter_id' => $filter_id,
          ':filter_group_id' => $filter_group_id,
          ':filter_name' => $filter_name,
          ':language_id' => $this->getDefaultLanguage()
        ]);

        return $filter_id;
      } else {
        return 0;
      }
    } else {
      return $filter[0]['filter_id'];
    }
  }

  /**
   * @return int - язык по умолчанию
   */
  private function getDefaultLanguage(): int {
    return intval($this->db->query("SELECT language_id FROM oc_language ORDER BY sort_order LIMIT 1")->fetchColumn());
  }
}