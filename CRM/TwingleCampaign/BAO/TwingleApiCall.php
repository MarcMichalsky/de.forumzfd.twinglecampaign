<?php

use CRM_TwingleCampaign_BAO_TwingleProject as TwingleProject;
use CRM_TwingleCampaign_ExtensionUtil as E;


class CRM_TwingleCampaign_BAO_TwingleApiCall {

  private $apiKey;

  private $baseUrl = '.twingle.de/api/';

  private $protocol = 'https://';

  private $organisationId;

  private $limit;

  private $extensionName;

  /**
   * TwingleApiCall constructor.
   *
   * @param $apiKey
   *
   * @param int $limit
   * Limit for the number of events that should get requested per call to the
   * Twingle API
   *
   * @throws API_Exception
   */
  public function __construct(string $apiKey, int $limit = 20) {
    $this->apiKey = $apiKey;
    $this->limit = $limit;
    $this->extensionName = E::LONG_NAME;

    // Try to retrieve organisation id from cache
    $this->organisationId = Civi::cache()
      ->get('twinglecampaign_organisation_id');

    // else: retrieve organisation id via Twingle api
    if (NULL === $this->organisationId) {

      $curl = curl_init($this->protocol . 'organisation' . $this->baseUrl);
      curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
      curl_setopt($curl, CURLOPT_HTTPHEADER, [
        "x-access-code: $apiKey",
        'Content-Type: application/json',
      ]);

      $response = json_decode(curl_exec($curl), TRUE);
      curl_close($curl);

      if (empty($response)) {
        // Delete api key from cache
        Civi::cache()->delete('twinglecampaign_twingle_api');
        // Throw exception
        throw new API_Exception(
          "Twingle API call failed. Please check your api key.");
      }
      $this->organisationId = array_column($response, 'id')[0];
      Civi::cache('long')->set('twinglecampaign_organisation_id',
        $this->organisationId);
    }
  }

  /**
   * If $id parameter is empty, this function returns all projects for all
   * organisations this API key is assigned to.
   *
   * If $id parameter is given, this function returns a single project.
   *
   * @param int|null $projectId
   *
   * @return mixed
   */
  public function getProject(int $projectId = NULL) {

    $url = empty($projectId)
      ? $this->protocol . 'project' . $this->baseUrl . 'by-organisation/' . $this->organisationId
      : $this->protocol . 'project' . $this->baseUrl . $projectId;

    return $this->curlGet($url);
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
   * @throws \Exception
   */
  public function pushProject(TwingleProject &$project) {

    $values = $project->export();

    // Prepare url for curl
    if ($values['id']) {
      $url = $this->protocol . 'project' . $this->baseUrl . $values['id'];
    }
    else {
      $url = $this->protocol . 'project' . $this->baseUrl . 'by-organisation/' .
        $this->organisationId;
    }

    // Send curl and return result
    return $this->curlPost($url, $values);
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
  public function getEvent($projectId, $eventId = NULL) {
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

      // If no $eventId was given, expect one or more events.
      // Store the events, increase the offset and ask again until there
      // are no more events incoming.
      if ($response['data'] && !$eventId) {
        $result = array_merge($result, $response['data']);
        $offset = $offset + $this->limit;
        $finished = count($response['data']) < $this->limit;
      }
      // If $eventId was given, expect only one event
      else {
        // If result the array contains 'message', the $eventId does not exist
        if (!$result['message']) {
          $result = $response;
        }
        $finished = TRUE;
      }
    }
    return $result;
  }

  /**
   * @param $projectId
   *
   * @return array|NULL
   * @throws \Exception
   */
  public
  function getProjectEmbedData($projectId): array {

    $result = $this->getProject($projectId);

    if ($result['embed']) {
      // Include counter url into embed data
      $result['embed']['counter'] = $result['counter-url']['url'];

      return $result['embed'];
    }
    else {
      throw new Exception(
        "Could not get embed data for project $projectId."
      );
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
  private
  function curlGet($url, $params = NULL) {
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
  private
  function curlPost($url, $data) {
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
