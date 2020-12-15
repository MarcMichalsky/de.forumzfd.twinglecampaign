<?php

use CRM_TwingleCampaign_Utils_ExtensionCache as Cache;
use CRM_TwingleCampaign_Utils_StringOperations as StringOps;
use CRM_TwingleCampaign_BAO_Campaign as Campaign;
use CRM_TwingleCampaign_BAO_TwingleApiCall as TwingleApiCall;
use CRM_TwingleCampaign_BAO_Configuration as Configuration;

class CRM_TwingleCampaign_BAO_TwingleEvent extends Campaign {

  /**
   * TwingleEvent constructor.
   *
   * @param array $event
   * Result array of Twingle API call to
   * https://project.twingle.de/api/$project_id/event
   *
   * @throws Exception
   */
  protected function __construct(array $event) {
    parent::__construct($event);

    $this->prefix = 'twingle_event_';
    $this->values['campaign_type_id'] = 'twingle_event';
    $this->id_custom_field = Cache::getInstance()
      ->getCustomFieldMapping()['twingle_event_id'];
    $this->values['parent_id'] = $this->getParentCampaignId();

  }

  /**
   * Synchronizes events between Twingle and CiviCRM (both directions)
   * based on the timestamp.
   *
   * @param array $values
   * @param TwingleApiCall $twingleApi
   * @param int $user
   * @param bool $is_test
   * If TRUE, don't do any changes
   *
   * @return array|null
   * Returns a response array that contains title, id, event_id, project_id
   * and status or NULL if $values is not an array
   *
   * @throws \CiviCRM_API3_Exception
   */
  public static function sync(
    array $values,
    TwingleApiCall &$twingleApi,
    int $user,
    bool $is_test = FALSE
  ) {

    // If $values is an array
    if (is_array($values)) {

      // Instantiate TwingleEvent
      try {
        $event = new self($values);
      } catch (Exception $e) {
        $errorMessage = $e->getMessage();

        // Log Exception
        Civi::log()->error(
          "Failed to instantiate TwingleEvent: $errorMessage"
        );

        // Return result array with error description
        return [
          "title"      => $values['description'],
          "event_id"   => (int) $values['id'],
          "project_id" => (int) $values['project_id'],
          "status"     =>
            "Failed to instantiate TwingleEvent: $errorMessage",
        ];
      }

      // Check if the TwingleEvent campaign already exists
      if (!$event->exists()) {

        // ... if not, get embed data and create event
        try {
          $result = $event->create($is_test);
        } catch (Exception $e) {
          $errorMessage = $e->getMessage();

          // Log Exception
          Civi::log()->error(
            "Could not create campaign from TwingleEvent: $errorMessage"
          );

          // Return result array with error description
          return [
            "title"      => $values['description'],
            "event_id"   => (int) $values['id'],
            "project_id" => (int) $values['project_id'],
            "status"     =>
              "Could not create campaign from TwingleEvent: $errorMessage",
          ];
        }
      }
      else {
        $result = $event->getResponse('TwingleEvent exists');

        // If Twingle's version of the event is newer than the CiviCRM
        // TwingleEvent campaign update the campaign
        if ($values['updated_at'] > $event->lastUpdate()) {
          try {
            $event->update($values);
            $result = $event->create();
            $result['status'] = $result['status'] == 'TwingleEvent created'
              ? 'TwingleEvent updated'
              : 'TwingleEvent Update failed';
          } catch (Exception $e) {
            $errorMessage = $e->getMessage();

            // Log Exception
            Civi::log()->error(
              "Could not update TwingleEvent campaign: $errorMessage"
            );
            // Return result array with error description
            $result = $event->getResponse(
              "Could not update TwingleEvent campaign: $errorMessage"
            );
          }
        }
        elseif ($result['status'] == 'TwingleEvent exists') {
          $result = $event->getResponse('TwingleEvent up to date');
        }
      }

      // Return a response of the synchronization
      return $result;
    }
    else {
      return NULL;
    }
  }


  /**
   * Create the Event as a campaign in CiviCRM if it does not exist
   *
   * @param bool $is_test
   * If true: don't do any changes
   *
   * @return array
   * Returns a response array that contains title, id, project_id and status
   *
   * @throws \CiviCRM_API3_Exception
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
      $formattedValues = $values_prepared_for_import;
      $this->translateCustomFields(
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

      // Start a case for event initiator
      if (
        $result['is_error'] == 0 &&
        Configuration::get('twinglecampaign_start_case')
      ) {
        $result = civicrm_api3('Case', 'create', [
          'contact_id'   => $formattedValues['contact_id'],
          'case_type_id' => Configuration::get('twinglecampaign_start_case'),
          'subject'      => $formattedValues['title'],
          'start_date'   => $formattedValues['created_at'],
          'status_id'    => "Open",
        ]);
      }

    }
    // If this is a test, do not create campaign
    else {
      $response = $this->getResponse("$this->className not yet created");
    }

    return $response;
  }


  /**
   * Translate values between CiviCRM Campaigns and Twingle
   *
   * @param array $values
   * array of which values shall be translated
   *
   * @param string $direction
   * self::IN -> translate array values from Twingle to CiviCRM <br>
   * self::OUT -> translate array values from CiviCRM to Twingle
   *
   * @throws Exception
   */
  public static function formatValues(array &$values, string $direction) {

    if ($direction == self::IN) {

      // Change timestamps into DateTime strings
      if ($values['updated_at']) {
        $values['updated_at'] =
          self::getDateTime($values['updated_at']);
      }

      if ($values['confirmed_at']) {
        $values['confirmed_at'] =
          self::getDateTime($values['confirmed_at']);
        $values['status_id'] = 'In Progress';
      }
      else {
        $values['status_id'] = 'Planned';
      }

      if ($values['created_at']) {
        $values['created_at'] =
          self::getDateTime($values['created_at']);
      }

      if ($values['user_name']) {
        $values['contact_id'] = self::matchContact(
          $values['user_name'],
          $values['user_email']
        );
      }

      // Set campaign status
      if ($values['deleted']) {
        $values['status_id'] = 'Cancelled';
      }

      // Set URLs
      if (is_array($values['urls'])) {
        $values['url_internal'] = $values['urls']['show_internal'];
        $values['url_external'] = $values['urls']['show_external'];
        $values['url_edit_internal'] = $values['urls']['edit_internal'];
        $values['url_edit_external'] = $values['urls']['edit_internal'];
      }


    }
    elseif ($direction == self::OUT) {

      // Change DateTime string into timestamp
      $values['updated_at'] =
        self::getTimestamp($values['updated_at']);
      $values['confirmed_at'] =
        self::getTimestamp($values['confirmed_at']);
      $values['created_at'] =
        self::getTimestamp($values['created_at']);

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
    $field_translations = Cache::getInstance()
      ->getTranslations()['TwingleEvent'];

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
   * Get a response that describes the status of a TwingleEvent
   *
   * @param string $status
   * status of the TwingleEvent you want the response for
   *
   * @return array
   * Returns a response array that contains title, id, project_id and status
   */
  public function getResponse(string $status) {
    return [
      'title'      => $this->values['description'],
      'id'         => (int) $this->id,
      'event_id'   => (int) $this->values['id'],
      'project_id' => (int) $this->values['project_id'],
      'status'     => $status,
    ];
  }

  /**
   * Matches a single string that should contain first and lastname to match a
   * contact or create a new one if it does not exist yet.
   *
   * @param string $names
   * @param string $email
   *
   * @return int|null
   * Returns a contact id
   */
  private static function matchContact(string $names, string $email): ?int {
    $names = StringOps::split_names($names); // Hopefully just a temporary solution
    $firstnames = $names['firstnames'];
    $lastname = $names['lastname'];
    try {
      $contact = civicrm_api3('Contact', 'getorcreate', [
        'xcm_profile' => Civi::settings()->get('twinglecampaign_xcm_profile'),
        'first_name'  => $firstnames,
        'last_name'   => $lastname,
        'email'       => $email,
      ]);
      return (int) $contact['id'];
    } catch (CiviCRM_API3_Exception $e) {
      $errorMessage = $e->getMessage();
      Civi::log()->error("TwingleCampaign extension could not match or create a contact for:
      $firstnames $lastname $email./nError Message: $errorMessage");
      return NULL;
    }
  }

  /**
   * Gets the campaign id of the parent TwingleProject campaign.
   *
   * @return int|null
   * @throws CiviCRM_API3_Exception
   */
  private function getParentCampaignId() {
    $cf_project_id = Cache::getInstance()
      ->getCustomFieldMapping()['twingle_project_id'];
    $parentCampaign = civicrm_api3('Campaign', 'get', [
      'sequential'   => 1,
      $cf_project_id => $this->values['project_id'],
    ]);
    if ($parentCampaign['is_error'] == 0) {
      return (int) $parentCampaign['id'];
    }
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

    return self::getTimestamp($this->values['updated_at']);
  }


  /**
   * Returns the project_id of a TwingleEvent
   *
   * @return int
   */
  public function getProjectId() {
    return (int) $this->values['project_id'];
  }


  /**
   * Returns the event_id of a TwingleEvent
   *
   * @return int
   */
  public function getEventId() {
    return (int) $this->values['id'];
  }

}
