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

