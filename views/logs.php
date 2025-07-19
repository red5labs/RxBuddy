<?php
require_once __DIR__ . '/../includes/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// Get filter parameters
$medication_filter = $_GET['medication'] ?? null;
$date_from = $_GET['date_from'] ?? null;
$date_to = $_GET['date_to'] ?? null;
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Build query with filters
$where_conditions = ['l.medication_id = m.id', 'm.user_id = ?'];
$params = [$_SESSION['user_id']];

if ($medication_filter) {
    $where_conditions[] = 'm.id = ?';
    $params[] = $medication_filter;
}
if ($date_from) {
    $where_conditions[] = 'DATE(l.taken_at) >= ?';
    $params[] = $date_from;
}
if ($date_to) {
    $where_conditions[] = 'DATE(l.taken_at) <= ?';
    $params[] = $date_to;
}

$where_clause = implode(' AND ', $where_conditions);

// Get total count for pagination
$count_result = fetchOne("
    SELECT COUNT(*) as total
    FROM logs l
    JOIN medications m ON $where_clause
", $params);
$total_logs = $count_result['total'];
$total_pages = ceil($total_logs / $per_page);

// Get paginated logs
$logs = fetchAll("
    SELECT l.*, m.name as medication_name, m.dosage
    FROM logs l
    JOIN medications m ON $where_clause
    ORDER BY l.taken_at DESC
    LIMIT $per_page OFFSET $offset
", $params);

// Get all medications for filter dropdown
$medications = fetchAll('SELECT id, name FROM medications WHERE user_id = ? ORDER BY name', [$_SESSION['user_id']]);

include __DIR__ . '/../includes/templates/flash.php';
?>

<div class="mb-6">   <h1 class="text-2xl font-bold text-gray-900">Dose Logs</h1>
    <p class="text-gray-600">Track your medication history and adherence.</p>
</div>

<!-- Filters -->
<div class="bg-white rounded-lg shadow-sm p-6 mb-6">   <h2 class="text-lg font-semibold mb-4">Filters</h2>
    <form method="get" class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Medication</label>
            <select name="medication" class="border border-gray-300 rounded px-3 py-2 w-full focus:ring-indigo-200">
                <option value="">All Medications</option>
                <?php foreach ($medications as $med): ?>
                    <option value="<?php echo $med['id']; ?>" <?php echo $medication_filter == $med['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($med['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">From Date</label>
            <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>" class="border border-gray-300 rounded px-3 py-2 w-full focus:ring-indigo-200">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">To Date</label>
            <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>" class="border border-gray-300 rounded px-3 py-2 w-full focus:ring-indigo-200">
        </div>
        <div class="flex items-end space-x-2">
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded">Filter</button>
            <a href="index.php?page=logs" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded">Clear</a>
        </div>
    </form>
</div>

<!-- Export and Purge Buttons -->
<div class="mb-4 flex justify-between items-center">   <h2 class="text-lg font-semibold">Log Entries (<?php echo $total_logs; ?> total)</h2>  <?php if (!empty($logs)): ?>
        <div class="flex space-x-2">
            <a href="export-logs.php?<?php echo http_build_query($_GET); ?>" class="bg-emerald-500 hover:bg-emerald-600 text-white px-4 py-2 rounded text-sm">
                Export CSV
            </a>
            <button onclick="showPurgeModal()" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded text-sm">
                Purge Logs
            </button>
        </div>
    <?php endif; ?>
</div>

<!-- Purge Confirmation Modal -->
<div id="purge-modal" class="fixed inset-0 bg-black bg-opacity-30 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md">
        <div class="flex items-center mb-4">
            <div class="flex-shrink-0">
                <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z" />
                </svg>
            </div>
            <div class="ml-3">
                <h3 class="text-lg font-semibold text-gray-900">Purge All Logs</h3>
            </div>
        </div>
        <div class="mb-4">
            <p class="text-sm text-gray-600 mb-3">
                <strong>Warning:</strong> This action will permanently delete all your dose logs. This cannot be undone.
            </p>
            <p class="text-sm text-gray-600 mb-3">
                <strong>Recommendation:</strong> Export your logs to CSV first if you want to keep a record.
            </p>
            <p class="text-sm text-gray-600">
                Are you sure you want to continue?
            </p>
        </div>
        <div class="flex space-x-2">
            <button onclick="purgeLogs()" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded">Yes, Purge All Logs</button>
            <button onclick="hidePurgeModal()" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded">Cancel</button>
        </div>
    </div>
</div>

<script>
function showPurgeModal() {
    document.getElementById('purge-modal').classList.remove('hidden');
}

function hidePurgeModal() {
    document.getElementById('purge-modal').classList.add('hidden');
}

function purgeLogs() {
    fetch('ajax/purge-logs.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('All logs have been purged successfully.');
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
    });
}
</script>
</div>

<?php if (empty($logs)): ?>
    <div class="text-center py-12">
        <div class="text-gray-400 mb-4">
            <svg class="mx-auto h-12 w-12 fill-none viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 00-2 2v10a2 2 002 2h8a2 20 002-2V7a2 2 0 00-22h-2M9 5a2 2 002 2h2a2 2 002-2M9 5a2 2 01222 />
            </svg>
        </div>
        <h3 class="text-lg font-medium text-gray-900 mb-2">No logs found</h3>
        <p class="text-gray-500 mb-4">No dose logs match your current filters.</p>
        <a href="index.php?page=dashboard" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2 rounded">Back to Dashboard</a>
    </div>
<?php else: ?>
    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date & Time</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Medication</th>                   <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Dosage</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Method</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($logs as $log): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php echo date('M j, Y g:i A', strtotime($log['taken_at'])); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                <?php echo htmlspecialchars($log['medication_name']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo htmlspecialchars($log['dosage']); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100">
                                    <?php echo ucfirst($log['method']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500">
                                <?php echo $log['notes'] ? htmlspecialchars($log['notes']) : '-'; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="mt-6 flex items-center justify-between">
            <div class="text-sm text-gray-700">
                Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $per_page, $total_logs); ?> of <?php echo $total_logs; ?> results
            </div>
            <div class="flex space-x-2">
                <?php if ($page > 1): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="px-3 py-2 text-sm text-gray-500 hover:text-indigo-600 border border-gray-300 rounded">
                        Previous
                    </a>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                       class="px-3 py-2 text-sm <?php echo $i === $page ? 'bg-indigo-600 text-white' : 'text-gray-500 hover:text-indigo-600 border border-gray-300'; ?> rounded">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="px-3 py-2 text-sm text-gray-500 hover:text-indigo-600 border border-gray-300 rounded">
                        Next
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?> 