<?php
/**
 * 경기 상태를 현재 날짜 기준으로 계산
 * @param string $matchDate 경기 날짜 (Y-m-d 형식)
 * @param string $matchTime 경기 시간 (H:i:s 형식, 선택사항)
 * @return array ['status' => 상태, 'label' => 라벨, 'class' => CSS 클래스]
 */
function getMatchStatus($matchDate, $matchTime = null) {
    // 타임존 설정 (함수 내에서도 확실하게)
    date_default_timezone_set('Asia/Seoul');
    
    $today = date('Y-m-d');
    $now = strtotime(date('Y-m-d H:i:s'));
    
    // 경기 날짜 파싱
    $matchDateTimestamp = strtotime($matchDate);
    $todayTimestamp = strtotime($today);
    
    // 날짜만 비교
    if ($matchDateTimestamp < $todayTimestamp) {
        // 과거 경기
        return [
            'status' => 'finished',
            'label' => '완료',
            'class' => 'status-finished'
        ];
    } elseif ($matchDateTimestamp > $todayTimestamp) {
        // 미래 경기
        return [
            'status' => 'scheduled',
            'label' => '예정',
            'class' => 'status-scheduled'
        ];
    } else {
        // 오늘 경기인 경우 시간도 고려
        if ($matchTime) {
            // 시간 형식 정규화 (H:i 또는 H:i:s 모두 처리)
            $timeParts = explode(':', $matchTime);
            $normalizedTime = $timeParts[0] . ':' . (isset($timeParts[1]) ? $timeParts[1] : '00');
            if (!isset($timeParts[2])) {
                $normalizedTime .= ':00';
            } else {
                $normalizedTime .= ':' . $timeParts[2];
            }
            
            $matchDateTime = $matchDate . ' ' . $normalizedTime;
            $matchDateTimeTimestamp = strtotime($matchDateTime);
            
            if ($matchDateTimeTimestamp < $now) {
                // 오늘 경기인데 시간이 지났으면 완료
                return [
                    'status' => 'finished',
                    'label' => '완료',
                    'class' => 'status-finished'
                ];
            } else {
                // 오늘 경기인데 시간이 아직 안 지났으면 예정
                return [
                    'status' => 'scheduled',
                    'label' => '예정',
                    'class' => 'status-scheduled'
                ];
            }
        } else {
            // 시간 정보가 없으면 오늘 날짜면 예정으로 처리
            return [
                'status' => 'scheduled',
                'label' => '예정',
                'class' => 'status-scheduled'
            ];
        }
    }
}
?>

