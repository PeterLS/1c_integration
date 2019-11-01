<?php


namespace PeterLS\crm;


interface CRM {
  /**
   * CRM constructor.
   * @param array $db_params - параметры базы данных
   */
  public function __construct(array $db_params);

  /**
   * @param string $guid
   * @param array $fields - необязательный; если заданы значения возвращает только запрошенные поля
   * @return array - массив с данными или пустой массив
   */
  public function getProductData(string $guid, array $fields = []): array;

  /**
   * @param int $id - идентификатор продукта
   * @param array $data
   * @return void
   */
  public function updateProduct(string $id, array $data);

  /**
   * @param array $data
   * @return void
   */
  public function addProduct(array $data);

  /**
   * @param string $name - наименование категории
   * @param int $parent_id - ID родительской категории, 0 если нет родительской категории
   * @param bool $add_if_empty - добавить категорию если не существует
   * @return int
   */
  public function getCategoryId(string $name, int $parent_id = 0, bool $add_if_empty = false): int;
  public function getManufacturerId(string $name, bool $add_if_empty = false): int;

  public function getFilterId(string $filter_group_name, string $filter_name, bool $add_if_empty = false): int;

  public function getOrders(int $start_date, int $end_date): array;

  public function updateUser(array $data, bool $add_if_empty = false);
}