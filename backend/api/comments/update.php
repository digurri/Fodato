<?php
// backend/api/comments/update.php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: PUT");

include_once '../../config/db.php';
include_once '../../models/CommentsModel.php';

session_start(); // 세션 시작

try {
    $database = new Database();
    $db = $database->getConnection();
    $commentsModel = new CommentsModel($db);

    $data = json_decode(file_get_contents("php://input"));

    if (!empty($data->comment_id) && !empty($data->content)) {
        
        // 1. 현재 내 세션 ID
        $current_session_id = session_id();

        // 2. DB에 저장된 그 댓글의 세션 ID 가져오기
        $db_session_id = $commentsModel->getSessionIdByCommentId($data->comment_id);

        // 3. [검증] 두 ID가 똑같은지 비교
        if ($db_session_id === $current_session_id) {
            // 일치하면 수정 진행
            if ($commentsModel->updateComment($data->comment_id, $data->content)) {
                http_response_code(200);
                echo json_encode(array("message" => "댓글이 수정되었습니다."));
            } else {
                throw new Exception("수정 실패");
            }
        } else {
            // 불일치: 내 댓글이 아님 -> 401 권한 없음 리턴
            http_response_code(401);
            echo json_encode(array("message" => "수정 권한이 없습니다. (세션 불일치)"));
        }
    } else {
        http_response_code(400);
        echo json_encode(array("message" => "데이터가 불충분합니다."));
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array("message" => "내부 서버 에러"));
}
?>