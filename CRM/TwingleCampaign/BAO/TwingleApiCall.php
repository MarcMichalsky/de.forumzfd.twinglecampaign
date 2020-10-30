<?php

namespace CRM\TwingleCampaign\BAO;

use API_Exception;
use Civi;
use CRM_Core_BAO_Setting;
use CRM_TwingleCampaign_ExtensionUtil as E;
use Exception;


class TwingleApiCall {

  private $apiKey;

  private $baseUrl = '.twingle.de/api/';

  private $protocol = 'https://';

  private $organisationId;

  private $limit;

  /**
   * TwingleApiCall constructor.
   *
   * @param $apiKey
   *
   * @throws API_Exception
   */
  public function __construct($apiKey) {
    $this->apiKey = $apiKey;
    $this->limit = CRM_Core_BAO_Setting::getItem('', 'twingle_request_size');

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
   * TODO: Keys can only get assigned to one organisation. Save multiple keys
   * in settings instead.
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

  /**
   * Sends an curl post call to Twingle to update an existing project and then
   * updates the TwingleProject campaign.
   *
   * @param TwingleProject $project
   * The TwingleProject object that should get pushed to Twingle
   *
   * @return array
   * Returns a response array that contains title, id, project_id and status
   */
  public function pushProject(TwingleProject &$project) {

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
        $project->update($result);
        $project->create();
        return $project->getResponse('TwingleProject pushed to Twingle');
      } catch (Exception $e) {
        // Log Exception
        Civi::log()->error(
          "Could not push TwingleProject campaign: $e->getMessage()"
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

  /**
   * Returns all Events for the given $projectId or a single event if an
   * $eventId is given, too.
   *
   * @param int $projectId
   *
   * @param null|int $eventId
   *
   * @return array
   */
  public function getEvent(int $projectId, $eventId = NULL) {
    $result = [];

    $url = empty($eventId)
      ? $this->protocol . 'project' . $this->baseUrl . $projectId . '/event'
      : $this->protocol . 'project' . $this->baseUrl . $projectId . '/event/'
        . $eventId;

    $offset = 0;
    $finished = FALSE;

    // Get only as much results per call as configured in $this->limit
    while (!$finished) {
      $params = [
        'orderby'   => 'id',
        'direction' => 'desc',
        'limit'     => $this->limit,
        'offset'    => $offset,
        'image'     => 'as-boolean',
        'public'    => 0,
      ];
      $response = $this->curlGet($url, $params);
      $finished = is_null($eventId) || count($response['data']) < $this->limit;
      $offset = $offset + $this->limit;
      $result = array_merge($result, $response['data']);
    }
    return $result;
  }

  /**
   * @param $projectId
   *
   * @return array|NULL
   */
  public function getProjectEmbedData($projectId) {

    $result = $this->getProject($projectId);

    if ($result['embed']) {
      // Include counter url into embed data
      $result['embed']['counter'] = $result['counter-url']['url'];

      return $result['embed'];
    }
    else {
      Civi::log()->error("Could not get embed data for project $projectId.");
      return NULL;
    }
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

}
