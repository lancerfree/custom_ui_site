<?php

namespace Drupal\custom_ui_site\Custom;

Class CustomUISiteTable {

  /**
   * Base table name.
   *
   * @var string
   */
  const BASE_TABLE = 'custom_ui_site';

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  public $db;

  /**
   * CustomUISiteTable constructor.
   */
  public function __construct() {
    $this->db = \Drupal::database();
  }

  /**
   * Add new item to the table.
   *
   * @param array $data
   *   Data to add.
   * @throws \Exception
   */
  public function addItem($data) {
    $this->db->merge(self::BASE_TABLE)
      ->key('alias')
      ->insertFields($data)
      ->updateFields($data)
      ->execute();
  }

  /**
   * Delete item or items from the table.
   *
   * @param array $data
   *   Condition data.
   * @return int
   */
  public function deleteItems($data) {
    $query = $this->db->delete(self::BASE_TABLE);
    if ($data) {
      foreach ($data AS $key => $value) {
        $query->condition($key, $value);
      }
    }
    $nums = $query->execute();
    return $nums;
  }

  /**
   * Update data table.
   *
   * @param array $data
   *   Data.
   * @param array $cond
   *   Condition.
   * @return \Drupal\Core\Database\StatementInterface|int|null
   */
  public function updateItems($data, $cond) {
    $query = $this->db->update(self::BASE_TABLE)
      ->fields($data);
    foreach ($data AS $key => $value) {
      $query->condition($key, $value);
    }
    return $query->execute();
  }

  /**
   * Select item from the table.
   *
   * @param array $cond
   *   Condition.
   * @param bool $unserialize
   *   Flag.Is it needed unserialize?
   * @param bool $first
   *   Flag return first element.
   * @return array|mixed
   */
  public function selectItems($cond, $unserialize = TRUE, $first = TRUE) {
    $query = $this->db->select(self::BASE_TABLE, 'n');
    $query->fields('n', []);
    foreach ($cond AS $key => $value) {
      $query->condition($key, $value);
    }
    if ($first) {
      $query->range(0, 1);
    }
    $result = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
    if (!$result) {
      return [];
    }
    if ($first) {
      $result = array_slice($result, 0, 1);
    }
    if ($unserialize) {
      $result_unser = array_map(function ($item) {
        $item['data'] = unserialize($item['data']);
        return $item;
      }, $result);
    }
    if ($first) {
      return reset($result_unser);
    }
    return $result_unser;
  }


}