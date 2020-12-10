<?php

use CRM_TwingleCampaign_BAO_TwingleProject as TwingleProject;
use CRM_TwingleCampaign_ExtensionUtil as E;

include_once E::path() . '/CRM/TwingleCampaign/BAO/TwingleProject.php';

/**
 * TwingleProject.Create API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/api-architecture/
 */
function _civicrm_api3_twingle_project_Create_spec(array &$spec) {
  $spec['title'] = [
    'name'         => 'title',
    'title'        => E::ts('Campaign Title'),
    'type'         => CRM_Utils_Type::T_STRING,
    'api.required' => 1,
    'description'  => E::ts('Title of the Campaign'),
  ];
  $spec['project_type'] = [
    'name'         => 'project_type',
    'title'        => E::ts('Twingle Project Type'),
    'type'         => CRM_Utils_Type::T_STRING,
    'api.required' => 0,
    'description'  => E::ts('The type of the Twingle Project'),
  ];
  $spec['allow_more'] = [
    'name'         => 'allow_more',
    'title'        => E::ts('Allow more'),
    'type'         => CRM_Utils_Type::T_BOOLEAN,
    'api.required' => 0,
    'description'  => E::ts('Allow to donate more than is defined in the target'),
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
 * @throws \Exception
 * @see civicrm_api3_create_success
 *
 */
function civicrm_api3_twingle_project_Create($params) {
  $returnValues = [];

  $title = $params['title'];
  $name = strtolower(preg_replace('/[^A-Za-z0-9]/', '_', $title));
  $type = $params['project_type'] ?? 'default';
  $allow_more = $params['allow_more'] ?? 1;

  $values = [
    'title'      => $title,
    'name'       => $name,
    'type'       => $type,
    'allow_more' => $allow_more,
  ];

  $project = new TwingleProject($values, 'CIVICRM');

  $create_project = $project->create();

  $projectId = $project->getId();

  $sync = $result = civicrm_api3(
    'TwingleSync',
    'singlesync',
    ['campaign_id' => $projectId]
  );

  if ($sync['is_error'] == 0) {
    $returnValues = $sync['values']['project'];
  }
  elseif ($create_project['status'] == "TwingleProject created") {
    throw new API_Exception(
      "TwingleProject was created but could not get pushed to Twingle"
    );
  }
  else {
    throw new API_Exception(
      "TwingleProject creation failed"
    );
  }

  return civicrm_api3_create_success($returnValues, $params, 'TwingleProject', 'Create');
}
