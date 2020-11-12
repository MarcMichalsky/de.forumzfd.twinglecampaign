<?php

use CRM_TwingleCampaign_ExtensionUtil as E;

/**
 * Form controller class
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/quickform/
 */
class CRM_TwingleCampaign_Form_Settings extends CRM_Core_Form {

  protected $_settings = NULL;

  public function buildQuickForm() {

    $this->addElement('text',
      'twingle_api_key',
      E::ts('Twingle API key')
    );

    $this->addElement('select',
      'twinglecampaign_xcm_profile',
      E::ts('XCM Profile to match event initiator'),
      $this->getXCMProfiles(),
      ['class' => 'crm-select2 huge']
    );

    $this->addElement('select',
      'twinglecampaign_start_case',
      E::ts('Start a case for event initiator'),
      $this->getCaseTypes(),
      ['class' => 'crm-select2 huge']
    );

    $this->addButtons([
      [
        'type'      => 'submit',
        'name'      => E::ts('Save'),
        'isDefault' => TRUE,
      ],
    ]);

    parent::buildQuickForm();
  }

  public function setDefaultValues() {
    $defaultValues['twingle_api_key'] =
      Civi::settings()->get('twingle_api_key');
    $defaultValues['twinglecampaign_xcm_profile'] =
      Civi::settings()->get('twinglecampaign_xcm_profile');
    $defaultValues['twinglecampaign_start_case'] =
      Civi::settings()->get('twinglecampaign_start_case');
    return $defaultValues;
  }

  //TODO: validate Twingle API key

  public function postProcess() {
    $values = $this->exportValues();
    Civi::settings()->set('twingle_api_key', $values['twingle_api_key']);
    Civi::settings()
      ->set('twinglecampaign_xcm_profile', $values['twinglecampaign_xcm_profile']);
    Civi::settings()
      ->set('twinglecampaign_start_case', $values['twinglecampaign_start_case']);
    parent::postProcess();
  }

  /**
   * Retrieves XCM profiles (if supported). 'default' profile is always
   * available
   *
   * @return array
   */
  private function getXCMProfiles() {
    $xcmProfiles = [];
    if (method_exists('CRM_Xcm_Configuration', 'getProfileList')) {
      $profiles = CRM_Xcm_Configuration::getProfileList();
      foreach ($profiles as $profile_key => $profile_name) {
        $xcmProfiles[$profile_key] = $profile_name;
      }
    }
    return $xcmProfiles;
  }

  /**
   * Retrieves all case types
   *
   * @return array
   */
  private function getCaseTypes() {
    $caseTypes = [NULL => E::ts('none')];
    try {
      $result = civicrm_api3('CaseType', 'get', [
        'sequential' => 1,
      ]);
      if (is_array($result['values'])) {
        foreach ($result['values'] as $case) {
          $caseTypes[$case['name']] = $case['title'];
        }
      }
    } catch (CiviCRM_API3_Exception $e) {
      Civi::log()->error(
        'TwingleCampaign could not retrieve case types: ' .
        $e->getMessage());
    }
    return $caseTypes;
  }

}

