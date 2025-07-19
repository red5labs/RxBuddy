<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/validation.php';

class Calendar {
    private $user_id;
    private $timezone;
    
    public function __construct($user_id) {
        $this->user_id = $user_id;
        $this->timezone = defined('TIMEZONE') ? TIMEZONE : 'UTC';
        date_default_timezone_set($this->timezone);
    }
    
    /**
     * Get calendar data for a specific month
     */
    public function getMonthData($year, $month) {
        $start_date = sprintf('%04d-%02d-%02d', $year, $month, 1);
        $end_date = date('Y-m-t', strtotime($start_date));
        
        $calendar_data = [
            'year' => $year,
            'month' => $month,
            'month_name' => date('F Y', strtotime($start_date)),
            'days' => [],
            'medications' => $this->getActiveMedications(),
            'stats' => $this->getMonthStats($start_date, $end_date)
        ];
        
        // Get all days in the month
        $current_date = $start_date;
        while ($current_date <= $end_date) {
            $day_data = $this->getDayData($current_date);
            $calendar_data['days'][$current_date] = $day_data;
            $current_date = date('Y-m-d', strtotime($current_date) + 86400);
        }
        
        return $calendar_data;
    }
    
    /**
     * Get calendar data for a specific week
     */
    public function getWeekData($year, $month, $day) {
        $date = "$year-$month-$day";
        $week_start = date('Y-m-d', strtotime('monday this week', strtotime($date)));
        $week_end = date('Y-m-d', strtotime('sunday this week', strtotime($date)));
        
        $calendar_data = [
            'year' => $year,
            'month' => $month,
            'week_start' => $week_start,
            'week_end' => $week_end,
            'week_name' => date('M j', strtotime($week_start)) . ' - ' . date('M j, Y', strtotime($week_end)),
            'days' => [],
            'medications' => $this->getActiveMedications(),
            'stats' => $this->getWeekStats($week_start, $week_end)
        ];
        
        // Get all days in the week
        $current_date = $week_start;
        while ($current_date <= $week_end) {
            $day_data = $this->getDayData($current_date);
            $calendar_data['days'][$current_date] = $day_data;
            $current_date = date('Y-m-d', strtotime($current_date) + 86400);
        }
        
        return $calendar_data;
    }
    
    /**
     * Get data for a specific day
     */
    public function getDayData($date) {
        $day_data = [
            'date' => $date,
            'day_name' => date('l', strtotime($date)),
            'day_number' => date('j', strtotime($date)),
            'is_today' => $date === date('Y-m-d'),
            'is_past' => $date < date('Y-m-d'),
            'is_future' => $date > date('Y-m-d'),
            'scheduled_doses' => $this->getScheduledDoses($date),
            'taken_doses' => $this->getTakenDoses($date),
            'missed_doses' => $this->getMissedDoses($date)
        ];
        
        return $day_data;
    }
    
    /**
     * Get active medications for the user
     */
    public function getActiveMedications() {
        return fetchAll('
            SELECT m.*, s.time_of_day, s.interval_hours
            FROM medications m
            LEFT JOIN schedules s ON m.id = s.medication_id
            WHERE m.user_id = ? AND m.is_active = 1
            ORDER BY m.name
        ', [$this->user_id]);
    }
    
    /**
     * Get scheduled doses for a specific date
     */
    public function getScheduledDoses($date) {
        $doses = [];
        
        // Get time-based schedules
        $time_schedules = fetchAll('
            SELECT m.id, m.name, m.dosage, s.time_of_day
            FROM medications m
            JOIN schedules s ON m.id = s.medication_id
            WHERE m.user_id = ? 
            AND m.is_active = 1 
            AND s.time_of_day IS NOT NULL
            AND (m.start_date IS NULL OR m.start_date <= ?)
            AND (m.end_date IS NULL OR m.end_date >= ?)
        ', [$this->user_id, $date, $date]);
        
        foreach ($time_schedules as $schedule) {
            $doses[] = [
                'medication_id' => $schedule['id'],
                'medication_name' => $schedule['name'],
                'dosage' => $schedule['dosage'],
                'scheduled_time' => $date . ' ' . $schedule['time_of_day'],
                'type' => 'time_based'
            ];
        }
        
        // Get interval-based schedules (calculate based on last dose)
        $interval_schedules = fetchAll('
            SELECT m.id, m.name, m.dosage, s.interval_hours
            FROM medications m
            JOIN schedules s ON m.id = s.medication_id
            WHERE m.user_id = ? 
            AND m.is_active = 1 
            AND s.interval_hours IS NOT NULL
            AND (m.start_date IS NULL OR m.start_date <= ?)
            AND (m.end_date IS NULL OR m.end_date >= ?)
        ', [$this->user_id, $date, $date]);
        
        foreach ($interval_schedules as $schedule) {
            $next_dose = $this->calculateNextIntervalDose($schedule['id'], $date);
            if ($next_dose && date('Y-m-d', strtotime($next_dose)) === $date) {
                $doses[] = [
                    'medication_id' => $schedule['id'],
                    'medication_name' => $schedule['name'],
                    'dosage' => $schedule['dosage'],
                    'scheduled_time' => $next_dose,
                    'type' => 'interval_based'
                ];
            }
        }
        
        // Sort by scheduled time
        usort($doses, function($a, $b) {
            return strtotime($a['scheduled_time']) - strtotime($b['scheduled_time']);
        });
        
        return $doses;
    }
    
    /**
     * Calculate next interval-based dose
     */
    private function calculateNextIntervalDose($medication_id, $date) {
        $last_dose = fetchOne('
            SELECT taken_at FROM logs 
            WHERE medication_id = ? 
            ORDER BY taken_at DESC 
            LIMIT 1
        ', [$medication_id]);
        
        if (!$last_dose) {
            // No previous dose, check if medication started before this date
            $medication = fetchOne('
                SELECT start_date FROM medications WHERE id = ?
            ', [$medication_id]);
            
            if ($medication && $medication['start_date'] && $medication['start_date'] <= $date) {
                return $medication['start_date'] . ' 08:00:00'; // Default to 8 AM
            }
            return null;
        }
        
        $schedule = fetchOne('
            SELECT interval_hours FROM schedules WHERE medication_id = ?
        ', [$medication_id]);
        
        if (!$schedule) return null;
        
        $next_dose = date('Y-m-d H:i:s', strtotime($last_dose['taken_at']) + ($schedule['interval_hours'] * 3600));
        
        // Check if next dose falls on the requested date
        if (date('Y-m-d', strtotime($next_dose)) === $date) {
            return $next_dose;
        }
        
        return null;
    }
    
    /**
     * Get taken doses for a specific date
     */
    public function getTakenDoses($date) {
        return fetchAll('
            SELECT l.*, m.name as medication_name, m.dosage
            FROM logs l
            JOIN medications m ON l.medication_id = m.id
            WHERE l.user_id = ? 
            AND DATE(l.taken_at) = ?
            ORDER BY l.taken_at
        ', [$this->user_id, $date]);
    }
    
    /**
     * Get missed doses for a specific date
     */
    public function getMissedDoses($date) {
        $missed = [];
        $scheduled_doses = $this->getScheduledDoses($date);
        $taken_doses = $this->getTakenDoses($date);
        
        foreach ($scheduled_doses as $scheduled) {
            $taken = false;
            foreach ($taken_doses as $taken_dose) {
                if ($taken_dose['medication_id'] == $scheduled['medication_id']) {
                    $taken = true;
                    break;
                }
            }
            
            if (!$taken && $date <= date('Y-m-d')) {
                $missed[] = $scheduled;
            }
        }
        
        return $missed;
    }
    
    /**
     * Get month statistics
     */
    public function getMonthStats($start_date, $end_date) {
        $stats = fetchOne('
            SELECT 
                COUNT(*) as total_doses,
                COUNT(DISTINCT DATE(taken_at)) as days_with_doses,
                COUNT(DISTINCT medication_id) as medications_taken
            FROM logs 
            WHERE user_id = ? 
            AND DATE(taken_at) BETWEEN ? AND ?
        ', [$this->user_id, $start_date, $end_date]);
        
        $total_days = date('t', strtotime($start_date));
        $stats['adherence_rate'] = $total_days > 0 ? round(($stats['days_with_doses'] / $total_days) * 100, 1) : 0;
        
        return $stats;
    }
    
    /**
     * Get week statistics
     */
    private function getWeekStats($start_date, $end_date) {
        $stats = fetchOne('
            SELECT 
                COUNT(*) as total_doses,
                COUNT(DISTINCT DATE(taken_at)) as days_with_doses,
                COUNT(DISTINCT medication_id) as medications_taken
            FROM logs 
            WHERE user_id = ? 
            AND DATE(taken_at) BETWEEN ? AND ?
        ', [$this->user_id, $start_date, $end_date]);
        
        $total_days = 7;
        $stats['adherence_rate'] = round(($stats['days_with_doses'] / $total_days) * 100, 1);
        
        return $stats;
    }
    
    /**
     * Get calendar navigation data
     */
    public function getNavigationData($year, $month, $view = 'month') {
        $current = strtotime("$year-$month-01");
        
        if ($view === 'month') {
            $prev = date('Y-m', strtotime('-1 month', $current));
            $next = date('Y-m', strtotime('+1 month', $current));
        } else {
            $prev = date('Y-m-d', strtotime('-1 week', $current));
            $next = date('Y-m-d', strtotime('+1 week', $current));
        }
        
        return [
            'current' => date('Y-m', $current),
            'prev' => $prev,
            'next' => $next,
            'today' => date('Y-m-d')
        ];
    }
}
?> 