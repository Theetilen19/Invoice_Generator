<?php
//Error Reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

//SESSION
session_start();

//timezone
date_default_timezone_set('Africa/Nairobi');

//contants
define('BASE_URL', 'http://localhost:8000/'); // Project URL
define('COMPANY_NAME', 'Maser Tee'); 
define('COMPANY_SLOGAN', 'Your Trusted Partner in multi services');

?>