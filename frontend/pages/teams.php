<!-- 팀 목록 페이지 -->

<?php
require_once '../config/database.php';
require_once '../helpers/api_helper.php';
$db = getDB();

$pageTitle = "KBO 팀 목록";

$teamFilter = $_GET['team'] ?? '';

$teams = [];
$apiBaseUrl = getApiBaseUrl(3);
$result = callApi($apiBaseUrl . '/teams/list.php');

if ($result['success']) {
    $apiData = json_decode($result['response'], true);
    if (isset($apiData['data']) && is_array($apiData['data'])) {
        $allTeams = $apiData['data'];
        
        if ($teamFilter) {
            $teams = array_filter($allTeams, function($team) use ($teamFilter) {
                return ($team['team_id'] ?? '') == $teamFilter;
            });
        } else {
            $teams = $allTeams;
        }
    }
}

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

