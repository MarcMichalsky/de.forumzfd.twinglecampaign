<?php


class CRM_TwingleCampaign_BAO_CampaignType {

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
  public function create(bool $upgrade = false) {

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
        Civi::log()->info("Twingle Extension has created a new campaign type.
      label: $this->label
      name: $this->name"
        );
      }
      else {
        $error_message = $this->results['error_message'];
        Civi::log()->error("Twingle Extension could not create new campaign type
      for \"$this->label\": $error_message");
      }
    }
    elseif (!$upgrade) {
      $campaignType = self::fetch($this->name);
      foreach ($this as $var => $value) {
        if (array_key_exists($var, $campaignType->getSetAttributes())) {
          $this->$var = $campaignType->getSetAttributes()[$var];
        }
      }
    }
    else {
      $this->value = $field['values'][0]['value'];
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
   * @param string $name
   *
   * @return CRM_TwingleCampaign_BAO_CampaignType|null
   * @throws \CiviCRM_API3_Exception
   */
  public static function fetch(string $name) {
    $campaign_type = civicrm_api3(
      'OptionValue',
      'get',
      [
        'sequential' => 1,
        'name' => $name
      ]
    );
    if ($campaign_type = array_shift($campaign_type['values'])) {
      return new self($campaign_type);
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
      Civi::log()->info("TwingleCampaign Extension has deleted campaign type.\n
      label: $this->label\n
      name: $this->name"
      );

      foreach ($this->getSetAttributes() as $var => $attribute) {
        $this->$var = NULL;
      }
    }
    else {
      $error_message = $this->results['error_message'];
      if ($this->label) {
        Civi::log()->error("TwingleCampaign Extension could not delete campaign type
        \"$this->label\": $error_message"
        );
      }
      else {
        Civi::log()->error("TwingleCampaign Extension could not delete campaign type: 
        $error_message"
        );
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