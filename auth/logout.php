<?php
require_once __DIR__ . '/../config/session.php';

destroy_secure_session();
header('Location: ../index.php');
exit;
