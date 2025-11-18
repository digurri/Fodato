<?php
require_once '../config/database.php';
$db = getDB();

$pageTitle = "KBO 야구 통계 분석";

// 경기장별 통계 (RANKING 사용, 야구만)
$stadiumRankQuery = "
    SELECT 
        s.name as stadium_name,
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
    WHERE sp.name = '야구'
    GROUP BY s.id, s.name, r.name, sp.name
    ORDER BY total_matches DESC, avg_attendance DESC
";

$stadiumStats = $db->query($stadiumRankQuery)->fetchAll();

// 시즌별 통계 (연도 기준)
$seasonStatQuery = "
    SELECT 
        season_data.season,
        season_data.total_matches,
        season_data.stadium_count,
        COALESCE(team_counts.team_count, 0) as team_count,
        season_data.total_attendance,
        season_data.avg_attendance,
        season_data.max_attendance,
        season_data.min_attendance
    FROM (
        SELECT 
            YEAR(m.match_date) as season,
            COUNT(m.id) as total_matches,
            COUNT(DISTINCT m.stadium_id) as stadium_count,
            SUM(ms.attendance) as total_attendance,
            AVG(ms.attendance) as avg_attendance,
            MAX(ms.attendance) as max_attendance,
            MIN(ms.attendance) as min_attendance
        FROM matches m
        JOIN sports sp ON m.sport_id = sp.id
        LEFT JOIN match_stat ms ON m.id = ms.match_id
        WHERE sp.name = '야구'
        GROUP BY YEAR(m.match_date)
    ) season_data
    LEFT JOIN (
        SELECT 
            YEAR(match_date) as season,
            COUNT(DISTINCT team_id) as team_count
        FROM (
            SELECT match_date, home_team_id as team_id FROM matches
            UNION
            SELECT match_date, away_team_id as team_id FROM matches
        ) all_teams
        GROUP BY YEAR(match_date)
    ) team_counts ON season_data.season = team_counts.season
    ORDER BY season_data.season DESC
";

$seasonStats = $db->query($seasonStatQuery)->fetchAll();

// 지역별 통계 (야구만)
$regionStatQuery = "
    SELECT 
        r.name as region_name,
        COUNT(DISTINCT s.id) as stadium_count,
        COUNT(m.id) as total_matches,
        SUM(ms.attendance) as total_attendance,
        AVG(ms.attendance) as avg_attendance
    FROM regions r
    LEFT JOIN stadiums s ON r.id = s.region_id
    LEFT JOIN sports sp ON s.sport_id = sp.id
    LEFT JOIN matches m ON s.id = m.stadium_id
    LEFT JOIN match_stat ms ON m.id = ms.match_id
    WHERE sp.name = '야구' OR sp.name IS NULL
    GROUP BY r.id, r.name
    ORDER BY total_matches DESC
";

$regionStats = $db->query($regionStatQuery)->fetchAll();

// 날짜별 경기 통계 (WINDOWING 함수, 야구만)
$dailyStatQuery = "
    SELECT 
        match_date,
        COUNT(*) as match_count,
        SUM(attendance) as daily_attendance,
        AVG(attendance) as avg_attendance,
        RANK() OVER (ORDER BY COUNT(*) DESC) as match_rank,
        RANK() OVER (ORDER BY SUM(attendance) DESC) as attendance_rank
    FROM matches m
    JOIN sports sp ON m.sport_id = sp.id
    LEFT JOIN match_stat ms ON m.id = ms.match_id
    WHERE sp.name = '야구'
    GROUP BY match_date
    ORDER BY match_date DESC
    LIMIT 7
";

$dailyStats = $db->query($dailyStatQuery)->fetchAll();

// 최고 관중 수 경기 TOP 10 (야구만)
$topAttendanceQuery = "
    SELECT 
        m.id,
        m.match_date,
        m.match_time,
        sp.name as sport_name,
        s.name as stadium_name,
        ht.name as home_team,
        at.name as away_team,
        ms.attendance,
        RANK() OVER (ORDER BY ms.attendance DESC) as attendance_rank
    FROM matches m
    JOIN sports sp ON m.sport_id = sp.id
    JOIN stadiums s ON m.stadium_id = s.id
    JOIN teams ht ON m.home_team_id = ht.id
    JOIN teams at ON m.away_team_id = at.id
    JOIN match_stat ms ON m.id = ms.match_id
    WHERE sp.name = '야구' AND ms.attendance > 0
    ORDER BY ms.attendance DESC
    LIMIT 10
";

$topAttendance = $db->query($topAttendanceQuery)->fetchAll();

// 팀별 타율 통계 (타자 포지션의 position_stat 평균)
$teamBattingAvgQuery = "
    SELECT 
        t.id,
        t.name as team_name,
        r.name as region_name,
        COUNT(CASE WHEN p.position IN ('1루수', '3루수', '좌익수', '중견수', '우익수', '지명타자') THEN 1 END) as batter_count,
        AVG(CASE 
            WHEN p.position IN ('1루수', '3루수', '좌익수', '중견수', '우익수', '지명타자') 
            THEN p.position_stat 
            ELSE NULL 
        END) as team_batting_avg
    FROM teams t
    JOIN regions r ON t.region_id = r.id
    JOIN sports sp ON t.sport_id = sp.id
    LEFT JOIN players p ON t.id = p.team_id
    WHERE sp.name = '야구'
    GROUP BY t.id, t.name, r.name
    HAVING batter_count > 0
    ORDER BY team_batting_avg DESC
";

$teamBattingAvg = $db->query($teamBattingAvgQuery)->fetchAll();

// 팀별 도루 성공률 통계
$teamStealQuery = "
    SELECT 
        t.id,
        t.name as team_name,
        r.name as region_name,
        SUM(p.steal_attempts) as total_attempts,
        SUM(p.steal_success) as total_success,
        CASE 
            WHEN SUM(p.steal_attempts) > 0 
            THEN (SUM(p.steal_success) / SUM(p.steal_attempts)) * 100
            ELSE 0 
        END as steal_success_rate
    FROM teams t
    JOIN regions r ON t.region_id = r.id
    JOIN sports sp ON t.sport_id = sp.id
    LEFT JOIN players p ON t.id = p.team_id
    WHERE sp.name = '야구'
    GROUP BY t.id, t.name, r.name
    HAVING total_attempts > 0
    ORDER BY steal_success_rate DESC
";

$teamSteal = $db->query($teamStealQuery)->fetchAll();

// 포지션별 퍼포먼스 요약
$positionPerformanceQuery = "
    SELECT 
        p.position,
        COUNT(*) as player_count,
        AVG(p.position_stat) as avg_performance,
        MIN(p.position_stat) as min_performance,
        MAX(p.position_stat) as max_performance,
        CASE 
            WHEN p.position = '투수' THEN '평균자책점'
            WHEN p.position IN ('포수', '2루수', '유격수') THEN '수비율'
            ELSE '타율'
        END as stat_type
    FROM players p
    JOIN teams t ON p.team_id = t.id
    JOIN sports sp ON t.sport_id = sp.id
    WHERE sp.name = '야구' 
    AND p.position IS NOT NULL 
    AND p.position_stat IS NOT NULL
    GROUP BY p.position
    ORDER BY 
        CASE p.position
            WHEN '투수' THEN 1
            WHEN '포수' THEN 2
            WHEN '1루수' THEN 3
            WHEN '2루수' THEN 4
            WHEN '3루수' THEN 5
            WHEN '유격수' THEN 6
            WHEN '좌익수' THEN 7
            WHEN '중견수' THEN 8
            WHEN '우익수' THEN 9
            WHEN '지명타자' THEN 10
            ELSE 11
        END
";

$positionPerformance = $db->query($positionPerformanceQuery)->fetchAll();

include '../includes/header.php';
?>

<h2>KBO 야구 통계 분석</h2>

<div class="statistics-container">
    <!-- 경기장별 통계 -->
    <section class="stat-section">
        <h3>경기장별 통계 (경기 수 순위)</h3>
        <div class="table-responsive">
            <table class="stat-table">
                        <thead>
                    <tr>
                        <th>순위</th>
                        <th>경기장</th>
                        <th>지역</th>
                        <th>총 경기</th>
                        <th>최대 관중</th>
                        <th>평균 관중</th>
                        <th>총 관중</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($stadiumStats)): ?>
                        <tr>
                            <td colspan="7" class="no-data">데이터 없음</td>
                        </tr>
                    <?php else: ?>
                        <?php 
                        $rank = 1;
                        foreach ($stadiumStats as $stat): 
                        ?>
                            <tr>
                                <td><?php echo $rank++; ?></td>
                                <td><strong><?php echo htmlspecialchars($stat['stadium_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($stat['region_name']); ?></td>
                                <td><?php echo number_format($stat['total_matches']); ?></td>
                                <td><?php echo $stat['max_attendance'] ? number_format($stat['max_attendance']) : '-'; ?></td>
                                <td><?php echo $stat['avg_attendance'] ? number_format($stat['avg_attendance'], 0) : '-'; ?></td>
                                <td><?php echo $stat['total_attendance'] ? number_format($stat['total_attendance']) : '-'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- 시즌별 통계 -->
    <section class="stat-section">
        <h3>시즌별 통계</h3>
        <div class="table-responsive">
            <table class="stat-table">
                <thead>
                    <tr>
                        <th>시즌</th>
                        <th>총 경기</th>
                        <th>경기장 수</th>
                        <th>참가 팀 수</th>
                        <th>총 관중</th>
                        <th>평균 관중</th>
                        <th>최대 관중</th>
                        <th>최소 관중</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($seasonStats)): ?>
                        <tr>
                            <td colspan="8" class="no-data">데이터 없음</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($seasonStats as $stat): ?>
                            <tr>
                                <td><strong><?php echo $stat['season']; ?> 시즌</strong></td>
                                <td><?php echo number_format($stat['total_matches']); ?></td>
                                <td><?php echo number_format($stat['stadium_count']); ?></td>
                                <td><?php echo $stat['team_count'] ? number_format($stat['team_count']) : '-'; ?></td>
                                <td><?php echo $stat['total_attendance'] ? number_format($stat['total_attendance']) : '-'; ?></td>
                                <td><?php echo $stat['avg_attendance'] ? number_format($stat['avg_attendance'], 0) : '-'; ?></td>
                                <td><?php echo $stat['max_attendance'] ? number_format($stat['max_attendance']) : '-'; ?></td>
                                <td><?php echo $stat['min_attendance'] ? number_format($stat['min_attendance']) : '-'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- 지역별 통계 -->
    <section class="stat-section">
        <h3>지역별 통계</h3>
        <div class="table-responsive">
            <table class="stat-table">
                <thead>
                    <tr>
                        <th>지역</th>
                        <th>경기장 수</th>
                        <th>총 경기</th>
                        <th>총 관중</th>
                        <th>평균 관중</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($regionStats)): ?>
                        <tr>
                            <td colspan="5" class="no-data">데이터 없음</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($regionStats as $stat): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($stat['region_name']); ?></strong></td>
                                <td><?php echo number_format($stat['stadium_count']); ?></td>
                                <td><?php echo number_format($stat['total_matches']); ?></td>
                                <td><?php echo $stat['total_attendance'] ? number_format($stat['total_attendance']) : '-'; ?></td>
                                <td><?php echo $stat['avg_attendance'] ? number_format($stat['avg_attendance'], 0) : '-'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- 날짜별 통계 (WINDOWING) -->
    <section class="stat-section">
        <h3>최근 날짜별 경기 통계 (최근 7일, WINDOWING 함수 사용)</h3>
        <div class="table-responsive">
            <table class="stat-table">
                <thead>
                    <tr>
                        <th>날짜</th>
                        <th>경기 수</th>
                        <th>총 관중</th>
                        <th>평균 관중</th>
                        <th>경기 수 순위</th>
                        <th>관중 수 순위</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($dailyStats)): ?>
                        <tr>
                            <td colspan="6" class="no-data">데이터 없음</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($dailyStats as $stat): ?>
                            <tr>
                                <td><?php echo date('Y-m-d (D)', strtotime($stat['match_date'])); ?></td>
                                <td><?php echo number_format($stat['match_count']); ?></td>
                                <td><?php echo $stat['daily_attendance'] ? number_format($stat['daily_attendance']) : '-'; ?></td>
                                <td><?php echo $stat['avg_attendance'] ? number_format($stat['avg_attendance'], 0) : '-'; ?></td>
                                <td><span class="rank-badge"><?php echo $stat['match_rank']; ?>위</span></td>
                                <td><span class="rank-badge"><?php echo $stat['attendance_rank']; ?>위</span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- 최고 관중 수 경기 TOP 10 -->
    <section class="stat-section">
        <h3>최고 관중 수 경기 TOP 10</h3>
        <div class="table-responsive">
            <table class="stat-table">
                        <thead>
                    <tr>
                        <th>순위</th>
                        <th>날짜</th>
                        <th>경기장</th>
                        <th>경기</th>
                        <th>관중 수</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($topAttendance)): ?>
                        <tr>
                            <td colspan="5" class="no-data">데이터 없음</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($topAttendance as $match): ?>
                            <tr>
                                <td><span class="rank-badge rank-<?php echo $match['attendance_rank']; ?>">
                                    <?php echo $match['attendance_rank']; ?>위
                                </span></td>
                                <td><?php echo date('Y-m-d', strtotime($match['match_date'])); ?></td>
                                <td><?php echo htmlspecialchars($match['stadium_name']); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($match['home_team']); ?> vs 
                                    <?php echo htmlspecialchars($match['away_team']); ?>
                                </td>
                                <td><strong><?php echo number_format($match['attendance']); ?>명</strong></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- 팀별 타율 통계 -->
    <section class="stat-section">
        <h3>팀별 타율 통계</h3>
        <div class="table-responsive">
            <table class="stat-table">
                <thead>
                    <tr>
                        <th>순위</th>
                        <th>팀명</th>
                        <th>지역</th>
                        <th>타자 수</th>
                        <th>팀 타율</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($teamBattingAvg)): ?>
                        <tr>
                            <td colspan="5" class="no-data">데이터 없음</td>
                        </tr>
                    <?php else: ?>
                        <?php 
                        $rank = 1;
                        foreach ($teamBattingAvg as $team): 
                        ?>
                            <tr>
                                <td><span class="rank-badge rank-<?php echo $rank; ?>"><?php echo $rank++; ?>위</span></td>
                                <td><strong><?php echo htmlspecialchars($team['team_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($team['region_name']); ?></td>
                                <td><?php echo number_format($team['batter_count']); ?>명</td>
                                <td><strong class="stat-highlight"><?php echo $team['team_batting_avg'] ? number_format((float)$team['team_batting_avg'], 3) : '-'; ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- 팀별 도루 성공률 통계 -->
    <section class="stat-section">
        <h3>팀별 도루 성공률</h3>
        <div class="table-responsive">
            <table class="stat-table">
                <thead>
                    <tr>
                        <th>순위</th>
                        <th>팀명</th>
                        <th>지역</th>
                        <th>시도</th>
                        <th>성공</th>
                        <th>성공률</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($teamSteal)): ?>
                        <tr>
                            <td colspan="6" class="no-data">데이터 없음</td>
                        </tr>
                    <?php else: ?>
                        <?php 
                        $rank = 1;
                        foreach ($teamSteal as $team): 
                        ?>
                            <tr>
                                <td><span class="rank-badge rank-<?php echo $rank; ?>"><?php echo $rank++; ?>위</span></td>
                                <td><strong><?php echo htmlspecialchars($team['team_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($team['region_name']); ?></td>
                                <td><?php echo number_format($team['total_attempts']); ?>회</td>
                                <td><?php echo number_format($team['total_success']); ?>회</td>
                                <td><strong class="stat-highlight"><?php echo number_format((float)$team['steal_success_rate'], 1); ?>%</strong></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- 포지션별 퍼포먼스 요약 -->
    <section class="stat-section">
        <h3>포지션별 퍼포먼스 요약</h3>
        <div class="table-responsive">
            <table class="stat-table">
                <thead>
                    <tr>
                        <th>포지션</th>
                        <th>지표 유형</th>
                        <th>선수 수</th>
                        <th>평균</th>
                        <th>최고</th>
                        <th>최저</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($positionPerformance)): ?>
                        <tr>
                            <td colspan="6" class="no-data">데이터 없음</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($positionPerformance as $perf): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($perf['position']); ?></strong></td>
                                <td><span class="stat-type-badge"><?php echo htmlspecialchars($perf['stat_type']); ?></span></td>
                                <td><?php echo number_format($perf['player_count']); ?>명</td>
                                <td><strong><?php 
                                    if ($perf['position'] === '투수') {
                                        echo number_format((float)$perf['avg_performance'], 2);
                                    } else {
                                        echo number_format((float)$perf['avg_performance'], 3);
                                    }
                                ?></strong></td>
                                <td class="stat-max"><?php 
                                    if ($perf['position'] === '투수') {
                                        echo number_format((float)$perf['min_performance'], 2); // 투수는 낮을수록 좋음
                                    } else {
                                        echo number_format((float)$perf['max_performance'], 3);
                                    }
                                ?></td>
                                <td class="stat-min"><?php 
                                    if ($perf['position'] === '투수') {
                                        echo number_format((float)$perf['max_performance'], 2); // 투수는 높을수록 나쁨
                                    } else {
                                        echo number_format((float)$perf['min_performance'], 3);
                                    }
                                ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<?php include '../includes/footer.php'; ?>


