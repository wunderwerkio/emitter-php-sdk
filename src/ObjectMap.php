<?php

declare(strict_types=1);

namespace Wunderwerk\PhpEmitter;

/**
 * Maps one object to another.
 */
class ObjectMap {
  
  /**
   * Array that contains the hash of $key and $object as value.
   */
  private array $objectHashMap = [];

  /**
   * Add an object $object keyed by $key.
   * 
   * @param object $key 
   *   The object to use as key.
   * @param object $subject 
   *   The object to use as value.
   */
  public function add(object $key, object $subject): void {
    $this->objectHashMap[spl_object_hash($key)] = $subject;
  }

  /**
   * Get object by $key.
   * 
   * @param object $key 
   *   The object to use as key.
   * 
   * @return object 
   *   The object that is mapped to $key.
   */
  public function get(object $key): object {
    return $this->objectHashMap[spl_object_hash($key)];
  }

  /**
   * Check if $key is mapped to an object.
   * 
   * @param object $key 
   *   The object to use as key.
   * 
   * @return bool 
   *   TRUE if $key is mapped to an object, FALSE otherwise.
   */
  public function has(object $key): bool {
    return isset($this->objectHashMap[spl_object_hash($key)]);
  }

  /**
   * Remove object by $key.
   * 
   * @param object $key 
   *   The object to use as key.
   */
  public function remove(object $key): void {
    unset($this->objectHashMap[spl_object_hash($key)]);
  }

  /** 
   * Clear the map.
   */
  public function clear(): void {
    $this->objectHashMap = [];
  }

  /**
   * Get the number of objects in the map.
   * 
   * @return int 
   *   The number of objects in the map.
   */
  public function count(): int {
    return count($this->objectHashMap);
  }

}