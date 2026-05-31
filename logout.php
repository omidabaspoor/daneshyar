<?php
require_once __DIR__ . '/includes/functions.php';
logout_user();
redirect(BASE_URL . '/login.php');
