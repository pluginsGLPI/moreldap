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
         break;
   	case '0.1.1':
   	   $query = "ALTER TABLE `glpi_plugin_moreldap_authldaps`
             ADD COLUMN `entities_id` INT(11) NOT NULL default  '0',
   	       ADD COLUMN `is_recursive` INT(1) NOT NULL DEFAULT '0'";
   	   $DB->query($query) or die($DB->error());
         break;
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
   $result = $pluginAuthLDAP->find("");
   
   if (is_array($result)) {
      foreach ($result as $attribute) {
         // Explode multiple attributes for location hierarchy 
         $locationHierarchy = explode('>', $attribute['location']);
         foreach ($locationHierarchy as $locationSubAttribute) {
            $locationSubAttribute = trim($locationSubAttribute);
            $fields[$locationSubAttribute] = $locationSubAttribute;
         }
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
   $user = new User();
   if (!$user->getFromDBbyDn($fields['user_dn'])) {
      return $fields;
   }
   $pluginAuthLDAP = new PluginMoreldapAuthLDAP;
   $authLDAP = new AuthLDAP();

   // default : store locations outside of any entity
   $entityID = -1;

   if ($pluginAuthLDAP->getFromDBByQuery("WHERE `id`='" . $user->fields["auths_id"] . "'")) {
      
      $entityID = $pluginAuthLDAP->fields['entities_id'];
      
      $locationHierarchy = explode('>', $pluginAuthLDAP->fields['location']);
      $locationPath = array();
      $incompleteLocation = false;
      foreach ($locationHierarchy as $locationSubAttribute) {
         $locationSubAttribute = trim($locationSubAttribute);
         if (isset($fields['_ldap_result'][0][strtolower($locationSubAttribute)][0])) {
            $locationPath[] = $fields['_ldap_result'][0][strtolower($locationSubAttribute)][0];
         } else {
            $incompleteLocation = true;
         }
      }
      
      if ($incompleteLocation == false) {
         if ($pluginAuthLDAP->fields['location_enabled'] == 'Y') {
            $location = new Location;
            $locationAncestor = 0;
            $locationCompleteName = array();
            foreach ($locationPath as $locationItem) {
               $locationCompleteName[] = $locationItem;
               $locationItem = Toolbox::addslashes_deep(array(
                  'entities_id' => $entityID,
                  'name' => $locationItem,
                  'locations_id' => $locationAncestor,
                  'completename' => implode(' > ', $locationCompleteName),
                  'is_recursive' => $pluginAuthLDAP->fields['is_recursive'],
                  'comment'      => __("Created by MoreLDAP", "moreldap")
               ));
               $locationAncestor = $location->findID($locationItem);
               if ($locationAncestor == -1) {
                  // The location does not exists yet
                  $locationAncestor = $location->add($locationItem);
               } 
               if ($locationAncestor == false) {
                  // If a location could not be imported, then give up importing children items 
                  break;
               }
            }
            if ($locationAncestor != false) {
               $fields['locations_id'] = $locationAncestor;
            }
         } else {
            // If the location retrieval is disabled, enablig this line will erase the location for the user.
            // $fields['locations_id'] = 0;
         }
      } 

   }
   return $fields;
}

function plugin_moreldap_item_add_user($user) {
   Toolbox::logDebug($user);
   $pluginAuthLDAP = new PluginMoreldapAuthLDAP;
   $pluginAuthLDAP->getFromDBByQuery("WHERE `id`='" . $user->input["auths_id"] . "'");

   $field           = $pluginAuthLDAP->fields['location'];
   $ldap_connection = $user->input['_ldap_conn'];
   $userdn          = $user->input['user_dn'];
   $sr              = @ldap_read($ldap_connection, $userdn, "objectClass=*", array($field));
   $v               = AuthLDAP::get_entries_clean($ldap_connection, $sr);
   $locations_name  = $v[0][$field][0];
   $locations_name  = 'Formation > GLPI';

   //check if this location exist
   $location  = new location;
   $locations = $location->find("name = '$locations_name'");
   if (count($locations) > 0) {
      //get existing location
      $first_location = array_shift($locations);
      $locations_id   = $first_location['id'];

   } else {
      //create new location
      $new_location = array(
         'name'         => $locations_name,
         'comment'      => __("Created by MoreLDAP", "moreldap"),
         'entities_id'  => 0,
         'is_recursive' => 1
      );
      $locations_id = $location->add($new_location);
   }

   $user->update(array(
      'id'           => $user->getID(),
      'locations_id' => $locations_id,
   ));
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
      return $data['value'];
   }
    
}
