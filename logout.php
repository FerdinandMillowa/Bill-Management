<?php
require_once 'auth-helper.php';
logoutUser();
header("Location: index.php");
exit;
