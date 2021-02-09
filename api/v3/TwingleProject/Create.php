<?php

use CRM_TwingleCampaign_BAO_TwingleProject as TwingleProject;
use CRM_TwingleCampaign_ExtensionUtil as E;

/**
 * TwingleProject.Create API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/api-architecture/
 */
function _civicrm_api3_twingle_project_Create_spec(array &$spec) {
  $spec['name'] = [
    'name'         => 'name',
    'title'        => E::ts('Twingle Project Name'),
    'type'         => CRM_Utils_Type::T_STRING,
    'api.required' => 0,
    'description'  => E::ts('Name of the Twingle Project'),
  ];
  $spec['id'] = [
    'name'         => 'id',
    'title'        => E::ts('Twingle Project ID'),
    'type'         => CRM_Utils_Type::T_INT,
    'api.required' => 0,
    'description'  => E::ts('The Twingle Project ID'),
  ];
  $spec['type'] = [
    'name'         => 'type',
    'title'        => E::ts('Twingle Project Type'),
    'type'         => CRM_Utils_Type::T_STRING,
    'api.required' => 0,
    'description'  => E::ts('The type of the Twingle Project'),
    'api.default'  => 'default',
  ];
  $spec['allow_more'] = [
    'name'         => 'allow_more',
    'title'        => E::ts('Allow more'),
    'type'         => CRM_Utils_Type::T_BOOLEAN,
    'api.required' => 0,
    'description'  => E::ts('Allow to donate more than is defined in the target'),
    'api.default'  => TRUE,
  ];
  $spec['project_target'] = [
    'name'         => 'project_target',
    'title'        => E::ts('Project Target'),
    'type'         => CRM_Utils_Type::T_MONEY,
    'api.required' => 0,
    'description'  => E::ts('Financial Target of a Project'),
  ];
  $spec['page'] = [
    'name'         => 'page',
    'title'        => E::ts('Project Page'),
    'type'         => CRM_Utils_Type::T_STRING,
    'api.required' => 0,
    'description'  => E::ts('The URL of the TwingleProject page'),
  ];
}

/**
 * TwingleProject.Create API
 *
 * @param array $params
 *
 * @return array
 *   API result descriptor
 *
 * @see civicrm_api3_create_success
 */
function civicrm_api3_twingle_project_Create(array $params): array {

  // filter parameters
  $allowed_params = [];
  _civicrm_api3_twingle_project_Create_spec($allowed_params);
  $params = array_intersect_key($params, $allowed_params);

  try {
    // Try to instantiate project
    // If id parameter is provided, alter the existing project
    if ($params['id']) {
      $result = civicrm_api3('TwingleProject', 'getsingle',
        ['id' => $params['id']]
      );
      $result['values']['id'] = $result['values']['project_id'];
      unset($result['values']['project_id']);
      $project = new TwingleProject($result['values'], $params['id']);
      $project->update($params);
      $project->setEmbedData($params);
    }
    // If no id is provided, try to create a new project with provided values
    else {
      $id = $params['id'];
      unset($params['id']);
      $project = new TwingleProject($params, $id);
    }
  } catch (Exception $e) {
    return civicrm_api3_create_error(
      'Could not instantiate TwingleProject: ' . $e->getMessage()
    );
  }


    // Try to create the TwingleProject campaign
    try {
      $project->create();
      return civicrm_api3_create_success(
        $project->getResponse('TwingleProject created'),
        $params,
        'TwingleProject',
        'Create'
      );
    } catch(Exception $e){
      Civi::log()->error(
        E::LONG_NAME .
        ' could not create TwingleProject: ' .
        $e->getMessage(),
        $project->getResponse()
      );
      return civicrm_api3_create_error(
        'Could not create TwingleProject: ' . $e->getMessage(),
        $project->getResponse()
      );
    }

}
