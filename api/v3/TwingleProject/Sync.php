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
 * # TwingleProject.Sync API
 *
 * Synchronize one ore more campaigns of the type TwingleProject between
 * CiviCRM
 * and Twingle.
 *
 * * If you provide an **id** or **project_id** parameter, *only one project*
 * will be synchronized.
 *
 * * If you provide no **id** or **project_id** parameter, *all projects* will
 * be synchronized.
 *
 * @param array $params
 *
 * @return array
 *   API result descriptor
 * @throws \CiviCRM_API3_Exception
 * @throws \Exception
 * @see civicrm_api3_create_success
 */
function civicrm_api3_twingle_project_Sync(array $params): array {

  // filter parameters
  $allowed_params = [];
  _civicrm_api3_twingle_project_Sync_spec($allowed_params);
  $params = array_intersect_key($params, $allowed_params);

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
  if (isset($params['id']) || isset($params['project_id'])) {

    // Get project from db via API
    $params['sequential'] = 1;
    $result = civicrm_api3('TwingleProject', 'getsingle', $params);

    // If the TwingleProject campaign already has a project_id try to get the
    // project from Twingle
    if ($result['project_id']) {
      $project_from_twingle = $twingleApi->getProject($result['project_id']);

      // instantiate project from CiviCRM
      $id = $result['id'];
      unset($result['id']);
      $project = new TwingleProject($result, $id);

      // Synchronize projects
      if (!empty($project_from_twingle)) {
        return _projectSync($project, $project_from_twingle, $twingleApi, $params);
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
      $id = $result['id'];
      unset($result['id']);

      // instantiate project
      $project = new TwingleProject($result, $id);

      // Push project to Twingle
      return _pushProjectToTwingle($project, $twingleApi, $params);
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
        E::LONG_NAME .
        ' could retrieve projects from TwingleProject.get: ',
        $projects_from_civicrm
      );
      return $projects_from_civicrm;
    }

    // Push missing projects to Twingle
    $returnValues = [];
    foreach ($projects_from_civicrm['values'] as $project_from_civicrm) {
      if (!in_array($project_from_civicrm['project_id'],
        array_column($projects_from_twingle, 'id'))) {
        // store campaign id in $id
        $id = $project_from_civicrm['id'];
        unset($project_from_civicrm['id']);
        // instantiate project with values from TwingleProject.Get
        $project = new TwingleProject($project_from_civicrm, $id);
        // push project to Twingle
        $result = _pushProjectToTwingle($project, $twingleApi, $params);
        if ($result['is_error'] != 0) {
          $errors_occurred++;
          $returnValues[$project->getId()] =
            $project->getResponse($result['error_message']);
        }
        else {
          $returnValues[$project->getId()] = $result['values'];
        }
      }
    }

    // Create missing projects as campaigns in CiviCRM
    foreach ($projects_from_twingle as $project_from_twingle) {
      if (!in_array($project_from_twingle['id'],
        array_column($projects_from_civicrm['values'], 'project_id'))) {
        $project = new TwingleProject($project_from_twingle);

        try {
          // If this is a test, do not make db changes
          if (isset($params['is_test']) && $params['is_test']) {
            $returnValues[$project->getId()] =
              $project->getResponse('Ready to create TwingleProject');
          }

          $project->create(TRUE);
          $returnValues[$project->getId()] =
            $project->getResponse('TwingleProject created');
        } catch (Exception $e) {
          $errors_occurred++;
          Civi::log()->error(
            E::LONG_NAME .
            ' could not create TwingleProject: ' .
            $e->getMessage(),
            $project->getResponse()
          );
          $returnValues[$project->getId()] = $project->getResponse(
            "TwingleProject could not get created: " . $e->getMessage()
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
          $result = _projectSync(
              $project,
              $project_from_twingle,
              $twingleApi,
              $params);
          if (!$result['is_error'] == 0) {
            $errors[$result['id']] = $result['error_message'];
            $returnValues[$project->getId()] =
              $project->getResponse($result['error_message']);
          }
          else {
            $returnValues[$result['id']] = $result['values'][$result['id']];
          }
          break;
        }
      }
    }

    // Return results
    if ($errors_occurred > 0) {
      $errorMessage = ($errors_occurred > 1)
        ? "$errors_occurred synchronisation processes resulted with an error"
        : "1 synchronisation process resulted with an error";
      return civicrm_api3_create_error(
        $errorMessage,
        $returnValues
      );
    }
    else {
      return civicrm_api3_create_success(
        $returnValues,
        $params,
        'TwingleProject',
        'Sync'
      );
    }
  }
}


/**
 * ## Update a TwingleProject campaign locally
 *
 * @param array $project_from_twingle
 * @param \CRM_TwingleCampaign_BAO_TwingleProject $project
 * @param array $params
 * @param \CRM_TwingleCampaign_BAO_TwingleApiCall $twingleApi
 *
 * @return array
 */
function _updateProjectLocally(array $project_from_twingle,
                               TwingleProject $project,
                               array $params,
                               TwingleApiCall $twingleApi): array {

  try {
    $project->update($project_from_twingle);

    // If this is a test, do not make db changes
    if (array_key_exists('is_test', $params) && $params['is_test']) {
      return civicrm_api3_create_success(
        $project->getResponse('TwingleProject ready to update'),
        $params,
        'TwingleProject',
        'Sync'
      );
    }
    // ... else, update local TwingleProject campaign
    $project->create(TRUE);
    $response = $project->getResponse('TwingleProject updated successfully');
    return civicrm_api3_create_success(
      $response,
      $params,
      'TwingleProject',
      'Sync'
    );
  } catch (Exception $e) {
    Civi::log()->error(
      E::LONG_NAME .
      ' could not update TwingleProject campaign: ' .
      $e->getMessage(),
      $project->getResponse()
    );
    return civicrm_api3_create_error(
      'Could not update TwingleProject campaign: ' . $e->getMessage(),
      $project->getResponse()
    );
  }
}


/**
 * ## Push a TwingleProject to Twingle
 *
 * @param \CRM_TwingleCampaign_BAO_TwingleProject $project
 * @param \CRM_TwingleCampaign_BAO_TwingleApiCall $twingleApi
 * @param array $params
 *
 * @return array
 * @throws \CiviCRM_API3_Exception
 */
function _pushProjectToTwingle(TwingleProject $project,
                               TwingleApiCall $twingleApi,
                               array $params): array {

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
    $result = $twingleApi->pushProject($project->export());
  } catch (Exception $e) {
    Civi::log()->error(
      E::LONG_NAME .
      ' could not push TwingleProject to Twingle: '
      . $e->getMessage(),
      $project->getResponse()
    );
    return civicrm_api3_create_error(
      'Could not push TwingleProject to Twingle: ' . $e->getMessage(),
      $project->getResponse()
    );
  }

  // Update local campaign with data returning from Twingle
  if ($result) {
    $project->update($result);
    try {
      // Create updated campaign
      $project->create(TRUE);
      $response = $project->getResponse('TwingleProject pushed to Twingle');
      return civicrm_api3_create_success(
        $response,
        $params,
        'TwingleProject',
        'Sync'
      );
    } catch (Exception $e) {
      Civi::log()->error(
        E::LONG_NAME .
        ' pushed TwingleProject to Twingle but local update failed: ' .
        $e->getMessage(),
        $project->getResponse()
      );
      return civicrm_api3_create_error(
        'TwingleProject was pushed to Twingle but local update failed: ' .
        $e->getMessage(),
        $project->getResponse()
      );
    }
  }
  // If the curl fails, the $result may be empty
  else {
    Civi::log()->error(
      E::LONG_NAME .
      ' could not push TwingleProject campaign',
      $project->getResponse()
    );
    return civicrm_api3_create_error(
      "Could not push TwingleProject campaign",
      $project->getResponse()
    );
  }
}


/**
 * ## Synchronize TwingleProject
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
function _projectSync(TwingleProject $project,
                      array $project_from_twingle,
                      TwingleApiCall $twingleApi,
                      array $params): array {

  // If Twingle's version of the project is newer than the CiviCRM
  // TwingleProject campaign, update the campaign
  if ($project_from_twingle['last_update'] > $project->lastUpdate()) {
    return _updateProjectLocally($project_from_twingle, $project, $params, $twingleApi);
  }

  // If the CiviCRM TwingleProject campaign was changed, update the project
  // on Twingle's side
  elseif ($project_from_twingle['last_update'] < $project->lastUpdate()) {
    // Make sure that the project hast a correct project_id. This is important
    // to avoid an accidental cloning of project.
    if (empty($project->getProjectId())) {
      throw new \CiviCRM_API3_Exception(
        'Missing project_id for project that is meant to get updated on Twingle side.');
    }

    // By merging the project values with the values coming from Twingle, we
    // make sure that the project contains all values needed to get pushed
    $project->complement($project_from_twingle);

    return _pushProjectToTwingle($project, $twingleApi, $params);
  }

  // If both versions are still synchronized
  else {
    $response = $project->getResponse('TwingleProject up to date');
    return civicrm_api3_create_success(
      $response,
      $params,
      'TwingleProject',
      'Sync'
    );
  }
}
