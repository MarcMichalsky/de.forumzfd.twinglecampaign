<?php

use CRM_TwingleCampaign_BAO_TwingleApiCall as TwingleApiCall;
use CRM_TwingleCampaign_BAO_TwingleProject as TwingleProject;
use CRM_TwingleCampaign_BAO_TwingleEvent as TwingleEvent;
use CRM_TwingleCampaign_ExtensionUtil as E;

/**
 * TwingleSync.Sync API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/api-architecture/
 */
function _civicrm_api3_twingle_sync_Sync_spec(array &$spec) {
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
  $spec['project_id'] = [
    'name'         => 'project_id',
    'title'        => E::ts('Twingle Project ID'),
    'type'         => CRM_Utils_Type::T_INT,
    'api.required' => 0,
    'description'  => E::ts('Twingle ID for a project'),
  ];
  $spec['id'] = [
    'name'         => 'id',
    'title'        => E::ts('Campaign ID'),
    'type'         => CRM_Utils_Type::T_INT,
    'api.required' => 0,
    'description'  => E::ts('Unique Campaign ID'),
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
 * TwingleSync.Sync API
 *
 * @param array $params
 *
 * @return array
 *   API result descriptor
 *
 * @throws API_Exception|\CiviCRM_API3_Exception
 * @throws \Exception
 * @see civicrm_api3_create_success
 *
 */
function civicrm_api3_twingle_sync_Sync($params) {

  $result_values = [];
  $is_test = FALSE;

  // Is this call a test?
  if ($params['is_test']) {
    $is_test = $params['is_test'];
  }

  // If function call provides an API key, use it instead of the API key set
  // on the extension settings page
  $apiKey = empty($params['twingle_api_key'])
    ? trim(Civi::settings()->get('twingle_api_key'))
    : trim($params['twingle_api_key']);
  // If function call does not provide a limit, set a default value
  $limit = ($params['limit']) ?? 20;

  // Try to retrieve twingleApi from cache
  $twingleApi = Civi::cache()->get('twinglecampaign_twingle_api');
  if (NULL === $twingleApi || $params['twingle_api_key']) {
    $twingleApi = new TwingleApiCall($apiKey, $limit);
    Civi::cache('long')->set('twinglecampaign_twingle_api', $twingleApi);
  }

  if ($params['id'] && !$params['project_id']) {
    // Get single TwingleProject
    $projects_from_civicrm =
      civicrm_api3('TwingleProject', 'getsingle',
        ['id' => $params['id'], 'is_active' => 1])['values'];

    // Get single project from Twingle
    $projects_from_twingle[0] =
      $projects_from_civicrm[$params['id']]['project_id']
        ? $twingleApi->getProject($projects_from_civicrm[$params['id']]['project_id'])
        : NULL;
  }
  else {
    if ($params['project_id']) {
      // Get single project from Twingle
      $projects_from_twingle[0] = $twingleApi->getProject($params['project_id']);

      // Get single TwingleProject
      $projects_from_civicrm = civicrm_api3('TwingleProject', 'get',
        ['is_active' => 1, 'project_id' => $params['project_id']]);
    }
    else {
      // Get all projects from Twingle
      $projects_from_twingle = $twingleApi->getProject();

      // Get all TwingleProjects from CiviCRM
      $projects_from_civicrm = civicrm_api3('TwingleProject', 'get',
        ['is_active' => 1,])['values'];
    }
  }

  $i = 0;

  // Push missing projects to Twingle
  foreach ($projects_from_civicrm as $project_from_civicrm) {
    if (!in_array($project_from_civicrm['project_id'],
      array_column($projects_from_twingle, 'id'))) {
      // store campaign id in $id
      $id = $project_from_civicrm['id'];
      unset($project_from_civicrm['id']);
      // instantiate project with values from TwingleProject.Get
      $project = new TwingleProject($project_from_civicrm, $id);
      // push project to Twingle
      $result = $twingleApi->pushProject($project);
      // update local campaign with data coming back from Twingle
      $project->update($result);
      $project_create = $project->create();
      // set status
      $project_create['status'] =
        $project_create['status'] == 'TwingleProject created'
          ? 'TwingleProject pushed to Twingle'
          : 'TwingleProject got likely pushed to Twingle but local update failed';
      $result_values['sync']['projects'][$i++] = $project_create;
    }
  }

  // Sync existing projects
  foreach ($projects_from_twingle as $project) {
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
