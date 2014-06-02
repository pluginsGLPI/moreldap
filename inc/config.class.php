<?php
/*
 * @version $Id: setup.php 44 2014-03-27 21:05:00Z Thierry Bugier $
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

 if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

class PluginMoreLdapConfig extends CommonDBTM {

	// Type reservation : https://forge.indepnet.net/projects/plugins/wiki/PluginTypesReservation
	// Reserved range   : none 
	const RESERVED_TYPE_RANGE_MIN = 0;
	const RESERVED_TYPE_RANGE_MAX = 0;
}