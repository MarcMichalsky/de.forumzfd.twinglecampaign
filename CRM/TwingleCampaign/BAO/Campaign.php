<?php

use CRM_TwingleCampaign_Utils_ExtensionCache as Cache;

abstract class CRM_TwingleCampaign_BAO_Campaign {

  // IN means: heading into CiviCRM database
  public const IN = 'IN';

  // OUT means: coming from the CiviCRM database
  public const OUT = 'OUT';

  public const PROJECT = 'TwingleProject';

  public const EVENT = 'TwingleEvent';

  protected $className;

  protected $id;

  protected $values;

  protected $id_custom_field = NULL;

  protected $prefix = NULL;

  protected  $formattedValues;

  /**
   * ## Campaign constructor.
   *
   * @param array $values
   * @param int|null $id
   */
  protected function __construct(array $values = [], int $id = NULL) {

    $this->id = $id;
    $tmpClassName = explode('_', get_class($this));
    $this->className = array_pop($tmpClassName);

    // Set campaign values
    $this->update($values);
  }

  /**
   * ## Create this entity as campaign in CiviCRM
   *
   * Returns _TRUE_ if creation was successful or _FALSE_ if it creation failed.
   *
   * @param bool $no_hook
   * Do not trigger postSave hook to prevent recursion
   *
   * @return bool
   * @throws \Exception
   */
  public function create(bool $no_hook = FALSE): bool {
    // Prepare project values for import into database
    $values_prepared_for_import = $this->values;
    $this->formatValues(
      $values_prepared_for_import,
      self::IN
    );
    $this->translateKeys(
      $values_prepared_for_import,
      $this->className,
      self::IN
    );
    $this->formattedValues = $values_prepared_for_import;
    $this->translateCustomFields(
      $values_prepared_for_import,
      self::IN
    );

    // Set id
    if (isset($this->id)) {
      $values_prepared_for_import['id'] = $this->id;
    }

    // Set a flag to not trigger the hook
    if ($no_hook) {
      $_SESSION['CiviCRM']['de.forumzfd.twinglecampaign']['no_hook'] = TRUE;
    }

    // Cast booleans to integers
    foreach ($values_prepared_for_import as $key => $value) {
      if ($value === false) {
        $values_prepared_for_import[$key] = 0;
      }
      elseif ($value === true) {
        $values_prepared_for_import[$key] = 1;
      }
    }

    // Create campaign
    $result = civicrm_api3('Campaign', 'create', $values_prepared_for_import);

    // Update id
    $this->id = $result['id'];

    // Check if campaign was created successfully
    if ($result['is_error'] != 0) {
      throw new Exception($result['error_message']);
    }
    return TRUE;
  }

  /**
   * ## Update instance values
   * This method updates the **$values** array of this instance with the values
   * from the provided array if they are defined in
   * *CRM/TwingleCampaign/resources/twingle_api_templates.php*
   *
   * @param array $values
   * Array with values to update
   */
  public function update(array $values) {
    // Update campaign values
    $filter = Cache::getInstance()->getTemplates()[$this->className];
    foreach ($values as $key => $value) {
      if ($this->in_array_r($key, $filter)) {
        $this->values[$key] = $value;
      }
    }
  }

  /**
   * ## Complement campaign values
   * Complement existing campaign values with new ones.
   * Existing values will not get overwritten.
   *
   * @param array $arrayToComplement
   */
  public function complement(array $arrayToComplement) {
    $this->complement_r($this->values, $arrayToComplement);
    $this->values = $arrayToComplement;
  }

  private function complement_r($orig, &$fill) {
    foreach ($orig as $key => $value) {
      if (is_array($value)) {
        $this->complement_r($orig[$key], $fill[$key]);
      } else {
        $fill[$key] = $value;
      }
    }
  }

  /**
   * ## Merge campaign values
   * Merge existing campaign values with new ones.
   * Existing values will be overwritten!
   *
   * @param array $arrayToMerge
   */
  public function merge(array $arrayToMerge) {
    $this->complement_r($arrayToMerge, $this->values);
  }

  private function merge_r($orig, &$fill) {
    foreach ($orig as $key => $value) {
      if (is_array($value)) {
        $this->complement_r($orig[$key], $fill[$key]);
      } else {
        $fill[$key] = $value;
      }
    }
  }

  public static abstract function formatValues(array &$values, string $direction);

  /**
   * ## Translate array keys between CiviCRM Campaigns and Twingle
   *
   * Constants for **$direction**:<br>
   * **Campaign::IN** translate array keys from Twingle format into
   * CiviCRM format <br>
   * **Campaign::OUT** translate array keys from CiviCRM format into
   * Twingle format
   *
   * @param array $values
   * array of keys to translate
   * @param string $campaignType
   * const: Campaign::PROJECT or Campaign::EVENT
   * @param string $direction
   * const: Campaign::OUT or Campaign::OUT
   *
   * @throws \Exception
   */
  public static function translateKeys(
    array &$values,
    string $campaignType,
    string $direction) {

    if ($campaignType != self::PROJECT && $campaignType != self::EVENT) {
      throw new Exception(
        "Invalid Parameter $campaignType for translateKeys()"
      );
    }

    // Get translations for fields
    $field_translations = Cache::getInstance()
      ->getTranslations()[$campaignType];

    // Set the direction of the translation
    if ($direction == self::OUT) {
      $field_translations = array_flip($field_translations);
    }
    // Throw error if $direction constant does not match IN or OUT
    elseif ($direction != self::IN) {
      throw new Exception(
        "Invalid Parameter $direction for translateKeys()"
      );
    }

    // Translate keys
    foreach ($field_translations as $origin => $translation) {
      if (isset($values[$origin])) {
        $values[$translation] = $values[$origin];
        unset($values[$origin]);
      }
    }
  }

  /**
   * ## Translate field names and custom field names
   *
   * Constants for **$direction**:<br>
   * **Campaign::IN** Translate field name to custom field name <br>
   * **Campaign::OUT** Translate from custom field name to field name
   *
   * @param array $values
   * array of keys to translate
   *
   * @param string $direction
   * const: Campaign::OUT or Campaign::OUT
   */
  public function translateCustomFields(array &$values, string $direction) {

    // Translate field name to custom field name
    if ($direction == self::IN) {

      foreach (Cache::getInstance()
                 ->getCustomFieldMapping() as $field => $custom) {

        if (array_key_exists(
          str_replace(
            $this->prefix,
            '',
            $field
          ),
          $values)
        ) {

          $values[$custom] = $values[str_replace(
            $this->prefix,
            '',
            $field
          )];

          unset($values[str_replace(
              $this->prefix,
              '',
              $field
            )]
          );
        }
      }
    }
    // Translate from custom field name to field name
    elseif ($direction == self::OUT) {

      foreach (Cache::getInstance()
                 ->getCustomFieldMapping() as $field => $custom) {

        if (array_key_exists(
          $custom,
          $values
        )
        ) {
          $values[str_replace(
            $this->prefix,
            '',
            $field
          )] = $values[$custom];
          unset($values[$custom]);
        }
      }
    }
  }

  /**
   * ## Delete Campaign
   * Deletes this Campaign from CiviCRM
   *
   * @throws CiviCRM_API3_Exception
   */
  public function delete(): bool {

    // Delete campaign via Campaign.delete api
    if ($this->getId()) {
      $result = civicrm_api3('Campaign', 'delete',
        ['id' => $this->getId()]);
    }
    else {
      throw new CiviCRM_API3_Exception($this->className . ' not found');
    }
    return ($result['is_error'] == 0);
  }

  /**
   * ## Deactivate this campaign
   *
   * @return bool
   * TRUE if deactivation was successful
   *
   * @throws CiviCRM_API3_Exception
   */
  public function deactivate(): bool {
    return self::deactivateByid($this->id);
  }

  /**
   * ## Deactivate campaign by ID
   *
   * @param $id
   * ID of the campaign that should get deactivated
   *
   * @return bool
   * TRUE if deactivation was successful
   *
   * @throws CiviCRM_API3_Exception
   */
  public static function deactivateById($id): bool {

    $result = civicrm_api3('Campaign', 'getsingle', [
      'id'         => $id,
      'sequential' => 1,
    ]);

    $result = civicrm_api3('Campaign', 'create', [
      'title'     => $result['title'],
      'id'        => $id,
      'is_active' => '0',
    ]);

    // Return TRUE if the campaign was deactivated successfully
    if ($result['is_error'] == 0) {
      return TRUE;
    }
    // Return FALSE if deactivation failed
    else {
      return FALSE;
    }
  }

  public abstract function getResponse(string $status): array;

  /**
   * ## Get timestamp
   * Validates **$input** to be either a *DateTime string* or an *Unix
   * timestamp* and in both cases returns a *Unix time stamp*.
   *
   * @param $input
   * Provide a DateTime string or a Unix timestamp
   *
   * @return int|null
   * Returns a Unix timestamp or NULL if $input is invalid
   */
  public static function getTimestamp($input): ?int {

    // Check whether $input is a Unix timestamp
    if (
    $dateTime = DateTime::createFromFormat('U', $input)
    ) {
      return $input;
    }
    // ... or a DateTime string
    elseif (
    $dateTime = DateTime::createFromFormat('Y-m-d H:i:s', $input)
    ) {
      return $dateTime->getTimestamp();
    }
    // ... or invalid
    else {
      return NULL;
    }
  }

  public abstract function lastUpdate();

  /**
   * ## Get DateTime
   * Validates **$input** to be either a *DateTime string* or an *Unix
   * timestamp* and in both cases returns *DateTime string*.
   *
   * @param $input
   * Provide a DateTime string or a Unix timestamp
   *
   * @return string
   * Returns a DateTime string or NULL if $input is invalid
   */
  public static function getDateTime($input): ?string {

    // Check whether $input is a Unix timestamp
    if (
    $dateTime = DateTime::createFromFormat('U', $input)
    ) {
      return $dateTime
        ->setTimezone(new DateTimeZone(date_default_timezone_get()))
        ->format('Y-m-d H:i:s');
    }
    // ... or a DateTime string
    elseif (
    $dateTime = DateTime::createFromFormat('Y-m-d H:i:s', $input)
    ) {
      return $input;
    }
    // ... or invalid
    else {
      return NULL;
    }
  }

  /**
   * ## Get id
   * Returns the **id** of this campaign.
   *
   * @return int
   */
  public function getId(): int {
    return (int) $this->id;
  }

  /**
   * Helper function to search a value in a multidimensional array.
   *
   * @param $needle
   * @param $haystack
   * @param bool $strict
   *
   * @return bool
   */
  protected function in_array_r($needle, $haystack, $strict = false): bool {
    foreach ($haystack as $item) {
      if (($strict ? $item === $needle : $item == $needle) ||
        (is_array($item) && $this->in_array_r($needle, $item, $strict))) {
        return true;
      }
    }
    return false;
  }

  /**
   * Helper function to check if the provided field is of type Boolean
   *
   * @param $fieldName
   * @param string $campaignType Campaign::PROJECT or Campaign::EVENT
   *
   * @return bool
   * @throws \Exception
   */
  public static function isBoolean($fieldName, string $campaignType): bool {
    $fields = Cache::getInstance()->getCampaigns()['custom_fields'];
    if ($campaignType == self::PROJECT) {
      if (isset($fields['twingle_project_' . $fieldName])) {
        return $fields['twingle_project_' . $fieldName]['data_type'] == 'Boolean';
      }
      else {
        return FALSE;
      }
    }
    elseif ($campaignType == self::EVENT) {
      if (isset($fields['twingle_event_' . $fieldName])) {
        return $fields['twingle_event_' . $fieldName]['data_type'] == 'Boolean';
      }
      else {
        return FALSE;
      }
    }
    throw new Exception('Unknown campaign type');
  }
}
