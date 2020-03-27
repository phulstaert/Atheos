<?php

/*
*  Copyright (c) Codiad & Kent Safranski (codiad.com), distributed
*  as-is and without warranty under the MIT License. See
*  [root]/license.txt for more. This information must remain intact.
*/

//////////////////////////////////////////////////////////////////////
// Paths
//////////////////////////////////////////////////////////////////////

$path = $_POST['path'];

$rel = str_replace('/components/install/process.php', '', $_SERVER['REQUEST_URI']);

$workspace = $path . "/workspace";
$users = $path . "/data/users.json";
$projects = $path . "/data/projects.json";
$active = $path . "/data/active.json";
$config = $path . "/config.php";

//////////////////////////////////////////////////////////////////////
// Functions
//////////////////////////////////////////////////////////////////////

function saveFile($file, $data) {
	$write = fopen($file, 'w') or die("can't open file");
	fwrite($write, $data);
	fclose($write);
}

function saveJSON($file, $data) {
	$data = json_encode($data, JSON_PRETTY_PRINT);
	saveFile($file, $data);
}

function cleanUsername($username) {
	return preg_replace('#[^A-Za-z0-9'.preg_quote('-_@. ').']#', '', $username);
}

function isAbsPath($path) {
	return $path[0] === '/';
}

function cleanPath($path) {

	// prevent Poison Null Byte injections
	$path = str_replace(chr(0), '', $path);

	// prevent go out of the workspace
	while (strpos($path, '../') !== false) {
		$path = str_replace('../', '', $path);
	}

	return $path;
}

//////////////////////////////////////////////////////////////////////
// Verify no overwrites
//////////////////////////////////////////////////////////////////////

if (!file_exists($users) && !file_exists($projects) && !file_exists($active)) {
	//////////////////////////////////////////////////////////////////
	// Get POST responses
	//////////////////////////////////////////////////////////////////

	$username = cleanUsername($_POST['username']);
	$password = password_hash($_POST['password'], PASSWORD_DEFAULT);
	$project_name = $_POST['project_name'];
	if (isset($_POST['project_path'])) {
		$project_path = $_POST['project_path'];
	} else {
		$project_path = $project_name;
	}
	$timezone = $_POST['timezone'];

	//////////////////////////////////////////////////////////////////
	// Create Projects files
	//////////////////////////////////////////////////////////////////

	$project_path = cleanPath($project_path);

	if (!isAbsPath($project_path)) {
		$project_path = str_replace(" ", "_", preg_replace('/[^\w-\.]/', '', $project_path));
		mkdir($workspace . "/" . $project_path);
	} else {
		$project_path = cleanPath($project_path);
		if (substr($project_path, -1) == '/') {
			$project_path = substr($project_path, 0, strlen($project_path)-1);
		}
		if (!file_exists($project_path)) {
			if (!mkdir($project_path.'/', 0755, true)) {
				die("Unable to create Absolute Path");
			}
		} else {
			if (!is_writable($project_path) || !is_readable($project_path)) {
				die("No Read/Write Permission");
			}
		}
	}
	$project_data = array("name" => $project_name, "path" => $project_path);

	saveJSON($projects, array($project_data));

	//////////////////////////////////////////////////////////////////
	// Create Users file
	//////////////////////////////////////////////////////////////////

	$user_data = array(
		"username" => $username,
		"password" => $password,
		"activeProject" => $project_path,
		"permissions" => ["configure", "read", "write"],
		"userACL" => "full"
	);

	saveJSON($users, array($user_data));

	//////////////////////////////////////////////////////////////////
	// Create Active file
	//////////////////////////////////////////////////////////////////

	saveJSON($active, array(''));

	//////////////////////////////////////////////////////////////////
	// Create Config
	//////////////////////////////////////////////////////////////////


	$config_data = '<?php

/*
*  Copyright (c) Codiad & Kent Safranski (codiad.com), distributed
*  as-is and without warranty under the MIT License. See
*  [root]/license.txt for more. This information must remain intact.
*/

//////////////////////////////////////////////////////////////////
// CONFIG
//////////////////////////////////////////////////////////////////

// PATH TO CODIAD
define("BASE_PATH", "' . $path . '");

// BASE URL TO CODIAD (without trailing slash)
define("BASE_URL", "' . $_SERVER["HTTP_HOST"] . $rel . '");

// THEME : default, modern or clear (look at /themes)
define("THEME", "atheos");

// ABSOLUTE PATH
define("WHITEPATHS", BASE_PATH . ",/home");

// SESSIONS (e.g. 7200)
$cookie_lifetime = "0";

// TIMEZONE
date_default_timezone_set("' . $_POST['timezone'] . '");

// External Authentification
//define("AUTH_PATH", "/path/to/customauth.php");

//////////////////////////////////////////////////////////////////
// ** DO NOT EDIT CONFIG BELOW **
//////////////////////////////////////////////////////////////////

// PATHS
define("COMPONENTS", BASE_PATH . "/components");
define("PLUGINS", BASE_PATH . "/plugins");
define("THEMES", BASE_PATH . "/themes");
define("DATA", BASE_PATH . "/data");
define("WORKSPACE", BASE_PATH . "/workspace");

// URLS
define("WSURL", BASE_URL . "/workspace");

// Marketplace
//define("MARKETURL", "https://atheos.io/market/json");

// Update Check
define("UPDATEURL", "http://https://atheos.io/update?v={VER}&o={OS}&p={PHP}&w={WEB}&a={ACT}");
define("ARCHIVEURL", "https://github.com/Atheos/Atheos/archive/master.zip");
define("COMMITURL", "https://api.github.com/repos/Atheos/Atheos/commits");
	';

	saveFile($config, $config_data);

	echo("success");
}