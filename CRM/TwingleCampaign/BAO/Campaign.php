<?php

use CRM_TwingleCampaign_Utils_ExtensionCache as Cache;
use CRM_TwingleCampaign_BAO_CampaignType as CampaignType;
use CRM_TwingleCampaign_BAO_TwingleProject as TwingleProject;
use CRM_TwingleCampaign_BAO_TwingleEvent as TwingleEvent;
use CRM_TwingleCampaign_BAO_TwingleCampaign as TwingleCampaign;

abstract class CRM_TwingleCampaign_BAO_Campaign {

  // IN means: heading into CiviCRM database
  public const IN = 'IN';

  // OUT means: coming from the CiviCRM database
  public const OUT = 'OUT';

  protected $className;

  protected $id;

  protected $values;

  protected $id_custom_field = NULL;

  protected $prefix = NULL;


  /**
   * Campaign constructor.
   *
   * @param array $campaign
   * Result array of Twingle API call
   *
   * Origin of the arrays. It can be one of two constants:
   * Campaign::TWINGLE|CIVICRM
   *
   * @throws Exception
   */
  protected function __construct(array $campaign) {

    $tmpClassName = explode('_', get_class($this));
    $this->className = array_pop($tmpClassName);

    // Set campaign values
    $this->update($campaign);
  }

  /**
   * Check if a campaign already exists and if so set attributes
   * to the values of the existing campaign.
   *
   * @return bool
   * Returns TRUE id the campaign already exists
   *
   * @throws CiviCRM_API3_Exception
   *
   * @throws Exception
   */
  public function exists(): bool {

    if (!$this->values['id']) {
      return FALSE;
    }

    $single = FALSE;

    $query = ['sequential' => 1,];

    switch($this->className) {
      case 'TwingleProject':
        $query['project_id'] = $this->values['id'];
        break;
      case 'TwingleEvent':
        $query['event_id'] = $this->values['id'];
    }

    $result = civicrm_api3($this->className, 'get', $query);

    // If there is more than one campaign for this entity, handle the duplicates
    if ($result['count'] > 1) {
      self::handleDuplicates($result);
    }
    else {
      $single = TRUE;
    }

    // If this campaign already exists, get its attributes
    if ($result['count'] == 1) {

      // Extract campaign values from result array
      $values = $result['values'][0];

      // Set id attribute
      $this->id = $values['id'];

      // Set attributes to the values of the existing campaign
      // to reflect the state of the actual campaign in the database
      $this->update($values);

      return TRUE;
    }
    else {
      return FALSE;
    }
  }


  /**
   * Update an existing campaign
   *
   * @param array $values
   * Array with values to update
   *
   */
  public function update(array $values) {
    // Update campaign values
    $filter = Cache::getInstance()->getTemplates()[$this->className];
    foreach ($values as $key => $value) {
      if ($this->className == "TwingleProject" && $key == 'project_id') {
        $this->values['id'] = $value;
      }
      else if (in_array($key, $filter)) {
        $this->values[$key] = $value;
      }
    }
  }


  /**
   * Deactivate all duplicates of a campaign but the newest one
   *
   * @param array $result
   *
   * @throws CiviCRM_API3_Exception
   */
  protected function handleDuplicates(array &$result) {

    // Sort campaigns ascending by the value of the last_modified_date
    uasort($result['values'], function ($a, $b) {
      return $a['last_modified_date'] <=> $b['last_modified_date'];
    });

    // Deactivate all but the first campaign
    while (sizeof($result['values']) > 1) {
      self::deactivateById(array_pop($result['values']));
    }
  }

  /**
   * Translate values between CiviCRM Campaigns and Twingle format
   *
   * @param array $values
   * array of which values shall be translated
   *
   * @param string $direction
   * TwingleProject::IN -> translate array values from Twingle to CiviCRM <br>
   * TwingleProject::OUT -> translate array values from CiviCRM to Twingle
   *
   * @throws Exception
   */
  public static abstract function formatValues(array &$values, string $direction);


  /**
   * Translate array keys between CiviCRM Campaigns and Twingle
   *
   * @param array $values
   * array of which keys to translate
   *
   * @param string $direction
   * Campaign::IN -> translate array keys from Twingle format into
   * CiviCRM format <br>
   * Campaign::OUT -> translate array keys from CiviCRM format into
   * Twingle format
   *
   * @throws Exception
   */
  public static abstract function translateKeys(array &$values, string $direction);


  /**
   * Translate between field names and custom field names
   *
   * @param array $values
   * array of which keys shall be translated
   *
   * @param string $direction
   * Campaign::IN -> translate field names into custom field names <br>
   * Campaign::OUT -> translate custom field names into field names
   *
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
   * Set embed data fields
   *
   * @param array $embedData
   * Array with embed data from Twingle API
   */
  public function setEmbedData(array $embedData) {

    // Get all embed_data keys from template
    $embed_data_keys = Cache::getInstance()
      ->getTemplates()['project_embed_data'];

    // Transfer all embed_data values
    foreach ($embed_data_keys as $key) {
      $this->values[$key] = htmlspecialchars($embedData[$key]);
    }
  }

  /**
   * Set counter url
   *
   * @param String $counterUrl
   * URL of the counter
   */
  public function setCounterUrl(string $counterUrl) {
    $this->values['counter'] = $counterUrl;
  }

  /**
   * Deactivate this Campaign campaign
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
   * Deactivate a Campaign campaign by ID
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


  /**
   * Get a response that describes the status of a Campaign
   *
   * @param string $status
   * status of the Campaign you want the response for
   *
   * @return array
   * Returns a response array that contains title, id, project_id and status
   */
  public abstract function getResponse(string $status): array;

  /**
   * Validates $input to be either a DateTime string or an Unix timestamp
   *
   * @param $input
   * Pass a DateTime string or a Unix timestamp
   *
   * @return int
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

  /**
 * Return a timestamp of the last update of the Campaign
 *
 * @return int|string|null
 */
  public abstract function lastUpdate();


  /**
   * Validates $input to be either a DateTime string or an Unix timestamp
   *
   * @param $input
   * Pass a DateTime string or a Unix timestamp
   *
   * @return string
   * Returns a DateTime string or NULL if $input is invalid
   */
  public static function getDateTime($input) {

    // Check whether $input is a Unix timestamp
    if (
    $dateTime = DateTime::createFromFormat('U', $input)
    ) {
      return $dateTime->format('Y-m-d H:i:s');
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
   * Returns the project_id of a Campaign
   *
   * @return int
   */
  public function getProjectId() {
    return $this->values['id'];
  }


  /**
   * @return mixed
   */
  public function getId() {
    return $this->id;
  }

}
