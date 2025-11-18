<!-- 경기 일정의 상태를 판별하는 PHP 함수 -->

<?php
/**
 * 경기 상태를 현재 날짜 기준으로 계산
 * @param string $matchDate 경기 날짜 (Y-m-d 형식)
 * @param string $matchTime 경기 시간 (H:i:s 형식, 선택사항)
 * @return array ['status' => 상태, 'label' => 라벨, 'class' => CSS 클래스]
 */
function getMatchStatus($matchDate, $matchTime = null) {
    $today = date('Y-m-d');
    $now = date('Y-m-d H:i:s');
    
    // 경기 날짜와 시간을 결합
    if ($matchTime) {
        $matchDateTime = $matchDate . ' ' . $matchTime;
    } else {
        $matchDateTime = $matchDate . ' 23:59:59'; // 시간이 없으면 하루 끝으로 설정
    }
    
    // 날짜만 비교
    if ($matchDate < $today) {
        return [
            'status' => 'finished',
            'label' => '완료',
            'class' => 'status-finished'
        ];
    } elseif ($matchDate > $today) {
        return [
            'status' => 'scheduled',
            'label' => '예정',
            'class' => 'status-scheduled'
        ];
    } else {
        // 오늘 경기인 경우 시간도 고려
        if ($matchTime && $matchDateTime < $now) {
            return [
                'status' => 'finished',
                'label' => '완료',
                'class' => 'status-finished'
            ];
        } else {
            return [
                'status' => 'scheduled',
                'label' => '예정',
                'class' => 'status-scheduled'
            ];
        }
    }
}
?>

