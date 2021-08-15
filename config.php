<?php
error_reporting(1);
ini_set('display_errors', 'On');

date_default_timezone_set('Asia/Kolkata');

$dbhost = 'localhost';
$dbuser = 'root';
$dbpass = '';
$dbname = 'parkingManagmentdb';

//Connect
$mysqli = new mysqli($dbhost, $dbuser, $dbpass, $dbname);
if (mysqli_connect_errno()) {
	printf("MySQLi connection failed: ", mysqli_connect_error());
	exit();
}

// Change character set to utf8
if (!$mysqli->set_charset('utf8')) {
	printf('Error loading character set utf8: %s\n', $mysqli->error);
}

// No of parking slot
define("PARKINGSLOT", '10');

// Category of user type
define("GERNAL_CATEGORY", 'gen');
define("PHYSICAL_HANDICAP", 'ph');
define("PREGNANT_WOMAN", 'pw');
define("IS_RESERVED_IDX", '1');
define("PARKING_ID_IDX", '0');

?>