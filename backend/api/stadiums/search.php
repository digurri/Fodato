<?php

require_once '../../models/StadiumsModel.php';
header('Content-Type: application/json; charset=utf-8');

try {
    // GET -> 쿼리, 지역, id 값 추출 (없으면 기본값 설정))
    $query = isset($_GET['q']) ? $_GET['q'] : '';
    $region_id = isset($_GET['region_id']) ? intval($_GET['region_id']) : null;
    $id = isset($_GET['id']) ? intval($_GET['id']) : null;

    $model = new StadiumsModel();
    $result = $model->searchStadiums($query, $region_id, $id);

    if (empty($result)) {
        // 데이터 없음 (404)
        http_response_code(404);
        echo json_encode(['message' => '해당하는 경기장이 없습니다.'], JSON_UNESCAPED_UNICODE);
    } else {
        // 데이터 조회 성공
        echo json_encode(['message' => '경기장 조회에 성공했습니다.', 'stadiums' => $result], JSON_UNESCAPED_UNICODE);
    }
} catch (PDOException $e) {
    // db 관련 오류 발생 (500)
    error_log("DB 오류: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['message' => '데이터베이스 처리 중 오류가 발생했습니다.'], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    // 서버 관련 오류 발생 (500)
    error_log("서버 오류: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['message' => '서버 내부 오류가 발생했습니다.'], JSON_UNESCAPED_UNICODE);
}
exit;
?>