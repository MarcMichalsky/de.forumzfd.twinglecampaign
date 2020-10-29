<?php


namespace CRM\TwingleCampaign\BAO;

use CRM_Civirules_Utils_LoggerFactory as Civi;

class CustomGroup {

  private $id;
  private $title;
  private $name;
  private $extends;
  private $weight;
  private $extends_entity_column_value;
  private $collapse_display;
  private $results;

  /**
   * CustomGroup constructor.
   *
   * @param array $attributes
   */
  public function __construct(array $attributes) {
    foreach ($this as $var => $value) {
      if (array_key_exists($var, $attributes))
        $this->$var = $attributes[$var];
    }
  }

  /**
   * @throws \CiviCRM_API3_Exception
   */
  public function create() {

    $field = civicrm_api3(
      'CustomGroup',
      'get',
      [
        'sequential' => 1,
        'name'       => $this->getName()
      ]
    );

    if ($field['count'] == 0) {

      $this->results = civicrm_api3('CustomGroup', 'create', $this->getSetAttributes());

      $this->id = $this->results['id'];

      if ($this->results['is_error'] == 0) {
        \Civi::log()->info("Twingle Extension has created a new custom group.
      title: $this->title
      name: $this->name
      extends: $this->extends
      id: $this->id
      column_value: $this->extends_entity_column_value"
        );
      }
      else {
        if ($this->title) {
          \Civi::log()->error("Twingle Extension could not create new custom group
        for \"$this->title\": $this->results['error_message']"
          );
        }
        else {
          \Civi::log()->error("Twingle Extension could not create new 
        custom group: $this->results['error_message']");
        }
      }
    }
  }

  /**
   * @return array
   */
  private function getSetAttributes() {
    $setAttributes = [];
    foreach ($this as $var => $value) {
      if (isset($value)) {
        $setAttributes[$var] = $value;
      }
    }
    return $setAttributes;
  }

  /**
   * @param $values
   *
   * @return bool
   * @throws \CiviCRM_API3_Exception
   */
  public function alter($values) {

    foreach ($values as $var => $value) {
      if ($this->$var) {
        $this->$var = $value;
      }
    }

    $this->results = civicrm_api3('CustomGroup', 'create', $this->getSetAttributes());

    return $this->results['is_error'] == 0;
  }

  /**
   * @param $name
   *
   * @return BAO\CustomGroup
   * @throws \CiviCRM_API3_Exception
   */
  public static function fetch($name) {

    $custom_group = civicrm_api3(
      'CustomGroup',
      'get',
      [
        'sequential' => 1,
        'name'       => $name
      ]
    );
    if ($custom_group = array_shift($custom_group['values'])) {
      return new CustomGroup($custom_group);
    }
    else {
      return NULL;
    }
  }
  public function delete() {
    $this->results = civicrm_api3(
      'CustomGroup',
      'delete',
      ['id' => $this->id]
    );

    if ($this->results['is_error'] == 0) {
      \Civi::log()->info("Twingle Extension has deleted custom group.
      title: $this->title
      name: $this->name
      extends: $this->extends
      id : $this->id"
      );

      foreach ($this->getSetAttributes() as $var => $attribute) {
        $this->$var = NULL;
      }
    }
    else {
      if ($this->title) {
        \Civi::log()->error("Twingle Extension could not delete custom group
        \"$this->title\": $this->results['error_message']"
        );
      }
      else {
        \Civi::log()->error("Twingle Extension could not delete custom group: 
        $this->results['error_message']");
      }
    }
  }

  /**
   * @param mixed $id
   *
   * @return bool
   * @throws \CiviCRM_API3_Exception
   */
  public function setId($id) {
    return $this->alter(['id', $id]);
  }

  /**
   * @param string $title
   *
   * @return bool
   * @throws \CiviCRM_API3_Exception
   */
  public function setTitle(string $title) {
    return $this->alter(['title', $title]);
  }

  /**
   * @param string $name
   *
   * @return bool
   * @throws \CiviCRM_API3_Exception
   */
  public function setName(string $name) {
    return $this->alter(['name', $name]);
  }

  /**
   * @param string $extends
   *
   * @return bool
   * @throws \CiviCRM_API3_Exception
   */
  public function setExtends(string $extends) {
    return $this->alter(['extends', $extends]);
  }

  /**
   * @param mixed $weight
   */
  public function setWeight($weight) {
    $this->weight = $weight;
  }

  /**
   * @param string $column_value
   *
   * @return bool
   * @throws \CiviCRM_API3_Exception
   */
  public function setColumnValue(string $column_value) {
    return $this->alter(['column_value', $column_value]);
  }

  /**
   * @return string
   */
  public function getTitle(): string {
    return $this->title;
  }

  /**
   * @return string
   */
  public function getName(): string {
    return $this->name;
  }

  /**
   * @return string
   */
  public function getExtends(): string {
    return $this->extends;
  }

  /**
   * @return mixed
   */
  public function getWeight() {
    return $this->weight;
  }

  /**
   * @return mixed
   */
  public function getCollapseDisplay() {
    return $this->collapse_display;
  }

  /**
   * @return mixed
   */
  public function getResult() {
    return $this->results;
  }


}