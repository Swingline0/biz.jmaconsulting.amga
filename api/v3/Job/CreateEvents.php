<?php

define('FULL_TUITION', 'custom_128');
define('REG_REQ', 'custom_138');
define('PROGRAM_STATUS', 'custom_111');
define('APPROVAL_STATUS', 'custom_129');
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
  
  $result = mysqli_query($con,"SELECT *, program_type FROM programs p LEFT JOIN program_types t on p.program_type_id = t.id LIMIT 0, 5");
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
  $regReq = '';
  while($row = mysqli_fetch_assoc($result)) {
    $sql = CRM_Core_DAO::singleValueQuery("SELECT 1 FROM civicrm_event WHERE title = '{$row['program_code']}'");
    if ($sql) {
      continue;
    }
    // Create the location
    $country = array_flip(CRM_Core_PseudoConstant::country(FALSE, FALSE));
    $state = array_flip(CRM_Core_PseudoConstant::stateProvinceAbbreviation(FALSE, FALSE));
    $loc = array(
      'address' => array(
        'city' => $row['location'],
        'country' => $country[$row['country']],
        'state_province_id' => $state[$row['state']],
        'location_type_id' => 1,
      ), 
      'email' => array(
        'email' => $row['contact_person_email'],
        'location_type_id' => 1,             
      ),
      'phone' => array(
        'phone' => $row['contact_person_phone'],
        'location_type_id' => 1,
      ),
    );
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
      'max_participants' => $row['capacity'],
      'start_date' => $row['start_date'],
      'end_date' => $row['end_date'],
      'loc_block_id' => $locBlock['id'],
      FULL_TUITION => $row['price'], // May be modified to a price set later
      'description' => $row['program_detail'],
      PROGRAM_STATUS => $row['program_status'],
      APPROVAL_STATUS => $row['approval_status'],
      'registration_start_date' => $row['enrollment_start_date'],
    );
    if ($regReq) {
      $params[REG_REQ] = $regReq . CRM_Core_DAO::VALUE_SEPARATOR;
    }
    //$event = civicrm_api3('Event', 'create', $params);
  }

  exit;
}