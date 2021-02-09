<?php

use CRM_TwingleCampaign_BAO_TwingleProject as TwingleProject;
use CRM_TwingleCampaign_BAO_TwingleApiCall as TwingleApiCall;
use CRM_TwingleCampaign_ExtensionUtil as E;

/**
 * TwingleProject.Delete API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/api-architecture/
 */
function _civicrm_api3_twingle_project_Delete_spec(array &$spec) {
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
 * # TwingleProject.Delete API
 *
 * Delete a TwingleProject on CiviCRM and Twingle side.
 *
 * @param array $params
 *
 * @return array
 *   API result descriptor
 *
 * @throws CiviCRM_API3_Exception
 * @see civicrm_api3_create_success
 *
 */
function civicrm_api3_twingle_project_Delete(array $params): array {

  // filter parameters
  $allowed_params = [];
  _civicrm_api3_twingle_project_Delete_spec($allowed_params);
  $params = array_intersect_key($params, $allowed_params);

  $result_values = [];
  $error_occurred = FALSE;

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

  $params['sequential'] = 1;

  // To delete the TwingleProject on Twingle's side, we need the project_id
  // If no project_id is provided, try to get it via TwingleProject.getsingle
  if (!$params['project_id']) {
    try {
      $project = civicrm_api3('TwingleProject', 'getsingle', $params);
      $params['project_id'] = $project['values'][0]['project_id'];
    } catch (CiviCRM_API3_Exception $e) {
      $result_values['twingle'] = 'Could not delete TwingleProject: ' . $e->getMessage();
    }
  }

  // Delete TwingleProject on Twingle's side
  if ($params['project_id']) {
    try {
      $twingleApi->deleteProject($params['project_id']);
      $result_values['twingle'] = 'TwingleProject deleted';
    } catch (Exception $e) {
      // If Twingle does not know the project_id
      if ($e->getMessage() == 'http status code 404 (not found)') {
        $result_values['twingle'] = 'project not found';
      }
      // If the deletion curl failed
      else {
        $error_occurred = TRUE;
        Civi::log()->error(
          E::LONG_NAME .
          ' could not delete TwingleProject (project_id ' . $params['project_id']
          . ') on Twingle\'s side: ' . $e->getMessage(),
        );
        // Set deletion status
        $result_values['twingle'] = 'Could not delete TwingleProject: ' . $e->getMessage();
      }
    }
  }



  // Delete the TwingleProject campaign on CiviCRM's side
  try {
    $project = civicrm_api3('TwingleProject', 'getsingle', $params);
    // The TwingleProject campaign may be already deleted
    if ($project['is_error'] == 0) {
      $project = new TwingleProject($project['values'][0], $project['values'][0]['id']);
      $project->delete();
      $result_values['civicrm'] = 'TwingleProject deleted';
    }
    // If deletion fails
  } catch (Exception $e) {
    $error_occurred = TRUE;
    Civi::log()->error(
      E::LONG_NAME .
      ' could not delete TwingleProject (project_id ' . $params['project_id'] .
      ') on CiviCRM\'s side: ' . $e->getMessage(),
    );
    // Set deletion status
    $result_values['civicrm'] = 'Could not delete TwingleProject: ' . $e->getMessage();
  }

  // Return the results
  if ($error_occurred) {
    return civicrm_api3_create_error(
      'TwingleProject deletion failed',
      ['deletion_status' => $result_values]
    );
  }
  else {
    return civicrm_api3_create_success(
      ['deletion_status' => $result_values],
      $params,
      'TwingleProject',
      'Delete'
    );
  }
}
