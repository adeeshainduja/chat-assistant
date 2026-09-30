<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

AdminAuth::logout();

header('Location: ' . ai_base_path() . '/admin/login.php');
exit;
