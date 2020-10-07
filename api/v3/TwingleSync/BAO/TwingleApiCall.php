<?php

namespace CRM\TwingleCampaign\BAO;

use CRM_Core_BAO_Setting;
use CRM_TwingleCampaign_ExtensionUtil as E;
use CRM\TwingleCampaign\BAO\TwingleProject as TwingleProject;

include_once E::path() . '/api/v3/TwingleSync/BAO/TwingleProject.php';

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
   * @throws \API_Exception
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
      throw new \API_Exception(
        "Twingle API call failed. Please check your api key.");
    }

    $this->organisationId = array_column($response, 'id');
  }

  /**
   * If $id parameter is empty, this function returns all projects for all
   * organisations this API key is assigned to.
   *
   * If $id parameter is given, this function returns a single project.
   *
   * @param null $projectId
   *
   * @return mixed
   */
  public function getProject($projectId = NULL) {
    $response = [];
    foreach ($this->organisationId as $organisationId) {
      $url = empty($projectId)
        ? $this->protocol . 'project' . $this->baseUrl . 'by-organisation/' . $organisationId
        : $this->protocol . 'project' . $this->baseUrl . $projectId;

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
   * Returns a response array that contains title, id, project_id and state or
   * NULL if $values is not an array
   *
   * @throws \CiviCRM_API3_Exception
   * @throws \Exception
   */
  public function syncProject(array $values, bool $is_test = FALSE) {

    // If $values is an array
    if (is_array($values)) {

      $project = new TwingleProject($values, TwingleProject::TWINGLE);

      // Check if the TwingleProject campaign already exists
      if (!$project->exists()) {
        // ... if not, create it
        $result = $project->create($is_test);
      }
      else {
        $result = $project->getResponse('TwingleProject exists');
      }

      // If Twingle's version of the project is newer than the CiviCRM
      // TwingleProject campaign update the campaign
      if (
        $result['state'] == 'TwingleProject exists' &&
        $values['last_update'] > $project->lastUpdate()
      ) {
        $project->update($values, TwingleProject::TWINGLE);
        $result = $project->create();
      }
      // If the CiviCRM TwingleProject campaign was changed, update the project
      // on Twingle's side
      elseif (
        $result['status'] == 'TwingleProject exists' &&
        $values['last_update'] < $project->lastUpdate()
      ) {
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
   * Returns a response array that contains title, id, project_id and state
   *
   */
  public function updateProject(TwingleProject $project) {

    $values = $project->export();

    // Prepare url for curl
    $url = $this->protocol . 'project' . $this->baseUrl . $values['id'];

    // Send curl
    $result = $this->curlPost($url, $values);

    // Update TwingleProject in Civi with results from api call
    if (is_array($result) && !array_key_exists('message', $result)) {
      $project->update($result, TwingleProject::TWINGLE);
      $project->create();
      return $project->getResponse('TwingleProject pushed to Twingle');
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
   * @return array
   */
  public function getOrganisationId() {
    return $this->organisationId;
  }

  /**
   *
   * Does a cURL and gives back the result array.
   *
   * @param $url
   *
   * @param null $params
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

}
