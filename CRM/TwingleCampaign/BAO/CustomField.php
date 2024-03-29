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

  private $extensionName;

  /**
   * CustomField constructor.
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
   * Creates a CustomField by calling CiviCRM API v.3
   *
   * @param bool $upgrade
   * If true: Does not show UF message if custom field already exists
   *
   * @returns array Result of custom field creation api call
   * @throws \CiviCRM_API3_Exception
   */
  public function create(bool $upgrade = FALSE): ?array {

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

      try {
        $this->result = civicrm_api3(
          'CustomField',
          'create',
          $this->getSetAttributes());

        if ($this->result['is_error'] == 0) {

          // Set field id
          $this->id = $this->result['id'];

          // Log field creation
          Civi::log()->info("$this->extensionName has created a new custom field.
            label: $this->label
            name: $this->name
            id: $this->id
            group: $this->custom_group_id"
          );
          return $this->result;
        }
        else {
          throw new CiviCRM_API3_Exception($this->result['error_message']);
        }
      } catch (CiviCRM_API3_Exception $e) {
        $errorMessage = $e->getMessage();
        // If the field could not get created: log error
        if ($this->name && $this->custom_group_id) {
          Civi::log()
            ->error("$this->extensionName could not create new custom field \"$this->name\" for group \"$this->custom_group_id\": $errorMessage");
          CRM_Utils_System::setUFMessage(E::ts('%1: Creation of custom field \'%2\' failed. Find more information in the logs.',
            [1 => $this->extensionName, 2 => $this->name]
          ));
        }
        // If there is not enough information: log simple error message
        else {
          Civi::log()
            ->error("$this->extensionName could not create new custom field: $errorMessage");
          CRM_Utils_System::setUFMessage(E::ts("Creation of custom field failed. Find more information in the logs."));
        }
        return $this->result;
      }
    }
    elseif (!$upgrade) {
      CRM_Utils_System::setUFMessage(E::ts('Creation of custom field \'%1\' failed, because a custom field with that name already exists. Find more information in the logs.', [1 => $this->name]));
      Civi::log()
        ->error("$this->extensionName could not create new custom field \"$this->name\" for group \"$this->custom_group_id\" because a field with that name already exists.");
      return NULL;
    }
    else {
      return NULL;
    }
  }

  /**
   * Update an existing custom field
   *
   * @returns array Result of custom field creation api call
   */
  public function update(): array {

    try {
      $this->result = civicrm_api3(
        'CustomField',
        'create',
        $this->getSetAttributes());

      // Log field creation
      if ($this->result['is_error'] == 0) {
        Civi::log()->info("$this->extensionName has updated a custom field.
      label: $this->label
      name: $this->name
      id: $this->id
      group: $this->custom_group_id"
        );
        return $this->result;
      }
      else {
        throw new CiviCRM_API3_Exception($this->result['error_message']);
      }
    } catch (CiviCRM_API3_Exception $e) {
      // If the field could not get created: log error
      $errorMessage = $e->getMessage();
      if ($this->name && $this->custom_group_id) {
        Civi::log()
          ->error("$this->extensionName could not create new custom field \"$this->name\" for group \"$this->custom_group_id\": $errorMessage");
        CRM_Utils_System::setUFMessage(E::ts('Creation of custom field \'%1\' failed. Find more information in the logs.', [1 => $this->name]));
      }
      // If there is not enough information: log simple error message
      else {
        Civi::log()
          ->error("$this->extensionName could not create new custom field: $errorMessage");
        CRM_Utils_System::setUFMessage(E::ts("Creation of custom field failed. Find more information in the logs."));
      }
      return $this->result;
    }
  }

  /**
   * Add additional options to custom field
   *
   * @param array $options
   *
   * @return array
   */
  public function addOptions(array $options): array {
    $result = [];

    try {
      $option_group_id = civicrm_api3(
        'CustomField',
        'getsingle',
        ['id' => $this->id]
      )['option_group_id'];
    } catch (CiviCRM_API3_Exception $e) {
      $errorMessage = $e->getMessage();
      Civi::log()
        ->error("$this->extensionName could not get get option group id for custom field \"$this->name\": $errorMessage");
      CRM_Utils_System::setUFMessage(
        E::ts('%1 could not get option group id for custom field \'%2\'. Find more information in the logs.',
          [1 => $this->extensionName, 2 => $this->name])
      );
    }

    try {
      foreach ($options as $key => $value) {

        $option_value_exists = civicrm_api3(
          'OptionValue',
          'get',
          [
            'sequential'      => 1,
            'option_group_id' => $option_group_id,
            'value'           => $key,
          ]
        );

        // If the option value does not yet exist, create it
        if ($option_value_exists['count'] == 0) {
          $result[] = civicrm_api3(
            'OptionValue',
            'create',
            [
              'option_group_id' => $option_group_id,
              'value'           => $key,
              'label'           => $value,
            ]
          );
        }
        // If the option value already exist, update it
        else {
          $result[] = civicrm_api3(
            'OptionValue',
            'create',
            [
              'id'         => $option_value_exists['values'][0]['id'],
              'label'      => $value,
            ]
          );
        }
      }
    } catch (CiviCRM_API3_Exception $e) {
      $errorMessage = $e->getMessage();
      Civi::log()
        ->error("$this->extensionName could not create additional option values for custom field \"$this->name\": $errorMessage");
      CRM_Utils_System::setUFMessage(
        E::ts('%1 could not create additional option values for custom field \'%2\'. Find more information in the logs.',
          [1 => $this->extensionName, 2 => $this->name])
      );
    }

    return $result;
  }

  /**
   * Gets all the set attributes of the object and returns them as an array.
   *
   * @return array
   * Array with all set attributes of this object.
   */
  private function getSetAttributes(): array {
    $setAttributes = [];
    foreach ($this as $var => $value) {
      if (isset($value)) {
        $setAttributes[$var] = $value;
      }
    }
    return $setAttributes;
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
      $result = [];

      // Get array with all custom fields for this extension
      $campaign_info =
        require E::path() . '/CRM/TwingleCampaign/resources/campaigns.php';

      // Recursive method call with all custom field names from the json file
      foreach ($campaign_info['custom_fields'] as $customField) {
        $result[] = self::fetch($customField['name']);
      }
      return $result;
    }
    // If a specific custom field is required
    try {
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
    } catch (CiviCRM_API3_Exception $e) {
      return NULL;
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
      Civi::log()->info("$this->extensionName has deleted custom field.
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
          ->error("$this->extensionName could not delete custom field
            \"$this->label\" for group \"$this->custom_group_id\": 
            $this->result['error_message']");
      }
      else {
        Civi::log()
          ->error("$this->extensionName could not delete custom field: 
            $this->result['error_message']");
      }
    }
  }

  /**
   * Get a custom field mapping (e.g. ['twingle_project_id' => 'custom_42'])
   *
   * @return array
   * Associative array with a mapping of all custom fields used by this
   *   extension
   *
   * @throws \CiviCRM_API3_Exception
   */
  public static function getMapping(): array {

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
  }

  /**
   * @param mixed $option_values
   */
  public function setOptionValues($option_values): void {
    $this->option_values = $option_values;
  }

}