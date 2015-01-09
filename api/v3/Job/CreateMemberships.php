<?php
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

function civicrm_api3_job_create_memberships($params) {
  $con = getDbConn($params);
  $result = mysqli_query($con,"SELECT membership_type, active FROM membership_types");

  while($row = mysqli_fetch_assoc($result)) {
    $check = civicrm_api3('membership_type', 'get', array('name' => $row['membership_type']));
    if (!empty($check['values'])) {
      continue;
    }
    $memParams = array(
      'name' => $row['membership_type'],
      'is_active' => $row['active'],
      'financial_type_id' => 2,
      'member_of_contact_id' => 1,
      'duration_unit' => 'year',
      'duration_interval' => '1',
      'period_type' => 'rolling',
      'domain_id' => 1,
    );
    civicrm_api3('membership_type', 'create', $memParams);
  }


  // Now create the memberships
  $memType = mysqli_query($con, "SELECT m.*, t.membership_type FROM memberships m LEFT JOIN membership_types t ON m.membership_type_id = t.id WHERE t.membership_type <> 'Unclassified'");
  while($row = mysqli_fetch_assoc($memType)) {
    // get the contact
    $contact = civicrm_api3('Contact', 'get', array('external_identifier' => $row['member_id']));
    if (!CRM_Utils_Array::value('id', $contact)) {
      continue;
    }
    $flag = FALSE;

    switch ($row['membership_type']) {
      case 'IFMGA via AMGA':
      case 'IFMGA via Reciprocal':
      case 'Certified Guide':
        $type = 1; // Professional Membership
        break;
      case 'Associate Member':
        // Check if member has been enrolled in any program
        $check = array(
          'contact_id' => $contact['id'],
          'is_active' => 1,
        );
        $parts = civicrm_api3('Participant', 'get', $check);
        if ($parts['count'] >= 1) {
          $flag = TRUE;
        }
        // Not enrolled in any program? Check for certifications
        if (!$flag) {
          $check['return.custom_131'] = 1;
          $cont = civicrm_api3('Contact', 'getsingle', $check);
          if (!empty($cont['custom_131'])) {
            $flag = TRUE;
          }
        }
        if ($flag) { // Member is enrolled in a program, or has a certification
          $type = 1; // Professional Membership
        }
        else {
          $type = 2; // Supporter Membership
        }
        break;
      case 'Individual Member':
      case 'Student Associate Member':
        // Check if member has been enrolled in any program
        $check = array(
          'contact_id' => $contact['id'],
          'is_active' => 1,
        );
        $parts = civicrm_api3('Participant', 'get', $check);
        if ($parts['count'] >= 1) {
          $flag = TRUE;
        }
        else {
          $type = 2; // Supporter Membership
        }
        // Not enrolled in any program? Check for accreditied business
        /* if (!$flag) { */
        /*   $curr = CRM_Core_DAO::getFieldValue('CRM_Contact_DAO_Contact', $contact['id'], 'employer_id'); */
        /*   if ($curr) { */
        /*     $flag = TRUE; */
        /*   } */
        /* } */
        if ($flag) {
          $type = 1; // Professional Membership
        }
        break;
      case 'Certified Single Pitch Instructor':
      case 'Certified Climbing Wall Instructor':
      case 'Certified Top Rope Site Manager':
      case 'IFMGA':
        $type = 1; // Professional Membership
        break;
      case 'Individual Member':
      case 'Inactive':
        // Check if member has been enrolled in any program
        $check = array(
          'contact_id' => $contact['id'],
          'is_active' => 1,
        );
        $parts = civicrm_api3('Participant', 'get', $check);
        if ($parts['count'] >= 1) {
          $flag = TRUE;
        }
        // Not enrolled in any program? Check for certifications
        if (!$flag) {
          $check['return.custom_131'] = 1;
          $cont = civicrm_api3('Contact', 'getsingle', $check);
          if (!empty($cont['custom_131'])) {
            $flag = TRUE;
          }
        }
        if ($flag) { // Member is enrolled in a program, or has a certification
          $type = 1; // Professional Membership
        }
        else {
          $type = 2; // Supporter Membership
        }
        break;
      default:
        break;
    }
    $member = array(
      'contact_id' => $contact['id'],
      'membership_type_id' => $type,
      'join_date' => $row['membership_join_date'],
      'end_date' => $row['membership_end_date'],
    );
    if (isset($row['newsletter_opt_out']) && strtolower($row['newsletter_opt_out']) == 'yes') {
      CRM_Core_DAO::setFieldValue('CRM_Contact_DAO_Contact', $contact['id'], 'is_opt_out', 1);
    }
    try{
      $members = civicrm_api3('Membership', 'create', $member);
    }
    catch (CiviCRM_API3_Exception $e) {
      // handle error here
      $errorMessage = $e->getMessage();
      $errorCode = $e->getErrorCode();
      $errorData = $e->getExtraParams();
      $errors = array('error' => $errorMessage, 'error_code' => $errorCode, 'error_data' => $errorData);
      CRM_Core_Error::debug_var( 'ERROR CAUGHT:', $errors );
    }
  }
}