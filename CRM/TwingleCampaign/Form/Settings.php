<?php

use CRM_TwingleCampaign_BAO_Configuration as Configuration;
use CRM_TwingleCampaign_ExtensionUtil as E;

include_once E::path() . '/CRM/TwingleCampaign/BAO/Configuration.php';

/**
 * Form controller class
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/quickform/
 */
class CRM_TwingleCampaign_Form_Settings extends CRM_Core_Form {

  protected $_settings = NULL;

  public function buildQuickForm() {

    $this->addElement(
      'text',
      'twingle_api_key',
      E::ts('Twingle API key')
    );

    $this->addElement(
      'select',
      'twinglecampaign_xcm_profile',
      E::ts('XCM Profile to match event initiators'),
      $this->getXCMProfiles(),
      ['class' => 'crm-select2 huge']
    );

    $this->addElement(
      'select',
      'twinglecampaign_start_case',
      E::ts('Start a case for event initiators'),
      $this->getCaseTypes(),
      ['class' => 'crm-select2 huge']
    );

    $this->addElement(
      'checkbox',
      'twinglecampaign_soft_credits',
      E::ts('Create soft credits for event initiators'),
      FALSE
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
    return Configuration::get();
  }

  //TODO: validate Twingle API key

  public function postProcess() {

    // Delete api key from cache
    Civi::cache()->delete('twinglecampaign_twingle_api');

    // Set configuration values
    Configuration::set($this->exportValues());
    parent::postProcess();
  }

  /**
   * Retrieves XCM profiles (if supported). 'default' profile is always
   * available
   *
   * @return array
   */
  private function getXCMProfiles(): array {
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
  private function getCaseTypes(): array {
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
        E::LONG_NAME . ' could not retrieve case types: ' .
        $e->getMessage());
    }
    return $caseTypes;
  }

}

