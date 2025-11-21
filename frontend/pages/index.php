<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
$db = getDB();

$pageTitle = "홈";

// 백엔드 API를 통해 오늘의 경기 가져오기 (today.php 사용)
$todayMatches = [];
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$basePath = dirname(dirname($_SERVER['PHP_SELF']));
$apiUrl = $protocol . '://' . $host . $basePath . '/backend/api/matches/today.php';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$apiResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($apiResponse !== false && $httpCode == 200) {
    $apiData = json_decode($apiResponse, true);
    if (isset($apiData['data']) && is_array($apiData['data'])) {
        $todayMatches = $apiData['data'];
    }
}

// 백엔드 API를 통해 지역별 경기 가져오기 


// 지역별 오늘 경기 수 (뷰 대신 직접 쿼리 사용)
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
        <?php foreach ($regionStats as $stat): ?>
            <p><?php echo htmlspecialchars($stat['region_name']); ?>: <?php echo $stat['match_count']; ?>경기</p>
        <?php endforeach; ?>
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
                        // status에 따라 상태 표시
                        $statusLabel = '';
                        $statusClass = '';
                        if (isset($match['status'])) {
                            switch($match['status']) {
                                case 'finished':
                                case '완료':
                                    $statusLabel = '완료';
                                    $statusClass = 'status-finished';
                                    break;
                                case 'scheduled':
                                case '예정':
                                    $statusLabel = '예정';
                                    $statusClass = 'status-scheduled';
                                    break;
                                default:
                                    $statusLabel = $match['status'];
                                    $statusClass = 'status-scheduled';
                            }
                        }
                        ?>
                        <span class="status-badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars($statusLabel); ?></span>
                    </div>
                    <div class="match-time">
                        <?php echo htmlspecialchars($match['time'] ?? ''); ?>
                    </div>
                    <div class="match-teams">
                        <div class="team home-team">
                            <strong><?php echo htmlspecialchars($match['home_team_name'] ?? ''); ?></strong>
                            <?php if (isset($match['home_score']) && $match['home_score'] !== null): ?>
                                <span class="score"><?php echo $match['home_score']; ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="vs">VS</div>
                        <div class="team away-team">
                            <strong><?php echo htmlspecialchars($match['away_team_name'] ?? ''); ?></strong>
                            <?php if (isset($match['away_score']) && $match['away_score'] !== null): ?>
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


