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
      case '0' :
      case '0.1' :
         include_once (GLPI_ROOT . "/plugins/moreldap/install/install.php");
         plugin_moreldap_DatabaseInstall();
         break;

      case '0.1.1' :
         $query = "ALTER TABLE `glpi_plugin_moreldap_authldaps`
             ADD COLUMN `entities_id` INT(11) NOT NULL default  '0',
   	       ADD COLUMN `is_recursive` INT(1) NOT NULL DEFAULT '0'";
         $DB->query($query) or die($DB->error());
         break;
   }
   $query = "UPDATE `glpi_plugin_moreldap_config`
             SET `value`='" . PLUGIN_MORELDAP_VERSION . "'
             WHERE `name`='Version'";
   $DB->query($query) or die($DB->error());
   return true;
}

function plugin_moreldap_uninstall() {
   include_once (GLPI_ROOT . "/plugins/moreldap/install/install.php");
   plugin_moreldap_DatabaseUninstall();
}

function plugin_moreldap_item_add_or_update_user($user) {

   //Ignore users without auths_id
   if (!isset($user->input["auths_id"])) return;
   
   // We update LDAP field only if LDAP directory is defined
   if (isset($user->input["locations_id"])) return;

   // default : store locations outside of any entity
   $entityID = -1;

   $pluginAuthLDAP = new PluginMoreldapAuthLDAP();
   $authsId = isset($user->input["auths_id"]) ? $user->input["auths_id"] : $user->fields["auths_id"];
   if ($authsId > 0 && $pluginAuthLDAP->getFromDBByQuery("WHERE `id`='$authsId'")) {

      // The target entity for the locations to be created
      $entityID = $pluginAuthLDAP->fields['entities_id'];

      // find from config all attributes to read from LDAP
      $fields = array();
      $locationHierarchy = explode('>', $pluginAuthLDAP->fields['location']);
      foreach ($locationHierarchy as $locationSubAttribute) {
         $locationSubAttribute = trim($locationSubAttribute);
         if (strlen($locationSubAttribute) > 0) {
            $fields[] = $locationSubAttribute;
         }
      }

      // LDAP query to read the needed attributes for the user
      $ldap_connection = 0;
      if (!isset($user->input["_ldap_conn"]) || !isset($user->fields["_ldap_conn"])) {
         $ldap = new AuthLDAP;
         $ldap->getFromDB($authsId);
         $ldap_connection = $ldap->connect();
      } else {
         $ldap_connection = isset($user->input["_ldap_conn"])
                              ? $user->input["_ldap_conn"]
                              : $user->fields["_ldap_conn"];
      }
      $userdn          = isset($user->input["user_dn"])
                                       ? $user->input["user_dn"]
                                       : $user->fields["user_dn"];
      $userdn          = str_replace('\\\\', '\\', $userdn);
      $sr              = @ldap_read($ldap_connection, $userdn, "objectClass=*", $fields);
      if (!is_resource($sr) || ldap_errno($ldap_connection) > 0) {
         return;
      }
      $v               = AuthLDAP::get_entries_clean($ldap_connection, $sr);

      //Find all locations needed to create the deepest one
      $locationPath = array();
      $incompleteLocation = false;
      foreach ($fields as $locationSubAttribute) {
         $locationSubAttribute = strtolower($locationSubAttribute);
         if (isset($v[0][$locationSubAttribute][0])) {
            $locationPath[] = $v[0][$locationSubAttribute][0];
         } else {
            // A LDAP attribute is not defined for the user. Cannot build the completename
            // Therefore we must giveup importing this location
            $incompleteLocation = true;
         }
      }

      // TODO : test if location import is enabled earlier in this function
      if ($pluginAuthLDAP->fields['location_enabled'] == 'Y') {
         if ($incompleteLocation == false) {
            $location = new Location();
            $locationAncestor = 0;
            $locationCompleteName = array();
            $allLocationsExist = true; // Assume we created or found all locations
            // while ($locatinItem = array_shift($locationPath) && $allLocationsExist) {
            foreach ($locationPath as $locationItem) {
               if ($allLocationsExist) {
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
                     // If a location could not be imported and does not exist
                     // then give up importing children items
                     $allLocationsExist = false;
                  }
               }
            }
            if ($allLocationsExist) {
               // All locations exist to match the path described un LDAP
               $locations_id = $locationAncestor;
               $myuser = new User; // new var to prevent user->input erasing (object are always passed by "reference")
               $myuser->update(array(
                     'id'           => $user->getID(),
                     'locations_id' => $locations_id,
               ));
            }
         }
      } else {
         // If the location retrieval is disabled, enablig this line will erase the location for the user.
         // $fields['locations_id'] = 0;
      }
   }
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
