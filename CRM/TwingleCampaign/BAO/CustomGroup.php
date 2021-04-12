<?php

use CRM_TwingleCampaign_ExtensionUtil as E;

class CRM_TwingleCampaign_BAO_CustomGroup {

  private $id;

  private $title;

  private $name;

  private $extends;

  private $weight;

  private $extends_entity_column_value;

  private $collapse_display;

  private $results;

  private $extensionName;

  /**
   * CustomGroup constructor.
   *
   * @param array $attributes
   */
  public function __construct(array $attributes) {

    $this->extensionName = E::LONG_NAME;

    foreach ($this as $var => $value) {

      // put array items into attributes
      if (array_key_exists($var, $attributes)) {
        $this->$var = $attributes[$var];
      }
    }
  }

  /**
   * @param bool $upgrade
   * If true: Does not show UF message if custom group already exists
   *
   * @throws \CiviCRM_API3_Exception
   */
  public function create(bool $upgrade = false) {

    $field = civicrm_api3(
      'CustomGroup',
      'get',
      [
        'sequential' => 1,
        'name'       => $this->getName(),
      ]
    );

    if ($field['count'] == 0) {

      $this->results = civicrm_api3('CustomGroup', 'create', $this->getSetAttributes());

      $this->id = $this->results['id'];

      if ($this->results['is_error'] == 0) {
        Civi::log()->info("$this->extensionName has created a new custom group.
      title: $this->title
      name: $this->name
      extends: $this->extends
      id: $this->id
      column_value: $this->extends_entity_column_value"
        );
      }
      else {
        if ($this->name) {
          Civi::log()->error("$this->extensionName could not create new custom group
        for \"$this->name\": $this->results['error_message']"
          );
          CRM_Utils_System::setUFMessage("Creation of custom group '$this->name'
      failed. Find more information in the logs.");
        }
        else {
          Civi::log()->error("$this->extensionName could not create new 
        custom group: $this->results['error_message']");
          CRM_Utils_System::setUFMessage("Creation of custom group 
      failed. Find more information in the logs.");
        }

      }
    }
    elseif (!$upgrade) {
      CRM_Utils_System::setUFMessage(E::ts('Creation of custom group \'%1\' failed, because a custom group with that name already exists. Find more information in the logs.', [1 => $this->name]));
      Civi::log()
        ->error("$this->extensionName could not create new custom group \"$this->name\" because a group with that name already exists.");
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
   * @return self
   * @throws \CiviCRM_API3_Exception
   */
  public static function fetch($name) {

    $custom_group = civicrm_api3(
      'CustomGroup',
      'get',
      [
        'sequential' => 1,
        'name'       => $name,
      ]
    );
    if ($custom_group = array_shift($custom_group['values'])) {
      return new self($custom_group);
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
      Civi::log()->info("$this->extensionName has deleted custom group.
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
        Civi::log()->error("$this->extensionName could not delete custom group
        \"$this->title\": $this->results['error_message']"
        );
      }
      else {
        Civi::log()->error("$this->extensionName could not delete custom group: 
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