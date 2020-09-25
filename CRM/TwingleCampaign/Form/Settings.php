<?php

use CRM_Twingle_ExtensionUtil as E;

/**
 * Form controller class
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/quickform/
 */
class CRM_TwingleCampaign_Form_Settings extends CRM_Admin_Form_Setting {

  protected $_settings = [
    'twingle_api_key'      => CRM_Core_BAO_Setting::SYSTEM_PREFERENCES_NAME,
    'twingle_request_size' => CRM_Core_BAO_Setting::SYSTEM_PREFERENCES_NAME,
  ];

  public function buildQuickForm() {
    parent::buildQuickForm();
    $this->assign('elementNames', array_keys($this->_settings));
  }

}