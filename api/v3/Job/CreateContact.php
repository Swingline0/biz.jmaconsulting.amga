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
function civicrm_api3_job_create_contact($params) {
  $con = getDbConn($params);

  // Proceed with import
  $result = mysqli_query($con,"SELECT * FROM members m LEFT JOIN notes n ON m.id = n.member_id");
  $params = array('contact_type' => 'Individual');
  while($row = mysqli_fetch_assoc($result)) {
    $params['external_identifier'] = $row['id'];
    $params['first_name'] = $row['first_name'];
    $params['last_name'] = $row['last_name'];
    $params['email'] = $row['email'];
    // check for dupes
    $dedupeParams = CRM_Dedupe_Finder::formatParams($params, 'Individual');
    $dupes = CRM_Dedupe_Finder::dupesByParams($dedupeParams, 'Individual');
    if (count($dupes) == 1) { // if a single dupe is found
      $params['contact_id'] = $dupes[0];
    } 
    elseif (count($dupes) > 1) { // if multiple dupes are found, check CMS to see which one has a UF match
      $dao = new CRM_Core_DAO_UFMatch();
      $dao->uf_name = $params['email'];
      if ($dao->find(TRUE)) {
        $params['contact_id'] = $dao->contact_id;
      }
      else { 
        $params['contact_id'] = $dupes[0]; // Still nothing found, then just update the first dupe
      }
    }
    // finished dupe checking
    // create contact
    if (!empty($params['contact_id'])) {
      $locParams = array(
        'contact_id' => $params['contact_id'],
        'location_type_id' => array('IN' => array(1,2)),
      );
      $phone = civicrm_api3('Phone', 'get', $locParams);
      if (!empty($phone['values'])) {
        foreach ($phone['values'] as $id => $p) {
          if ($p['location_type_id'] == 1 && CRM_Utils_Array::value('phone_type_id', $p) != 2) {
            $homePhoneID = $id;
          }
          if ($p['location_type_id'] == 1 && CRM_Utils_Array::value('phone_type_id', $p) == 2) {
            $mobilePhoneID = $id;
          }
          if ($p['location_type_id'] == 2) {
            $workPhoneID = $id;
          }
          if ($p['phone_type_id'] == 3) {
            $faxID = $id;
          }
        }
      }
    }
    // Location
    $count = 0;
    $params['api.Phone.create'] = array();
    if (!empty($row['home_phone'])) {
      $params['api.Phone.create'][$count] = array(
        'location_type_id' => 1,
        'phone' => $row['home_phone'],
      );
      if ($homePhoneID) {
        $params['api.Phone.create'][$count]['id'] = $homePhoneID;
      }
      $count++;
    }
    if (!empty($row['work_phone'])) {
      $params['api.Phone.create'][$count] = array(
        'location_type_id' => 2, 
        'phone' => $row['work_phone'],
        'phone_ext' => $row['work_phone_ext'],
      );
      if ($workPhoneID) {
        $params['api.Phone.create'][$count]['id'] = $workPhoneID;
      }
      $count++;
    }
    if (!empty($row['mobile_phone'])) {
      $params['api.Phone.create'][$count] = array(
        'phone_type_id' => 2, 
        'location_type_id' => 1, 
        'phone' => $row['mobile_phone'],
      );
      if ($mobilePhoneID) {
        $params['api.Phone.create'][$count]['id'] = $mobilePhoneID;
      }
      $count++;
    }
    if (!empty($row['fax'])) {
      $params['api.Phone.create'][$count] = array(
        'phone_type_id' => 3, 
        'location_type_id' => 1, 
        'phone' => $row['fax'],
      );
      if ($faxID) {
        $params['api.Phone.create'][$count]['id'] = $faxID;
      }
    }
    // Address
    if (!empty($params['contact_id'])) {
      $address = civicrm_api3('Address', 'get', $locParams);
      if (!empty($address['values']))  { 
        foreach ($address['values'] as $id => $p) {
          if ($p['location_type_id'] = 1) {
            $addId = $id;
          }
        }
      }
    }
    $country = CRM_Core_PseudoConstant::country();
    $params['api.Address.create'] = array(
      'street_address' => $row['street_address'],
      'city' => $row['city'],
      'postal_code' => $row['postal_code'],
      'country' => '',// Need to create them first
      'state_province_id' => '',// Need to create them first
    );
    if ($addId) {
      $params['api.Address.create']['id'] = $addId;
    }

    // Employer
    if ($row['employer']) {
      // Check for dupes
      $org = array(
        'organization_name' => $row['employer'], 
        'contact_type' => 'Organization',
      );
      $dedupeParams = CRM_Dedupe_Finder::formatParams($org, 'Organization');
      $dupes = CRM_Dedupe_Finder::dupesByParams($dedupeParams, 'Organization');
      if (count($dupes) >= 1) {
        $org['contact_id'] = $dupes[0];
      } 
      $employer = civicrm_api3('Contact', 'create', $org);
      $params['employer_id'] = $employer['id'];
    }
    // Notes
    if ($params['contact_id']) {
      $note = civicrm_api3('Note', 'get', array('entity_id' => $params['contact_id'], 'entity_table' => 'civicrm_contact'));
      if (!empty($note['values'])) {
        $noteId = $note['id'];
      }
    }
    $params['api.Note.create'] = array(
      'entity_table' => 'civicrm_contact', 
      'subject' => $row['topic'],
      'note' => $row['note'],
    );
    if ($noteId) {
      $params['api.Note.create']['id'] = $noteId;
    }
    // Website
    if ($params['contact_id']) {
      $webParams = array(
        'contact_id' => $params['contact_id'],
        'website_type_id' => array('IN' => array(1,3,4)),             
      );
      $website = civicrm_api3('Website', 'get', $webParams);
      if (!empty($website['values'])) {
        foreach ($website['values'] as $id => $web) {
          //TODO depending on how the website will be saved
        }
      }
    }
    // Gender & birthdate
    $gender = CRM_Core_OptionGroup::values('gender', TRUE);
    if ($row['gender']) {
      $params['gender_id'] = $gender[$row['gender']];
    }
    $params['birth_date'] = $row['birthdate']; 
    CRM_Core_Error::debug( '$params', $params );
    exit;
    
    $contact = civicrm_api3('Contact', 'create', $params);
    CRM_Core_Error::debug( '$contact', $contact );
    exit;
  }
  exit;
}