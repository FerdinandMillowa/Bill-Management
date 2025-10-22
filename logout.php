<?php
require_once 'auth-helper.php';
logoutUser();
header("Location: login.php");
exit;
