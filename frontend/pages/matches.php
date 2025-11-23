<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
$db = getDB();

$pageTitle = "KBO 야구 경기 일정";

// 필터 파라미터
$regionFilter = $_GET['region'] ?? '';
$monthFilter = $_GET['month'] ?? date('Y-m');

// 백엔드 API를 통해 경기 목록 가져오기 (list.php 사용)
$matches = [];
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
// frontend/pages/에서 backend/로 가려면 3단계 위로 올라가야 함
$basePath = dirname(dirname(dirname($_SERVER['PHP_SELF'])));
$baseApiUrl = $protocol . '://' . $host . $basePath . '/backend/api/matches/list.php';

// 경기장별 지역 정보 (region_name 조회용 - API 응답에 없는 정보 보완)
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

// 월 필터링: 해당 월의 모든 날짜에 대해 API 호출
$startDate = date('Y-m-01', strtotime($monthFilter . '-01'));
$endDate = date('Y-m-t', strtotime($monthFilter . '-01')); // 해당 월의 마지막 날

$currentDate = $startDate;
while ($currentDate <= $endDate) {
    $apiUrl = $baseApiUrl . '?date=' . urlencode($currentDate);
    if ($regionFilter) {
        $apiUrl .= '&region_id=' . urlencode($regionFilter);
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $apiResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($apiResponse !== false && $httpCode == 200) {
        $apiData = json_decode($apiResponse, true);
        if ($apiData !== null && isset($apiData['data']) && is_array($apiData['data'])) {
            foreach ($apiData['data'] as $match) {
                // date 필드를 match_date로 변환하여 기존 코드와 호환
                $match['match_date'] = $match['date'] ?? $currentDate;
                $match['match_time'] = $match['time'] ?? '';
                $match['id'] = $match['match_id'] ?? '';
                $match['stadium_name'] = $match['stadium'] ?? '';
                
                // 경기장 이름으로 지역 정보 조회
                $stadiumName = $match['stadium_name'];
                if (!empty($stadiumName) && isset($stadiumsMap[$stadiumName])) {
                    $match['region_name'] = $stadiumsMap[$stadiumName]['region_name'];
                } else {
                    $match['region_name'] = '';
                }
                
                $matches[] = $match;
            }
        }
    } else {
        // API 호출 실패 시 에러 로깅
        error_log("API 호출 실패: URL=$apiUrl, HTTP=$httpCode, Error=$curlError, Response=" . substr($apiResponse, 0, 500));
    }
    
    $currentDate = date('Y-m-d', strtotime($currentDate . ' +1 day'));
}

// 날짜 내림차순, 시간 오름차순 정렬
usort($matches, function($a, $b) {
    $dateCompare = strcmp($b['match_date'], $a['match_date']);
    if ($dateCompare !== 0) {
        return $dateCompare;
    }
    return strcmp($a['match_time'], $b['match_time']);
});

// 지역 목록 (필터용)
$regions = $db->query("SELECT * FROM regions ORDER BY name")->fetchAll();

include '../includes/header.php';
?>

<h2>KBO 야구 경기 일정</h2>

<div class="filter-section surface-card">
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
        <div class="matches-list surface-card">
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
                            <span class="badge region-badge"><?php echo htmlspecialchars($match['region_name']); ?></span>
                    <?php endif; ?>
                    <?php 
                    // 현재 날짜/시간 기준으로 상태 계산 (API의 status 값은 무시하고 항상 재계산)
                    date_default_timezone_set('Asia/Seoul'); // 타임존 설정
                    $status = getMatchStatus($match['match_date'], $match['match_time']);
                    $statusLabel = $status['label'];
                    $statusClass = $status['class'];
                    ?>
                        <span class="badge status-badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars($statusLabel); ?></span>
                </div>
                <div class="match-teams-col">
                    <div class="team-row">
                        <span class="team-name"><?php echo htmlspecialchars($match['home_team'] ?? ''); ?></span>
                        <?php if (isset($match['home_score']) && $match['home_score'] !== null): ?>
                            <span class="score"><?php echo $match['home_score']; ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="team-row">
                        <span class="team-name"><?php echo htmlspecialchars($match['away_team'] ?? ''); ?></span>
                        <?php if (isset($match['away_score']) && $match['away_score'] !== null): ?>
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


