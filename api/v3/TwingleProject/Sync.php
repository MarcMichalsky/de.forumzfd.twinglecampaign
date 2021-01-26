<?php

use CRM_TwingleCampaign_BAO_TwingleProject as TwingleProject;
use CRM_TwingleCampaign_BAO_TwingleApiCall as TwingleApiCall;
use CRM_TwingleCampaign_ExtensionUtil as E;


/**
 * TwingleProject.Sync API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/api-architecture/
 */
function _civicrm_api3_twingle_project_Sync_spec(array &$spec) {
  $spec['id'] = [
    'name'         => 'id',
    'title'        => E::ts('Campaign ID'),
    'type'         => CRM_Utils_Type::T_INT,
    'api.required' => 0,
    'description'  => E::ts('Unique Campaign ID'),
  ];
  $spec['project_id'] = [
    'name'         => 'project_id',
    'title'        => E::ts('Twingle Project ID'),
    'type'         => CRM_Utils_Type::T_INT,
    'api.required' => 0,
    'description'  => E::ts('Twingle ID for this project'),
  ];
  $spec['is_test'] = [
    'name'         => 'is_test',
    'title'        => E::ts('Test'),
    'type'         => CRM_Utils_Type::T_BOOLEAN,
    'api.required' => 0,
    'description'  => E::ts('If this is set true, no database change will be made'),
  ];
  $spec['twingle_api_key'] = [
    'name'         => 'twingle_api_key',
    'title'        => E::ts('Twingle API key'),
    'type'         => CRM_Utils_Type::T_STRING,
    'api.required' => 0,
    'description'  => E::ts('The key to access the Twingle API'),
  ];
}


/**
 * TwingleProject.Sync API
 *
 * @param array $params
 *
 * @return array
 *   API result descriptor
 * @throws \CiviCRM_API3_Exception
 * @see civicrm_api3_create_success
 */
function civicrm_api3_twingle_project_Sync(array $params): array {

  // For logging purpose
  $extensionName = E::LONG_NAME;

  // If call provides an API key, use it instead of the API key set
  // on the extension settings page
  $apiKey = empty($params['twingle_api_key'])
    ? trim(Civi::settings()->get('twingle_api_key'))
    : trim($params['twingle_api_key']);

  // Try to retrieve twingleApi from cache or create a new
  $twingleApi = Civi::cache()->get('twinglecampaign_twingle_api');
  if (NULL === $twingleApi || $params['twingle_api_key']) {
    try {
      $twingleApi = new TwingleApiCall($apiKey);
    } catch (Exception $e) {
      return civicrm_api3_create_error($e->getMessage());
    }
    Civi::cache('long')->set('twinglecampaign_twingle_api', $twingleApi);
  }

  // If an id or a project_id is given, synchronize only this one campaign
  if ($params['id'] || $params['project_id']) {

    // Get project from db via API
    $params['sequential'] = 1;
    $result = civicrm_api3('TwingleProject', 'getsingle', $params);
    if ($result['is_error'] == 0) {

      // If the TwingleProject campaign already has a project_id try to get the
      // project from Twingle
      if ($result['values'][0]['project_id']) {
        $project_from_twingle = $twingleApi->getProject($result['values'][0]['project_id']);

        // instantiate project from CiviCRM
        $id = $result['values'][0]['id'];
        unset($result['values'][0]['id']);
        $project = new TwingleProject($result['values'][0], $id);

        // Synchronize projects
        if (!empty($project_from_twingle)) {
          return sync($project, $project_from_twingle, $twingleApi, $params);
        }

        // If Twingle does not know a project with the given project_id, give error
        else {
          return civicrm_api3_create_error(
            "The project_id appears to be unknown to Twingle",
            $project->getResponse()
          );
        }
      }

      // If the TwingleProject campaign does not have a project_id, push it to
      // Twingle and update it with the returning values
      else {

        // store campaign id in $id
        $id = $result['values'][0]['id'];
        unset($result['values'][0]['id']);

        // instantiate project
        $project = new TwingleProject($result['values'][0], $id);

        // Push project to Twingle
        return pushToTwingle($project, $twingleApi, $params);
      }
    }

    // If the project could not get retrieved from TwingleProject.getsingle,
    // forward API error message
    else {
      Civi::log()->error(
        "$extensionName could retrieve project from TwingleProject.getsingle",
        $result
      );
      return $result;
    }
  }

  // If no id or project_id is given, synchronize all projects
  else {

    // Counter for sync errors
    $errors_occurred = 0;

    // Get all projects from Twingle
    $projects_from_twingle = $twingleApi->getProject();

    // Get all TwingleProjects from CiviCRM
    $projects_from_civicrm = civicrm_api3('TwingleProject', 'get',
      ['is_active' => 1,]);

    // If call to TwingleProject.get failed, forward error message
    if ($projects_from_civicrm['is_error'] != 0) {
      Civi::log()->error(
        "$extensionName could retrieve projects from TwingleProject.get",
        $projects_from_civicrm
      );
      return $projects_from_civicrm;
    }

    // Push missing projects to Twingle
    $result_values = [];
    foreach ($projects_from_civicrm['values'] as $project_from_civicrm) {
      if (!in_array($project_from_civicrm['project_id'],
        array_column($projects_from_twingle, 'id'))) {
        // store campaign id in $id
        $id = $project_from_civicrm['id'];
        unset($project_from_civicrm['id']);
        // instantiate project with values from TwingleProject.Get
        $project = new TwingleProject($project_from_civicrm, $id);
        // push project to Twingle
        $result = pushToTwingle($project, $twingleApi, $params);
        if ($result['is_error'] != 0) {
          $errors_occurred++;
          $result_values[$project->getId()] =
            $project->getResponse($result['error_message']);
        }
        else {
          $result_values[$project->getId()] = $result['values'];
        }
      }
    }

    // Create missing projects as campaigns in CiviCRM
    foreach ($projects_from_twingle as $project_from_twingle) {
      if (!in_array($project_from_twingle['id'],
        array_column($projects_from_civicrm['values'], 'project_id'))) {
        $project = new TwingleProject($project_from_twingle);

        // If this is a test, do not make db changes
        if ($params['is_test']) {
          $result_values[$project->getId()] =
            $project->getResponse('Ready to create TwingleProject');
        }

        try {
          $project->create(TRUE);
          $result_values[$project->getId()] =
            $project->getResponse('TwingleProject created');
        } catch (Exception $e) {
          $errors_occurred++;
          $errorMessage = $e->getMessage();
          Civi::log()->error(
            "$extensionName could not create TwingleProject: $errorMessage",
            $project->getResponse()
          );
          $result_values[$project->getId()] = $project->getResponse(
            "TwingleProject could not get created: $errorMessage"
          );
        }
      }
    }

    // Synchronize existing projects
    foreach ($projects_from_civicrm['values'] as $project_from_civicrm) {
      foreach ($projects_from_twingle as $project_from_twingle) {
        if ($project_from_twingle['id'] == $project_from_civicrm['project_id']) {
          // store campaign id in $id
          $id = $project_from_civicrm['id'];
          unset($project_from_civicrm['id']);
          // instantiate project with values from TwingleProject.Get
          $project = new TwingleProject($project_from_civicrm, $id);
          // sync project
          $result = sync($project, $project_from_twingle, $twingleApi, $params);
          if ($result['is_error'] != 0) {
            $errors_occurred++;
            $result_values[$project->getId()] =
              $project->getResponse($result['error_message']);

          }
          else {
            $result_values[$project->getId()] = $result['values'];
          }
          break;
        }
      }
    }

    // Give back results
    if ($errors_occurred > 0) {
      $errorMessage = ($errors_occurred > 1)
        ? "$errors_occurred synchronisation processes resulted with an error"
        : "1 synchronisation process resulted with an error";
      return civicrm_api3_create_error(
        $errorMessage,
        $result_values
      );
    }
    else {
      return civicrm_api3_create_success(
        $result_values,
        $params,
        'TwingleProject',
        'Sync'
      );
    }
  }
}


/**
 * Update a TwingleProject campaign locally
 *
 * @param array $project_from_twingle
 * @param \CRM_TwingleCampaign_BAO_TwingleProject $project
 * @param array $params
 * @param \CRM_TwingleCampaign_BAO_TwingleApiCall $twingleApi
 *
 * @return array
 */
function updateLocally(array $project_from_twingle,
                       TwingleProject $project,
                       array $params,
                       TwingleApiCall $twingleApi): array {

  // For logging purpose
  $extensionName = E::LONG_NAME;

  try {
    $project->update($project_from_twingle);
    $project->setEmbedData(
      $twingleApi->getProjectEmbedData($project->getProjectId())
    );
    // If this is a test, do not make db changes
    if ($params['is_test']) {
      return civicrm_api3_create_success(
        $project->getResponse('TwingleProject ready to update'),
        $params,
        'TwingleProject',
        'Sync'
      );
    }
    // ... else, update local TwingleProject campaign
    try {
      $project->create(TRUE);
      return civicrm_api3_create_success(
        $project->getResponse('TwingleProject updated successfully'),
        $params,
        'TwingleProject',
        'Sync'
      );
    } catch (Exception $e) {
      $errorMessage = $e->getMessage();
      Civi::log()->error(
        "$extensionName could not update TwingleProject: $errorMessage",
        $project->getResponse()
      );
      return civicrm_api3_create_error(
        "TwingleProject could not get updated: $errorMessage",
        $project->getResponse()
      );
    }
  } catch (Exception $e) {
    $errorMessage = $e->getMessage();
    Civi::log()->error(
      "$extensionName could not update TwingleProject campaign: $errorMessage"
    );
    return civicrm_api3_create_error(
      "Could not update TwingleProject campaign: $errorMessage",
      $project->getResponse()
    );
  }
}


/**
 * Push a TwingleProject via API to Twingle
 *
 * @param \CRM_TwingleCampaign_BAO_TwingleProject $project
 * @param \CRM_TwingleCampaign_BAO_TwingleApiCall $twingleApi
 * @param array $params
 *
 * @return array
 * @throws \CiviCRM_API3_Exception
 */
function pushToTwingle(TwingleProject $project,
                       TwingleApiCall $twingleApi,
                       array $params): array {

  // For logging purpose
  $extensionName = E::LONG_NAME;

  // If this is a test, do not make db changes
  if ($params['is_test']) {
    return civicrm_api3_create_success(
      $project->getResponse('TwingleProject ready to push to Twingle'),
      $params,
      'TwingleProject',
      'Sync'
    );
  }

  // Push project to Twingle
  try {
    $result = $twingleApi->pushProject($project);
  } catch (Exception $e) {
    $errorMessage = $e->getMessage();
    Civi::log()->error(
      "$extensionName could not push TwingleProject to Twingle: $errorMessage",
      $project->getResponse()
    );
    return civicrm_api3_create_error(
      "Could not push TwingleProject to Twingle: $errorMessage",
      $project->getResponse()
    );
  }

  // Update local campaign with data returning from Twingle
  if ($result) {
    $project->update($result);
    // Get embed data
    try {
      $project->setEmbedData(
        $twingleApi->getProjectEmbedData($project->getProjectId())
      );
      // Create updated campaign
      $project->create(TRUE);
      return civicrm_api3_create_success(
        $project->getResponse('TwingleProject pushed to Twingle'),
        $params,
        'TwingleProject',
        'Sync'
      );
    } catch (Exception $e) {
      $errorMessage = $e->getMessage();
      Civi::log()->error(
        "$extensionName pushed TwingleProject to Twingle but local update failed: $errorMessage",
        $project->getResponse()
      );
      return civicrm_api3_create_error(
        "TwingleProject was pushed to Twingle but local update failed: $errorMessage",
        $project->getResponse()
      );
    }
  }
  // If the curl fails, the $result may be empty
  else {
    Civi::log()->error(
      "$extensionName could not push TwingleProject campaign",
      $project->getResponse()
    );
    return civicrm_api3_create_error(
      "Could not push TwingleProject campaign",
      $project->getResponse()
    );
  }
}


/**
 * Synchronize a TwingleProject campaign with a project from Twingle
 *
 * @param \CRM_TwingleCampaign_BAO_TwingleProject $project
 * @param array $project_from_twingle
 * @param \CRM_TwingleCampaign_BAO_TwingleApiCall $twingleApi
 * @param array $params
 *
 * @return array
 * @throws \CiviCRM_API3_Exception
 */
function sync(TwingleProject $project,
              array $project_from_twingle,
              TwingleApiCall $twingleApi,
              array $params): array {

  // If Twingle's version of the project is newer than the CiviCRM
  // TwingleProject campaign, update the campaign
  if ($project_from_twingle['last_update'] > $project->lastUpdate()) {
    return updateLocally($project_from_twingle, $project, $params, $twingleApi);
  }

  // If the CiviCRM TwingleProject campaign was changed, update the project
  // on Twingle's side
  elseif ($project_from_twingle['last_update'] < $project->lastUpdate()) {
    return pushToTwingle($project, $twingleApi, $params);
  }

  // If both versions are still synchronized
  else {
    return civicrm_api3_create_success(
      $project->getResponse('TwingleProject up to date'),
      $params,
      'TwingleProject',
      'Sync'
    );
  }
}