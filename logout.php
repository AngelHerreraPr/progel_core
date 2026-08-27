<?php
// Progel_core/logout.php
session_start();
session_destroy();
header("Location: login.php");
exit();
?>