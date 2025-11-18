<?php
require_once '../../models/StadiumsModel.php';

header('Content-Type: application/json');

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid stadium id']);
    exit;
}

$model = new StadiumsModel();
$data = $model->getStadiumDetail($id);

if ($data) {
    echo json_encode(['success' => true, 'data' => $data]);
} else {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Stadium not found']);
}
?>