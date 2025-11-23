<?php
/**
 * 현재 날짜를 기준으로 경기 상태를 계산
 * @param string $matchDate 경기 날짜 (Y-m-d 형식)
 * @param string $matchTime 경기 시간 (H:i:s 형식, 선택 가능)
 * @return array 상태(status), 표시 라벨(label), CSS 클래스(class)
 */

function getMatchStatus($matchDate, $matchTime = null) {
    date_default_timezone_set('Asia/Seoul');
    
    $today = date('Y-m-d');
    $now = strtotime(date('Y-m-d H:i:s'));
    
    $matchDateTimestamp = strtotime($matchDate);
    $todayTimestamp = strtotime($today);
    
    if ($matchDateTimestamp < $todayTimestamp) {
        return [
            'status' => 'finished',
            'label' => '완료',
            'class' => 'status-finished'
        ];
    } elseif ($matchDateTimestamp > $todayTimestamp) {
        return [
            'status' => 'scheduled',
            'label' => '예정',
            'class' => 'status-scheduled'
        ];
    } else {
        if ($matchTime) {
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

