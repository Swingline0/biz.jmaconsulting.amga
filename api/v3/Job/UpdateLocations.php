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

function civicrm_api3_job_update_locations($params) {
  $con = getDbConn($params);
  $result = mysqli_query($con,"SELECT c.id as country_id, s.id as state_id, c.name as country, s.name as state, s.abbr, s.country_id as map FROM countries c LEFT JOIN locations s ON c.id = s.country_id");

 
  $countries = CRM_Core_PseudoConstant::country(FALSE, FALSE);
  while($row = mysqli_fetch_assoc($result)) {
    $count[$row['country_id']] = $row['country'];
    $map[$row['state_id']] = array($row['state'], $row['abbr'], $row['map']);
    $states[$row['state_id']] = $row['state'];
  }
  $country = array_diff($count, $countries);
  unset($country[1]); // This is for United States which is already present
  if (!empty($country)) {
    foreach ($country as $value) {
      $cDao = new CRM_Core_DAO_Country();
      $cDao->name = $value;
      $cDao->save();
      $cDao->free();
    }
  }

  $state = CRM_Core_PseudoConstant::stateProvince();
  $stateProvinces = array_diff(array_filter($states), $state);

}