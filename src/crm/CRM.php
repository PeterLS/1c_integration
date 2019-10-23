<?php


namespace PeterLS\crm;


interface CRM {
  /**
   * @param $ident - принимает код продукта из 1с
   * @param array $fields - необязательный; если заданы значения возвращает только запрошенные поля
   * @return array - массив с данными или пустой массив
   */
  public function getProductData($ident, array $fields = []): array;

  /**
   * @param int $id - идентификатор продукта
   * @param array $data - поля, которые нужно обновить
   * @return bool
   */
  public function updateProduct(int $id, array $data): bool;

  /**
   * @param array $data - данные добавляемого продукта
   * @return int - идентификатор добавленного продукта
   */
  public function addProduct(array $data): int;

  /**
   * @param string $name - наименование категории
   * @param int $parent_id - ID родительской категории, 0 если нет родительской категории
   * @param bool $add_if_empty - добавить категорию если не существует
   * @return int
   */
  public function getCategoryId(string $name, int $parent_id = 0, bool $add_if_empty = false): int;
}