<?php

use CRM_TwingleCampaign_Utils_ExtensionCache as Cache;
use CRM_TwingleCampaign_Utils_StringOperations as StringOps;
use CRM_TwingleCampaign_BAO_Campaign as Campaign;
use CRM_TwingleCampaign_BAO_Configuration as Configuration;
use CRM_TwingleCampaign_ExtensionUtil as E;

class CRM_TwingleCampaign_BAO_TwingleEvent extends Campaign {

  /**
   * ## TwingleEvent constructor
   *
   * @param array $event
   * Event values
   *
   * @param int|null $id
   * CiviCRM Campaign id
   *
   * @throws \Exception
   */
  public function __construct(array $event = [], int $id = NULL) {
    parent::__construct($event, $id);

    $this->prefix = 'twingle_event_';
    $this->values['campaign_type_id'] = 'twingle_event';
    $this->id_custom_field = Cache::getInstance()
      ->getCustomFieldMapping()['twingle_event_id'];

    if (!isset($this->values['parent_id'])) {
      try {
        $this->values['parent_id'] = $this->getParentCampaignId();
      } catch (CiviCRM_API3_Exception $e) {
        $errorMessage = $e->getMessage();
        throw new Exception("Could not identify parent Campaign: $errorMessage");
      }
    }

  }


  /**
   * ## Create the Event as a campaign in CiviCRM if it does not exist
   * Returns _TRUE_ if creation was successful or _FALSE if it creation failed.
   *
   * @param bool $no_hook
   *
   * @return bool
   * @throws \CiviCRM_API3_Exception
   * @throws \Exception
   */
  public function create(bool $no_hook = FALSE): bool {

    if (parent::create()) {

      // Get case type
      $parentProject = civicrm_api3(
        'TwingleProject',
        'getsingle',
        ['id' => $this->values['parent_id']]
      );
      $caseType = $parentProject['case']
        ?? Configuration::get('twinglecampaign_default_case');

      if ($caseType) {
        // check for existence
        $result = civicrm_api3('Case', 'get', [
          'contact_id'   => $this->formattedValues['contact'],
          'case_type_id' => $caseType,
          'subject'      => $this->formattedValues['title'] . ' | Event-ID: ' .
            $this->formattedValues['id'],
        ]);

        // Open a case
        if ($result['count'] == 0) {
          $result = civicrm_api3('Case', 'create', [
            'contact_id'   => $this->formattedValues['contact'],
            'case_type_id' => $caseType,
            'subject'      => $this->formattedValues['title'] . ' | Event-ID: ' .
              $this->formattedValues['id'],
            'start_date'   => $this->formattedValues['created_at'],
            'status_id'    => "Open",
          ]);
        }
        if ($result['is_error'] != 0) {
          throw new Exception('Could not create case');
        }
      }
      return TRUE;
    }
    return FALSE;
  }


  /**
   * ## Translate values between CiviCRM Campaigns and Twingle formats
   * Constants for **$direction**:<br>
   * **TwingleProject::IN** translate array values from Twingle to CiviCRM
   * format<br>
   * **TwingleProject::OUT** translate array values from CiviCRM to Twingle
   * format
   *
   * @param array $values
   * array of values to translate
   *
   * @param string $direction
   * const: TwingleProject::IN or TwingleProject::OUT
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
        $values['contact'] = self::matchContact(
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
      if (isset($values['project_target'])) {
        $values['project_target'] = (int) $values['project_target'];
      }

    }
    else {

      throw new Exception(
        "Invalid Parameter $direction for formatValues()"
      );
    }
  }


  /**
   * ## Get a response
   * Get a response that describes the status of this TwingleEvent instance.
   * Returns an array that contains **title**, **id**, **event_id**,
   * **project_id** and **status** (if provided)
   *
   * @param string|null $status
   * status of the TwingleEvent you want to give back along with the response
   *
   * @return array
   */
  public
  function getResponse(string $status = NULL): array {
    $response = [
      'title'      => $this->values['description'],
      'id'         => (int) $this->id,
      'event_id'   => (int) $this->values['id'],
      'project_id' => (int) $this->values['project_id'],
    ];
    if ($status) {
      $response['status'] = $status;
    }
    return $response;
  }


  /**
   * ## Match a contact
   * This method uses a single string that is expected to contain first and
   * lastname to match a contact or create a new one if it does not exist yet.
   *
   * @param string $names
   * @param string $email
   *
   * @return int|null
   * Returns a contact id
   */
  private
  static function matchContact(string $names, string $email): ?int {
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
   * ## Get parent campaign id
   * Returns the campaign id of the parent TwingleProject campaign.
   *
   * @return int|null
   * @throws CiviCRM_API3_Exception
   */
  private
  function getParentCampaignId(): ?int {
    $cf_project_id = Cache::getInstance()
      ->getCustomFieldMapping()['twingle_project_id'];
    $parentCampaign = civicrm_api3('Campaign', 'get', [
      'sequential'   => 1,
      $cf_project_id => $this->values['project_id'],
      'options'      => ['limit' => 0],
    ]);
    if ($parentCampaign['is_error'] == 0) {
      return (int) $parentCampaign['id'];
    }
    else {
      return NULL;
    }
  }

  /**
   * ## Last update
   * Returns a timestamp of the last update of the TwingleEvent campaign.
   *
   * @return int|string|null
   */
  public
  function lastUpdate() {
    return self::getTimestamp($this->values['updated_at']);
  }


  /**
   * ## Get project id
   * Returns the **project_id** of this TwingleEvent.
   *
   * @return int
   */
  public
  function getProjectId(): int {
    return (int) $this->values['project_id'];
  }


  /**
   * ## Get event id
   * Returns the **event_id** of this TwingleEvent.
   *
   * @return int
   */
  public
  function getEventId(): int {
    return (int) $this->values['id'];
  }

}
