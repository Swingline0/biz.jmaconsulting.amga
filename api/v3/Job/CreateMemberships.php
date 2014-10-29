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
    $memTypes= civicrm_api3('membership_type', 'create', $memParams);
  }


  // Now create the memberships
}