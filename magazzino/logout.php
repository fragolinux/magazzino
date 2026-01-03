<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2025-10-20 16:50:47 
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2025-10-20 16:51:11
*/

session_start();
session_unset();
session_destroy();
header('Location: login.php');
exit;