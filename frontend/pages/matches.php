<!-- 경기 조회 페이지 -->

<?php
require_once '../config/database.php';
require_once '../helpers/match_helper.php';
require_once '../helpers/api_helper.php';
$db = getDB();

$pageTitle = "KBO 야구 경기 일정";

$regionFilter = $_GET['region'] ?? '';
$monthFilter = $_GET['month'] ?? date('Y-m');

$matches = [];
$apiBaseUrl = getApiBaseUrl(3);
$baseApiUrl = $apiBaseUrl . '/matches/list.php';

$stadiumsMap = [];
$stadiumsQuery = "SELECT s.name, r.name as region_name, r.id as region_id 
                  FROM stadiums s 
                  JOIN regions r ON s.region_id = r.id";
$stadiumsList = $db->query($stadiumsQuery)->fetchAll();
foreach ($stadiumsList as $stadium) {
    $stadiumsMap[$stadium['name']] = [
        'region_name' => $stadium['region_name'],
        'region_id' => $stadium['region_id']
    ];
}

$startDate = date('Y-m-01', strtotime($monthFilter . '-01'));
$endDate = date('Y-m-t', strtotime($monthFilter . '-01'));

// 선택한 월의 모든 날짜에 대해 API 호출 (일별 필터링)
$currentDate = $startDate;
while ($currentDate <= $endDate) {
    $apiUrl = $baseApiUrl . '?date=' . urlencode($currentDate);
    if ($regionFilter) {
        $apiUrl .= '&region_id=' . urlencode($regionFilter);
    }
    
    $result = callApi($apiUrl, 10);
    
    if ($result['success']) {
        $apiData = json_decode($result['response'], true);
        if ($apiData !== null && isset($apiData['data']) && is_array($apiData['data'])) {
            foreach ($apiData['data'] as $match) {
                // API 응답을 프론트엔드 형식으로 변환
                $match['match_date'] = $match['date'] ?? $currentDate;
                $match['match_time'] = $match['time'] ?? '';
                $match['id'] = $match['match_id'] ?? '';
                $match['stadium_name'] = $match['stadium'] ?? '';
                
                // 경기장명으로 지역 정보 매핑
                if (!empty($match['stadium_name']) && isset($stadiumsMap[$match['stadium_name']])) {
                    $match['region_name'] = $stadiumsMap[$match['stadium_name']]['region_name'];
                } else {
                    $match['region_name'] = '';
                }
                
                $matches[] = $match;
            }
        }
    }
    
    $currentDate = date('Y-m-d', strtotime($currentDate . ' +1 day'));
}

// 경기목록 : 날짜 내림차순, 시간 오름차순 정렬
usort($matches, function($a, $b) {
    $dateCompare = strcmp($b['match_date'], $a['match_date']);
    if ($dateCompare !== 0) {
        return $dateCompare;
    }
    return strcmp($a['match_time'], $b['match_time']);
});

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
                $matchDate = $match['match_date'];
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
                    $status = getMatchStatus($match['match_date'], $match['match_time']);
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


