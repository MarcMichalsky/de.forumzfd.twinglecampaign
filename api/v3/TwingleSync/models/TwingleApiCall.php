<?php

namespace CRM\TwingleCampaign\Models;

use CRM_Core_BAO_Setting;
use CRM_TwingleCampaign_ExtensionUtil as E;
use CRM\TwingleCampaign\Models\TwingleProject as TwingleProject;

include_once E::path() . '/api/v3/TwingleSync/models/TwingleProject.php';

class TwingleApiCall {

  private $apiKey;

  private $baseUrl = '.twingle.de/api/';

  private $protocol = 'https://';

  private $organisationIds;

  /**
   * TwingleApiCall constructor.
   *
   * @param $apiKey
   *
   * @throws \API_Exception
   */
  public function __construct($apiKey) {
    $this->apiKey = $apiKey;

    $curl = curl_init($this->protocol . 'organisation' . $this->baseUrl);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($curl, CURLOPT_HTTPHEADER, [
      "x-access-code: $apiKey",
      'Content-Type: application/json',
    ]);

    $response = json_decode(curl_exec($curl), TRUE);
    curl_close($curl);

    if (empty($response)) {
      throw new \API_Exception("Twingle API call failed");
    }

    $this->organisationIds = array_column($response, 'id');
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
    foreach ($this->organisationIds as $organisationId) {
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
   * @param $values
   *
   * @return array|null
   * @throws \CiviCRM_API3_Exception
   * @throws \Exception
   */
  public function syncProject(array $values, bool $is_test = FALSE) {

    if (is_array($values)) {
      $project = new TwingleProject($values);
      $result = $project->create($is_test);
      $result = $project->create();

      if (
        $result['state'] == 'exists' &&
        $values['last_update'] > $project->getTimestamp()
      ) {
        $result = $project->update($is_test);
      }
      elseif (
        $result['state'] == 'exists' &&
        $values['last_update'] < $project->getTimestamp()
      ) {
        $result = $this->updateProject($project->export());
      }

      return $result;
    }
    else {
      return NULL;
    }

  }

  public function updateProject(array $values, bool $is_test = FALSE) {
    // TODO: Implement $is_test
    $url = $this->protocol . 'project' . $this->baseUrl . $values['id'];
    return $this->curlPost($url, $values);
  }

  public function updateEvent() {
  }

  /**
   * @return array
   */
  public function getOrganisationIds() {
    return $this->organisationIds;
  }

  /**
   *
   * Does a cURL and gives back the result array.
   *
   * @param $url
   *
   * @param null $params
   *
   * @return mixed
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
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER, [
      "x-access-code: $this->apiKey",
      'Content-Type: application/json',
    ]);
    curl_setopt($curl, CURLOPT_POSTFIELDS,  json_encode($data));
    $response = json_decode(curl_exec($curl), TRUE);
    if (empty($response)) {
      $response = curl_error($curl);
    }
    curl_close($curl);
    return $response;
  }

}