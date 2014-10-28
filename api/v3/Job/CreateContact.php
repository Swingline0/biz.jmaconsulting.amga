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


/**
 * Process scheduled voice broadcasts
 *
 * @param array $params
 *
 * @return array
 */
function civicrm_api3_job_create_contact($params) {
  $con = getDbConn($params);

  // Proceed with import
  $result = mysqli_query($con,"SELECT * FROM members LIMIT 0, 5");
  $params = array('contact_type' => 'Individual');
  while($row = mysqli_fetch_assoc($result)) {
    //$params['external_identifier'] = $row['id'];
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
    $params['api.Address.create'] = array();
    if (!empty($params['contact_id'])) {
      $address = civicrm_api3('Address', 'get', $locParams);
      CRM_Core_Error::debug( '$address', $address );
      //if (!empty($phone['values'])) /* { */
      /*   foreach ($phone['values'] as $id => $p) { */
      /*     if ($p['location_type_id'] == 1 && CRM_Utils_Array::value('phone_type_id', $p) != 2) { */
      /*       $homePhoneID = $id; */
      /*     } */
      /*     if ($p['location_type_id'] == 1 && CRM_Utils_Array::value('phone_type_id', $p) == 2) { */
      /*       $mobilePhoneID = $id; */
      /*     } */
      /*     if ($p['location_type_id'] == 2) { */
      /*       $workPhoneID = $id; */
      /*     } */
      /*     if ($p['phone_type_id'] == 3) { */
      /*       $faxID = $id; */
      /*     } */
      /*   } */
      /* } */
    }
    $country = CRM_Core_PseudoConstant::country();
    CRM_Core_Error::debug( '$country', $country );
    $addressparams = array(
      'street_address' => $row['street_address'],
      'city' => $row['city'],
      'postal_code' => $row['postal_code'],
      'country' => '',
    );

    exit;



    CRM_Core_Error::debug( '$params', $params );
    //$contact = civicrm_api3('Contact', 'create', $params);
    //CRM_Core_Error::debug( '$result', $contact );
  }
  exit;
}