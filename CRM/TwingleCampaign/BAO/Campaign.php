<?php


namespace CRM\TwingleCampaign\BAO;

use CRM_TwingleCampaign_ExtensionUtil as E;
use CRM\TwingleCampaign\Utils\ExtensionCache as Cache;
use DateTime;
use Exception;
use CiviCRM_API3_Exception;


abstract class Campaign {

  // IN means: heading into CiviCRM database
  public const IN = 'IN';

  // OUT means: coming from the CiviCRM database
  public const OUT = 'OUT';

  public const CIVICRM = 'CIVICRM';

  public const TWINGLE = 'TWINGLE';

  protected $className;

  protected $id;

  protected $values;

  protected $id_custom_field = NULL;


  /**
   * Campaign constructor.
   *
   * @param array $campaign
   * Result array of Twingle API call
   *
   * @param string $origin
   * Origin of the arrays. It can be one of two constants:
   * Campaign::TWINGLE|CIVICRM
   *
   * @throws Exception
   */
  public function __construct(array $campaign, string $origin) {

    $this->className = get_class($this);

    // If values come from CiviCRM Campaign API
    if ($origin == self::CIVICRM) {

      // Set id (campaign id) attribute
      $this->id = $campaign['id'];

      // Translate custom field names into Twingle field names
      self::translateCustomFields($campaign, self::OUT);

      // Translate keys and values
      self::formatValues($campaign, self::OUT);
      self::translateKeys($campaign, self::OUT);

    }

    // Set project values attribute
    $this->values = $campaign;

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
  public function exists() {

    $result = [];
    $single = FALSE;

    // If there is more than one campaign for this entity, handle the duplicates
    while (!$single) {
      $result = civicrm_api3('Campaign', 'get', [
        'sequential'           => 1,
        'is_active'            => 1,
        $this->id_custom_field => $this->values['id'],
      ]);

      if ($result['count'] > 1) {
        // TODO: abort loop if function fails
        self::handleDuplicates($result);
      }
      else {
        $single = TRUE;
      }
    }

    // If this campaign already exists, get its attributes
    if ($result['count'] == 1) {

      // Extract campaign values from result array
      $values = $result['values'][0];

      // Set id attribute
      $this->id = $values['id'];

      // Translate custom field names back
      self::translateCustomFields($values, self::OUT);

      // Translate keys from CiviCRM format to Twingle format
      self::translateKeys($values, self::OUT);

      // Set attributes to the values of the existing TwingleEvent campaign
      // to reflect the state of the actual campaign in the database
      $this->update($values);

      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  /**
   * Create the Campaign as a campaign in CiviCRM if it does not exist
   *
   * @param bool $is_test
   * If true: don't do any changes
   *
   * @return array
   * Returns a response array that contains title, id, project_id and status
   *
   * @throws CiviCRM_API3_Exception
   * @throws Exception
   */
  public function create(bool $is_test = FALSE) {

    // Create campaign only if it does not already exist
    if (!$is_test) {

      // Prepare project values for import into database
      $values_prepared_for_import = $this->values;
      self::formatValues(
        $values_prepared_for_import,
        self::IN
      );
      self::translateKeys(
        $values_prepared_for_import,
        self::IN
      );
      self::translateCustomFields(
        $values_prepared_for_import,
        self::IN
      );

      // Set id
      $values_prepared_for_import['id'] = $this->id;

      // Create campaign
      $result = civicrm_api3('Campaign', 'create', $values_prepared_for_import);

      // Update id
      $this->id = $result['id'];

      // Check if campaign was created successfully
      if ($result['is_error'] == 0) {
        $response = $this->getResponse("$this->className created");
      }
      else {
        $response = $this->getResponse("$this->className creation failed");
      }

    }
    // If this is a test, do not create campaign
    else {
      $response = $this->getResponse("$this->className not yet created");
    }

    return $response;
  }


  /**
   * Update an existing campaign
   *
   * @param array $values
   * Array with values to update
   *
   * @throws Exception
   */
  public function update(array $values) {

    // Update campaign values
    $this->values = array_merge($this->values, $values);
  }


  /**
   * Instantiate an existing campaign by its id
   *
   * @param $id
   *
   * @return TwingleProject|TwingleEvent|TwingleCampaign|NULL
   * @throws CiviCRM_API3_Exception
   * @throws Exception
   */
  public static function fetch($id) {
    $result = civicrm_api3('Campaign', 'getsingle', [
      'sequential' => 1,
      'id'         => $id,
    ]);

    $twingle_campaign_types = Cache::getInstance()
      ->getCampaigns()['campaign_types'];

    $twingle_campaign_type_values = [];

    foreach ($twingle_campaign_types as $twingle_campaign_type) {
      $twingle_campaign_type_values[$twingle_campaign_type['name']] =
        CampaignType::fetch($twingle_campaign_type['name'])->getValue();
    }

    switch ($result->values['campaign_type_id']) {
      case $twingle_campaign_type_values['twingle_project']:
        return new TwingleProject(
          $result['values'],
          self::CIVICRM
        );
        break;
      case $twingle_campaign_type_values['twingle_event']:
        return new TwingleEvent(
          $result['values'],
          self::CIVICRM
        );
      case $twingle_campaign_type_values['twingle_campaign']:
        return new TwingleCampaign(
          $result['values'],
          self::CIVICRM
        );
        break;
      default:
        return NULL;
    }
  }


  /**
   * Deactivate all duplicates of a project but the newest one
   *
   * @param array $result
   * The $result array of a civicrm_api3-get-project call
   *
   * @throws CiviCRM_API3_Exception
   */
  protected function handleDuplicates(array $result) {

    // Sort projects ascending by the value of the last_modified_date
    uasort($result['values'], function ($a, $b) {
      return $a['last_modified_date'] <=> $b['last_modified_date'];
    });

    // Delete the newest project from array to keep it active
    array_shift($result['values']);

    // deactivate the projects
    foreach ($result['values'] as $p) {
      self::deactivateById($p['id']);
    }

  }


  /**
   * Translate array keys between CiviCRM Campaigns and Twingle
   *
   * @param array $values
   * array of which keys shall be translated
   *
   * @param string $direction
   * Campaign::IN -> translate array keys from Twingle format into
   * CiviCRM format <br>
   * Campaign::OUT -> translate array keys from CiviCRM format into
   * Twingle format
   *
   * @throws Exception
   */
  public static function translateKeys(array &$values, string $direction) {

    // Get translations for fields
    $field_translations = Cache::getInstance()->getTranslations()['fields'];

    // Set the direction of the translation
    if ($direction == self::OUT) {
      $field_translations = array_flip($field_translations);
    }
    // Throw error if $direction constant does not match IN or OUT
    elseif ($direction != self::IN) {
      throw new Exception(
        "Invalid Parameter $direction for translateKeys()"
      );
      // TODO: use specific exception or create own
    }

    // Translate keys
    foreach ($field_translations as $origin => $translation) {
      $values[$translation] = $values[$origin];
      unset($values[$origin]);
    }
  }


  /**
   * Translate values between CiviCRM Campaigns and Twingle
   *
   * @param array $values
   * array of which values shall be translated
   *
   * @param string $direction
   * Campaign::IN -> translate array values from Twingle to CiviCRM <br>
   * Campaign::OUT -> translate array values from CiviCRM to Twingle
   *
   * @throws Exception
   */
  private function formatValues(array &$values, string $direction) {

    if ($direction == self::IN) {

      // Change timestamp into DateTime string
      if ($values['last_update']) {
        $values['last_update'] =
          self::getDateTime($values['last_update']);
      }

      // empty project_type to 'default'
      if (!$values['type']) {
        $values['type'] = 'default';
      }
    }
    elseif ($direction == self::OUT) {

      // Change DateTime string into timestamp
      $values['last_update'] =
        self::getTimestamp($values['last_update']);

      // Default project_type to ''
      $values['type'] = $values['type'] == 'default'
        ? ''
        : $values['type'];

      // Cast project target to integer
      $values['project_target'] = (int) $values['project_target'];

    }
    else {

      throw new Exception(
        "Invalid Parameter $direction for formatValues()"
      );

    }
  }


  /**
   * Translate between Twingle field names and custom field names
   *
   * @param array $values
   * array of which keys shall be translated
   *
   * @param string $direction
   * Campaign::IN -> translate field names into custom field names <br>
   * Campaign::OUT -> translate custom field names into Twingle field
   * names
   *
   */
  public static function translateCustomFields(array &$values, string $direction) {

    // Translate from Twingle field name to custom field name
    if ($direction == self::IN) {

      foreach (Cache::getInstance()
                 ->getCustomFieldMapping() as $field => $custom) {

        if (array_key_exists(
          str_replace(
            'twingle_project_',
            '',
            $field
          ),
          $values)
        ) {

          $values[$custom] = $values[str_replace(
            'twingle_project_',
            '',
            $field
          )];

          unset($values[str_replace(
              'twingle_project_',
              '',
              $field
            )]
          );
        }
      }
    }
    // Translate from custom field name to Twingle field name
    elseif ($direction == self::OUT) {

      foreach (Cache::getInstance()
                 ->getCustomFieldMapping() as $field => $custom) {

        if (array_key_exists(
          $custom,
          $values
        )
        ) {
          $values[str_replace(
            'twingle_project_',
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
  public function deactivate() {

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
  public static function deactivateById($id) {

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
  public function getResponse(string $status) {
    return [
      'title'        => $this->values['name'],
      'id'           => (int) $this->id,
      'project_id'   => (int) $this->values['id'],
      'project_type' => $this->values['type'],
      'status'       => $status,
    ];
  }


  /**
   * Validates $input to be either a DateTime string or an Unix timestamp
   *
   * @param $input
   * Pass a DateTime string or a Unix timestamp
   *
   * @return int
   * Returns a Unix timestamp or NULL if $input is invalid
   */
  public static function getTimestamp($input) {

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
   * Return a timestamp of the last update of the Campaign
   *
   * @return int|null
   */
  public function lastUpdate() {

    return self::getTimestamp($this->values['last_update']);
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
