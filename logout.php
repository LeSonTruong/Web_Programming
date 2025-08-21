<?php
ob_start(); // Bật buffer
session_start();
session_unset();
session_destroy();
header("Location: login.php");
ob_end_flush(); // Flush buffer
exit();
