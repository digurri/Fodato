<!-- written by 2171090 SeungHyeon Lee -->
<?php
/**
 * API 기본 URL을 반환
 * config/에 정의된 API_BASE_URL 상수를 사용함
 */
function getApiBaseUrl() {
    // 상수가 정의되어 있으면 그거 쓰고, 없으면 안전하게 기본 경로 반환
    if (defined('API_BASE_URL')) {
        return API_BASE_URL;
    }
    
    // 혹시 모를 폴백(Fallback) - 기본값
    return '/backend/api';
}

/**
 * API 호출 함수(CURL 사용)
 * @param string $url 호출할 API URL
 * @param int $timeout 요청 타임아웃(초)
 * @return array 응답 본문, HTTP 코드, 성공 여부
 */

function callApi($url, $timeout = 5) {
    $ch = curl_init();
    
    curl_setopt_array($ch,[
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => $timeout,
    ]);

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

