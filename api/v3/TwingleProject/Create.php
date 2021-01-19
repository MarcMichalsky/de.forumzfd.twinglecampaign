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
  $spec['name'] = [
    'name'         => 'name',
    'title'        => E::ts('Twingle Project Name'),
    'type'         => CRM_Utils_Type::T_STRING,
    'api.required' => 1,
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
  $spec['organisation_id'] = [
    'name'         => 'organisation_id',
    'title'        => E::ts('Organisation ID'),
    'type'         => CRM_Utils_Type::T_INT,
    'api.required' => 0,
    'description'  => E::ts('ID of your Organisation'),
  ];
  $spec['last_update'] = [
    'name'         => 'last_update',
    'title'        => E::ts('Last Project Update '),
    'type'         => CRM_Utils_Type::T_STRING,
    'api.required' => 0,
    'description'  => E::ts('UNIX-Timestamp ot the moment when the Project was last updated'),
  ];
  $spec['allow_more'] = [
    'name'         => 'allow_more',
    'title'        => E::ts('Allow more'),
    'type'         => CRM_Utils_Type::T_BOOLEAN,
    'api.required' => 0,
    'description'  => E::ts('Allow to donate more than is defined in the target'),
    'api.default'  => TRUE,
  ];
  $spec['identifier'] = [
    'name'         => 'identifier',
    'title'        => E::ts('Project Identifier'),
    'type'         => CRM_Utils_Type::T_STRING,
    'api.required' => 0,
    'description'  => E::ts('Unique identifier of a Project')
  ];
  $spec['project_target'] = [
    'name'         => 'project_target',
    'title'        => E::ts('Project Target'),
    'type'         => CRM_Utils_Type::T_MONEY,
    'api.required' => 0,
    'description'  => E::ts('Financial Target of a Project'),
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

  $project = new TwingleProject($params, 'TWINGLE');

  if (!$project->exists()) {
    $returnValues = $project->create();
  }

  return civicrm_api3_create_success($returnValues, $params, 'TwingleProject', 'Create');
}
