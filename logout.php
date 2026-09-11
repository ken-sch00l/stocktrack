<?php
session_start();
session_destroy();
header("Location: /stocktrack/login.php");
exit();
