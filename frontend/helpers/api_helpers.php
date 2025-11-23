<?php
/**
 * API 기본 URL을 반환
 * @param int $depth 경로 깊이 (pages: 3, includes: 2)
 * @return string API 기본 URL
 */
function getApiBaseUrl($depth = 3) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $basePath = '';
    for ($i = 0; $i < $depth; $i++) {
        $basePath = dirname($basePath ?: $_SERVER['PHP_SELF']);
    }
    return $protocol . '://' . $host . $basePath . '/backend/api';
}

/**
 * 지정한 API 호출
 * @param string $url 호출할 API URL
 * @param int $timeout 요청 타임아웃(초)
 * @return array 응답 본문, HTTP 코드, 성공 여부
 */

function callApi($url, $timeout = 10) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'response' => $response,
        'httpCode' => $httpCode,
        'success' => ($response !== false && $httpCode == 200)
    ];
}
?>

