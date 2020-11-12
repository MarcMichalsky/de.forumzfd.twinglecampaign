<?php

use CRM\TwingleCampaign\BAO\TwingleApiCall as TwingleApiCall;
use CRM\TwingleCampaign\BAO\TwingleProject;
use CRM\TwingleCampaign\BAO\TwingleEvent;
use CRM\TwingleCampaign\BAO\TwingleCampaign;
use CRM_TwingleCampaign_ExtensionUtil as E;

include_once E::path() . '/CRM/TwingleCampaign/BAO/TwingleApiCall.php';
include_once E::path() . '/CRM/TwingleCampaign/BAO/TwingleProject.php';
include_once E::path() . '/CRM/TwingleCampaign/BAO/TwingleEvent.php';
include_once E::path() . '/CRM/TwingleCampaign/BAO/TwingeCampaign.php';

/**
 * TwingleSync.Post API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/api-architecture/
 */
function _civicrm_api3_twingle_sync_Post_spec(array &$spec) {
  $spec['twingle_api_key'] = [
    'name'         => 'twingle_api_key',
    'title'        => E::ts('Twingle API key'),
    'type'         => CRM_Utils_Type::T_STRING,
    'api.required' => 0,
    'description'  => E::ts('The key to access the Twingle API'),
  ];
  $spec['limit'] = [
    'name'         => 'limit',
    'title'        => E::ts('Limit'),
    'type'         => CRM_Utils_Type::T_INT,
    'api.required' => 0,
    'description'  => E::ts('Limit for the number of events that should get requested per call to the Twingle API'),
  ];
  $spec['is_test'] = [
    'name'         => 'is_test',
    'title'        => E::ts('Test'),
    'type'         => CRM_Utils_Type::T_BOOLEAN,
    'api.required' => 0,
    'description'  => E::ts('If this is set true, no database change will be made'),
  ];
}

/**
 * TwingleSync.Post API
 *
 * @param array $params
 *
 * @return array
 *   API result descriptor
 *
 * @throws \CiviCRM_API3_Exception
 * @throws \API_Exception
 * @see civicrm_api3_create_success
 */
function civicrm_api3_twingle_sync_Post(array $params) {
  $result_values = [];

  // Is this call a test?
  $is_test = (boolean) $params['is_test'];

  // If function call provides an API key, use it instead of the API key set
  // on the extension settings page
  $apiKey = empty($params['twingle_api_key'])
    ? trim(Civi::settings()->get('twingle_api_key'))
    : trim($params['twingle_api_key']);
  // If function call does not provide a limit, set a default value
  $limit = ($params['limit']) ?? NULL;
  $twingleApi = new TwingleApiCall($apiKey, $limit);

  // Get all projects from Twingle
  $projects = $twingleApi->getProject();

  // Create projects as campaigns if they do not exist and store results in
  // $result_values
  $i = 0;
  foreach ($projects as $project) {
    if (is_array($project)) {
      $result_values['sync']['projects'][$i++] =
        TwingleProject::sync($project, $twingleApi, $is_test);
    }
  }

  // Get all events from projects of type "event" and create them as campaigns
  // if they do not exist yet
  $j = 0;
  foreach ($result_values['sync']['projects'] as $project) {
    if ($project['project_type'] == 'event') {
      $events = $twingleApi->getEvent($project['project_id']);
      if (is_array($events)) {
        foreach ($events as $event) {
          if ($event) {
            $result_values['sync']['events'][$j++] =
              TwingleEvent::sync($event, $twingleApi, $is_test);
          }
        }
      }
    }
  }

  return civicrm_api3_create_success($result_values);
}


