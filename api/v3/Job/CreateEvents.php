<?php

define('FULL_TUITION', 'custom_128');
define('REG_REQ', 'custom_138');
define('PROGRAM_STATUS', 'custom_111');
define('APPROVAL_STATUS', 'custom_129');
define('PROVIDER', 'custom_130');
define('TEMPLATE', 28);
// $Id$

/*
 +--------------------------------------------------------------------+
 | CiviCRM version 4.5                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2014                                |
 +--------------------------------------------------------------------+
 | This file is a part of CiviCRM.                                    |
 |                                                                    |
 | CiviCRM is free software; you can copy, modify, and distribute it  |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
 |                                                                    |
 | CiviCRM is distributed in the hope that it will be useful, but     |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License and the CiviCRM Licensing Exception along                  |
 | with this program; if not, contact CiviCRM LLC                     |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +--------------------------------------------------------------------+
*/

/**
 * new version of civicrm apis. See blog post at
 * http://civicrm.org/node/131
 * @todo Write sth
 *
 * @package CiviCRM_APIv3
 * @subpackage API_Job
 * @copyright CiviCRM LLC (c) 2004-2014
 * $Id: Contact.php 30879 2010-11-22 15:45:55Z shot $
 *
 */

function civicrm_api3_job_create_events($params) {
  $con = getDbConn($params);
  
  $result = mysqli_query($con,"SELECT *, program_type FROM programs p LEFT JOIN program_types t on p.program_type_id = t.id");
  $eventTypes = CRM_Core_OptionGroup::values('event_type', TRUE, FALSE, FALSE, NULL, 'label', FALSE);
  $mapping = array(
    'health_statement'         => 'health_statement',
    'liability_2011'           => 'aor',
    'liability'                => 'liability',
    'liability_mountaineering' => 'liability_mtnrng',
    'black_canyon'             => 'liability_blckcnyn',
    'north_cascade'            => 'liability_nrthcscds',
    'enrollment_agreement'     => 'enrollment',
    'rmnp'                     => 'liability_rpmp',
    'cpr'                      => 'cpr_card',
    'basic_first_aid'          => 'firstaid',
    'wfr'                      => 'wfr_card',
    'avalanche_level_2'        => 'av_lvl2',
    'avalanche_level_3'        => 'av_lvl3',
  );
  $status = array(
    'Cancelled'            => 'cancelled',
    'Enrolling'            => 'open',
    'EvaluationsPublished' => 'published',
    'Full'                 => 'closed',
  );
  $country = array_flip(CRM_Core_PseudoConstant::country(FALSE, FALSE));
  $state = array_flip(array_filter(CRM_Core_PseudoConstant::stateProvinceAbbreviation(FALSE, FALSE)));
  $count = 0;
  while($row = mysqli_fetch_assoc($result)) {
    $sql = CRM_Core_DAO::singleValueQuery("SELECT id FROM civicrm_event WHERE title = '{$row['program_code']}'");
    $regReq = '';
    // Create the location
    $loc = array(
      'address' => array(
        'city' => $row['location'],
        'country' => $country[$row['country']],
        'state_province_id' => $state[$row['state']],
        'location_type_id' => 1,
        'manual_geo_code' => 1,
      ),
    );
    if(!empty($row['contact_person_email']) && filter_var($row['contact_person_email'], FILTER_VALIDATE_EMAIL)) {
      $loc['email'] = array(
        'email' => $row['contact_person_email'],
        'location_type_id' => 1,             
      );
    }
    if(!empty($row['contact_person_phone'])) {
      $loc['phone'] = array(
        'phone' => $row['contact_person_phone'],
        'location_type_id' => 1,             
      );
    }
    $locBlock = civicrm_api3('LocBlock', 'create', $loc);
    // Construct the option values
    foreach ($mapping as $key => $value) {
      if (strtolower($row[$key]) == 'yes') {
        $regReq .= CRM_Core_DAO::VALUE_SEPARATOR . $value;
      }
    }
    $params = array(
      'title' => $row['program_code'],
      'event_type_id' => $eventTypes[$row['program_type']],
      'template_id' => TEMPLATE,
      'max_participants' => $row['capacity'],
      'start_date' => $row['start_date'],
      'end_date' => $row['end_date'],
      'loc_block_id' => $locBlock['id'],
      FULL_TUITION => round($row['price'], 2), // May be modified to a price set later
      'summary' => $row['program_detail'],
      PROGRAM_STATUS => $status[$row['program_status']],
      APPROVAL_STATUS => $row['approval_status'],
      'registration_start_date' => $row['enrollment_start_date'],
      'is_public' => 1,
      'is_active' => 1,
      'is_online_registration' => 1,
    );
    if ($sql) {
      $params['id'] = $sql;
    }
    if ($regReq) {
      $params[REG_REQ] = $regReq . CRM_Core_DAO::VALUE_SEPARATOR;
    }
    // Provider
    if (!empty($row['provider']) && $row['provider'] == 'Provider' && !empty($row['member_id'])) {
      $provider = civicrm_api3('Contact', 'get', array('external_identifier' => $row['member_id']));
      if (!empty($provider['values'])) {
        reset($provider['values']);
        $params[PROVIDER] = key($provider['values']);
      }
    }
    // Price Set
    if (!empty($row['price'])) {
      $params['is_monetary'] = 1;
      $n = strtolower(str_replace('-', '_', $row['program_code']));
      $p = CRM_Core_DAO::singleValueQuery("SELECT id FROM civicrm_price_set WHERE name = '$n'");
      $f = CRM_Core_DAO::singleValueQuery("SELECT id FROM civicrm_price_field WHERE name = '$n'");
      $v = CRM_Core_DAO::singleValueQuery("SELECT id FROM civicrm_price_field_value WHERE name = '$n'");
      $pset = array( 
        //name' => strtolower(str_replace('-', '_', $row['program_code'])),
        'title' => $row['program_code'] . '-' . $count,
        'is_active' => 1,
        'extends' => 1,
        'financial_type_id' => 4,
        'is_quick_config' => 1,
        'api.PriceField.create' => array(
          'price_set_id' => '$value.id',
          //'name' => strtolower(str_replace('-', '_', $row['program_code'])),
          'label' => $row['program_code'],
          'html_type' => 'Radio',
          'format.only_id' => 1,
        ),
        'api.PriceFieldValue.create' => array(
          'price_field_id' => '$value.api.PriceField.create',
          'amount' => $row['price'],
          'label' => $row['program_code'],
          'is_active' => 1,
        ),
        'api.PriceFieldValue.create' => array(
          'price_field_id' => '$value.api.PriceField.create',
          'amount' => '65.00',
          'label' => ts('Application Fee'),
          'is_active' => 1,
          'is_default' => 1,
        ),
      );
      if ($p) {
        $pset['id'] = $p; 
      }
      if ($f) {
        $pset['api.PriceField.create']['id'] = $f; 
      }
      if ($v) {
        $pset['api.PriceFieldValue.create']['id'] = $v; 
      }
      $price = civicrm_api3('PriceSet', 'create', $pset);
      $count++;
    }
    
    try{
      $event = civicrm_api3('Event', 'create', $params);
    }
    catch (CiviCRM_API3_Exception $e) {
      // handle error here
      $errorMessage = $e->getMessage();
      $errorCode = $e->getErrorCode();
      $errorData = $e->getExtraParams();
      $errors[] = array('error' => $errorMessage, 'error_code' => $errorCode, 'error_data' => $errorData);
      CRM_Core_Error::debug_var( 'ERROR CAUGHT:', $errors );
    }
    // Add an entry in price set entity table
    if (!empty($event['values'])) {
      CRM_Price_BAO_PriceSet::addTo('civicrm_event', $event['id'], $price['id']);
    }
  }
}