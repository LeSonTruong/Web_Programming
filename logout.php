<?php
ob_start(); // Bật buffer
session_start();
session_unset();
session_destroy();
header("Location: /");
ob_end_flush(); // Flush buffer
exit();
