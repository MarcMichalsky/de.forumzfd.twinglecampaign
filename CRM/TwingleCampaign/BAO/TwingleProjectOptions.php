<?php


namespace CRM\TwingleCampaign\BAO;

use CRM_TwingleCampaign_ExtensionUtil as E;
use CRM_Utils_Array;
use Exception;

include_once E::path() . '/CRM/TwingleCampaign/BAO/CustomField.php';


class TwingleProjectOptions {

  private $values;


  /**
   * TwingleProjectOptions constructor.
   *
   * @param array $options
   * Result array of Twingle API call to
   * https://project.twingle.de/api/by-organisation/$project_id/options
   *
   * @param string $origin
   * Origin of the arrays. It can be one of two constants:
   * TwingleProject::TWINGLE|CIVICRM
   *
   * @throws Exception
   */
  public function __construct(array $options, string $origin) {

    // If values come from CiviCRM Campaign API
    if ($origin == TwingleProject::CIVICRM) {

      // Translate custom field names into Twingle field names
      TwingleProject::translateCustomFields($options, TwingleProject::OUT);

      // Format values
      self::formatValues($options, TwingleProject::OUT);

    }

    // Unset project options id
    unset($options['id']);

    // Set project values attribute
    $this->values = $options;
  }


  /**
   * Update an this object
   *
   * @param array $options
   * Array with values to update
   *
   * @throws Exception
   */
  public function update(array $options) {

    // Update values
    $this->values = array_merge($this->values, $options);

  }


  /**
   * Export values. Ensures that only those values will be exported which the
   * Twingle API expects.
   *
   * @return array
   * Array with all values to send to the Twingle API
   *
   * @throws Exception
   */
  public function export() {

    $values = $this->values;
    self::formatValues($values, TwingleProject::OUT);
    TwingleProject::translateKeys($values, TwingleProject::OUT);

    // Get Template for project options
    $project_options_template = TwingleProject::$templates['project_options'];

    // Replace array items which the Twingle API does not expect
    foreach ($values as $key => $value) {
      if (!key_exists($key, $project_options_template)) {
        unset($values[$key]);
      }
    }

    // Format project target format
    if (key_exists('has_projecttarget_as_money', $values)) {
      $values['has_projecttarget_as_money'] =
        $values['has_projecttarget_as_money'] ? 'in Euro' : 'percentage';
    }

    return $values;
  }


  /**
   * Translate values between CiviCRM Campaigns and Twingle
   *
   * @param array $values
   * array of which values shall be translated
   *
   * @param string $direction
   * TwingleProject::IN -> translate array values from Twingle to CiviCRM <br>
   * TwingleProject::OUT -> translate array values from CiviCRM to Twingle
   *
   * @throws Exception
   */
  public static function formatValues(array &$values, string $direction) {

    if ($direction == TwingleProject::IN) {

      // Change timestamp into DateTime string
      if ($values['last_update']) {
        $values['last_update'] =
          TwingleProject::getDateTime($values['last_update']);
      }

      // format donation rhythm
      if (is_array($values['donation_rhythm'])) {
        $tmp = [];
        foreach ($values['donation_rhythm'] as $key => $value) {
          if ($value) {
            $tmp[$key] = $key;
          }
        }
        $values['donation_rhythm'] = CRM_Utils_Array::implodePadded($tmp);
      }

      // Format contact fields
      if ($values['exclude_contact_fields']) {
        $possible_contact_fields =
          TwingleProject::$campaigns['custom_fields']
          ['twingle_project_exclude_contact_fields']['option_values'];

        $exclude_contact_fields = explode(
          ',',
          $values['exclude_contact_fields']
        );

        foreach ($exclude_contact_fields as $exclude_contact_field) {
          unset($possible_contact_fields[$exclude_contact_field]);
        }

        $values['exclude_contact_fields'] =
          CRM_Utils_Array::implodePadded($possible_contact_fields);
      }

      // Format languages
      if ($values['languages']) {
        $values['languages'] =
          CRM_Utils_Array::implodePadded(
            explode(
              ',',
              $values['languages']
            )
          );
      }

      // Format project target format
      if (key_exists('has_projecttarget_as_money', $values)) {
        $values['has_projecttarget_as_money'] =
          $values['has_projecttarget_as_money'] ? 'in Euro' : 'percentage';
      }
    }

    elseif ($direction == TwingleProject::OUT) {

      // Change DateTime string into timestamp
      $values['last_update'] =
        TwingleProject::getTimestamp($values['last_update']);

      // format donation rhythm
      if (is_array($values['donation_rhythm'])) {
        $tmp = [];
        foreach ($values['donation_rhythm'] as $key => $value) {
          if ($value) {
            $tmp[$key] = $key;
          }
        }
        $values['donation_rhythm'] = CRM_Utils_Array::implodePadded($tmp);
      }

      // Format contact fields
      if ($values['exclude_contact_fields']) {
        $possible_contact_fields =
          TwingleProject::$campaigns['custom_fields']
          ['twingle_project_exclude_contact_fields']['option_values'];

        $exclude_contact_fields = explode(
          ',',
          $values['exclude_contact_fields']
        );

        foreach ($exclude_contact_fields as $exclude_contact_field) {
          unset($possible_contact_fields[$exclude_contact_field]);
        }

        $values['exclude_contact_fields'] =
          CRM_Utils_Array::implodePadded($possible_contact_fields);
      }

      // Format languages
      if ($values['languages']) {
        $values['languages'] =
          CRM_Utils_Array::implodePadded(
            explode(
              ',',
              $values['languages']
            )
          );
      }

      // Cast project_target to integer
      $values['project_target'] = (int) $values['project_target'];

    }
    else {

      throw new Exception(
        "Invalid Parameter $direction for formatValues()"
      );
    }
  }

  /**
   * @return array
   */
  public function getValues(): array {
    return $this->values;
  }

  public function lastUpdate() {
    return $this->values['last_update'];
  }

}
