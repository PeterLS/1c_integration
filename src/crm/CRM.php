<?php


namespace PeterLS\crm;


interface CRM {
  /**
   * @param $ident - принимает код продукта из 1с
   * @param array $fields - необязательный; если заданы значения возвращает только запрошенные поля
   * @return array - возвращает массив с данными или пустой массив
   */
  public function getProductData($ident, array $fields = []): array;

  public function updateProduct(int $id, array $data): bool;

  public function addProduct(array $data): bool;
}