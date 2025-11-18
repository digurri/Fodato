<?php
// backend/api/comments/create.php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

include_once '../../config/db.php';
include_once '../../models/CommentsModel.php';

// 1. 세션 시작 (필수! 이걸 해야 session_id를 가져올 수 있음)
session_start();

try {
    // 2. DB 연결
    $database = new Database();
    $db = $database->getConnection();
    $commentsModel = new CommentsModel($db);

    // 3. 프론트에서 보낸 JSON 데이터 받기
    $data = json_decode(file_get_contents("php://input"));

    // 4. 필수 데이터 확인 (match_id, content)
    if (!empty($data->match_id) && !empty($data->content)) {
        
        // 5. 현재 사용자의 세션 ID 가져오기
        $current_session_id = session_id();

        // 6. 선택적 데이터 처리 (팀/선수 ID가 없으면 null)
        $team_id = isset($data->team_id) ? $data->team_id : null;
        $player_id = isset($data->player_id) ? $data->player_id : null;

        // 7. 모델에게 저장 요청
        if ($commentsModel->createComment($data->match_id, $data->content, $current_session_id, $team_id, $player_id)) {
            http_response_code(201); // 201 Created
            echo json_encode(array("message" => "댓글이 등록되었습니다."));
        } else {
            throw new Exception("댓글 저장 실패");
        }
    } else {
        http_response_code(400); // 400 Bad Request
        echo json_encode(array("message" => "댓글 내용이 비어있습니다."));
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array("message" => "내부 서버 에러: " . $e->getMessage()));
}
?>