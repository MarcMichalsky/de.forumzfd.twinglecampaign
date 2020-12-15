<?php

use CRM_TwingleCampaign_Utils_ExtensionCache as Cache;
use CRM_TwingleCampaign_BAO_Campaign as Campaign;
use CRM_TwingleCampaign_BAO_TwingleApiCall as TwingleApiCall;

class CRM_TwingleCampaign_BAO_TwingleProject extends Campaign {

  /**
   * TwingleProject constructor.
   *
   * @param array $project
   * Result array of Twingle API call to
   * https://project.twingle.de/api/by-organisation/$organisation_id
   *
   * @param int|null $id
   *
   * @throws \Exception
   */
  function __construct(array $project, int $id = NULL) {
    parent::__construct($project);

    $this->id = $id;
    $this->prefix = 'twingle_project_';
    $this->values['campaign_type_id'] = 'twingle_project';
    $this->id_custom_field = Cache::getInstance()
      ->getCustomFieldMapping()['twingle_project_id'];

  }


  /**
   * Synchronizes projects between Twingle and CiviCRM (both directions)
   * based on the timestamp.
   *
   * @param array $values
   * @param TwingleApiCall $twingleApi
   * @param bool $is_test
   * If TRUE, don't do any changes
   *
   * @return array|null
   * Returns a response array that contains title, id, project_id and status or
   * NULL if $values is not an array
   *
   * @throws CiviCRM_API3_Exception
   */
  public static function sync(
    array $values,
    TwingleApiCall $twingleApi,
    bool $is_test = FALSE
  ): ?array {

    // If $values is an array
    if (is_array($values)) {

      // Instantiate TwingleProject
      try {
        $project = new self($values);
      } catch (Exception $e) {
        $errorMessage = $e->getMessage();

        // Log Exception
        Civi::log()->error(
          "Failed to instantiate TwingleProject: $errorMessage"
        );

        // Return result array with error description
        return [
          "title"      => $values['name'],
          "project_id" => (int) $values['id'],
          "status"     =>
            "Failed to instantiate TwingleProject: $errorMessage",
        ];
      }

      // Check if the TwingleProject campaign already exists
      if (!$project->exists()) {

        // ... if not, get embed data and create project
        try {
          $project->setEmbedData(
            $twingleApi->getProjectEmbedData($project->getProjectId())
          );
          $result = $project->create($is_test);
        } catch (Exception $e) {
          $errorMessage = $e->getMessage();

          // Log Exception
          Civi::log()->error(
            "Could not create campaign from TwingleProject: $errorMessage"
          );

          // Return result array with error description
          return [
            "title"      => $values['name'],
            "project_id" => (int) $values['id'],
            "status"     =>
              "Could not create campaign from TwingleProject: $errorMessage",
          ];
        }
      }
      else {
        $result = $project->getResponse('TwingleProject exists');

        // If Twingle's version of the project is newer than the CiviCRM
        // TwingleProject campaign update the campaign
        if ($values['last_update'] > $project->lastUpdate()) {
          try {
            $project->update($values);
            $project->setEmbedData(
              $twingleApi->getProjectEmbedData($project->getProjectId())
            );
            $result = $project->create();
            $result['status'] = $result['status'] == 'TwingleProject created'
              ? 'TwingleProject updated'
              : 'TwingleProject Update failed';
          } catch (Exception $e) {
            $errorMessage = $e->getMessage();

            // Log Exception
            Civi::log()->error(
              "Could not update TwingleProject campaign: $errorMessage"
            );
            // Return result array with error description
            $result = $project->getResponse(
              "Could not update TwingleProject campaign: $errorMessage"
            );
          }
        }
        // If the CiviCRM TwingleProject campaign was changed, update the project
        // on Twingle's side
        elseif ($values['last_update'] < $project->lastUpdate()) {
          // If this is a test do not make database changes
          if ($is_test) {
            $result = $project->getResponse(
              'TwingleProject ready to push'
            );
          }
          else {
            $result = $twingleApi->pushProject($project);
            // Update TwingleProject in Civi with results from api call
            if (is_array($result) && !array_key_exists('message', $result)) {
              // Try to update the local TwingleProject campaign
              try {
                $project->update($result);
                $project->create();
                return $project->getResponse('TwingleProject pushed to Twingle');
              } catch (Exception $e) {
                // Log Exception
                $errorMessage = $e->getMessage();
                Civi::log()->error(
                  "Could not push TwingleProject campaign: $errorMessage"
                );
                // Return result array with error description
                return $project->getResponse(
                  "TwingleProject was likely pushed to Twingle but the local " .
                  "update of the campaign failed: $errorMessage"
                );
              }
            }
            else {
              $message = $result['message'];
              return $project->getResponse(
                $message
                  ? "TwingleProject could not get pushed to Twingle: $message"
                  : 'TwingleProject could not get pushed to Twingle'
              );
            }

          }
        }
        elseif ($result['status'] == 'TwingleProject exists') {
          $result = $project->getResponse('TwingleProject up to date');
        }
      }

      // Return the result of the synchronization
      return $result;
    }
    else {
      return NULL;
    }
  }


  /**
   * Export values. Ensures that only those values will be exported which the
   * Twingle API expects.
   *
   * @return array
   * Array with all values to send to the Twingle API
   *
   * @throws Exception
   */
  public function export(): array {

    $values = $this->values;
    self::formatValues($values, self::OUT);

    // Get template for project
    $project = Cache::getInstance()->getTemplates()['TwingleProject'];

    // Replace array items which the Twingle API does not expect
    foreach ($values as $key => $value) {
      if (!in_array($key, $project)) {
        unset($values[$key]);
      }
    }

    return $values;
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
  public function create(bool $is_test = FALSE): array {

    // Create campaign only if this is not a test
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
   * TwingleProject::IN -> translate array values from Twingle to CiviCRM <br>
   * TwingleProject::OUT -> translate array values from CiviCRM to Twingle
   *
   * @throws Exception
   */
  protected function formatValues(array &$values, string $direction) {

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
      $this->values[$key] = $embedData[$key];
    }
  }


  /**
   * Deactivate this TwingleProject campaign
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
   * Get a response that describes the status of a TwingleProject
   *
   * @param string $status
   * status of the TwingleProject you want the response for
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
   * Return a timestamp of the last update of the Campaign
   *
   * @return int|null
   */
  public function lastUpdate() {

    return self::getTimestamp($this->values['last_update']);
  }


  /**
   * Returns the project_id of a TwingleProject
   *
   * @return int
   */
  public function getProjectId() {
    return (int) $this->values['id'];
  }

}
