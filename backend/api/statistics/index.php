<?php
require_once '../../models/StatisticsModel.php';
header('Content-Type: application/json; charset=utf-8');

try {
    $model = new StatisticsModel();
    $result = $model->getAllAggregatedData();

    if ($result) {
        // 데이터 조회 성공
        echo json_encode(['message' => "KBO 통계 조회에 성공했습니다.", 'result' => $result], JSON_UNESCAPED_UNICODE);
    } else {
        // 데이터 없음 (404)
        http_response_code(404);
        echo json_encode(['message' => "KBO 통계 데이터가 없습니다."], JSON_UNESCAPED_UNICODE);
    }
} catch (PDOException $e) {
    // db 오류 (500)
    error_log("DB 오류: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['message' => "데이터베이스 처리 중 오류가 발생했습니다."], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    // 서버 오류 (500)
    error_log("서버 오류: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['message' => "서버 내부 오류가 발생했습니다."], JSON_UNESCAPED_UNICODE);
}
exit;
?>
