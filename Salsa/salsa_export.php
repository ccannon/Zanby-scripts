#!/usr/bin/env php
<?php         
/**
* @desc Script for syncing zanby and salsa users. It reseives data from local database and save it into Salsa CRM. 
* Prefered place of executing is database server.
* Period: zanbylab - each 5 minutes, production - each day. 
*/

ini_set('memory_limit', '550M');
set_time_limit(18000);

system('/usr/bin/php ' . dirname( __FILE__) . DIRECTORY_SEPARATOR . 'surveygizmo_sync.php');

define('TURN_OFF_DEBUG', 1);
                                                                                                                              
require_once realpath(dirname( __FILE__).DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'init/Initializing.php');
$application->bootstrap(array('FileCache', 'Defines', 'Databases', 'CssJsImagesPaths', 'MVCPaths'));
require_once 'Console/Getopt.php'; 
require_once "ZCCF/Salsa/API.php";
require_once "ZCCF/Salsa/Export.php";

$cg = new Console_Getopt();

$allowedShortOptions = "";
$allowedLongOptions = array( "salsaURL=", "salsaUser=", "salsaPass=", "help");
$args = $cg->readPHPArgv();
$ret = $cg->getopt( $args, $allowedShortOptions, $allowedLongOptions);
if ( PEAR::isError( $ret)) die ("Error in command line: " . $ret->getMessage() . "\n");

$cache = Warecorp_Cache::getFileCache();
if ( !$cfgSalsa = $cache->load('cfg_salsa_xml') ) {
    $cfgLoader = Warecorp_Config_Loader::getInstance();
    $cfgSalsa   = $cfgLoader->getAppConfig('cfg.credentials.xml')->salsa;
}

$salsaURL = isset($cfgSalsa->url) ? $cfgSalsa->url : "";
$salsaUser = isset($cfgSalsa->login) ? $cfgSalsa->login : "";
$salsaPass = isset($cfgSalsa->password) ? $cfgSalsa->password : "";
//        


$opts = $ret[0];
if ( sizeof($opts) > 0 ) {
    foreach ($opts as $o) {
        switch ($o[0]) {
            case '--salsaURL':
                if (isset($o[1])) $salsaURL = $o[1];
                break;
            case '--salsaUser':
                if (isset($o[1])) $salsaUser = $o[1];
                break;
            case '--salsaPass':
                if (isset($o[1])) $salsaPass = $o[1];
                break;
            case '--help':
                echo "Options:\n  --salsaURL=url\t\t\tSet Salsa base URL\n  --salsaUser=user\t\t\tSet Salsa manager e-mail\n  --salsaPass=password\t\t\tSet Salsa manager password\n  --help\t\t\tPring this help\n";
                exit;
        }
    }
}


if (empty($salsaURL) || empty($salsaUser) || empty($salsaPass)) {
	throw new Exception ('Salsa account data is undefined');
}

ZCCF_Salsa_Export::setContext('zccf');

ZCCF_Salsa_Export::exportData($salsaURL, $salsaUser, $salsaPass);
