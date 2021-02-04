<?php

use CRM_TwingleCampaign_BAO_TwingleEvent as TwingleEvent;
use CRM_TwingleCampaign_ExtensionUtil as E;

/**
 * TwingleEvent.Delete API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/api-architecture/
 */
function _civicrm_api3_twingle_event_Delete_spec(array &$spec) {
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
  $spec['event_id'] = [
    'name'         => 'event_id',
    'title'        => E::ts('Twingle Event ID'),
    'type'         => CRM_Utils_Type::T_INT,
    'api.required' => 0,
    'description'  => E::ts('Twingle ID for this Event'),
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
 * # TwingleEvent.Delete API
 * Delete one or more TwingleEvent campaigns.
 * * If you provide an **id** or **event_id** parameter, only one event will be
 * deleted.
 * * If you provide a **project_id** as parameter, all events of that project
 * will be deleted.
 * *NOTE:* This API won't delete TwingleEvents on Twingle's side
 *
 * @param array $params
 *
 * @return array
 *   API result descriptor
 *
 * @throws \CiviCRM_API3_Exception
 * @see civicrm_api3_create_success
 */
function civicrm_api3_twingle_event_Delete(array $params): array {

  $result_values = [];
  $events = [];
  $errors_occurred = 0;

  $params['sequential'] = 1;

  // Get one or more events from the TwingleEvent.get api depending on the
  // provided parameters
  if ($params['id']) {
    $events[] = civicrm_api3('TwingleEvent', 'getsingle',
      [$params['id']])['values'];
  }
  elseif ($params['event_id']) {
    $events[] = civicrm_api3('TwingleEvent', 'getsingle',
      [$params['event_id']])['values'];
  }
  elseif ($params['project_id']) {
    $events = civicrm_api3('TwingleEvent', 'get',
      [$params['project_id']])['values'];
  }

  // Delete TwingleEvents
  foreach ($events as $event) {
    try {
      $delete_event = new TwingleEvent($event, $event['id']);
      try {
        if ($delete_event->delete()) {
          $result_values[] = $delete_event->getResponse('Event deleted');
        }
        else {
          $errors_occurred++;
          $result_values[] = $delete_event->getResponse('Could not delete Event');
        }
      } catch (CiviCRM_API3_Exception $e) {
        $errors_occurred++;
        $result_values[] = $delete_event->getResponse(
          'Could not delete Project: ' . $e->getMessage()
        );
      }
    } catch (Exception $e) {
      $event['status'] = 'Could not delete Event: ' . $e->getMessage();
      $result_values[] = $event;
    }
  }

  // Return results
  if ($errors_occurred > 0) {
    $errorMessage = $errors_occurred . ' of ' . count($events) .
      ' TwingleEvents could not get deleted.';
    return civicrm_api3_create_error(
      $errorMessage,
      $result_values
    );
  }
  else {
    return civicrm_api3_create_success(
      $result_values,
      $params,
      'TwingleEvent',
      'Delete'
    );
  }
}
