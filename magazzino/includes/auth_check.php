<?php
/*
 * @Author: gabriele.riva 
 * @Date: 2025-10-20 16:45:58 
 * @Last Modified by: gabriele.riva
 * @Last Modified time: 2025-10-20 16:46:22
*/

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: /magazzino/login.php');
    exit;
}
?>