<!-- 메인 페이지 -->

<?php
require_once '../config/database.php';
require_once '../config/config.php';
require_once '../helpers/match_helper.php';
require_once '../helpers/api_helper.php';

$db = getDB();

$pageTitle = "홈";

$todayMatches = [];
$apiBaseUrl = getApiBaseUrl();
$result = callApi($apiBaseUrl . '/matches/today.php', 2);

if ($result['success']) {
    $json = json_decode($result['response'], true);
    $todayMatches = $json['data'] ?? [];
}

$regionStats = [];
$analyticsResult = callApi($apiBaseUrl . '/matches/analytics.php', 2);

if ($analyticsResult['success']) {
    $json = json_decode($analyticsResult['response'], true);
    $rawStats = $json['data'] ?? [];

    $regionStats = array_filter($rawStats, fn($item) => ($item['region_name'] ?? '') !== 'Total');
}

// 지역 매핑 정보
$regionMap = [];
$regions = $db->query("SELECT * FROM regions ORDER BY name")->fetchAll();
foreach ($regions as $row) {
    $regionMap[$row['name']] = $row['id'];
}

include '../includes/header.php';
?>

<div class="hero-section">
    <h2>오늘의 KBO 매치업</h2>
    <p><?php echo date('Y년 m월 d일'); ?> 경기 일정</p>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <h3 class="title-spacing">오늘의 경기</h3>
        <p class="stat-number"><?php echo count($todayMatches); ?></p>
        <p class="stat-label">경기</p>
    </div>
    <div class="stat-card">
        <h3>지역별</h3>
        <?php if (empty($regionStats)): ?>
            <p style="color: #999; font-style: italic; text-align: center;">오늘 예정된 경기가 없습니다.</p>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 10px; align-items: center;">
                <?php foreach ($regionStats as $stat): 
                    $regionName = $stat['region_name'];
                    $regionId = $regionMap[$regionName] ?? null;
                    $todayMonth = date('Y-m');
                    if ($regionId !== null):
                ?>
                    <div style="display: flex; align-items: center; gap: 8px; justify-content: center;">
                        <span><?php echo htmlspecialchars($regionName); ?>: <strong><?php echo $stat['match_count']; ?>경기</strong></span>
                        <a href="matches.php?region=<?php echo urlencode($regionId); ?>&month=<?php echo urlencode($todayMonth); ?>" 
                           class="btn btn-detail"
                           style="padding: 6px 12px; font-size: 0.85rem; white-space: nowrap;"
                           title="클릭하여 <?php echo htmlspecialchars($regionName); ?> 지역 경기 상세보기">
                            상세보기
                        </a>
                    </div>
                <?php else: ?>
                    <div style="display: flex; align-items: center; justify-content: center;">
                        <span><?php echo htmlspecialchars($regionName); ?>: <strong><?php echo $stat['match_count']; ?>경기</strong></span>
                    </div>
                <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>


<div class="filter-section">
    <h3 class="title-spacing">필터</h3>
    <form method="GET" action="matches.php" class="filter-form">
        <select name="region">
            <option value="">전체 지역</option>
            <?php
            $regions = $db->query("SELECT * FROM regions ORDER BY name")->fetchAll();
            foreach ($regions as $region):
            ?>
                <option value="<?php echo $region['id']; ?>"><?php echo htmlspecialchars($region['name']); ?></option>
            <?php endforeach; ?>
        </select>
        
        <button type="submit">검색</button>
    </form>
</div>

<div class="matches-section">
    <h3 class="title-spacing">오늘의 경기 일정</h3>
    <?php if (empty($todayMatches)): ?>
        <p class="no-data">데이터 없음</p>
    <?php else: ?>
        <div class="matches-grid">
            <?php foreach ($todayMatches as $match): ?>
                <div class="match-card">
                    <div class="match-header">
                        <?php 
                        date_default_timezone_set('Asia/Seoul');
                        $today = date('Y-m-d');
                        $matchTime = $match['time'] ?? '';
                        $status = getMatchStatus($today, $matchTime);
                        $statusLabel = $status['label'];
                        $statusClass = $status['class'];
                        ?>
                        <span class="status-badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars($statusLabel); ?></span>
                    </div>
                    <div class="match-time">
                        <?php echo htmlspecialchars($match['time'] ?? ''); ?>
                    </div>
                    <div class="match-teams">
                        <div class="team home-team">
                            <strong><?php echo htmlspecialchars($match['home_team_name'] ?? ''); ?></strong>
                            <?php if ($status['status'] === 'finished' && isset($match['home_score']) && $match['home_score'] !== null): ?>
                                <span class="score"><?php echo $match['home_score']; ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="vs">VS</div>
                        <div class="team away-team">
                            <strong><?php echo htmlspecialchars($match['away_team_name'] ?? ''); ?></strong>
                            <?php if ($status['status'] === 'finished' && isset($match['away_score']) && $match['away_score'] !== null): ?>
                                <span class="score"><?php echo $match['away_score']; ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="match-info">
                        <p><strong>경기장:</strong> <?php echo htmlspecialchars($match['stadium_name'] ?? ''); ?></p>
                    </div>
                    <a href="match_detail.php?id=<?php echo $match['match_id'] ?? ''; ?>" class="btn-detail">상세보기</a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>


