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

  public function addProduct(array $data): bool {
    // TODO: Implement addProduct() method.
  }
}