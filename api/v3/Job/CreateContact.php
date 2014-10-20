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
  if (empty($params['db_name'])) {
    return civicrm_api3_create_error('Please specify the database name from which you want to import members from AMGA legacy to CiviCRM contacts [e.g. db_name=amga]');
  }
  $db = $params['db_name'];
  $user = $params['user'];
  $password = $params['password'];
  $server = $params['host'];
  $con = mysqli_connect($server, $user, $password, $db);  

  if(mysqli_connect_errno()) {    
    return civicrm_api3_create_error('Cannot connect to server');  
  } 

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
    // FIXME: need to check if phone number present in CSV before creating array
    $params['api.Phone.create'] = array(
      array(
        'location_type_id' => 1, 
        'phone' => $row['home_phone'],
      ),
      array(
        'location_type_id' => 2, 
        'phone' => '123'.$row['work_phone'],
      ),
      array(
        'phone_type_id' => 2, 
        'location_type_id' => 1, 
        'phone' => 'qw'.$row['mobile_phone'],
      ),
    );
    // Address, website to follow
    $result = civicrm_api3('Contact', 'create', $params);
  }
  exit;
}