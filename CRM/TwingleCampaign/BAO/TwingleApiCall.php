<?php

use CRM_TwingleCampaign_ExtensionUtil as E;


class CRM_TwingleCampaign_BAO_TwingleApiCall {

  private $apiKey;

  private $baseUrl = '.twingle.de/api/';

  private $protocol = 'https://';

  private $organisationId;

  private $limit;

  private $extensionName;

  /**
   * ## TwingleApiCall constructor
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
   * ## Get project from Twingle
   * If the **$id** parameter is empty, this function returns all projects for
   * this organisation.
   *
   * If the **$id** parameter is provided, this function returns a single
   * project.
   *
   * @param int|null $projectId
   *
   * @return array|false
   * @throws Exception
   */
  public function getProject(int $projectId = NULL): ?array {

    // If no project id is provided, return all projects
    if (empty($projectId)) {
      $response = [];
      $projects = $this->curlGet($this->protocol . 'project' .
        $this->baseUrl . 'by-organisation/' . $this->organisationId);
      foreach ($projects as $project) {
        $response[] = $this->getProject($project['id']);
      }
      return $response;
    }
    // If a project id is provided, return only one project
    else {

      // Get all general project information
      $project = $this->curlGet($this->protocol . 'project' .
        $this->baseUrl . $projectId);

      // Get project options
      $project['project_options'] = $this->getProjectOptions($projectId);

      // Get project payment methods
      $project['payment_methods'] =
        $this->getProjectPaymentMethods($projectId);

      // Set last update time
      $project['last_update'] = max(
        $project['last_update'],
        $project['project_options']['last_update'],
        $project['payment_methods']['updated_at']
      );
      unset($project['project_options']['last_update']);
      unset($project['payment_methods']['updated_at']);

      return $project;
    }
  }

  /**
   * ## Push project to Twingle
   * Sends a curl post call to Twingle to update an existing project.
   *
   * Returns an array with all project values.
   *
   * @param array $project
   * The project values array that should get pushed to Twingle
   *
   * @return array
   * @throws \Exception
   */
  public function pushProject(array $project): array {

    $projectOptions = $project['project_options'];
    unset($project['project_options']);
    $paymentMethods = $project['payment_methods'];
    unset($project['payment_methods']);

    try {
      if (!isset($project['id'])) {
        $url = $this->protocol . 'project' . $this->baseUrl . 'by-organisation/' .
          $this->organisationId;

        // Post project values
        $updatedProject = $this->curlPost($url, $project);

        $url = $this->protocol . 'project' . $this->baseUrl .
          $updatedProject['id'];
      }
      else {
        $url = $this->protocol . 'project' . $this->baseUrl . $project['id'];

        // Post project values
        $updatedProject = $this->curlPost($url, $project);
      }

      // Post project_options
      $updatedProject['project_options'] =
        $this->curlPost($url . '/options', $projectOptions);

      // Post payment_methods
      $this->curlPost($url . '/payment-methods', $paymentMethods);
      $updatedProject['payment_methods'] =
        $this->getProjectPaymentMethods($updatedProject['id']);

      // Set last update time
      $updatedProject['last_update'] = max(
        $updatedProject['last_update'],
        $updatedProject['project_options']['last_update'],
        $updatedProject['payment_methods']['updated_at']
      );
      unset($updatedProject['project_options']['last_update']);
      unset($updatedProject['payment_methods']['updated_at']);

      return $updatedProject;
    } catch (Exception $e) {
      throw new Exception(
        E::SHORT_NAME . 'Call to Twingle API failed: ' .
        $e->getMessage()
      );
    }


  }


  /**
   * ## Get event from Twingle
   * Returns all events for the provided **$projectId** or a single event if an
   * **$eventId** is provided, too.
   *
   * @param int $projectId
   * @param null|int $eventId
   *
   * @return array
   * @throws Exception
   */
  public function getEvent(int $projectId, int $eventId = NULL): array {
    $result = [];

    // Construct url for curl
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
      if (!$eventId) {
        $result = array_merge($result, $response['data']);
        $offset = $offset + $this->limit;
        $finished = count($response['data']) < $this->limit;
      }
      // If $eventId was given, expect only one event
      else {
        // If the response array contains 'message', the $eventId does not exist
        if (!$response['message']) {
          $result = $response;
        }
        $finished = TRUE;
      }
    }
    return $result;
  }

  /**
   * ## Get project embed data
   * Get embed data for a specific project.
   *
   * @param $projectId
   *
   * @return array
   * @throws Exception
   */
  public function getProjectEmbedData($projectId): array {

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
   * ## Get project options
   * Gets all project options from the Twingle API
   * @param $projectId
   *
   * @return array
   * @throws \Exception
   */
  public function getProjectOptions($projectId): array {
    $url = $this->protocol . 'project' . $this->baseUrl . $projectId . '/options';
    return $this->curlGet($url);
  }

  /**
   * ## Get project payment methods
   * Gets all project payment methods from the Twingle API
   * @param $projectId
   *
   * @return array
   * @throws \Exception
   */
  public function getProjectPaymentMethods($projectId): array {
    $url = $this->protocol . 'project' . $this->baseUrl . $projectId
      . '/payment-methods';
    return $this->curlGet($url);
  }


  /**
   * ## Delete Project
   * Sends a DELETE cURL and returns the result array.
   *
   * @param int $projectId
   *
   * @return bool
   * @throws Exception
   */
  public function deleteProject(int $projectId): bool {
    $url = $this->protocol . 'project' . $this->baseUrl . $projectId;
    return $this->curlDelete($url);
  }


  /**
   * ## Delete Event
   * Sends a DELETE cURL and returns TRUE id deletion was successful.
   *
   * *NOTE:* It's only possible to delete one event at a time.
   *
   * @param int $projectId
   * @param int $eventId
   *
   * @return bool
   * @throws Exception
   */
  public function deleteEvent(int $projectId, int $eventId): bool {
    $url = $this->protocol . 'project' . $this->baseUrl . $projectId .
      '/event/' . $eventId;
    return $this->curlDelete($url);
  }


  /**
   * ## Send a GET cURL
   * Sends a GET cURL and returns the result array.
   *
   * @param $url
   * The destination url
   *
   * @param null $params
   * The parameters you want to send (optional)
   *
   * @return array
   * Returns the result array of the curl or FALSE, if the curl failed
   * @throws Exception
   */
  private
  function curlGet($url, $params = NULL): array {
    if (!empty($params)) {
      $url = $url . '?' . http_build_query($params);
    }
    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($curl, CURLOPT_HTTPHEADER,
      [
        "x-access-code: $this->apiKey",
        'Content-Type: application/json',
      ]
    );
    $response = json_decode(curl_exec($curl), TRUE);
    $curl_status_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    if ($response === FALSE) {
      throw new Exception('GET curl failed');
    }
    if ($curl_status_code == 404) {
      throw new Exception('http status code 404 (not found)');
    } elseif ($curl_status_code == 500) {
      throw new Exception('https status code 500 (internal error)');
    }
    return $response;
  }


  /**
   * ## Send a POST cURL
   * Sends a POST cURL and returns the result array.
   *
   * @param $url
   * The destination url
   *
   * @param $data
   * The data that should get send
   *
   * @return array
   * Returns the result array of the curl or FALSE, if the curl failed
   * @throws Exception
   */
  private
  function curlPost($url, $data): array {
    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($curl, CURLOPT_POST, TRUE);
    curl_setopt($curl, CURLOPT_HTTPHEADER,
      [
        "x-access-code: $this->apiKey",
        'Content-Type: application/json',
      ]
    );
    $json = json_encode($data);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $json);
    $response = json_decode(curl_exec($curl), TRUE);
    $curl_status_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    if ($response === FALSE) {
      throw new Exception('POST curl failed');
    }
    if ($curl_status_code == 404) {
      throw new Exception('http status code 404 (not found)');
    } elseif ($curl_status_code == 500) {
      throw new Exception('https status code 500 (internal error)');
    }
    return $response;
  }


  /**
   * ## Send a DELETE cURL
   * Sends a DELETE cURL and returns the result array.
   *
   * @param $url
   * The parameters you want to send
   *
   * @param $params
   * The data that should get send
   *
   * @return bool
   * Returns the result array of the curl or FALSE, if the curl failed
   * @throws Exception
   */
  private
  function curlDelete($url, $params = NULL): bool {
    if (!empty($params)) {
      $url = $url . '?' . http_build_query($params);
    }
    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'DELETE');
    curl_setopt($curl, CURLOPT_HTTPHEADER,
      [
        "x-access-code: $this->apiKey",
        'Content-Type: application/json',
      ]
    );
    $response = json_decode(curl_exec($curl), TRUE);
    $curl_status_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    if ($response === FALSE) {
      throw new Exception('DELETE curl failed');
    }
    if ($curl_status_code == 404) {
      throw new Exception('http status code 404 (not found)');
    } elseif ($curl_status_code == 500) {
      throw new Exception('https status code 500 (internal error)');
    }
    return ($curl_status_code == 200);
  }

}
