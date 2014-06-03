<?php
/*
 * @version $Id: hook.php 44 2014-03-27 21:05:00Z Dethegeek $
 LICENSE

  This file is part of the moreldap plugin.

 Order plugin is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 Order plugin is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with GLPI; along with moreldap. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 @package   moreldap
 @author    Dethegeek
 @since     2014
 ---------------------------------------------------------------------- */
function plugin_moreldap_install() {
	
   global $DB;
   
   $oldVersion =  plugin_moreldap_getVersion();
   switch ($oldVersion) {
   	case '0':
   	   include_once(GLPI_ROOT . "/plugins/moreldap/install/install.php");
   	   plugin_moreldap_cleanInstall();
   }
   $query = "UPDATE `glpi_plugin_moreldap_config`
             SET `value`='" . PLUGIN_MORELDAP_VERSION ."'
             WHERE `name`='Version'";
   $DB->query($query) or die($DB->error());
       return true;
}

function plugin_moreldap_uninstall() {
	return true;
}

/**
 * Hook to add more fields from LDAP
 *
 * @param $fields   array
 *
 * @return un tableau
 **/
function plugin_retrieve_more_field_from_ldap_moreldap($fields) {
   $fields[] = "PhysicalDeliveryOfficeName";
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
   if (isset($fields["_ldap_result"][0]["physicaldeliveryofficename"][0])) {
      $fields['locations_id'] = Dropdown::importExternal('Location',
                                                        addslashes($fields["_ldap_result"][0]["physicaldeliveryofficename"][0]));
   }

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
      return $data['Version'];
   }
    
}

