<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
$db = getDB();

$pageTitle = "KBO 야구 경기장 정보";

$stadiumId = $_GET['id'] ?? null;

if ($stadiumId) {
    // 백엔드 API를 통해 경기장 상세 정보 가져오기 (detail.php 사용)
    $stadiumData = null;
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    // frontend/pages/에서 backend/로 가려면 3단계 위로 올라가야 함
    $basePath = dirname(dirname(dirname($_SERVER['PHP_SELF'])));
    $detailApiUrl = $protocol . '://' . $host . $basePath . '/backend/api/stadiums/detail.php?id=' . urlencode($stadiumId);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $detailApiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $detailResponse = curl_exec($ch);
    $detailHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($detailResponse !== false && $detailHttpCode == 200) {
        $detailData = json_decode($detailResponse, true);
        if (isset($detailData['stadium']) && $detailData['stadium'] !== null) {
            $stadiumData = $detailData['stadium'];
        }
    }
    
    if (!$stadiumData) {
        header('Location: stadiums.php');
        exit;
    }
    
    // API 응답을 기존 코드와 호환되도록 변환
    $stadium = [
        'id' => $stadiumId,
        'name' => $stadiumData['name'] ?? '',
        'region_name' => $stadiumData['region'] ?? '',
        'location' => $stadiumData['location'] ?? '',
        'address' => $stadiumData['address'] ?? '', // API에 없을 수 있음
        'capacity' => $stadiumData['capacity'] ?? 0,
        'total_matches' => $stadiumData['total_matches'] ?? 0,
        'avg_attendance' => $stadiumData['avg_spectators'] ?? null,
        'max_attendance' => null, // API에 없음
        'total_attendance' => null, // API에 없음
    ];
    
    // recent_match 정보가 있으면 경기 목록으로 변환
    $stadiumMatches = [];
    if (isset($stadiumData['recent_match']) && $stadiumData['recent_match'] !== null) {
        $recentMatch = $stadiumData['recent_match'];
        $stadiumMatches[] = [
            'id' => $recentMatch['id'] ?? null,
            'match_date' => $recentMatch['date'] ?? '',
            'match_time' => $recentMatch['time'] ?? '',
            'status' => $recentMatch['state'] ?? '',
            'home_team' => explode(' vs ', $recentMatch['teams'] ?? '')[0] ?? '',
            'away_team' => explode(' vs ', $recentMatch['teams'] ?? '')[1] ?? '',
            'attendance' => $recentMatch['spectators'] ?? null,
        ];
    }
    
    include '../includes/header.php';
    ?>
    
    <div class="stadium-detail">
        <h2><?php echo htmlspecialchars($stadium['name']); ?></h2>
        
        <div class="stadium-info-grid">
            <div class="info-card">
                <h4>기본 정보</h4>
                <table>
                    <tr>
                        <th>지역</th>
                        <td><?php echo htmlspecialchars($stadium['region_name'] ?? ''); ?></td>
                    </tr>
                    <tr>
                        <th>위치</th>
                        <td><?php echo htmlspecialchars($stadium['location'] ?? ''); ?></td>
                    </tr>
                    <?php if (isset($stadium['address']) && $stadium['address']): ?>
                    <tr>
                        <th>주소</th>
                        <td><?php echo htmlspecialchars($stadium['address']); ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <th>수용 인원</th>
                        <td><?php echo number_format($stadium['capacity']); ?>명</td>
                    </tr>
                </table>
            </div>
            
            <div class="info-card">
                <h4>통계 정보</h4>
                <table>
                    <tr>
                        <th>총 경기 수</th>
                        <td><?php echo number_format($stadium['total_matches']); ?>경기</td>
                    </tr>
                    <?php if (isset($stadium['avg_attendance']) && $stadium['avg_attendance']): ?>
                    <tr>
                        <th>평균 관중 수</th>
                        <td><?php echo number_format($stadium['avg_attendance'], 0); ?>명</td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
        
        <div class="stadium-matches">
            <h4>최근 경기</h4>
            <?php if (empty($stadiumMatches)): ?>
                <div class="no-data">
                    <p>데이터 없음</p>
                </div>
            <?php else: ?>
                <div class="matches-list">
                    <?php foreach ($stadiumMatches as $match): 
                        if (isset($match['match_date']) && isset($match['match_time'])) {
                            $status = getMatchStatus($match['match_date'], $match['match_time']);
                        } else {
                            $status = ['class' => '', 'label' => $match['status'] ?? ''];
                        }
                    ?>
                        <div class="match-item">
                            <div class="match-date">
                                <?php if (isset($match['match_date']) && isset($match['match_time'])): ?>
                                    <?php echo date('Y-m-d H:i', strtotime($match['match_date'] . ' ' . $match['match_time'])); ?>
                                <?php else: ?>
                                    <?php echo htmlspecialchars($match['match_date'] ?? ''); ?>
                                <?php endif; ?>
                                <?php if (isset($status['class']) && $status['class']): ?>
                                    <span class="status-badge <?php echo $status['class']; ?>"><?php echo $status['label']; ?></span>
                                <?php else: ?>
                                    <span class="status-badge"><?php echo htmlspecialchars($match['status'] ?? ''); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="match-teams">
                                <?php echo htmlspecialchars($match['home_team'] ?? ''); ?> vs <?php echo htmlspecialchars($match['away_team'] ?? ''); ?>
                            </div>
                            <div class="attendance">
                                관중: 
                                <?php if (isset($match['attendance']) && $match['attendance']): ?>
                                    <?php echo number_format($match['attendance']); ?>명
                                <?php else: ?>
                                    <span style="color: #999; font-style: italic;">정보 없음</span>
                                <?php endif; ?>
                            </div>
                            <?php if (isset($match['id']) && $match['id']): ?>
                                <a href="match_detail.php?id=<?php echo $match['id']; ?>" class="btn-detail">상세보기</a>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="action-buttons">
            <a href="stadiums.php" class="btn btn-secondary">목록으로</a>
        </div>
    </div>
    
    <?php
} else {
    // 백엔드 API를 통해 경기장 목록 가져오기 (search.php 사용)
    $regionFilter = $_GET['region'] ?? '';
    $stadiums = [];
    
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    // frontend/pages/에서 backend/로 가려면 3단계 위로 올라가야 함
    $basePath = dirname(dirname(dirname($_SERVER['PHP_SELF'])));
    $searchApiUrl = $protocol . '://' . $host . $basePath . '/backend/api/stadiums/search.php';
    
    // region_id 파라미터 추가
    if ($regionFilter) {
        $searchApiUrl .= '?region_id=' . urlencode($regionFilter);
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $searchApiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $searchResponse = curl_exec($ch);
    $searchHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($searchResponse !== false && $searchHttpCode == 200) {
        $searchData = json_decode($searchResponse, true);
        if (isset($searchData['stadiums']) && is_array($searchData['stadiums'])) {
            $stadiums = $searchData['stadiums'];
        }
    }
    
    // 지역 목록은 DB에서 가져오기 (필터링 드롭다운용)
    $regions = $db->query("SELECT * FROM regions ORDER BY name")->fetchAll();
    
    include '../includes/header.php';
    ?>
    
    <h2>KBO 야구 경기장 정보</h2>
    
    <div class="filter-section">
        <form method="GET" action="stadiums.php" class="filter-form">
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
            <a href="stadiums.php" class="btn-reset">초기화</a>
        </form>
    </div>
    
    <div class="stadiums-grid">
        <?php if (empty($stadiums)): ?>
            <div class="no-data" style="grid-column: 1 / -1;">
                <p>데이터 없음</p>
            </div>
        <?php else: ?>
            <?php foreach ($stadiums as $stadium): ?>
                <div class="stadium-card">
                    <h3><?php echo htmlspecialchars($stadium['name'] ?? ''); ?></h3>
                    <div class="stadium-badges">
                        <span class="region-badge"><?php echo htmlspecialchars($stadium['region'] ?? ''); ?></span>
                    </div>
                    <div class="stadium-info">
                        <p><strong>위치:</strong> <?php echo htmlspecialchars($stadium['location'] ?? ''); ?></p>
                        <p><strong>수용 인원:</strong> <?php echo number_format($stadium['capacity'] ?? 0); ?>명</p>
                        <p><strong>총 경기:</strong> <?php echo number_format($stadium['total_matches'] ?? 0); ?>경기</p>
                    </div>
                    <a href="stadiums.php?id=<?php echo $stadium['id'] ?? ''; ?>" class="btn-detail">상세보기</a>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <?php
}

include '../includes/footer.php';
?>


