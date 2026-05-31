<?php
require_once __DIR__ . '/../includes/functions.php';
admin_logout();
redirect(BASE_URL . '/admin/login.php');
