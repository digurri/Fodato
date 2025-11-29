<!-- written by 2171090 SeungHyeon Lee -->
<!-- 경기 조회 페이지 -->

<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../helpers/match_helper.php';
require_once '../helpers/api_helper.php';
$db = getDB();

$pageTitle = "KBO 야구 경기 일정";

// 필터링
$regionFilter = filter_input(INPUT_GET, 'region', FILTER_VALIDATE_INT);
$monthFilter = $_GET['month'] ?? date('Y-m');

// 경기장/지역 정보 미리 로드
$stadiumMap = [];
$stmt = $db->query("SELECT s.name, r.name as region_name, r.id as region_id FROM stadiums s JOIN regions r ON s.region_id = r.id");
while ($row = $stmt->fetch()) {
    $stadiumMap[$row['name']] = $row;
}

// 날짜 범위 계산
$startDate = date('Y-m-01', strtotime($monthFilter . '-01'));
$endDate = date('Y-m-t', strtotime($monthFilter . '-01'));

$matches = [];
$apiBaseUrl = getApiBaseUrl(); 
$currentDate = $startDate;

// 일별 필터링 
while ($currentDate <= $endDate) {
    $url = $apiBaseUrl . '/matches/list.php?date=' . urlencode($currentDate);
    if ($regionFilter) {
        $url .= '&region_id=' . $regionFilter;
    }
    
    // 타임아웃
    $result = callApi($url, 1); 
    
    if ($result['success']) {
        $json = json_decode($result['response'], true);
        $list = $json['data'] ?? [];

        foreach ($list as $item) {
            // API 데이터에 DB의 지역 정보 매핑
            $stadiumName = $item['stadium'] ?? '';
            $regionInfo = $stadiumMap[$stadiumName] ?? ['region_name' => '', 'region_id' => 0];
            $matches[] = [
                'id' => $item['match_id'],
                'date' => $item['date'] ?? $currentDate,
                'time' => $item['time'] ?? '',
                'home_team' => $item['home_team'] ?? '',
                'home_score' => $item['home_score'] ?? null,
                'away_team' => $item['away_team'] ?? '',
                'away_score' => $item['away_score'] ?? null,
                'stadium_name' => $stadiumName,
                'region_name' => $regionInfo['region_name'],
                'attendance' => null // 리스트 API에는 관중 정보 없음
            ];
        }
    }
    
    // 하루 증가
    $currentDate = date('Y-m-d', strtotime($currentDate . ' +1 day'));
}

// 정렬 (내림차순)
usort($matches, function($a, $b) {
    if ($a['date'] === $b['date']) {
        return strcmp($a['time'], $b['time']);
    }
    return strcmp($b['date'], $a['date']);
});

// 지역 필터 목록 (DB)
$regions = $db->query("SELECT * FROM regions ORDER BY name")->fetchAll();
    
include '../includes/header.php';
?>

<h2>KBO 야구 경기 일정</h2>

<div class="filter-section">
    <form method="GET" action="matches.php" class="filter-form">
        <label>
            월:
            <input type="month" name="month" value="<?php echo htmlspecialchars($monthFilter); ?>">
        </label>
        
        <label>
            지역:
            <select name="region">
                <option value="">전체</option>
                <?php foreach ($regions as $region): ?>
                    <option value="<?php echo $region['id']; ?>" 
                        <?php echo $regionFilter == $region['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($region['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        
        <button type="submit">검색</button>
        <a href="matches.php" class="btn-reset">초기화</a>
    </form>
</div>

<div class="matches-section">
    <?php if (empty($matches)): ?>
        <p class="no-data">데이터 없음</p>
        <p style="color: #666; font-size: 0.9em;">해당 기간에 등록된 경기가 없습니다.</p>
    <?php else: ?>
        <div class="matches-list">
            <?php 
            $currentDate = '';
            foreach ($matches as $match): 
                $matchDate = $match['date'] ?? '';
                if ($currentDate !== $matchDate):
                    $currentDate = $matchDate;
                    $dateStr = date('Y년 m월 d일 (D)', strtotime($matchDate));
            ?>
                <h3 class="date-divider"><?php echo $dateStr; ?></h3>
            <?php endif; ?>
            
            <div class="match-item">
                <div class="match-time-col">
                    <div class="time"><?php echo htmlspecialchars($match['match_time'] ?? ''); ?></div>
                    <?php if (!empty($match['region_name'])): ?>
                        <span class="region-badge"><?php echo htmlspecialchars($match['region_name']); ?></span>
                    <?php endif; ?>
                    <?php 
                    date_default_timezone_set('Asia/Seoul');
                    $matchDate = $match['date'] ?? '';
                    $matchTime = $match['time'] ?? '';
                    $status = getMatchStatus($matchDate, $matchTime);
                    $statusLabel = $status['label'];
                    $statusClass = $status['class'];
                    ?>
                    <span class="status-badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars($statusLabel); ?></span>
                </div>
                <div class="match-teams-col">
                    <div class="team-row">
                        <span class="team-name"><?php echo htmlspecialchars($match['home_team'] ?? ''); ?></span>
                        <?php if ($status['status'] === 'finished' && isset($match['home_score']) && $match['home_score'] !== null): ?>
                            <span class="score"><?php echo $match['home_score']; ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="team-row">
                        <span class="team-name"><?php echo htmlspecialchars($match['away_team'] ?? ''); ?></span>
                        <?php if ($status['status'] === 'finished' && isset($match['away_score']) && $match['away_score'] !== null): ?>
                            <span class="score"><?php echo $match['away_score']; ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="match-info-col">
                    <p><strong><?php echo htmlspecialchars($match['stadium_name'] ?? ''); ?></strong></p>
                    <?php if (!empty($match['region_name'])): ?>
                        <p><?php echo htmlspecialchars($match['region_name']); ?></p>
                    <?php endif; ?>
                    <?php if (isset($match['attendance']) && $match['attendance'] > 0): ?>
                        <p class="attendance">관중: <?php echo number_format($match['attendance']); ?>명</p>
                    <?php endif; ?>
                </div>
                <div class="match-action-col">
                    <a href="match_detail.php?id=<?php echo $match['id']; ?>" class="btn-detail">상세보기</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>


