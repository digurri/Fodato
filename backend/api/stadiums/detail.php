<?php
require_once '../../models/StadiumsModel.php';
header('Content-Type: application/json; charset=utf-8');

try {
    // GET -> id 값 추출 (없으면 0)
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;

    // id 값 유효성 검사 (1 미만, 10 초과시 오류처리)
    if ($id <= 0 || $id > 10) {
        // 요청 파라미터 검증 오류
        http_response_code(400);
        echo json_encode(['message' => '유효하지 않은 경기장 ID입니다.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $model = new StadiumsModel();
    $result = $model->getStadiumDetail($id);

    if ($result) {
        // 데이터 조회 성공
        echo json_encode(['message' => "경기장 상세 조회에 성공하였습니다.", 'stadium' => $result], JSON_UNESCAPED_UNICODE);
    } else {
        // 데이터 없음 (404)
        http_response_code(404);
        echo json_encode(['message' => "해당 경기장을 찾을 수 없습니다."], JSON_UNESCAPED_UNICODE);
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
