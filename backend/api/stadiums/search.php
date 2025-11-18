<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../../models/StadiumsModel.php';

header('Content-Type: application/json');

$query = isset($_GET['q']) ? $_GET['q'] : '';
$region_id = isset($_GET['region_id']) ? intval($_GET['region_id']) : null;
$id = isset($_GET['id']) ? intval($_GET['id']) : null;

$model = new StadiumsModel();
$result = $model->searchStadiums($query, $region_id, $id);

echo json_encode([
    'success' => true,
    'count' => count($result),
    'data' => $result
]);
?>