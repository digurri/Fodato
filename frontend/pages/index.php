<!-- 메인 페이지 -->

<?php
require_once '../config/database.php';
require_once '../helpers/match_helper.php';
require_once '../helpers/api_helper.php';
$db = getDB();

$pageTitle = "홈";

$todayMatches = [];
$apiBaseUrl = getApiBaseUrl(3);
$result = callApi($apiBaseUrl . '/matches/today.php', 10);

if ($result['success']) {
    $apiData = json_decode($result['response'], true);
    if (isset($apiData['data']) && is_array($apiData['data'])) {
        $todayMatches = $apiData['data'];
    }
} else {
    // API 호출이 실패하면 DB에서 직접 조회하도록 처리
    try {
        $todayMatchesQuery = "
            SELECT 
                m.id AS match_id,
                m.time,
                m.status,
                IFNULL(ms.home_score, 0) AS home_score,
                IFNULL(ms.away_score, 0) AS away_score,
                ht.name AS home_team_name,
                at.name AS away_team_name,
                s.name AS stadium_name
            FROM matches m
            LEFT JOIN teams ht ON m.home_team_id = ht.id
            LEFT JOIN teams at ON m.away_team_id = at.id
            LEFT JOIN stadiums s ON m.stadium_id = s.id
            LEFT JOIN match_stat ms ON m.id = ms.match_id
            WHERE m.date = CURDATE()
            ORDER BY m.time ASC
        ";
        $todayMatchesStmt = $db->query($todayMatchesQuery);
        $todayMatches = $todayMatchesStmt->fetchAll();
    } catch (PDOException $e) {
        $todayMatches = [];
    }
}

$regionStats = [];
$result = callApi($apiBaseUrl . '/matches/analytics.php', 10);

if ($result['success']) {
    $analyticsData = json_decode($result['response'], true);
    if (isset($analyticsData['data']) && is_array($analyticsData['data'])) {
        // ROLLUP 결과에 포함된 'Total' 값은 제외
        $regionStats = array_filter($analyticsData['data'], function($item) {
            return $item['region_name'] !== 'Total';
        });
    }
} else {
    // API 호출이 실패하면 DB에서 직접 조회하도록 처리
    $regionStatsQuery = "
        SELECT 
            r.name as region_name,
            COUNT(*) as match_count
        FROM matches m
        JOIN stadiums s ON m.stadium_id = s.id
        JOIN regions r ON s.region_id = r.id
        WHERE m.date = CURDATE()
        GROUP BY r.id, r.name
        ORDER BY match_count DESC
    ";
    $regionStats = $db->query($regionStatsQuery)->fetchAll();
}

// 지역명을 ID로 매핑해서 drill-down 링크에 활용
$regionNameToIdMap = [];
$regionsList = $db->query("SELECT id, name FROM regions ORDER BY name")->fetchAll();
foreach ($regionsList as $region) {
    $regionNameToIdMap[$region['name']] = $region['id'];
}

include '../includes/header.php';
?>

<div class="hero-section">
    <h2>오늘의 KBO 야구 경기</h2>
    <p><?php echo date('Y년 m월 d일'); ?> 경기 일정</p>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <h3>오늘의 경기</h3>
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
                    $regionId = $regionNameToIdMap[$regionName] ?? null;
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
    <h3>필터</h3>
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
    <h3>오늘의 경기 일정</h3>
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


