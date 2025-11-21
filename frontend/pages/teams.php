<?php
require_once '../config/database.php';
$db = getDB();

$pageTitle = "KBO 팀 목록";

$teamFilter = $_GET['team'] ?? '';

// 백엔드 API를 통해 팀 목록 가져오기 (list.php 사용)
$teams = [];
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
// frontend/pages/에서 backend/로 가려면 3단계 위로 올라가야 함
$basePath = dirname(dirname(dirname($_SERVER['PHP_SELF'])));
$apiUrl = $protocol . '://' . $host . $basePath . '/backend/api/teams/list.php';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$apiResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($apiResponse !== false && $httpCode == 200) {
    $apiData = json_decode($apiResponse, true);
    if (isset($apiData['data']) && is_array($apiData['data'])) {
        $allTeams = $apiData['data'];
        
        // 팀 필터링 적용
        if ($teamFilter) {
            $teams = array_filter($allTeams, function($team) use ($teamFilter) {
                return ($team['team_id'] ?? '') == $teamFilter;
            });
        } else {
            $teams = $allTeams;
        }
    }
} else {
    // 디버깅: API 호출 실패 시 에러 정보 출력 (개발 중에만)
    error_log("Teams API 호출 실패 - URL: $apiUrl, HTTP Code: $httpCode, cURL Error: $curlError, Response: " . substr($apiResponse, 0, 200));
}

// 필터링 드롭다운용 전체 팀 목록 (필터링 전)
$allTeamsForDropdown = $allTeams ?? [];

include '../includes/header.php';
?>

<h2>KBO 팀 목록</h2>

<div class="filter-section">
    <form method="GET" action="teams.php" class="filter-form">
        <label>
            팀 선택:
            <select name="team" onchange="this.form.submit()">
                <option value="">전체 팀</option>
                <?php foreach ($allTeamsForDropdown as $team): ?>
                    <option value="<?php echo $team['team_id'] ?? ''; ?>"
                        <?php echo $teamFilter == ($team['team_id'] ?? '') ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($team['name'] ?? ''); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <?php if ($teamFilter): ?>
            <a href="teams.php" class="btn-reset">초기화</a>
        <?php endif; ?>
    </form>
</div>

<div class="teams-section">
    <?php if (empty($teams)): ?>
        <p class="no-data">데이터 없음</p>
        <?php if (isset($_GET['debug'])): ?>
            <div style="background: #f8f9fa; padding: 15px; border: 1px solid #ddd; margin: 20px 0; border-radius: 5px;">
                <h4>디버그 정보</h4>
                <p><strong>API URL:</strong> <?php echo htmlspecialchars($apiUrl ?? 'N/A'); ?></p>
                <p><strong>HTTP Code:</strong> <?php echo htmlspecialchars($httpCode ?? 'N/A'); ?></p>
                <p><strong>cURL Error:</strong> <?php echo htmlspecialchars($curlError ?? 'None'); ?></p>
                <p><strong>Response:</strong></p>
                <pre style="background: #fff; padding: 10px; border: 1px solid #ccc; overflow-x: auto; max-height: 300px;"><?php echo htmlspecialchars(substr($apiResponse ?? '', 0, 1000)); ?></pre>
                <p><strong>Teams Array:</strong></p>
                <pre style="background: #fff; padding: 10px; border: 1px solid #ccc; overflow-x: auto;"><?php print_r($teams); ?></pre>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="stadiums-grid">
            <?php foreach ($teams as $team): ?>
                <div class="stadium-card">
                    <h3><?php echo htmlspecialchars($team['name'] ?? ''); ?></h3>
                    <div class="stadium-badges">
                        <span class="region-badge"><?php echo htmlspecialchars($team['region'] ?? ''); ?></span>
                    </div>
                    <div class="stadium-info">
                        <p><strong>선수 수:</strong> <?php echo number_format($team['player_count'] ?? 0); ?>명</p>
                        <p><strong>경기 수:</strong> <?php echo number_format($team['match_count'] ?? 0); ?>경기</p>
                    </div>
                    <a href="team_detail.php?id=<?php echo $team['team_id'] ?? ''; ?>" class="btn-detail">팀 상세보기</a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>

