<?php
/*
 * @version $Id: hook.php 36 2012-08-31 13:59:28Z dethegeek $
----------------------------------------------------------------------
MoreLDAP plugin for GLPI
----------------------------------------------------------------------

LICENSE

This file is part of MoreLDAP plugin.

MoreLDAP plugin is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

MoreLDAP plugin is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with MoreLDAP plugin; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
------------------------------------------------------------------------
@package   MoreLDAP
@author    the MoreLDAP plugin team
@copyright Copyright (c) 2014-2014 MoreLDAP plugin team
@license   GPLv2+
http://www.gnu.org/licenses/gpl.txt
@link      https://forge.indepnet.net/projects/moreldap
@link      http://www.glpi-project.org/
@since     2014
------------------------------------------------------------------------
*/
function plugin_moreldap_install() {
	
   global $DB;
   
   $oldVersion =  plugin_moreldap_getVersion();
   switch ($oldVersion) {
      case '0':
   	case '0.1':
   	   include_once(GLPI_ROOT . "/plugins/moreldap/install/install.php");
   	   plugin_moreldap_DatabaseInstall();
   	
   	case '1.1':
   	      	   
   }
   $query = "UPDATE `glpi_plugin_moreldap_config`
             SET `value`='" . PLUGIN_MORELDAP_VERSION ."'
             WHERE `name`='Version'";
   $DB->query($query) or die($DB->error());
   return true;
}

function plugin_moreldap_uninstall() {
	   include_once(GLPI_ROOT . "/plugins/moreldap/install/install.php");
	   plugin_moreldap_DatabaseUninstall();
}

/**
 * Hook to add more fields from LDAP
 *
 * @param $fields   array
 *
 * @return un tableau
 **/
function plugin_retrieve_more_field_from_ldap_moreldap($fields) {
   $pluginAuthLDAP = new PluginMoreldapAuthLDAP;
   
   
   // There is no way to know which LDAP will be used, so we have 
   // to retrieve all LDAP attributes in any LDAP server
   $result = $pluginAuthLDAP->find("location_enabled='Y'");
   
   if (is_array($result)) {
      foreach ($result as $attribute) {
         $fields[] = $attribute['location'];
      }
   }
   return $fields;
}

/**
 * Hook to add more data from ldap
 *
 * @param $datas   array
 *
 * @return un tableau
 **/
function plugin_retrieve_more_data_from_ldap_moreldap(array $fields) {
   $pluginAuthLDAP = new PluginMoreldapAuthLDAP;
   $authLDAP = new AuthLDAP();
   $user = new User();
   $user->getFromSSO($fields['name']);
   
   
   if (isset($fields["_ldap_result"][0]["physicaldeliveryofficename"][0])) {
      $fields['locations_id'] = Dropdown::importExternal('Location',
                                                        addslashes($fields["_ldap_result"][0]["physicaldeliveryofficename"][0]));
   }
   //die(print_r($fields));
   return $fields;
}

/**
 * 
 * Check if the plugin has already been installed
 * 
 */
function plugin_moreldap_getVersion() {
   
   global $DB;
   
   if (!TableExists('glpi_plugin_moreldap_config')) {
      return "0";
   } else {
      $query = "SELECT `name`, `value`
                FROM `glpi_plugin_moreldap_config` 
                WHERE `name`='Version'";
      $result = $DB->query($query) or die(__("Unable to upgrade the plugin.", "moreldap"));
      if ($DB->numrows($result) != 1) {
         die(__("Unable to upgrade the plugin.", "moreldap"));
      }
      $data = $DB->fetch_assoc($result);
      return $data['name'];
   }
    
}

