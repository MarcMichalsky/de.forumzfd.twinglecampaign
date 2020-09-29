<?php

namespace CRM\TwingleCampaign\Models;

use CRM_Civirules_Utils_LoggerFactory as Civi;

class CampaignType {

  private static $campaignTypes = [];
  private $id;
  private $name;
  private $label;
  private $value;
  private $option_group_id = 'campaign_type';
  private $results;

  /**
   * CampaignType constructor.
   *
   * @param array $attributes
   */
  public function __construct(array $attributes) {
    foreach ($this as $var => $value) {
      if (array_key_exists($var, $attributes)) {
        $this->$var = $attributes[$var];
      }
    }

    self::$campaignTypes[$this->name] = $this;
  }

  /**
   * @throws \CiviCRM_API3_Exception
   */
  public function create() {

    $field = civicrm_api3(
      'OptionValue',
      'get',
      [
        'sequential'      => 1,
        'option_group_id' => $this->option_group_id,
        'name'            => $this->getName()
      ]
    );

    if ($field['count'] == 0)
    {
      $this->results = civicrm_api3('OptionValue', 'create', $this->getSetAttributes());

      $this->value = array_column($this->results['values'], 'value')[0];

      if ($this->results['is_error'] == 0) {
        \Civi::log()->info("Twingle Extension has created a new campaign type.\n
      label: $this->label\n
      name: $this->name"
        );
      }
      else {
        \Civi::log()->error("Twingle Extension could not create new campaign type
      for \"$this->label\": $this->results['error_message']");
      }
    }
    else {
      $campaignType = CampaignType::fetch($this->name);
      foreach ($this as $var => $value) {
        if (array_key_exists($var, $campaignType->getSetAttributes())) {
          $this->$var = $campaignType->getSetAttributes()[$var];
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
      if (isset($this->$var)) {
        $this->$var = $value;
      }
    }

    $this->results = civicrm_api3('OptionValue', 'create', $this->getSetAttributes());

    return $this->results['is_error'] == 0;
  }

  /**
   * @param $name
   *
   * @return \CRM\TwingleCampaign\Models\CampaignType|null
   * @throws \CiviCRM_API3_Exception
   */
  public static function fetch($name) {
    $campaign_type = civicrm_api3(
      'OptionValue',
      'get',
      [
        'sequential' => 1,
        'name' => $name
      ]
    );
    if ($campaign_type = array_shift($campaign_type['values'])) {
      return new CampaignType($campaign_type);
    }
    else {
      return NULL;
    }
  }

  /**
   * @throws \CiviCRM_API3_Exception
   */
  public function delete() {
    $this->results = civicrm_api3(
      'OptionValue',
      'delete',
      ['id' => $this->id]
    );

    if ($this->results['is_error'] == 0) {
      \Civi::log()->info("Twingle Extension has deleted campaign type.\n
      label: $this->label\n
      name: $this->name"
      );

      foreach ($this->getSetAttributes() as $var => $attribute) {
        $this->$var = NULL;
      }
    }
    else {
      if ($this->label) {
        \Civi::log()->error("Twingle Extension could not delete campaign type
        \"$this->label\": $this->results['error_message']"
        );
      }
      else {
        \Civi::log()->error("Twingle Extension could not delete campaign type: 
        $this->results['error_message']");
      }
    }
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
   * @param string $value
   *
   * @return bool
   * @throws \CiviCRM_API3_Exception
   */
  public function setValue(string $value) {
    return $this->alter(['value', $value]);
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
   * @return array
   */
  public static function getCampaignTypes(): array {
    return self::$campaignTypes;
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
  public function getValue(): string {
    return $this->value;
  }

  /**
   * @return string
   */
  public function getLabel(): string {
    return $this->label;
  }

  /**
   * @return mixed
   */
  public function getResults() {
    return $this->results;
  }


}