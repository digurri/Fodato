<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
$db = getDB();

$pageTitle = "KBO 야구 경기장 정보";

$stadiumId = $_GET['id'] ?? null;

if ($stadiumId) {
    // 특정 경기장 상세 정보
    $query = "
        SELECT 
            s.*,
            r.name as region_name,
            sp.name as sport_name,
            COUNT(m.id) as total_matches,
            MAX(ms.attendance) as max_attendance,
            AVG(ms.attendance) as avg_attendance,
            SUM(ms.attendance) as total_attendance
        FROM stadiums s
        JOIN regions r ON s.region_id = r.id
        JOIN sports sp ON s.sport_id = sp.id
        LEFT JOIN matches m ON s.id = m.stadium_id
        LEFT JOIN match_stat ms ON m.id = ms.match_id
        WHERE s.id = :id
        GROUP BY s.id
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute([':id' => $stadiumId]);
    $stadium = $stmt->fetch();
    
    if (!$stadium) {
        header('Location: stadiums.php');
        exit;
    }
    
    // 이 경기장에서 열린 경기 목록
    $matchesQuery = "
        SELECT 
            m.*,
            ht.name as home_team,
            at.name as away_team,
            ms.attendance
        FROM matches m
        JOIN teams ht ON m.home_team_id = ht.id
        JOIN teams at ON m.away_team_id = at.id
        LEFT JOIN match_stat ms ON m.id = ms.match_id
        WHERE m.stadium_id = :stadium_id
        ORDER BY m.match_date DESC, m.match_time DESC
        LIMIT 10
    ";
    
    $matchesStmt = $db->prepare($matchesQuery);
    $matchesStmt->execute([':stadium_id' => $stadiumId]);
    $stadiumMatches = $matchesStmt->fetchAll();
    
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
                        <td><?php echo htmlspecialchars($stadium['region_name']); ?></td>
                    </tr>
                    <tr>
                        <th>종목</th>
                        <td><?php echo htmlspecialchars($stadium['sport_name']); ?></td>
                    </tr>
                    <tr>
                        <th>위치</th>
                        <td><?php echo htmlspecialchars($stadium['location']); ?></td>
                    </tr>
                    <tr>
                        <th>주소</th>
                        <td><?php echo htmlspecialchars($stadium['address']); ?></td>
                    </tr>
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
                    <?php if ($stadium['avg_attendance']): ?>
                    <tr>
                        <th>평균 관중 수</th>
                        <td><?php echo number_format($stadium['avg_attendance'], 0); ?>명</td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($stadium['total_attendance']): ?>
                    <tr>
                        <th>총 관중 수</th>
                        <td><?php echo number_format($stadium['total_attendance']); ?>명</td>
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
                        $status = getMatchStatus($match['match_date'], $match['match_time']);
                    ?>
                        <div class="match-item">
                            <div class="match-date">
                                <?php echo date('Y-m-d H:i', strtotime($match['match_date'] . ' ' . $match['match_time'])); ?>
                                <span class="status-badge <?php echo $status['class']; ?>"><?php echo $status['label']; ?></span>
                            </div>
                            <div class="match-teams">
                                <?php echo htmlspecialchars($match['home_team']); ?> vs <?php echo htmlspecialchars($match['away_team']); ?>
                            </div>
                            <div class="attendance">
                                관중: 
                                <?php if ($match['attendance']): ?>
                                    <?php echo number_format($match['attendance']); ?>명
                                <?php else: ?>
                                    <span style="color: #999; font-style: italic;">정보 없음</span>
                                <?php endif; ?>
                            </div>
                            <a href="match_detail.php?id=<?php echo $match['id']; ?>" class="btn-detail">상세보기</a>
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
    // 경기장 목록 (야구만)
    $regionFilter = $_GET['region'] ?? '';
    
    $query = "
        SELECT 
            s.*,
            r.name as region_name,
            sp.name as sport_name,
            COUNT(m.id) as total_matches,
            MAX(ms.attendance) as max_attendance,
            AVG(ms.attendance) as avg_attendance
        FROM stadiums s
        JOIN regions r ON s.region_id = r.id
        JOIN sports sp ON s.sport_id = sp.id
        LEFT JOIN matches m ON s.id = m.stadium_id
        LEFT JOIN match_stat ms ON m.id = ms.match_id
        WHERE sp.name = '야구'
    ";
    
    $params = [];
    
    if ($regionFilter) {
        $query .= " AND s.region_id = :region";
        $params[':region'] = $regionFilter;
    }
    
    $query .= " GROUP BY s.id ORDER BY r.name, s.name";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $stadiums = $stmt->fetchAll();
    
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
                    <h3><?php echo htmlspecialchars($stadium['name']); ?></h3>
                    <div class="stadium-badges">
                        <span class="region-badge"><?php echo htmlspecialchars($stadium['region_name']); ?></span>
                    </div>
                    <div class="stadium-info">
                        <p><strong>위치:</strong> <?php echo htmlspecialchars($stadium['location']); ?></p>
                        <p><strong>수용 인원:</strong> <?php echo number_format($stadium['capacity']); ?>명</p>
                        <p><strong>총 경기:</strong> <?php echo number_format($stadium['total_matches']); ?>경기</p>
                        <?php if ($stadium['avg_attendance']): ?>
                            <p><strong>평균 관중:</strong> <?php echo number_format($stadium['avg_attendance'], 0); ?>명</p>
                        <?php endif; ?>
                    </div>
                    <a href="stadiums.php?id=<?php echo $stadium['id']; ?>" class="btn-detail">상세보기</a>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <?php
}

include '../includes/footer.php';
?>


