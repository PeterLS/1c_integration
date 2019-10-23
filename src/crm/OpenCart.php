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

  public function getProductData($model, array $data = []): array {
    $STH = $this->db->prepare("SELECT *, product_id AS id FROM oc_product WHERE model = :model LIMIT 1");
    $STH->execute([':model' => $model]);
    $row = ($STH->fetchAll(PDO::FETCH_ASSOC))[0];
    if (empty($row)) {
      return [];
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

  public function updateProduct(int $id, array $data): bool {
    // TODO: Implement updateProduct() method.
  }

  public function addProduct(array $data): int {
    // TODO: Implement addProduct() method.
  }

  public function getCategoryId(string $name, int $parent_id = 0, bool $add_if_empty = false): int {
    $STH = $this->db->prepare("SELECT cd.category_id FROM oc_category c, oc_category_description cd WHERE cd.category_id = c.category_id AND LOWER(cd.name) = LOWER(:category_name) AND c.parent_id = :parent_id LIMIT 1");
    $STH->execute([
      ':category_name' => $name,
      ':parent_id' => $parent_id
    ]);
    $row = ($STH->fetchAll(PDO::FETCH_ASSOC))[0];
    if (empty($row)) {
      if ($add_if_empty) {
        $STH = $this->db->prepare("INSERT INTO oc_category (image, parent_id, top, `column`, `status`, date_added, date_modified) VALUES ('', :parent_id, 1, 1, 1, CURRENT_TIME, CURRENT_TIME)");
        $STH->execute([
          ':parent_id' => $parent_id
        ]);
        $last_insert_id = $this->db->lastInsertId('category_id');
        $STH = $this->db->prepare("INSERT INTO oc_category_description (category_id, language_id, name) VALUES (:category_id, (SELECT language_id FROM oc_language ORDER by sort_order LIMIT 1), :category_name)");
        $STH->execute([
          ':category_id' => $last_insert_id,
          ':category_name' => $name
        ]);

        return $last_insert_id;
      } else {
        return 0;
      }
    }
  }
}