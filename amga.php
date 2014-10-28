<?php

require_once 'amga.civix.php';

/**
 * Implementation of hook_civicrm_config
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_config
 */
function amga_civicrm_config(&$config) {
  _amga_civix_civicrm_config($config);
}

/**
 * Implementation of hook_civicrm_xmlMenu
 *
 * @param $files array(string)
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_xmlMenu
 */
function amga_civicrm_xmlMenu(&$files) {
  _amga_civix_civicrm_xmlMenu($files);
}

/**
 * Implementation of hook_civicrm_install
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function amga_civicrm_install() {
  _amga_civix_civicrm_install();
}

/**
 * Implementation of hook_civicrm_uninstall
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_uninstall
 */
function amga_civicrm_uninstall() {
  _amga_civix_civicrm_uninstall();
}

/**
 * Implementation of hook_civicrm_enable
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function amga_civicrm_enable() {
  _amga_civix_civicrm_enable();
}

/**
 * Implementation of hook_civicrm_disable
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_disable
 */
function amga_civicrm_disable() {
  _amga_civix_civicrm_disable();
}

/**
 * Implementation of hook_civicrm_upgrade
 *
 * @param $op string, the type of operation being performed; 'check' or 'enqueue'
 * @param $queue CRM_Queue_Queue, (for 'enqueue') the modifiable list of pending up upgrade tasks
 *
 * @return mixed  based on op. for 'check', returns array(boolean) (TRUE if upgrades are pending)
 *                for 'enqueue', returns void
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_upgrade
 */
function amga_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _amga_civix_civicrm_upgrade($op, $queue);
}

function getDbConn($params) {

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

  return $con;

}