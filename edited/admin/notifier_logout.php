<?php
session_start();
unset($_SESSION['notifier_logged'], $_SESSION['notifier_id'], $_SESSION['notifier_username']);
session_write_close();
header('Location: notifier_login.php');
exit;
