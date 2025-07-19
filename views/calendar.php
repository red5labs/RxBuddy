<?php
require_once __DIR__ . '/../includes/calendar.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/validation.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?page=login');
    exit;
}

// Get calendar parameters
$view = sanitizeInput($_GET['view'] ?? 'month', 'string');
$year = sanitizeInput($_GET['year'] ?? date('Y'), 'int');
$month = sanitizeInput($_GET['month'] ?? date('n'), 'int');
$day = sanitizeInput($_GET['day'] ?? date('j'), 'int');

// Validate parameters
if (!in_array($view, ['month', 'week'])) {
    $view = 'month';
}
if ($year < 2020 || $year > 2030) {
    $year = date('Y');
}
if ($month < 1 || $month > 12) {
    $month = date('n');
}
if ($day < 1 || $day > 31) {
    $day = date('j');
}

// Initialize calendar
$calendar = new Calendar($_SESSION['user_id']);

// Get calendar data
if ($view === 'month') {
    $calendar_data = $calendar->getMonthData($year, $month);
    $nav_data = $calendar->getNavigationData($year, $month, 'month');
} else {
    $calendar_data = $calendar->getWeekData($year, $month, $day);
    $nav_data = $calendar->getNavigationData($year, $month, 'week');
}

include __DIR__ . '/../includes/templates/flash.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Calendar Header -->
    <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">
                    <?php echo $view === 'month' ? $calendar_data['month_name'] : $calendar_data['week_name']; ?>
                </h1>
                <p class="text-gray-600">Medication Schedule & History</p>
            </div>
            
            <!-- View Toggle -->
            <div class="flex items-center space-x-4">
                <div class="flex bg-gray-100 rounded-lg p-1">
                    <a href="?page=calendar&view=month&year=<?php echo $year; ?>&month=<?php echo $month; ?>" 
                       class="px-3 py-1 rounded <?php echo $view === 'month' ? 'bg-white shadow-sm' : 'text-gray-600'; ?>">
                        Month
                    </a>
                    <a href="?page=calendar&view=week&year=<?php echo $year; ?>&month=<?php echo $month; ?>&day=<?php echo $day; ?>" 
                       class="px-3 py-1 rounded <?php echo $view === 'week' ? 'bg-white shadow-sm' : 'text-gray-600'; ?>">
                        Week
                    </a>
                </div>
                
                <!-- Navigation -->
                <div class="flex items-center space-x-2">
                    <a href="?page=calendar&view=<?php echo $view; ?>&year=<?php echo $nav_data['prev']; ?>" 
                       class="p-2 text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                        </svg>
                    </a>
                    <a href="?page=calendar&view=<?php echo $view; ?>&year=<?php echo date('Y'); ?>&month=<?php echo date('n'); ?>&day=<?php echo date('j'); ?>" 
                       class="px-3 py-1 text-sm bg-indigo-100 text-indigo-700 rounded hover:bg-indigo-200">
                        Today
                    </a>
                    <a href="?page=calendar&view=<?php echo $view; ?>&year=<?php echo $nav_data['next']; ?>" 
                       class="p-2 text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="bg-blue-50 p-4 rounded-lg">
                <div class="text-2xl font-bold text-blue-600"><?php echo $calendar_data['stats']['total_doses']; ?></div>
                <div class="text-sm text-blue-600">Total Doses</div>
            </div>
            <div class="bg-green-50 p-4 rounded-lg">
                <div class="text-2xl font-bold text-green-600"><?php echo $calendar_data['stats']['days_with_doses']; ?></div>
                <div class="text-sm text-green-600">Days with Doses</div>
            </div>
            <div class="bg-purple-50 p-4 rounded-lg">
                <div class="text-2xl font-bold text-purple-600"><?php echo $calendar_data['stats']['medications_taken']; ?></div>
                <div class="text-sm text-purple-600">Medications Taken</div>
            </div>
            <div class="bg-orange-50 p-4 rounded-lg">
                <div class="text-2xl font-bold text-orange-600"><?php echo $calendar_data['stats']['adherence_rate']; ?>%</div>
                <div class="text-sm text-orange-600">Adherence Rate</div>
            </div>
        </div>
    </div>
    
    <!-- Calendar Grid -->
    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
        <?php if ($view === 'month'): ?>
            <!-- Desktop Month View -->
            <div class="hidden md:grid grid-cols-7 gap-px bg-gray-200">
                <!-- Day Headers -->
                <div class="bg-gray-50 p-3 text-center text-sm font-medium text-gray-500">Sun</div>
                <div class="bg-gray-50 p-3 text-center text-sm font-medium text-gray-500">Mon</div>
                <div class="bg-gray-50 p-3 text-center text-sm font-medium text-gray-500">Tue</div>
                <div class="bg-gray-50 p-3 text-center text-sm font-medium text-gray-500">Wed</div>
                <div class="bg-gray-50 p-3 text-center text-sm font-medium text-gray-500">Thu</div>
                <div class="bg-gray-50 p-3 text-center text-sm font-medium text-gray-500">Fri</div>
                <div class="bg-gray-50 p-3 text-center text-sm font-medium text-gray-500">Sat</div>
                
                <?php
                // Get first day of month and day of week
                $first_day = date('w', strtotime("$year-$month-01"));
                $days_in_month = date('t', strtotime("$year-$month-01"));
                
                // Add empty cells for days before month starts
                for ($i = 0; $i < $first_day; $i++) {
                    echo '<div class="bg-white p-2 min-h-[120px]"></div>';
                }
                
                // Add days of the month
                for ($day_num = 1; $day_num <= $days_in_month; $day_num++) {
                    $date = sprintf('%04d-%02d-%02d', $year, $month, $day_num);
                    $day_data = $calendar_data['days'][$date] ?? null;
                    
                    if ($day_data) {
                        renderDayCell($day_data, $view);
                    } else {
                        echo '<div class="bg-white p-2 min-h-[120px] border-l border-t border-gray-100">';
                        echo '<div class="text-sm font-medium text-gray-900">' . $day_num . '</div>';
                        echo '</div>';
                    }
                }
                ?>
            </div>
            
            <!-- Mobile Month View -->
            <div class="md:hidden">
                <?php
                // Get first day of month and day of week
                $first_day = date('w', strtotime("$year-$month-01"));
                $days_in_month = date('t', strtotime("$year-$month-01"));
                
                // Add days of the month
                for ($day_num = 1; $day_num <= $days_in_month; $day_num++) {
                    $date = sprintf('%04d-%02d-%02d', $year, $month, $day_num);
                    $day_data = $calendar_data['days'][$date] ?? null;
                    
                    if ($day_data) {
                        renderMobileDayCell($day_data, $view);
                    } else {
                        renderEmptyMobileDayCell($day_num, $date);
                    }
                }
                ?>
            </div>
        <?php else: ?>
            <!-- Desktop Week View -->
            <div class="hidden md:grid grid-cols-7 gap-px bg-gray-200">
                <!-- Day Headers -->
                <?php foreach ($calendar_data['days'] as $date => $day_data): ?>
                    <div class="bg-gray-50 p-3 text-center">
                        <div class="text-sm font-medium text-gray-500"><?php echo $day_data['day_name']; ?></div>
                        <div class="text-lg font-semibold text-gray-900"><?php echo $day_data['day_number']; ?></div>
                    </div>
                <?php endforeach; ?>
                
                <!-- Day Content -->
                <?php foreach ($calendar_data['days'] as $date => $day_data): ?>
                    <?php renderDayCell($day_data, $view); ?>
                <?php endforeach; ?>
            </div>
            
            <!-- Mobile Week View -->
            <div class="md:hidden">
                <?php foreach ($calendar_data['days'] as $date => $day_data): ?>
                    <?php renderMobileDayCell($day_data, $view); ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Legend -->
    <div class="mt-6 bg-white rounded-lg shadow-sm p-4">
        <h3 class="text-lg font-medium text-gray-900 mb-3">Legend</h3>
        <div class="flex flex-wrap gap-4">
            <div class="flex items-center">
                <div class="w-4 h-4 bg-green-100 border-2 border-green-500 rounded mr-2"></div>
                <span class="text-sm text-gray-600">Taken Doses</span>
            </div>
            <div class="flex items-center">
                <div class="w-4 h-4 bg-blue-100 border-2 border-blue-500 rounded mr-2"></div>
                <span class="text-sm text-gray-600">Scheduled Doses</span>
            </div>
            <div class="flex items-center">
                <div class="w-4 h-4 bg-red-100 border-2 border-red-500 rounded mr-2"></div>
                <span class="text-sm text-gray-600">Missed Doses</span>
            </div>
            <div class="flex items-center">
                <div class="w-4 h-4 bg-gray-100 border-2 border-gray-400 rounded mr-2"></div>
                <span class="text-sm text-gray-600">Today</span>
            </div>
        </div>
    </div>
</div>

<!-- Dose Details Modal -->
<div id="dose-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md mx-4">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold text-gray-900" id="modal-title">Medication Details</h3>
            <button onclick="closeDoseModal()" class="text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        
        <div id="modal-content">
            <!-- Content will be populated by JavaScript -->
        </div>
        
        <div class="flex justify-end space-x-3 mt-6">
            <button onclick="closeDoseModal()" class="px-4 py-2 text-gray-600 hover:text-gray-800">
                Close
            </button>
            <button id="mark-taken-btn" onclick="markDoseAsTaken()" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 hidden">
                Mark as Taken
            </button>
        </div>
    </div>
</div>

<!-- Day Details Modal -->
<div id="day-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-2xl mx-4 max-h-[80vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold text-gray-900" id="day-modal-title">Day Details</h3>
            <button onclick="closeDayModal()" class="text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        
        <div id="day-modal-content">
            <!-- Content will be populated by JavaScript -->
        </div>
        
        <div class="flex justify-end mt-6">
            <button onclick="closeDayModal()" class="px-4 py-2 bg-gray-600 text-white rounded hover:bg-gray-700">
                Close
            </button>
        </div>
    </div>
</div>

<script>
// Global variables for modals
let currentMedicationId = null;
let currentScheduledTime = null;

// Collapsible day cells
function showAllDoses(dateId) {
    document.getElementById('expanded-' + dateId).classList.remove('hidden');
}

function hideAllDoses(dateId) {
    document.getElementById('expanded-' + dateId).classList.add('hidden');
}

// Dose details modal
function showDoseDetails(medicationId, medicationName, dosage, time, isTaken, scheduledTime, takenAt) {
    currentMedicationId = medicationId;
    currentScheduledTime = scheduledTime;
    
    const modal = document.getElementById('dose-modal');
    const title = document.getElementById('modal-title');
    const content = document.getElementById('modal-content');
    const markTakenBtn = document.getElementById('mark-taken-btn');
    
    title.textContent = medicationName;
    
    let statusText = isTaken ? 'Taken' : 'Scheduled';
    let statusClass = isTaken ? 'text-green-600' : 'text-blue-600';
    
    content.innerHTML = `
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-gray-500">Status</span>
                <span class="text-sm font-semibold ${statusClass}">${statusText}</span>
            </div>
            
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-gray-500">Dosage</span>
                <span class="text-sm text-gray-900">${dosage}</span>
            </div>
            
            ${scheduledTime ? `
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-gray-500">Scheduled Time</span>
                <span class="text-sm text-gray-900">${scheduledTime}</span>
            </div>
            ` : ''}
            
            ${isTaken && takenAt ? `
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-gray-500">Taken At</span>
                <span class="text-sm text-gray-900">${takenAt}</span>
            </div>
            ` : ''}
        </div>
    `;
    
    // Show/hide mark as taken button
    if (!isTaken) {
        markTakenBtn.classList.remove('hidden');
    } else {
        markTakenBtn.classList.add('hidden');
    }
    
    modal.classList.remove('hidden');
}

function closeDoseModal() {
    document.getElementById('dose-modal').classList.add('hidden');
    currentMedicationId = null;
    currentScheduledTime = null;
}

function markDoseAsTaken() {
    if (!currentMedicationId) return;
    
    // Show loading state
    const btn = document.getElementById('mark-taken-btn');
    const originalText = btn.textContent;
    btn.textContent = 'Marking...';
    btn.disabled = true;
    
    // Send AJAX request
    fetch('ajax/mark-taken.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            medication_id: currentMedicationId,
            note: ''
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Close modal and refresh page
            closeDoseModal();
            location.reload();
        } else {
            alert('Error: ' + data.message);
            btn.textContent = originalText;
            btn.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error marking dose as taken');
        btn.textContent = originalText;
        btn.disabled = false;
    });
}

// Day details modal
function showDayDetails(date) {
    const modal = document.getElementById('day-modal');
    const title = document.getElementById('day-modal-title');
    const content = document.getElementById('day-modal-content');
    
    // Fix timezone issue by adding 'T00:00:00' to ensure correct date
    const dateObj = new Date(date + 'T00:00:00');
    const formattedDate = dateObj.toLocaleDateString('en-US', { 
        weekday: 'long', 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric' 
    });
    
    title.textContent = `Day Details - ${formattedDate}`;
    
    // Show loading state
    content.innerHTML = `
        <div class="flex items-center justify-center py-8">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
            <span class="ml-2 text-gray-600">Loading day details...</span>
        </div>
    `;
    
    modal.classList.remove('hidden');
    
    // Fetch day details via AJAX
    fetch('ajax/get-day-details.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            date: date
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            renderDayDetails(data.data, formattedDate);
        } else {
            content.innerHTML = `
                <div class="text-center py-8">
                    <p class="text-red-600">Error loading day details: ${data.message}</p>
                </div>
            `;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        content.innerHTML = `
            <div class="text-center py-8">
                <p class="text-red-600">Error loading day details. Please try again.</p>
            </div>
        `;
    });
}

function renderDayDetails(dayData, formattedDate) {
    const content = document.getElementById('day-modal-content');
    
    let html = `
        <div class="space-y-6">
            <!-- Date Information -->
            <div class="bg-blue-50 p-4 rounded-lg">
                <h4 class="font-medium text-blue-900 mb-2">Date Information</h4>
                <p class="text-blue-700">${formattedDate}</p>
            </div>
            
            <!-- Statistics -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-green-50 p-3 rounded-lg text-center">
                    <div class="text-lg font-bold text-green-600">${dayData.stats.total_doses}</div>
                    <div class="text-xs text-green-600">Total Doses</div>
                </div>
                <div class="bg-blue-50 p-3 rounded-lg text-center">
                    <div class="text-lg font-bold text-blue-600">${dayData.stats.scheduled_doses}</div>
                    <div class="text-xs text-blue-600">Scheduled</div>
                </div>
                <div class="bg-orange-50 p-3 rounded-lg text-center">
                    <div class="text-lg font-bold text-orange-600">${dayData.stats.taken_doses}</div>
                    <div class="text-xs text-orange-600">Taken</div>
                </div>
                <div class="bg-red-50 p-3 rounded-lg text-center">
                    <div class="text-lg font-bold text-red-600">${dayData.stats.missed_doses}</div>
                    <div class="text-xs text-red-600">Missed</div>
                </div>
            </div>
    `;
    
    // Scheduled Doses Section
    if (dayData.scheduled_doses && dayData.scheduled_doses.length > 0) {
        html += `
            <div class="bg-gray-50 p-4 rounded-lg">
                <h4 class="font-medium text-gray-900 mb-3">Scheduled Doses</h4>
                <div class="space-y-3">
        `;
        
        dayData.scheduled_doses.forEach(dose => {
            const isTaken = dayData.taken_doses.some(taken => taken.medication_id == dose.medication_id);
            const takenDose = dayData.taken_doses.find(taken => taken.medication_id == dose.medication_id);
            const statusClass = isTaken ? 'border-green-200 bg-green-50' : 'border-blue-200 bg-blue-50';
            const statusText = isTaken ? 'Taken' : 'Scheduled';
            const statusColor = isTaken ? 'text-green-600' : 'text-blue-600';
            
            html += `
                <div class="border rounded-lg p-3 ${statusClass}">
                    <div class="flex items-center justify-between mb-2">
                        <h5 class="font-medium text-gray-900">${dose.medication_name}</h5>
                        <span class="text-sm font-medium ${statusColor}">${statusText}</span>
                    </div>
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <span class="text-gray-500">Dosage:</span>
                            <span class="text-gray-900">${dose.dosage}</span>
                        </div>
                        <div>
                            <span class="text-gray-500">Time:</span>
                            <span class="text-gray-900">${dose.scheduled_time}</span>
                        </div>
                        ${isTaken && takenDose ? `
                        <div class="col-span-2">
                            <span class="text-gray-500">Taken at:</span>
                            <span class="text-gray-900">${takenDose.taken_at}</span>
                        </div>
                        ${takenDose.note ? `
                        <div class="col-span-2">
                            <span class="text-gray-500">Note:</span>
                            <span class="text-gray-900">${takenDose.note}</span>
                        </div>
                        ` : ''}
                        ` : ''}
                    </div>
                    ${!isTaken ? `
                    <div class="mt-3">
                        <button onclick="markDoseAsTakenFromModal(${dose.medication_id}, '${dose.medication_name}')" 
                                class="px-3 py-1 bg-green-600 text-white text-sm rounded hover:bg-green-700">
                            Mark as Taken
                        </button>
                    </div>
                    ` : ''}
                </div>
            `;
        });
        
        html += `
                </div>
            </div>
        `;
    }
    
    // Taken Doses Section (if any doses were taken without being scheduled)
    const unscheduledTaken = dayData.taken_doses.filter(taken => 
        !dayData.scheduled_doses.some(scheduled => scheduled.medication_id == taken.medication_id)
    );
    
    if (unscheduledTaken.length > 0) {
        html += `
            <div class="bg-green-50 p-4 rounded-lg">
                <h4 class="font-medium text-green-900 mb-3">Additional Doses Taken</h4>
                <div class="space-y-3">
        `;
        
        unscheduledTaken.forEach(dose => {
            html += `
                <div class="border border-green-200 rounded-lg p-3 bg-white">
                    <div class="flex items-center justify-between mb-2">
                        <h5 class="font-medium text-gray-900">${dose.medication_name}</h5>
                        <span class="text-sm font-medium text-green-600">Taken</span>
                    </div>
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <span class="text-gray-500">Dosage:</span>
                            <span class="text-gray-900">${dose.dosage}</span>
                        </div>
                        <div>
                            <span class="text-gray-500">Taken at:</span>
                            <span class="text-gray-900">${dose.taken_at}</span>
                        </div>
                        ${dose.note ? `
                        <div class="col-span-2">
                            <span class="text-gray-500">Note:</span>
                            <span class="text-gray-900">${dose.note}</span>
                        </div>
                        ` : ''}
                    </div>
                </div>
            `;
        });
        
        html += `
                </div>
            </div>
        `;
    }
    
    // Missed Doses Section
    if (dayData.missed_doses && dayData.missed_doses.length > 0) {
        html += `
            <div class="bg-red-50 p-4 rounded-lg">
                <h4 class="font-medium text-red-900 mb-3">Missed Doses</h4>
                <div class="space-y-3">
        `;
        
        dayData.missed_doses.forEach(dose => {
            html += `
                <div class="border border-red-200 rounded-lg p-3 bg-white">
                    <div class="flex items-center justify-between mb-2">
                        <h5 class="font-medium text-gray-900">${dose.medication_name}</h5>
                        <span class="text-sm font-medium text-red-600">Missed</span>
                    </div>
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <span class="text-gray-500">Dosage:</span>
                            <span class="text-gray-900">${dose.dosage}</span>
                        </div>
                        <div>
                            <span class="text-gray-500">Scheduled:</span>
                            <span class="text-gray-900">${dose.scheduled_time}</span>
                        </div>
                    </div>
                    <div class="mt-3">
                        <button onclick="markDoseAsTakenFromModal(${dose.medication_id}, '${dose.medication_name}')" 
                                class="px-3 py-1 bg-green-600 text-white text-sm rounded hover:bg-green-700">
                            Mark as Taken
                        </button>
                    </div>
                </div>
            `;
        });
        
        html += `
                </div>
            </div>
        `;
    }
    
    // No doses section
    if ((!dayData.scheduled_doses || dayData.scheduled_doses.length === 0) && 
        (!dayData.taken_doses || dayData.taken_doses.length === 0)) {
        html += `
            <div class="bg-gray-50 p-4 rounded-lg text-center">
                <h4 class="font-medium text-gray-900 mb-2">No Doses</h4>
                <p class="text-gray-600">No medications were scheduled or taken on this day.</p>
            </div>
        `;
    }
    
    html += `</div>`;
    
    content.innerHTML = html;
}

function markDoseAsTakenFromModal(medicationId, medicationName) {
    // Show a simple prompt for note (can be enhanced later)
    const note = prompt(`Add a note for ${medicationName} (optional):`);
    
    // Show loading state
    const btn = event.target;
    const originalText = btn.textContent;
    btn.textContent = 'Marking...';
    btn.disabled = true;
    
    // Send AJAX request
    fetch('ajax/mark-taken.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            medication_id: medicationId,
            note: note || ''
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Close modal and refresh page
            closeDayModal();
            location.reload();
        } else {
            alert('Error: ' + data.message);
            btn.textContent = originalText;
            btn.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error marking dose as taken');
        btn.textContent = originalText;
        btn.disabled = false;
    });
}

function closeDayModal() {
    document.getElementById('day-modal').classList.add('hidden');
}

// Close modals when clicking outside
document.addEventListener('click', function(event) {
    const doseModal = document.getElementById('dose-modal');
    const dayModal = document.getElementById('day-modal');
    
    if (event.target === doseModal) {
        closeDoseModal();
    }
    
    if (event.target === dayModal) {
        closeDayModal();
    }
});

// Handle day cell clicks (only for empty areas)
function handleDayCellClick(event, date) {
    // Don't trigger if clicking on a medication pill or button
    if (event.target.closest('.inline-flex') || event.target.closest('button')) {
        return;
    }
    
    // Only show day details if clicking on empty space
    showDayDetails(date);
}

// Close modals with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeDoseModal();
        closeDayModal();
    }
});
</script>

<?php
function renderDayCell($day_data, $view) {
    $bg_class = 'bg-white';
    $border_class = 'border-l border-t border-gray-100';
    
    if ($day_data['is_today']) {
        $bg_class = 'bg-blue-50';
        $border_class = 'border-2 border-blue-300';
    } elseif ($day_data['is_past']) {
        $bg_class = 'bg-gray-50';
    }
    
    $date_id = str_replace('-', '', $day_data['date']);
    
    echo '<div class="' . $bg_class . ' p-2 min-h-[120px] ' . $border_class . ' relative">';
    echo '<div class="text-sm font-medium text-gray-900 mb-1">' . $day_data['day_number'] . '</div>';
    
    // Combine all doses for this day
    $all_doses = [];
    
    // Add scheduled doses
    foreach ($day_data['scheduled_doses'] as $dose) {
        $time = date('H:i', strtotime($dose['scheduled_time']));
        $is_taken = false;
        $is_missed = false;
        
        // Check if taken
        foreach ($day_data['taken_doses'] as $taken) {
            if ($taken['medication_id'] == $dose['medication_id']) {
                $is_taken = true;
                break;
            }
        }
        
        // Check if missed
        if (!$is_taken && $day_data['is_past']) {
            $is_missed = true;
        }
        
        $all_doses[] = [
            'type' => 'scheduled',
            'medication_id' => $dose['medication_id'],
            'medication_name' => $dose['medication_name'],
            'dosage' => $dose['dosage'],
            'time' => $time,
            'scheduled_time' => $dose['scheduled_time'],
            'is_taken' => $is_taken,
            'is_missed' => $is_missed,
            'taken_at' => null
        ];
    }
    
    // Add taken doses (if not already shown as scheduled)
    foreach ($day_data['taken_doses'] as $taken) {
        $already_shown = false;
        foreach ($all_doses as $dose) {
            if ($dose['medication_id'] == $taken['medication_id']) {
                $already_shown = true;
                break;
            }
        }
        
        if (!$already_shown) {
            $time = date('H:i', strtotime($taken['taken_at']));
            $all_doses[] = [
                'type' => 'taken',
                'medication_id' => $taken['medication_id'],
                'medication_name' => $taken['medication_name'],
                'dosage' => $taken['dosage'],
                'time' => $time,
                'scheduled_time' => null,
                'is_taken' => true,
                'is_missed' => false,
                'taken_at' => $taken['taken_at']
            ];
        }
    }
    
    // Sort by time
    usort($all_doses, function($a, $b) {
        return strtotime($a['time']) - strtotime($b['time']);
    });
    
    // Show doses (collapsible if more than 3)
    $max_visible = 3;
    $total_doses = count($all_doses);
    
    for ($i = 0; $i < min($max_visible, $total_doses); $i++) {
        $dose = $all_doses[$i];
        renderDosePill($dose, $date_id);
    }
    
    // Show "more" indicator if there are additional doses
    if ($total_doses > $max_visible) {
        $remaining = $total_doses - $max_visible;
        echo '<div class="mb-1 relative z-10">';
        echo '<button onclick="event.stopPropagation(); showAllDoses(\'' . $date_id . '\')" class="text-xs text-gray-500 hover:text-indigo-600">';
        echo '+ ' . $remaining . ' more';
        echo '</button>';
        echo '</div>';
    }
    
    // Hidden expanded doses
    if ($total_doses > $max_visible) {
        echo '<div id="expanded-' . $date_id . '" class="hidden">';
        for ($i = $max_visible; $i < $total_doses; $i++) {
            $dose = $all_doses[$i];
            renderDosePill($dose, $date_id);
        }
        echo '<div class="mb-1 relative z-10">';
        echo '<button onclick="event.stopPropagation(); hideAllDoses(\'' . $date_id . '\')" class="text-xs text-gray-500 hover:text-indigo-600">';
        echo 'Show less';
        echo '</button>';
        echo '</div>';
        echo '</div>';
    }
    
    // Add click handler for day cell (only for empty areas)
    if ($total_doses > 0) {
        echo '<div class="absolute inset-0 cursor-pointer z-0" onclick="handleDayCellClick(event, \'' . $day_data['date'] . '\')"></div>';
    }
    
    echo '</div>';
}

function renderDosePill($dose, $date_id) {
    $pill_class = 'bg-blue-100 border-blue-500 text-blue-800';
    if ($dose['is_taken']) {
        $pill_class = 'bg-green-100 border-green-500 text-green-800';
    } elseif ($dose['is_missed']) {
        $pill_class = 'bg-red-100 border-red-500 text-red-800';
    }
    
    echo '<div class="mb-1 relative z-10">';
    echo '<div class="inline-flex items-center px-2 py-1 rounded-full text-xs border ' . $pill_class . ' cursor-pointer hover:opacity-80" ';
    $scheduled_time_display = $dose['scheduled_time'] ? date('Y-m-d H:i', strtotime($dose['scheduled_time'])) : '';
    $taken_at_display = $dose['taken_at'] ? date('Y-m-d H:i', strtotime($dose['taken_at'])) : '';
    echo 'onclick="event.stopPropagation(); showDoseDetails(' . $dose['medication_id'] . ', \'' . $dose['medication_name'] . '\', \'' . $dose['dosage'] . '\', \'' . $dose['time'] . '\', ' . ($dose['is_taken'] ? 'true' : 'false') . ', \'' . $scheduled_time_display . '\', \'' . $taken_at_display . '\')">';
    echo '<span class="font-medium">' . $dose['time'] . '</span>';
    echo '<span class="ml-1">' . htmlspecialchars($dose['medication_name']) . '</span>';
    if ($dose['type'] === 'taken') {
        echo '<span class="ml-1 text-xs">(taken)</span>';
    }
    echo '</div>';
    echo '</div>';
}

function renderMobileDayCell($day_data, $view) {
    $bg_class = 'bg-white';
    $border_class = 'border border-gray-200';
    
    if ($day_data['is_today']) {
        $bg_class = 'bg-blue-50';
        $border_class = 'border-2 border-blue-300';
    } elseif ($day_data['is_past']) {
        $bg_class = 'bg-gray-50';
    }
    
    $date_id = str_replace('-', '', $day_data['date']);
    $formatted_date = date('l, F j, Y', strtotime($day_data['date']));
    
    echo '<div class="' . $bg_class . ' p-4 ' . $border_class . ' mb-2 rounded-lg">';
    echo '<div class="flex items-center justify-between mb-3">';
    echo '<div>';
    echo '<div class="text-lg font-semibold text-gray-900">' . $day_data['day_name'] . ', ' . $day_data['day_number'] . '</div>';
    echo '<div class="text-sm text-gray-500">' . date('F Y', strtotime($day_data['date'])) . '</div>';
    echo '</div>';
    echo '<button onclick="showDayDetails(\'' . $day_data['date'] . '\')" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium">View Details</button>';
    echo '</div>';
    
    // Combine all doses for this day
    $all_doses = [];
    
    // Add scheduled doses
    foreach ($day_data['scheduled_doses'] as $dose) {
        $time = date('H:i', strtotime($dose['scheduled_time']));
        $is_taken = false;
        $is_missed = false;
        
        // Check if taken
        foreach ($day_data['taken_doses'] as $taken) {
            if ($taken['medication_id'] == $dose['medication_id']) {
                $is_taken = true;
                break;
            }
        }
        
        // Check if missed
        if (!$is_taken && $day_data['is_past']) {
            $is_missed = true;
        }
        
        $all_doses[] = [
            'type' => 'scheduled',
            'medication_id' => $dose['medication_id'],
            'medication_name' => $dose['medication_name'],
            'dosage' => $dose['dosage'],
            'time' => $time,
            'scheduled_time' => $dose['scheduled_time'],
            'is_taken' => $is_taken,
            'is_missed' => $is_missed,
            'taken_at' => null
        ];
    }
    
    // Add taken doses (if not already shown as scheduled)
    foreach ($day_data['taken_doses'] as $taken) {
        $already_shown = false;
        foreach ($all_doses as $dose) {
            if ($dose['medication_id'] == $taken['medication_id']) {
                $already_shown = true;
                break;
            }
        }
        
        if (!$already_shown) {
            $time = date('H:i', strtotime($taken['taken_at']));
            $all_doses[] = [
                'type' => 'taken',
                'medication_id' => $taken['medication_id'],
                'medication_name' => $taken['medication_name'],
                'dosage' => $taken['dosage'],
                'time' => $time,
                'scheduled_time' => null,
                'is_taken' => true,
                'is_missed' => false,
                'taken_at' => $taken['taken_at']
            ];
        }
    }
    
    // Sort by time
    usort($all_doses, function($a, $b) {
        return strtotime($a['time']) - strtotime($b['time']);
    });
    
    if (count($all_doses) > 0) {
        echo '<div class="space-y-2">';
        foreach ($all_doses as $dose) {
            renderMobileDoseItem($dose, $date_id);
        }
        echo '</div>';
    } else {
        echo '<div class="text-center py-4 text-gray-500">No medications scheduled</div>';
    }
    
    echo '</div>';
}

function renderEmptyMobileDayCell($day_num, $date) {
    echo '<div class="bg-white p-4 border border-gray-200 mb-2 rounded-lg">';
    echo '<div class="flex items-center justify-between mb-3">';
    echo '<div>';
    echo '<div class="text-lg font-semibold text-gray-900">' . date('l', strtotime($date)) . ', ' . $day_num . '</div>';
    echo '<div class="text-sm text-gray-500">' . date('F Y', strtotime($date)) . '</div>';
    echo '</div>';
    echo '<button onclick="showDayDetails(\'' . $date . '\')" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium">View Details</button>';
    echo '</div>';
    echo '<div class="text-center py-4 text-gray-500">No medications scheduled</div>';
    echo '</div>';
}

function renderMobileDoseItem($dose, $date_id) {
    $status_class = 'bg-blue-50 border-blue-200 text-blue-800';
    $status_text = 'Scheduled';
    
    if ($dose['is_taken']) {
        $status_class = 'bg-green-50 border-green-200 text-green-800';
        $status_text = 'Taken';
    } elseif ($dose['is_missed']) {
        $status_class = 'bg-red-50 border-red-200 text-red-800';
        $status_text = 'Missed';
    }
    
    echo '<div class="flex items-center justify-between p-3 bg-white border border-gray-200 rounded-lg">';
    echo '<div class="flex-1">';
    echo '<div class="flex items-center justify-between mb-1">';
    echo '<div class="font-medium text-gray-900">' . htmlspecialchars($dose['medication_name']) . '</div>';
    echo '<span class="text-xs font-medium px-2 py-1 rounded-full border ' . $status_class . '">' . $status_text . '</span>';
    echo '</div>';
    echo '<div class="text-sm text-gray-600">' . $dose['dosage'] . ' • ' . $dose['time'] . '</div>';
    if ($dose['type'] === 'taken' && $dose['taken_at']) {
        echo '<div class="text-xs text-gray-500 mt-1">Taken at ' . date('H:i', strtotime($dose['taken_at'])) . '</div>';
    }
    echo '</div>';
    $scheduled_time_display = $dose['scheduled_time'] ? date('Y-m-d H:i', strtotime($dose['scheduled_time'])) : '';
    $taken_at_display = $dose['taken_at'] ? date('Y-m-d H:i', strtotime($dose['taken_at'])) : '';
    echo '<button onclick="event.stopPropagation(); showDoseDetails(' . $dose['medication_id'] . ', \'' . $dose['medication_name'] . '\', \'' . $dose['dosage'] . '\', \'' . $dose['time'] . '\', ' . ($dose['is_taken'] ? 'true' : 'false') . ', \'' . $scheduled_time_display . '\', \'' . $taken_at_display . '\')" class="ml-3 text-indigo-600 hover:text-indigo-800">';
    echo '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">';
    echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>';
    echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>';
    echo '</svg>';
    echo '</button>';
    echo '</div>';
}
?> 