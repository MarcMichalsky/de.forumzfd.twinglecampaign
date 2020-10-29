<?php

namespace CRM\TwingleCampaign\BAO;

use API_Exception;
use Civi;
use CRM_Core_BAO_Setting;
use CRM_TwingleCampaign_ExtensionUtil as E;
use CRM\TwingleCampaign\BAO\TwingleProject as TwingleProject;
use Exception;

include_once E::path() . '/CRM/TwingleCampaign/BAO/TwingleProject.php';

class TwingleApiCall {

  private $apiKey;

  private $baseUrl = '.twingle.de/api/';

  private $protocol = 'https://';

  private $organisationId;

  /**
   * TwingleApiCall constructor.
   *
   * @param $apiKey
   *
   * @throws API_Exception
   */
  public function __construct($apiKey) {
    $this->apiKey = $apiKey;

    // Get organisation id
    $curl = curl_init($this->protocol . 'organisation' . $this->baseUrl);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($curl, CURLOPT_HTTPHEADER, [
      "x-access-code: $apiKey",
      'Content-Type: application/json',
    ]);

    $response = json_decode(curl_exec($curl), TRUE);
    curl_close($curl);

    if (empty($response)) {
      throw new API_Exception(
        "Twingle API call failed. Please check your api key.");
    }

    $this->organisationId = array_column($response, 'id');
  }

  /**
   * If $id parameter is empty, this function returns all projects for all
   * organisations this API key is assigned to.
   *
   * TODO: Keys can only get assigned to one organisation. Save multiple keys in settings instead.
   *
   * If $id parameter is given, this function returns a single project.
   *
   * @param int|null $projectId
   *
   * @return mixed
   */
  public function getProject(int $projectId = NULL) {
    $response = [];
    foreach ($this->organisationId as $organisationId) {
      $url = empty($projectId)
        ? $this->protocol . 'project' . $this->baseUrl . 'by-organisation/' . $organisationId
        : $this->protocol . 'project' . $this->baseUrl . $projectId;

      $response = array_merge($this->curlGet($url));
    }
    return $response;
  }


  public function getProjectOptions(int $projectId) {
    $response = [];
    foreach ($this->organisationId as $organisationId) {
      $url = $this->protocol . 'project' . $this->baseUrl . $projectId .
        '/options';

      $response = array_merge($this->curlGet($url));
    }
    return $response;
  }

  /**
   *
   * Returns all Events for the given $projectId
   *
   * @param $projectId
   *
   * @return array
   */
  public function getEvent($projectId) {
    $result = [];
    $url = $this->protocol . 'project' . $this->baseUrl . $projectId . '/event';
    $limit = CRM_Core_BAO_Setting::getItem('', 'twingle_request_size');
    $offset = 0;
    $finished = FALSE;

    while (!$finished) {
      $params = [
        'orderby'   => 'id',
        'direction' => 'desc',
        'limit'     => $limit,
        'offset'    => $offset,
        'image'     => 'as-boolean',
        'public'    => 0,
      ];
      $response = $this->curlGet($url, $params);
      $finished = count($response['data']) < $limit;
      $offset = $offset + $limit;
      $result = array_merge($result, $response['data']);
    }
    return $result;
  }

  /**
   * Synchronizes projects between Twingle and CiviCRM (both directions)
   * based on the timestamp.
   *
   * @param array $values
   *
   * @param bool $is_test
   * If TRUE, don't do any changes
   *
   * @return array|null
   * Returns a response array that contains title, id, project_id and status or
   * NULL if $values is not an array
   *
   * @throws \CiviCRM_API3_Exception
   */
  public function syncProject(array $values, bool $is_test = FALSE) {

    // If $values is an array
    if (is_array($values)) {

      // Get project options
      try {
        $values['options'] = $this->getProjectOptions($values['id']);
      } catch (Exception $e) {

        // Log Exception
        Civi::log()->error(
          "Failed to instantiate TwingleProject: $e->getMessage()"
        );

        // Return result array with error description
        return [
          "title"      => $values['name'],
          "project_id" => (int) $values['id'],
          "status"     =>
            "Failed to get project options from Twingle: $e->getMessage()",
        ];
      }

      // Instantiate TwingleProject
      try {
        $project = new TwingleProject(
          $values,
          TwingleProject::TWINGLE
        );
      } catch (Exception $e) {

        // Log Exception
        Civi::log()->error(
          "Failed to instantiate TwingleProject: $e->getMessage()"
        );

        // Return result array with error description
        return [
          "title"      => $values['name'],
          "project_id" => (int) $values['id'],
          "status"     =>
            "Failed to instantiate TwingleProject: $e->getMessage()",
        ];
      }

      // Check if the TwingleProject campaign already exists
      if (!$project->exists()) {

        // ... if not, create it
        try {
          $result = $project->create($is_test);
        } catch (Exception $e) {

          // Log Exception
          Civi::log()->error(
            "Could not create campaign from TwingleProject: $e->getMessage()"
          );

          // Return result array with error description
          return [
            "title"      => $values['name'],
            "project_id" => (int) $values['id'],
            "status"     =>
              "Could not create campaign from TwingleProject: $e->getMessage()",
          ];
        }
      }
      else {
        $result = $project->getResponse('TwingleProject exists');

        // If Twingle's version of the project is newer than the CiviCRM
        // TwingleProject campaign update the campaign
        $lastUpdate = $values['last_update'] > $values['options']['last_update']
          ? $values['last_update']
          : $values['options']['last_update'];
        if ($lastUpdate > $project->lastUpdate()) {
          try {
            $project->update($values);
            $result = $project->create();
            $result['status'] = $result['status'] == 'TwingleProject created'
              ? 'TwingleProject updated'
              : 'TwingleProject Update failed';
          } catch (Exception $e){
            // Log Exception
            Civi::log()->error(
              "Could not update TwingleProject campaign: $e->getMessage()"
            );
            // Return result array with error description
            $result = $project->getResponse(
              "Could not update TwingleProject campaign: $e->getMessage()"
            );
          }
        }
        // If the CiviCRM TwingleProject campaign was changed, update the project
        // on Twingle's side
        elseif ($lastUpdate < $project->lastUpdate()) {
          // If this is a test do not make database changes
          if ($is_test) {
            $result = $project->getResponse(
              'TwingleProject ready to push'
            );
          }
          else {
            $result = $this->updateProject($project);
          }
        }
        elseif ($result['status'] == 'TwingleProject exists') {
          $result = $project->getResponse('TwingleProject up to date');
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
   * Sends an curl post call to Twingle to update an existing project and then
   * updates the TwingleProject campaign.
   *
   * @param \CRM\TwingleCampaign\BAO\TwingleProject $project
   * The TwingleProject object that should get pushed to Twingle
   *
   * @return array
   * Returns a response array that contains title, id, project_id and status
   *
   */
  public function updateProject(TwingleProject &$project) {

    try {
      $values = $project->export();
    } catch (Exception $e) {
      // Log Exception
      Civi::log()->error(
        "Could not export TwingleProject values: $e->getMessage()"
      );
      // Return result array with error description
      return $project->getResponse(
        "Could not export TwingleProject values: $e->getMessage()"
      );
    }

    // Prepare url for curl
    $url = $this->protocol . 'project' . $this->baseUrl . $values['id'];

    // Send curl
    $result = $this->curlPost($url, $values);

    // Update TwingleProject in Civi with results from api call
    if (is_array($result) && !array_key_exists('message', $result)) {
      // Try to update the local TwingleProject campaign
      try {
        $project->update($result, TwingleProject::TWINGLE);
        $project->create();
        return $project->getResponse('TwingleProject pushed to Twingle');
      } catch (Exception $e) {
        // Log Exception
        Civi::log()->error(
          "Could not update TwingleProject campaign: $e->getMessage()"
        );
        // Return result array with error description
        return $project->getResponse(
          "TwingleProject was likely pushed to Twingle but the 
          local update of the campaign failed: $e->getMessage()"
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


  public function updateEvent() {
  }


  /**
   * Does a cURL and gives back the result array.
   *
   * @param $url
   * The url the curl should get sent to
   *
   * @param null $params
   * The parameters you want to send (optional)
   *
   * @return array|bool
   * Returns the result array of the curl or FALSE, if the curl failed
   */
  private function curlGet($url, $params = NULL) {
    if (!empty($params)) {
      $url = $url . '?' . http_build_query($params);
    }
    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($curl, CURLOPT_HTTPHEADER, [
      "x-access-code: $this->apiKey",
      'Content-Type: application/json',
    ]);
    $response = json_decode(curl_exec($curl), TRUE);
    if (empty($response)) {
      $response = curl_error($curl);
    }
    curl_close($curl);
    return $response;
  }

  /**
   * Sends a curl post and gives back the result array.
   *
   * @param $url
   * The url the curl should get sent to
   *
   * @param $data
   * The data that should get posted
   *
   * @return false|mixed
   * Returns the result array of the curl or FALSE, if the curl failed
   */
  private function curlPost($url, $data) {
    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($curl, CURLOPT_POST, TRUE);
    curl_setopt($curl, CURLOPT_HTTPHEADER, [
      "x-access-code: $this->apiKey",
      'Content-Type: application/json',
    ]);
    $json = json_encode($data);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $json);
    $response = json_decode(curl_exec($curl), TRUE);
    if (empty($response)) {
      $response = FALSE;
    }
    curl_close($curl);
    return $response;
  }

  /**
   * Returns a result array
   *
   * @param $values
   * Project values to generate result array from
   *
   * @param $status
   * Status of the array
   *
   * @return array
   */
  private function getResultArray($values, $status) {
    return [
      "title"      => $values['name'],
      "project_id" => (int) $values['id'],
      "project_type" => $values['project_type'],
      "status"     => $status
    ];
  }

}
