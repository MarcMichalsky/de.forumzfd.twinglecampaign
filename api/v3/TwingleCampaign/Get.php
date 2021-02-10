<?php

use CRM_TwingleCampaign_ExtensionUtil as E;
use CRM_TwingleCampaign_Utils_ExtensionCache as Cache;

/**
 * TwingleCampaign.Get API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/api-architecture/
 */
function _civicrm_api3_twingle_campaign_Get_spec(array &$spec) {
  $spec['id'] = [
    'name'         => 'id',
    'title'        => E::ts('Twingle Campaign ID'),
    'type'         => CRM_Utils_Type::T_INT,
    'api.required' => 0,
    'description'  => E::ts('The Twingle Campaign ID'),
  ];
  $spec['project_id'] = [
    'name'         => 'project_id',
    'title'        => E::ts('Parent Twingle Project ID'),
    'type'         => CRM_Utils_Type::T_INT,
    'api.required' => 0,
    'description'  => E::ts('Twingle ID of the parent TwingleProject'),
  ];
}

/**
 * TwingleCampaign.Get API
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
function civicrm_api3_twingle_campaign_Get(array $params): array {

  // filter parameters
  $allowed_params = [];
  _civicrm_api3_twingle_campaign_Get_spec($allowed_params);
  $params = array_intersect_key($params, $allowed_params);

  $returnValues = [];

  // If an id is provided, get a single project
  if ($params['id']) {
    $campaigns = civicrm_api3('Campaign',
      'get',
      ['id' => $params['id']]
    );
  }
  // If no id but a project_id is provided, get all TwingleCampaign children of
  // this TwingleProject
  elseif ($params['project_id']) {

    // Get campaign type id for TwingleCampaign
    $twingle_campaign_campaign_type_id =
      Cache::getInstance()
        ->getCampaigns()['campaign_types']['twingle_campaign']['id'];

    $project = civicrm_api3('TwingleProject',
      'get',
      ['project_id' => $params['project_id']]
    );

    $campaigns = civicrm_api3('Campaign',
      'get',
      [
        'parent_id' => $project['id'],
        'campaign_type_id' => $twingle_campaign_campaign_type_id
      ]
    );
  }

  // Translate custom fields
  if (!empty($campaigns)) {
    $custom_field_mapping_reverse = array_flip(Cache::getInstance()->getCustomFieldMapping());

    foreach ($campaigns['values'] as $campaign) {
      foreach ($campaign as $key => $value) {
        if (array_key_exists($key, $custom_field_mapping_reverse)) {
          $returnValues[$campaign['id']][$custom_field_mapping_reverse[$key]]
            = $value;
        }
        else {
          $returnValues[$campaign['id']][$key] = $value;
        }
      }
      foreach($returnValues[$campaign['id']] as $key => $value) {
        if ($key != 'twingle_campaign_id' && strpos($key, 'twingle_campaign_') === 0) {
          $returnValues[$campaign['id']][str_replace('twingle_campaign_', '', $key)]
            = $value;
          unset($returnValues[$campaign['id']][$key]);
        }
      }
    }

    return civicrm_api3_create_success($returnValues, $params, 'TwingleCampaign', 'Get');

  }

  return civicrm_api3_create_success($returnValues, $params, 'TwingleCampaign', 'Get');
}
