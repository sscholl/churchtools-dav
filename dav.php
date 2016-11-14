<?php

ini_set('display_errors', 1);
error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING);

// set some constants to avoid exceptions
if (!defined('DEBUG_SELECT')) define ('DEBUG_SELECT', FALSE);

// get kOOL config and api
require_once('lib/sabre/kool_vcard.php');

include_once('system/includes/start.php');
require ("system/includes/constants.php");
include_once (INCLUDES."/functions.php");
includePlugins();
include_once (INCLUDES . "/db.php");


  ct_startTimer();

  $files_dir = DEFAULT_SITE;

  // which module is requested?
  $q = $q_orig = getVar("q", userLoggedIn() ? "churchhome" : getConf("site_startpage", "churchhome"));
  // Shortlink home for churchhome
  if ($q == "home") header("Location: ?q=churchhome");

  // ccli oauth handling
  if (getVar("oauth_token") && ($q == "churchhome"))
    header("Location: ?q=ccli&oauth_token=".getVar("oauth_token")."&oauth_verifier=".getVar("oauth_verifier"));

  // $currentModule is needed for class autoloading and maybe other include paths
  list ($currentModule) = explode('/', getVar("q")); // get first part of $q or churchcore
  if ($currentModule == "home") $currentModule = "churchhome";
  $embedded = getVar("embedded", false);

  $stations = null;
  $currentStation = null;
  $config = loadConfig();

// settings
date_default_timezone_set('Europe/Berlin');

/* Database */
try {
	//$dsn = 'mysql:dbname='.$mysql_db.';host='.$$mysql_server;
	//$pdo = new PDO('mysql:dbname=usrdb_vmfredbb_kool;host=localhost', $mysql_user, $mysql_pass);

	$base_url = getBaseUrl();
	  if ($config) {
	    if (db_connect()) {
	    }
	  }
	$pdo = $db_pdo;
} catch (PDOException $e) {
    echo 'Connection failed: ' . $e->getMessage();
}

$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);


//Mapping PHP errors to exceptions
function exception_error_handler($errno, $errstr, $errfile, $errline ) {
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
}
set_error_handler("exception_error_handler", E_ERROR);



// Autoloader
require_once ('lib/sabre/vendor/autoload.php');

// Backends
$authBackend      = new Sabre\DAV\Auth\Backend\kOOL($pdo);
$principalBackend = new Sabre\DAVACL\PrincipalBackend\kOOL($pdo);
$carddavBackend   = new Sabre\CardDAV\Backend\kOOL($pdo);
//$caldavBackend    = new Sabre\CalDAV\Backend\PDO($pdo);

// Setting up the directory tree //
$nodes = array(
    new Sabre\DAVACL\PrincipalCollection($principalBackend),
//    new Sabre\CalDAV\CalendarRootNode($authBackend, $caldavBackend),
    new Sabre\CardDAV\AddressBookRoot($principalBackend, $carddavBackend),
);

// The object tree needs in turn to be passed to the server class
$server = new Sabre\DAV\Server($nodes);

$BASE_URL = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] && $_SERVER['HTTPS'] != "off") ? "https://" : "http://";
$BASE_URL .= $_SERVER['SERVER_NAME'] . '/';
$server->setBaseUri(parse_url($BASE_URL, PHP_URL_PATH).basename(__FILE__).'/');


//$stmt = $db_pdo->prepare('SELECT email, password FROM cdb_person WHERE email="?"');
//$stmt->execute("regs@sdscholl.de");
//$result = $stmt->fetchAll();
//var_dump($result);


// Plugins
//$server->addPlugin(new Sabre\DAV\Auth\Plugin($authBackend,'kOOL CardDAV Server'));
$server->addPlugin(new Sabre\DAV\Browser\Plugin());
//$server->addPlugin(new Sabre\CalDAV\Plugin());
$server->addPlugin(new Sabre\CardDAV\Plugin());
//$server->addPlugin(new Sabre\DAVACL\Plugin());

// And off we go!
$server->exec();
