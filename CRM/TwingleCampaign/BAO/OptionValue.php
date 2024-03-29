<?php

use CRM_TwingleCampaign_ExtensionUtil as E;

class CRM_TwingleCampaign_BAO_OptionValue {

  private $id;

  private $name;

  private $label;

  private $description;

  private $option_group_id;

  private $result;

  private $extensionName;

  /**
   * CRM_TwingleCampaign_BAO_OptionValue constructor.
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
   * Creates a OptionValue by calling CiviCRM API v.3
   *
   * @throws \CiviCRM_API3_Exception
   */
  public function create() {

    // Check if the option value already exists
    $ov = civicrm_api3(
      'OptionValue',
      'get',
      [
        'sequential' => 1,
        'name'       => $this->getName(),
      ]
    );

    // If the option value does not exist, create it
    if ($ov['count'] == 0) {
      $this->result = civicrm_api3(
        'OptionValue',
        'create',
        $this->getSetAttributes());

      // Set option value id
      $this->id = $this->result['id'];

      // Log custom value creation
      if ($this->result['is_error'] == 0) {
        Civi::log()->info("$this->extensionName has created a new option value.
      label: $this->label
      name: $this->name
      id: $this->id
      group: $this->option_group_id"
        );
      }
      // If the option value could not get created: log error
      else {
        CRM_Utils_System::setUFMessage("Creation of custom value '$this->name)'
      failed. Find more information in the logs.");
        if ($this->label && $this->option_group_id) {
          Civi::log()
            ->error("$this->extensionName could not create new option value
            \"$this->label\" for option group \"$this->option_group_id\": 
            $this->result['error_message']");
        }
        // If there is not enough information: log simple error message
        else {
          Civi::log()
            ->error("$this->extensionName could not create new option value: 
            $this->result['error_message']");
        }
      }
    }
    else {
      CRM_Utils_System::setUFMessage("Creation of custom value '$this->name'
      failed, because a custom value with that name already exists.
      Find more information in the logs."
        );
        Civi::log()
          ->error("$this->extensionName could not create new custom value
            \"$this->name\" for group \"$this->option_group_id\" because a 
            value with that name already exists.");
    }
  }


  /**
   * Gets all the set attributes of the object and returns them as an array.
   *
   * @return array
   */
  private
  function getSetAttributes(): array {
    $setAttributes = [];
    foreach ($this as $var => $value) {
      if (isset($value)) {
        $setAttributes[$var] = $value;
      }
    }
    return $setAttributes;
  }


  /**
   * Get an instance of a OptionValue by its name or get an array with all
   * option values by leaving parameters empty.
   *
   * @param string|null $name
   * The name of the option value you wish to instantiate.
   *
   * @return array|CRM_TwingleCampaign_BAO_OptionValue
   * The required OptionValue or an array with all OptionValues.
   *
   * @throws \CiviCRM_API3_Exception
   * @throws \Exception
   */
  public static function fetch(string $name = NULL) {

    // If no specific option value is requested
    if (!$name) {
      $result = [];

      // Get all custom fields for this extension from option_values.php
      $option_values =
        include(E::path() . '/CRM/TwingleCampaign/resources/option_values.php');

      // Log an error and throw an exception if the file cannot get read
      if (!$option_values) {
        Civi::log()->error("Could not read json file");
        throw new Exception('Could not read json file');
      }

      // Recursive method call with all custom field names from the json file
      foreach ($option_values as $optionValue) {
        $result[] = self::fetch($optionValue['name']);
      }
      return $result;
    }
    // If a specific option value is required
    try {
      $option_value = civicrm_api3(
        'OptionValue',
        'get',
        [
          'sequential' => 1,
          'name'       => $name,
        ]
      );
      if ($option_value = array_shift($option_value['values'])) {
        return new self($option_value);
      }
      else {
        return NULL;
      }
    } catch (CiviCRM_API3_Exception $e) {
      return NULL;
    }
  }

  /**
   * Delete an OptionValue
   *
   * @throws \CiviCRM_API3_Exception
   */
  public function delete() {

    // Delete this OptionValue by API call
    $this->result = civicrm_api3(
      'OptionValue',
      'delete',
      ['id' => $this->id]
    );

    // Check if custom field was deleted successfully
    if ($this->result['is_error'] == 0) {
      Civi::log()->info("$this->extensionName has deleted OptionValue.
      label: $this->label
      name: $this->name
      id: $this->id
      group: $this->option_group_id"
      );
    }
    // ... else: log error
    else {
      if ($this->label && $this->option_group_id) {
        Civi::log()
          ->error("$this->extensionName could not delete OptionValue
            \"$this->label\" for group \"$this->option_group_id\": 
            $this->result['error_message']");
      }
      else {
        Civi::log()
          ->error("$this->extensionName could not delete OptionValue: 
            $this->result['error_message']");
      }
    }
  }

  /**
   * @return mixed
   */
  public function getName() {
    return $this->name;
  }

  /**
   * @return mixed
   */
  public function getId() {
    return $this->id;
  }

  /**
   * @return mixed
   */
  public function getLabel() {
    return $this->label;
  }

  /**
   * @return string
   */
  public function getDescription(): string {
    return $this->description;
  }

  /**
   * @return mixed
   */
  public function getOptionGroupId() {
    return $this->option_group_id;
  }

}