<?php
require_once __DIR__ . '/../includes/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$medications = fetchAll('SELECT * FROM medications WHERE user_id = ? AND is_active = 0 ORDER BY end_date DESC, name', [$_SESSION['user_id']]);

include __DIR__ . '/../includes/templates/flash.php';
?>
<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Past Medications</h1>
    <p class="text-gray-600">These medications have been archived.</p>
</div>
<?php if (empty($medications)): ?>
    <div class="text-center py-12">
        <div class="text-gray-400 mb-4">
            <svg class="mx-auto h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2" />
            </svg>
        </div>
        <h3 class="text-lg font-medium text-gray-900 mb-2">No past medications</h3>
        <p class="text-gray-500 mb-4">You have not archived any medications yet.</p>
        <a href="index.php?page=dashboard" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2 rounded">Back to Dashboard</a>
    </div>
<?php else: ?>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($medications as $med): ?>
            <div class="bg-white rounded-lg shadow-sm p-6 hover:shadow-md transition-shadow">
                <h3 class="text-lg font-semibold text-gray-900 mb-2"><?php echo htmlspecialchars($med['name']); ?></h3>
                <div class="text-sm text-gray-600 mb-2">
                    <span class="font-medium">Dosage:</span> <?php echo htmlspecialchars($med['dosage']); ?><br>
                    <span class="font-medium">Frequency:</span> <?php echo htmlspecialchars($med['frequency']); ?><br>
                    <?php if ($med['end_date']): ?>
                        <span class="font-medium">Ended:</span> <?php echo date('M j, Y', strtotime($med['end_date'])); ?><br>
                    <?php endif; ?>
                </div>
                <div class="mt-4">
                    <button onclick="unarchiveMedication(<?php echo $med['id']; ?>)" class="bg-indigo-500 hover:bg-indigo-600 text-white px-3 py-1 rounded text-xs">
                        Un-archive
                    </button>
                </div>
                <?php if (strpos($med['notes'], 'Stopped:') !== false): ?>
                    <div class="mb-2 text-xs text-red-600">
                        <span class="font-medium">Stop Reason:</span>
                        <?php echo nl2br(htmlspecialchars(preg_replace('/.*Stopped:/s', '', $med['notes']))); ?>
                    </div>
                <?php endif; ?>
                <?php if ($med['notes']): ?>
                    <div class="mb-2 text-xs text-gray-500">
                        <span class="font-medium">Notes:</span>
                        <?php echo nl2br(htmlspecialchars($med['notes'])); ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?> 