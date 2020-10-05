<?php


namespace CRM\TwingleCampaign\BAO;

use CRM_TwingleCampaign_ExtensionUtil as E;

class CustomField {

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
      if (array_key_exists($var, $attributes)) {
        $this->$var = $attributes[$var];
      }
      if ($this->help_post) {
        $this->help_post = E::ts($this->help_post);
      }
    }
  }

  /**
   * @throws \CiviCRM_API3_Exception
   */
  public function create() {

    $field = civicrm_api3(
      'CustomField',
      'get',
      [
        'sequential' => 1,
        'name'       => $this->getName(),
      ]
    );

    if ($field['count'] == 0) {
      $this->result = civicrm_api3(
        'CustomField',
        'create',
        $this->getSetAttributes());

      $this->id = $this->result['id'];

      if ($this->result['is_error'] == 0) {
        \Civi::log()->info("Twingle Extension has created a new custom field.\n
      label: $this->label\n
      name: $this->name\n
      id: $this->id\n
      group: $this->custom_group_id"
        );
      }
      else {
        if ($this->label && $this->custom_group_id) {
          \Civi::log()
            ->error("Twingle Extension could not create new custom field
            \"$this->label\" for group \"$this->custom_group_id\": 
            $this->result['error_message']");
        }
        else {
          \Civi::log()
            ->error("Twingle Extension could not create new custom field: 
            $this->result['error_message']");
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
   * Alter a custom field
   *
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

    $this->result = civicrm_api3('CustomField', 'create', $this->getSetAttributes());

    return $this->result['is_error'] == 0;
  }

  /**
   * @param $name
   *
   * @return array|\CRM\TwingleCampaign\BAO\CustomField
   * @throws \CiviCRM_API3_Exception
   */
  public static function fetch($name = NULL) {

    if (!$name) {
      $customFields = [];

      $json_file = file_get_contents(E::path() .
        '/CRM/TwingleCampaign/Upgrader/resources/campaigns.json');
      $campaign_info = json_decode($json_file, TRUE);

      if (!$campaign_info) {
        \Civi::log()->error("Could not read json file");
        throw new \Exception('Could not read json file');
      }

      foreach ($campaign_info['custom_fields'] as $custom_field) {
        $result = CustomField::fetch($custom_field['name']);
        array_push($customFields, $result);
      }
      return $customFields;
    }
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
        return new CustomField($custom_field);
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
    $this->result = civicrm_api3(
      'CustomField',
      'delete',
      ['id' => $this->id]
    );

    if ($this->result['is_error'] == 0) {
      \Civi::log()->info("Twingle Extension has deleted custom field.\n
      label: $this->label\n
      name: $this->name\n
      id: $this->id\n
      group: $this->custom_group_id"
      );

      foreach ($this->getSetAttributes() as $var => $attribute) {
        $this->$var = NULL;
      }
    }
    else {
      if ($this->label && $this->custom_group_id) {
        \Civi::log()
          ->error("Twingle Extension could not delete custom field
            \"$this->label\" for group \"$this->custom_group_id\": 
            $this->result['error_message']");
      }
      else {
        \Civi::log()
          ->error("Twingle Extension could not delete custom field: 
            $this->result['error_message']");
      }
    }
  }

  /**
   * Get a custom field mapping
   *
   * @return array
   * @throws \CiviCRM_API3_Exception
   */
  public static function getMapping() {

    $customFields = CustomField::fetch();
    $customFieldMapping = [];

    foreach ($customFields as $customField) {
      $customFieldMapping[$customField->getName()] = 'custom_' . $customField->getId();
    }

    return $customFieldMapping;
  }


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