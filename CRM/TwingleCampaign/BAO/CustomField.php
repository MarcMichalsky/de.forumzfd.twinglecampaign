<?php

use CRM_TwingleCampaign_ExtensionUtil as E;

class CRM_TwingleCampaign_BAO_CustomField {

  private $id;

  private $custom_group_id;

  private $label;

  private $name;

  private $is_required;

  private $is_searchable;

  private $data_type;

  private $html_type;

  private $option_values;

  private $text_length;

  private $is_active;

  private $is_view;

  private $weight;

  private $help_post;

  private $default_value;

  private $result;

  /**
   * CustomField constructor.
   *
   * @param array $attributes
   */
  public function __construct(array $attributes) {
    foreach ($this as $var => $value) {

      // put array items into attributes
      if (array_key_exists($var, $attributes)) {
        $this->$var = $attributes[$var];
      }

      // translate help_post
      if ($this->help_post) {
        $this->help_post = E::ts($this->help_post);
      }
    }
  }


  /**
   * Creates a CustomField by calling CiviCRM API v.3
   *
   * @throws \CiviCRM_API3_Exception
   */
  public function create() {

    // Check if the field already exists
    $field = civicrm_api3(
      'CustomField',
      'get',
      [
        'sequential' => 1,
        'name'       => $this->getName(),
      ]
    );

    // If the field does not exist, create it
    if ($field['count'] == 0) {
      $this->result = civicrm_api3(
        'CustomField',
        'create',
        $this->getSetAttributes());

      // Set field id
      $this->id = $this->result['id'];

      // Log field creation
      if ($this->result['is_error'] == 0) {
        Civi::log()->info("Twingle Extension has created a new custom field.
      label: $this->label
      name: $this->name
      id: $this->id
      group: $this->custom_group_id"
        );
      }
      // If the field could not get created: log error
      else {
        if ($this->label && $this->custom_group_id) {
          Civi::log()
            ->error("Twingle Extension could not create new custom field
            \"$this->label\" for group \"$this->custom_group_id\": 
            $this->result['error_message']");
        }
        // If there is not enough information: log simple error message
        else {
          Civi::log()
            ->error("Twingle Extension could not create new custom field: 
            $this->result['error_message']");
        }
      }
    }
  }

  /**
   * Gets all the set attributes of the object and returns them as an array.
   *
   * @return array
   * Array with all set attributes of this object.
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
   * Alter a custom field.
   *
   * @param $values
   * Values to alter.
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

    $this->result = civicrm_api3('CustomField', 'create', $this->getSetAttributes());

    return $this->result['is_error'] == 0;
  }

  /**
   * Get an instance of a CustomField by its name or get an array with all
   * custom fields by leaving parameters empty.
   *
   * @param string|null $name
   * The name of the field you wish to instantiate.
   *
   * @return array|CRM_TwingleCampaign_BAO_CustomField
   * The required CustomField or an array with all custom fields.
   *
   * @throws \CiviCRM_API3_Exception
   * @throws \Exception
   */
  public static function fetch(string $name = NULL) {

    // If no specific custom field is requested
    if (!$name) {
      $customFields = [];

      // Get json file with all custom fields for this extension
      $json_file = file_get_contents(E::path() .
        '/CRM/TwingleCampaign/resources/campaigns.json');
      $campaign_info = json_decode($json_file, TRUE);

      // Log an error and throw an exception if the file cannot get read
      if (!$campaign_info) {
        Civi::log()->error("Could not read json file");
        throw new Exception('Could not read json file');
      }

      // Recursive method call with all custom field names from the json file
      foreach ($campaign_info['custom_fields'] as $custom_field) {
        $result = self::fetch($custom_field['name']);
        array_push($customFields, $result);
      }
      return $customFields;
    }
    // If a specific custom field is required
    else {
      $custom_field = civicrm_api3(
        'CustomField',
        'get',
        [
          'sequential' => 1,
          'name'       => $name,
        ]
      );
      if ($custom_field = array_shift($custom_field['values'])) {
        return new self($custom_field);
      }
      else {
        return NULL;
      }
    }
  }

  /**
   * Delete a custom field
   *
   * @throws \CiviCRM_API3_Exception
   */
  public function delete() {

    // Delete this custom field by API call
    $this->result = civicrm_api3(
      'CustomField',
      'delete',
      ['id' => $this->id]
    );

    // Check if custom field was deleted successfully
    if ($this->result['is_error'] == 0) {
      Civi::log()->info("Twingle Extension has deleted custom field.
      label: $this->label
      name: $this->name
      id: $this->id
      group: $this->custom_group_id"
      );
    }
    // ... else: log error
    else {
      if ($this->label && $this->custom_group_id) {
        Civi::log()
          ->error("TwingleCampaign Extension could not delete custom field
            \"$this->label\" for group \"$this->custom_group_id\": 
            $this->result['error_message']");
      }
      else {
        Civi::log()
          ->error("TwingleCampaign Extension could not delete custom field: 
            $this->result['error_message']");
      }
    }
  }

  /**
   * Get a custom field mapping (e.g. ['project_id' => 'custom_42'])
   *
   * @return array
   * Associative array with a mapping of all custom fields used by this extension
   *
   * @throws \CiviCRM_API3_Exception
   */
  public static function getMapping() {

    // Get an array with all custom fields
    $customFields = self::fetch();

    $customFieldMapping = [];

    // Create a mapping (e.g. ['project_id' => 'custom_42'])
    foreach ($customFields as $customField) {
      if ($customField) {
        $customFieldMapping[$customField->getName()] = 'custom_' . $customField->getId();
      }
    }

    return $customFieldMapping;
  }


  // TODO: Remove unnecessary getters and setters
  /**
   * @param string $custom_group_id
   *
   * @return bool
   * @throws \CiviCRM_API3_Exception
   */
  public function setCustomGroupId(string $custom_group_id) {
    return $this->alter(['custom_group_id', $custom_group_id]);
  }

  /**
   * @param string $label
   *
   * @return bool
   * @throws \CiviCRM_API3_Exception
   */
  public function setLabel(string $label) {
    return $this->alter(['label', $label]);
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
   * @param int $is_required
   *
   * @return bool
   * @throws \CiviCRM_API3_Exception
   */
  public function setIsRequired(int $is_required) {
    return $this->alter(['is_required', $is_required]);
  }

  /**
   * @param int $is_searchable
   *
   * @return bool
   * @throws \CiviCRM_API3_Exception
   */
  public function setIsSearchable(int $is_searchable) {
    return $this->alter(['is_searchable', $is_searchable]);
  }

  /**
   * @param string $data_type
   *
   * @return bool
   * @throws \CiviCRM_API3_Exception
   */
  public function setDataType(string $data_type) {
    return $this->alter(['data_type', $data_type]);
  }

  /**
   * @param string $html_type
   *
   * @return bool
   * @throws \CiviCRM_API3_Exception
   */
  public function setHtmlType(string $html_type) {
    return $this->alter(['html_type', $html_type]);
  }

  /**
   * @param mixed $option_values
   */
  public function setOptionValues($option_values) {
    $this->option_values = $option_values;
  }

  /**
   * @param int $text_length
   *
   * @return bool
   * @throws \CiviCRM_API3_Exception
   */
  public function setTextLength(int $text_length) {
    return $this->alter(['text_length', $text_length]);
  }

  /**
   * @param int $is_active
   *
   * @return bool
   * @throws \CiviCRM_API3_Exception
   */
  public function setIsActive(int $is_active) {
    return $this->alter(['is_active', $is_active]);
  }

  /**
   * @param int $is_view
   *
   * @return bool
   * @throws \CiviCRM_API3_Exception
   */
  public function setIsView(int $is_view) {
    return $this->alter(['is_view', $is_view]);
  }

  /**
   * @param int $weight
   *
   * @return bool
   * @throws \CiviCRM_API3_Exception
   */
  public function setWeight(int $weight) {
    return $this->alter(['weight', $weight]);
  }

  /**
   * @param mixed $help_post
   */
  public function setHelpPost($help_post) {
    $this->help_post = $help_post;
  }

  /**
   * @param mixed $default_value
   */
  public function setDefaultValue($default_value) {
    $this->default_value = $default_value;
  }

  /**
   * @return string
   */
  public function getCustomGroupId(): string {
    return $this->custom_group_id;
  }

  /**
   * @return string
   */
  public function getLabel(): string {
    return $this->label;
  }

  /**
   * @return string
   */
  public function getName(): string {
    return $this->name;
  }

  /**
   * @return int
   */
  public function getIsRequired(): int {
    return $this->is_required;
  }

  /**
   * @return int
   */
  public function getIsSearchable(): int {
    return $this->is_searchable;
  }

  /**
   * @return string
   */
  public function getDataType(): string {
    return $this->data_type;
  }

  /**
   * @return string
   */
  public function getHtmlType(): string {
    return $this->html_type;
  }

  /**
   * @return mixed
   */
  public function getOptionValues() {
    return $this->option_values;
  }

  /**
   * @return int
   */
  public function getTextLength(): int {
    return $this->text_length;
  }

  /**
   * @return int
   */
  public function getIsActive(): int {
    return $this->is_active;
  }

  /**
   * @return int
   */
  public function getIsView(): int {
    return $this->is_view;
  }

  /**
   * @return int
   */
  public function getWeight(): int {
    return $this->weight;
  }

  /**
   * @return mixed
   */
  public function getHelpPost() {
    return $this->help_post;
  }

  /**
   * @return mixed
   */
  public function getDefaultValue() {
    return $this->default_value;
  }

  /**
   * @return mixed
   */
  public function getId() {
    return $this->id;
  }#

}