<?php
/**
 * Create the database tables for the first install of the plugin
 */
function plugin_moreldap_cleanInstall()
{
   global $DB;
   
   $query = "CREATE TABLE `glpi_plugin_moreldap_config` (
               `ID` int(11) NOT NULL auto_increment,
               `name` varchar(64) UNIQUE NOT NULL default '0',
               `value` varchar(250) NOT NULL default '',
               PRIMARY KEY  (`ID`)
            ) ENGINE=MyISAM
            DEFAULT
              CHARSET=utf8
              COLLATE=utf8_unicode_ci";
   $DB->query($query) or die($DB->error());
   
   $query = "INSERT INTO `glpi_plugin_moreldap_config`
             SET `name`='Version', `value`='" . PLUGIN_MORELDAP_VERSION ."'";
   $DB->query($query) or die($DB->error());
}