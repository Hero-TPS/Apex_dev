<?php
/**
 * Time utility for SAST-based business logic
 * All methods assume Africa/Johannesburg timezone
 */
class Time {
    const TIME_ZONE = 'Africa/Johannesburg';
    
    /**
     * Convert a Unix timestamp (UTC) to SAST date string (Y-m-d)
     */
    public static function unixToSastDate(int $unix): string {
        $dt = new DateTime('@' . $unix);
        $dt->setTimezone(new DateTimeZone(self::TIME_ZONE));
        return $dt->format('Y-m-d');
    }
    
    /**
     * Given a Monday Unix (00:00 SAST), return the SAST date range (Mon–Sun)
     */
    public static function weekIdToDateRange(int $weekId): array {
        $startDt = new DateTime('@' . $weekId);
        $startDt->setTimezone(new DateTimeZone(self::TIME_ZONE));
        $startDate = $startDt->format('Y-m-d');
        
        $endDt = clone $startDt;
        $endDt->modify('+6 days');
        $endDate = $endDt->format('Y-m-d');
        
        return [
            'start_date' => $startDate,
            'end_date' => $endDate
        ];
    }
    
    /**
     * Given a Monday Unix, return Unix range (Mon 00:00 → Sun 23:59:59 SAST)
     */
    public static function weekIdToUnixRange(int $weekId): array {
        $start = $weekId;
        $end = $weekId + (6 * 86400) + 86399; // Sunday 23:59:59
        return ['start' => $start, 'end' => $end];
    }
}