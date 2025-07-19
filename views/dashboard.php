<?php
require_once __DIR__ . '/../includes/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// Get current medications for the user
$medications = fetchAll('
    SELECT m.*, 
           (SELECT MAX(taken_at) FROM logs WHERE medication_id = m.id) as last_taken
    FROM medications m 
    WHERE m.user_id = ? AND m.is_active = 1
    ORDER BY m.name
', [$_SESSION['user_id']]);

include __DIR__ . '/../includes/templates/flash.php';
?>

<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</h1>
    <p class="text-gray-600">Manage your medications and track your doses.</p>
</div>

<div class="mb-6 flex justify-between items-center">
    <h2 class="text-xl font-semibold text-gray-800">Current Medications</h2>
    <a href="index.php?page=add-medication" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded">+ Add Medication</a>
</div>

<?php if (empty($medications)): ?>
    <div class="text-center py-12">
        <div class="text-gray-400 mb-4">
            <svg class="mx-auto h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2" />
            </svg>
        </div>
        <h3 class="text-lg font-medium text-gray-900 mb-2">No medications yet</h3>
        <p class="text-gray-500 mb-4">Get started by adding your first medication.</p>
        <a href="index.php?page=add-medication" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2 rounded">Add Your First Medication</a>
    </div>
<?php else: ?>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($medications as $med): ?>
            <div class="bg-white rounded-lg shadow-sm p-6 hover:shadow-md transition-shadow">
                <div class="flex justify-between items-start mb-4">
                    <h3 class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($med['name']); ?></h3>
                    <a href="index.php?page=edit-medication&id=<?php echo $med['id']; ?>" class="text-gray-400 hover:text-indigo-600" title="Edit">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536M9 13l6-6m2 2l-6 6m2-2l-6 6" />
                        </svg>
                    </a>
                </div>
                
                <!-- Pill Photo -->
                <?php if (!empty($med['photo_url'])): ?>
                <div class="mb-4">
                    <img src="<?php echo htmlspecialchars($med['photo_url']); ?>" 
                         alt="Photo of <?php echo htmlspecialchars($med['name']); ?>" 
                         class="w-full h-32 object-cover rounded-lg border border-gray-200"
                         onclick="showPhotoModal('<?php echo htmlspecialchars($med['photo_url']); ?>', '<?php echo htmlspecialchars($med['name']); ?>')"
                         style="cursor: pointer;">
                </div>
                <?php endif; ?>
                
                <div class="space-y-2 mb-4">
                    <div class="flex items-center text-sm text-gray-600">
                        <span class="font-medium">Dosage:</span>
                        <span class="ml-2"><?php echo htmlspecialchars($med['dosage']); ?></span>
                    </div>
                    <div class="flex items-center text-sm text-gray-600">
                        <span class="font-medium">Frequency:</span>
                        <span class="ml-2"><?php echo htmlspecialchars($med['frequency']); ?></span>
                    </div>
                    <?php if ($med['last_taken']): ?>
                        <div class="flex items-center text-sm text-gray-600">
                            <span class="font-medium">Last taken:</span>
                            <span class="ml-2"><?php echo date('M j, Y g:i A', strtotime($med['last_taken'])); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="flex space-x-2">
                    <button onclick="showMarkTakenModal(<?php echo $med['id']; ?>, '<?php echo htmlspecialchars($med['name']); ?>')" class="flex-1 bg-emerald-500 hover:bg-emerald-600 text-white px-3 py-2 rounded text-sm">Mark as Taken</button>
                    <button onclick="toggleDetails(<?php echo $med['id']; ?>)" class="px-3 py-2 text-gray-600 hover:text-indigo-600 border border-gray-300 rounded text-sm">Details</button>
                </div>
                <div id="details-<?php echo $med['id']; ?>" class="hidden mt-4 pt-4 border-t border-gray-200">
                    <?php if ($med['notes']): ?>
                        <div class="mb-3">
                            <h4 class="text-sm font-medium text-gray-700 mb-1">Notes:</h4>
                            <p class="text-sm text-gray-600"><?php echo nl2br(htmlspecialchars($med['notes'])); ?></p>
                        </div>
                    <?php endif; ?>
                    <div class="text-xs text-gray-500">
                        Started: <?php echo $med['start_date'] ? date('M j, Y', strtotime($med['start_date'])) : 'Not specified'; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Mark as Taken Modal -->
<div id="mark-taken-modal" class="fixed inset-0 bg-black bg-opacity-30 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md">
        <h3 class="text-lg font-semibold mb-2">Mark as Taken</h3>
        <p class="mb-4 text-gray-600">Marking <span id="medication-name"></span> as taken</p>
        <form id="mark-taken-form">
            <input type="hidden" id="medication-id" name="medication_id">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Note (optional)</label>
                <textarea id="dose-note" name="note" rows="3" class="border border-gray-300 rounded px-3 py-2 w-full focus:ring-indigo-200" placeholder="Any notes about this dose..."></textarea>
            </div>
            <div class="flex space-x-2">
                <button type="submit" class="bg-emerald-500 hover:bg-emerald-600 text-white px-4 py-2 rounded">Mark as Taken</button>
                <button type="button" onclick="hideMarkTakenModal()" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Photo Modal -->
<div id="photo-modal" class="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50 hidden">
    <div class="relative max-w-4xl max-h-full p-4">
        <div class="bg-white rounded-lg shadow-lg overflow-hidden">
            <div class="flex justify-between items-center p-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900" id="photo-modal-title">Pill Photo</h3>
                <button onclick="hidePhotoModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <div class="p-4">
                <img id="photo-modal-image" src="" alt="Pill photo" class="max-w-full max-h-96 object-contain mx-auto">
            </div>
        </div>
    </div>
</div>

<script>
function showMarkTakenModal(medicationId, medicationName) {
    document.getElementById('medication-id').value = medicationId;
    document.getElementById('medication-name').textContent = medicationName;
    document.getElementById('dose-note').value = '';
    document.getElementById('mark-taken-modal').classList.remove('hidden');
}

function hideMarkTakenModal() {
    document.getElementById('mark-taken-modal').classList.add('hidden');
}

function markAsTaken(medicationId, note) {
    fetch('ajax/mark-taken.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ 
            medication_id: medicationId,
            note: note
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
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

function toggleDetails(medicationId) {
    const details = document.getElementById('details-' + medicationId);
    details.classList.toggle('hidden');
}

function showPhotoModal(photoUrl, medicationName) {
    document.getElementById('photo-modal-image').src = photoUrl;
    document.getElementById('photo-modal-title').textContent = medicationName + ' - Pill Photo';
    document.getElementById('photo-modal').classList.remove('hidden');
}

function hidePhotoModal() {
    document.getElementById('photo-modal').classList.add('hidden');
}

// Close photo modal when clicking outside
document.getElementById('photo-modal').addEventListener('click', function(e) {
    if (e.target === this) {
        hidePhotoModal();
    }
});

// Handle form submission
document.getElementById('mark-taken-form').addEventListener('submit', function(e) {
    e.preventDefault();
    const medicationId = document.getElementById('medication-id').value;
    const note = document.getElementById('dose-note').value;
    markAsTaken(medicationId, note);
    hideMarkTakenModal();
});
</script> 