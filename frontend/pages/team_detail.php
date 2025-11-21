<?php
require_once '../config/database.php';
$db = getDB();

$pageTitle = "KBO 팀 상세";

$teamId = $_GET['id'] ?? 0;

if (!$teamId) {
    header('Location: teams.php');
    exit;
}

// 백엔드 API를 통해 팀 상세 정보 가져오기 (detail.php 사용)
$teamData = null;
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
// frontend/pages/에서 backend/로 가려면 3단계 위로 올라가야 함
$basePath = dirname(dirname(dirname($_SERVER['PHP_SELF'])));
$detailApiUrl = $protocol . '://' . $host . $basePath . '/backend/api/teams/detail.php?team_id=' . urlencode($teamId);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $detailApiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$detailResponse = curl_exec($ch);
$detailHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($detailResponse !== false && $detailHttpCode == 200) {
    $detailData = json_decode($detailResponse, true);
    if (isset($detailData['data']) && $detailData['data'] !== null) {
        $teamData = $detailData['data'];
    }
}

if (!$teamData) {
    header('Location: teams.php');
    exit;
}

// API 응답을 기존 코드와 호환되도록 변환
$team = [
    'id' => $teamData['team_id'],
    'team_id' => $teamData['team_id'],
    'name' => $teamData['name'],
    'region_name' => $teamData['region'],
];

$stats = [
    'total_matches' => $teamData['total_matches'] ?? 0,
    'finished_matches' => $teamData['completed_matches'] ?? 0,
    'today_matches' => $teamData['today_matches'] ?? 0,
];

// 백엔드 API를 통해 팀 선수 명단 가져오기 (players.php 사용)
$players = [];
$playersApiUrl = $protocol . '://' . $host . $basePath . '/backend/api/teams/players.php?team_id=' . urlencode($teamId);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $playersApiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$playersResponse = curl_exec($ch);
$playersHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($playersResponse !== false && $playersHttpCode == 200) {
    $playersData = json_decode($playersResponse, true);
    if (isset($playersData['data']) && is_array($playersData['data'])) {
        $players = $playersData['data'];
    }
}

include '../includes/header.php';
?>

<div class="team-detail">
    <h2><?php echo htmlspecialchars($team['name']); ?></h2>
    
    <div class="team-info-grid">
        <div class="info-card">
            <h4>기본 정보</h4>
            <table>
                <tr>
                    <th>지역</th>
                    <td><?php echo htmlspecialchars($team['region_name']); ?></td>
                </tr>
                <tr>
                    <th>총 경기</th>
                    <td><?php echo number_format($stats['total_matches']); ?>경기</td>
                </tr>
                <tr>
                    <th>완료 경기</th>
                    <td><?php echo number_format($stats['finished_matches']); ?>경기</td>
                </tr>
                <tr>
                    <th>오늘 경기</th>
                    <td><?php echo number_format($stats['today_matches']); ?>경기</td>
                </tr>
            </table>
        </div>
    </div>
    
    <div class="players-section">
        <h3>선수 명단 (<?php echo count($players); ?>명)</h3>
        <?php if (empty($players)): ?>
            <div class="no-data">
                <p>데이터 없음</p>
            </div>
        <?php else: ?>
            <div class="players-table-container">
                <table class="players-table">
                    <thead>
                        <tr>
                            <th>등번호</th>
                            <th>선수명</th>
                            <th>포지션</th>
                            <th>포지션 지표</th>
                            <th>생년월일</th>
                            <th>체격</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($players as $player): ?>
                            <tr>
                                <td><?php echo isset($player['uniform_number']) && $player['uniform_number'] > 0 ? '#' . $player['uniform_number'] : '-'; ?></td>
                                <td><strong><?php echo htmlspecialchars($player['name'] ?? ''); ?></strong></td>
                                <td><span class="position-badge"><?php echo htmlspecialchars($player['position'] ?? '-'); ?></span></td>
                                <td>
                                    <?php if (isset($player['stat']) && isset($player['stat']['label']) && isset($player['stat']['value'])): ?>
                                        <span class="position-stat">
                                            <span class="stat-label"><?php echo htmlspecialchars($player['stat']['label']); ?>:</span>
                                            <span class="stat-value"><?php echo htmlspecialchars($player['stat']['value']); ?></span>
                                        </span>
                                    <?php else: ?>
                                        <span class="no-stat">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo isset($player['birth_date']) && $player['birth_date'] ? htmlspecialchars($player['birth_date']) : '-'; ?></td>
                                <td>
                                    <?php 
                                    if (isset($player['physical_info']) && $player['physical_info']) {
                                        echo htmlspecialchars($player['physical_info']);
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="action-buttons">
        <a href="teams.php" class="btn btn-secondary">팀 목록</a>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

