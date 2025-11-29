<!-- written by 2171090 SeungHyeon Lee -->
<?php

// 설정 관련 모음

// 1. 타임존 설정
date_default_timezone_set('Asia/Seoul');

// 2. URL 설정
define('API_BASE_URL', 'http://' . $_SERVER['HTTP_HOST'] . '/fodato/backend/api');

// 2. 데이터베이스
define('DB_HOST', 'localhost');
define('DB_NAME', 'team05');
define('DB_USER', 'team05'); 
define('DB_PASS', 'team05');
?>