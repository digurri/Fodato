<?php
require_once '../../models/StatisticsModel.php';

header('Content-Type: application/json');

$match_id = isset($_GET['match_id']) ? intval($_GET['match_id']) : 0;

$model = new StatisticsModel();

if ($match_id > 0) {
    $data = $model->getStatisticsByMatchId($match_id);
    if ($data) {
        echo json_encode(['success' => true, 'data' => $data]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Statistics not found for this match']);
    }
} else {
    $data = $model->getAllStatistics();
    echo json_encode(['success' => true, 'count' => count($data), 'data' => $data]);
}
?>