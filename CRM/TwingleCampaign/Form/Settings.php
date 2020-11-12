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
      E::ts('XCM Profile'),
      $this->getXCMProfiles()
    );

    $this->addButtons([
      [
        'type'      => 'submit',
        'name'      => E::ts('Save'),
        'isDefault' => TRUE,
      ]
    ]);

    parent::buildQuickForm();
  }

  public function setDefaultValues() {
    $defaultValues['twingle_api_key'] =
      Civi::settings()->get('twingle_api_key');
    $defaultValues['twinglecampaign_xcm_profile'] =
      Civi::settings()->get('twinglecampaign_xcm_profile');
    return $defaultValues;
  }

  //TODO: validate Twingle API key

  public function postProcess() {
    $values = $this->exportValues();
    Civi::settings()->set('twingle_api_key', $values['twingle_api_key']);
    Civi::settings()->set('twinglecampaign_xcm_profile', $values['twinglecampaign_xcm_profile']);
    parent::postProcess();
  }

  /**
   * Retrieves XCM profiles (if supported). 'default' profile is always
   * available
   *
   * @return array
   */
  public function getXCMProfiles() {
    $xcmProfiles = [];
    if (!isset($this->_settings['twinglecampaign_xcm_profile'])) {
      if (method_exists('CRM_Xcm_Configuration', 'getProfileList')) {
        $profiles = CRM_Xcm_Configuration::getProfileList();
        foreach ($profiles as $profile_key => $profile_name) {
          $xcmProfiles[$profile_key] = $profile_name;
        }
      }
    }
    return $xcmProfiles;
  }
}