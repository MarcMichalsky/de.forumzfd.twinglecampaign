<?php

use CRM_TwingleCampaign_BAO_Configuration as Configuration;
use CRM_TwingleCampaign_ExtensionUtil as E;

include_once E::path() . '/CRM/TwingleCampaign/BAO/Configuration.php';
include_once E::path() . '/CRM/TwingleCampaign/Utils/CaseTypes.php';

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
      'twinglecampaign_default_case',
      E::ts('Default case to open for event initiators'),
      getCaseTypes(),
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

    // Display message
    CRM_Utils_System::setUFMessage(E::ts('TwingleCampaign configuration saved'));
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

}

