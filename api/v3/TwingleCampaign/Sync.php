<?php

use CRM_TwingleCampaign_ExtensionUtil as E;
use CRM_TwingleCampaign_BAO_TwingleCampaign as TwingleCampaign;

/**
 * TwingleCampaign.Sync API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/api-architecture/
 */
function _civicrm_api3_twingle_campaign_Sync_spec(array &$spec) {
  $spec['id'] = [
    'name'         => 'id',
    'title'        => E::ts('Twingle Campaign ID'),
    'type'         => CRM_Utils_Type::T_INT,
    'api.required' => 0,
    'description'  => E::ts('The Twingle Campaign ID'),
  ];
  $spec['project_id'] = [
    'name'         => 'parent_project_id',
    'title'        => E::ts('Parent Twingle Project ID'),
    'type'         => CRM_Utils_Type::T_INT,
    'api.required' => 0,
    'description'  => E::ts('Twingle ID of the parent TwingleProject'),
  ];
  $spec['parent_id'] = [
    'name'         => 'parent_id',
    'title'        => E::ts('Parent Project ID'),
    'type'         => CRM_Utils_Type::T_INT,
    'api.required' => 0,
    'description'  => E::ts('ID of the parent TwingleProject'),
  ];
}

/**
 * # TwingleCampaign.Sync API
 *
 * @param array $params
 *
 * @return array
 *   API result descriptor
 *
 * @throws \CiviCRM_API3_Exception
 * @see civicrm_api3_create_success
 */
function civicrm_api3_twingle_campaign_Sync(array $params): array {

  // filter parameters
  $allowed_params = [];
  _civicrm_api3_twingle_campaign_Sync_spec($allowed_params);
  $params = array_intersect_key($params, $allowed_params);

  // Get TwingleCampaigns
  $campaigns = civicrm_api3('TwingleCampaign', 'get', $params);

  $returnValues = [];
  $errors_occurred = 0;

  // Abort if TwingleProject does not have TingleCampaign children
  if ($campaigns['count'] == 0) {
    return civicrm_api3_create_success(
      $returnValues,
      $params,
      'TwingleCampaign',
      'Sync'
    );
  }

  if ($campaigns['is_error'] == 0) {

    // Instantiate and re-create TwingleCampaigns
    foreach ($campaigns['values'] as $campaign) {
      try {
        $campaign = new TwingleCampaign($campaign, $campaign['id']);
      } catch (CiviCRM_API3_Exception $e) {
        $errors_occurred++;
        $status = [
          "id"                => $campaign['id'],
          "name"              => $campaign['name'],
          "parent_project_id" => $campaign['parent_project_id'],
          "status"            => 'TwingleCampaign could not get instantiated',
        ];
        $returnValues[$campaign->getId()] = $status;
        Civi::log()->error(
          E::LONG_NAME .
          ' could not instantiate TwingleCampaign campaign',
          $status
        );
        continue;
      }
      try {
        $campaign->create(TRUE);
        $returnValues[$campaign->getId()] =
          $campaign->getResponse('TwingleCampaign updated');
      } catch (CiviCRM_API3_Exception $e) {
        $errors_occurred++;
        $returnValues[$campaign->getId()] =
          $campaign->getResponse(
            'TwingleCampaign update failed: ' .
            $e->getMessage()
          );
        Civi::log()->error(
          E::LONG_NAME .
          ' could not update TwingleCampaign campaign: ' .
          $e->getMessage(),
          $campaign->getResponse()
        );
      }
    }
  }
  else {
    return civicrm_api3_create_error(
      'Could not get TwingleCampaigns: ' .
      $campaigns['error_message'],
      $params
    );
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
      'TwingleCampaign',
      'Sync'
    );
  }
}