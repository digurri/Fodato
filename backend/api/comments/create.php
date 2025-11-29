<?php
// written by 2040042 Sarang Kim
// backend/api/comments/create.php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

include_once '../../config/db.php';
include_once '../../models/CommentsModel.php';

session_start();

try {
    $database = new Database();
    $db = $database->getConnection();
    $commentsModel = new CommentsModel($db);

    $data = json_decode(file_get_contents("php://input"));

    if (!empty($data->match_id) && !empty($data->content)) {
        
        $current_session_id = session_id();

        $team_id = isset($data->team_id) ? $data->team_id : null;
        $player_id = isset($data->player_id) ? $data->player_id : null;

        if ($commentsModel->createComment($data->match_id, $data->content, $current_session_id, $team_id, $player_id)) {
            http_response_code(201);
            echo json_encode(array("message" => "댓글이 등록되었습니다."));
        } else {
            throw new Exception("댓글 저장 실패");
        }
    } else {
        http_response_code(400); 
        echo json_encode(array("message" => "댓글 내용이 비어있습니다."));
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array("message" => "내부 서버 에러: " . $e->getMessage()));
}
?>