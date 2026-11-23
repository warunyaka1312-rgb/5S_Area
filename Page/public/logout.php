<?php
define('FIVE_S_NO_GUARD', true);
require_once dirname(__DIR__, 2).'/includes/config.php';
five_s_session();
$_SESSION = [];
if (session_status() === PHP_SESSION_ACTIVE) session_destroy();
header('Location: '.FIVE_S_BASE.'index.php'); exit;
