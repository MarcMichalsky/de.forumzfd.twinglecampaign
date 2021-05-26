<?php

use CRM_TwingleCampaign_ExtensionUtil as E;

/**
 * Retrieves all case types
 *
 * @return array
 */
function getCaseTypes(): array {
  $caseTypes = [NULL => E::ts('none')];
  try {
    $result = civicrm_api3('CaseType', 'get', [
      'sequential' => 1,
      'options' => ['limit' => 0]
    ]);
    if (is_array($result['values'])) {
      foreach ($result['values'] as $case) {
        $caseTypes[$case['name']] = $case['title'];
      }
    }
  } catch (CiviCRM_API3_Exception $e) {
    Civi::log()->error(
      E::LONG_NAME . ' could not retrieve case types: ' .
      $e->getMessage());
  }
  return $caseTypes;
}
