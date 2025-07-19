<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/../includes/upload_handler.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$med_id = sanitizeInput($_GET['id'] ?? '', 'int');
if (!$med_id || !validateInteger($med_id, 1)) {
    logSecurityEvent('Invalid medication ID in edit', "ID: $med_id");
    $_SESSION['flash']['error'][] = 'Invalid medication ID.';
    header('Location: index.php?page=dashboard');
    exit;
}

// Fetch medication and schedule
$med = fetchOne('SELECT * FROM medications WHERE id = ? AND user_id = ?', [$med_id, $_SESSION['user_id']]);
$schedule = fetchOne('SELECT * FROM schedules WHERE medication_id = ?', [$med_id]);
if (!$med) {
    $_SESSION['flash']['error'][] = 'Medication not found.';
    header('Location: index.php?page=dashboard');
    exit;
}
?>
<div class="max-w-lg mx-auto bg-white rounded-lg shadow-sm p-8 mt-8">
    <h2 class="text-2xl font-bold mb-6 text-indigo-600">Edit Medication</h2>
    
    <!-- Medication Lookup Section -->
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
        <h3 class="text-lg font-semibold text-blue-900 mb-4">💡 Search for Medication Information</h3>
        <p class="text-blue-800 text-sm mb-4">Find detailed information about your medication including dosage, side effects, and interactions.</p>
        
        <!-- Search Input -->
        <div class="relative mb-4">
            <input type="text" 
                   id="medication-search" 
                   placeholder="Search for a medication (e.g., aspirin, ibuprofen, acetaminophen)"
                   class="w-full px-4 py-2 border border-blue-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            <div id="search-spinner" class="absolute right-3 top-2.5 hidden">
                <svg class="animate-spin h-5 w-5 text-blue-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </div>
        </div>
        
        <!-- Search Results -->
        <div id="search-results" class="hidden">
            <h4 class="font-medium text-blue-900 mb-2">Search Results:</h4>
            <div id="results-list" class="space-y-2 max-h-60 overflow-y-auto"></div>
        </div>
    </div>
    
    <form method="post" autocomplete="off" enctype="multipart/form-data">
        <?php echo getCSRFTokenField(); ?>
        <div class="mb-4">
            <label class="block mb-1 text-gray-700">Medication Name</label>
            <input type="text" name="name" id="medication-name-input" value="<?php echo htmlspecialchars($med['name']); ?>" class="border border-gray-300 rounded px-3 py-2 w-full focus:ring-indigo-200" required>
        </div>
        <div class="mb-4">
            <label class="block mb-1 text-gray-700">Dosage</label>
            <input type="text" name="dosage" value="<?php echo htmlspecialchars($med['dosage']); ?>" class="border border-gray-300 rounded px-3 py-2 w-full focus:ring-indigo-200" required>
        </div>
        <div class="mb-4">
            <label class="block mb-1 text-gray-700">Frequency</label>
            <input type="text" name="frequency" value="<?php echo htmlspecialchars($med['frequency']); ?>" class="border border-gray-300 rounded px-3 py-2 w-full focus:ring-indigo-200" required>
        </div>
        <div class="mb-4">
            <label class="block mb-1 text-gray-700">Schedule</label>
            <div class="flex items-center space-x-4">
                <label class="inline-flex items-center">
                    <input type="radio" name="schedule_type" value="time" <?php echo ($schedule && $schedule['time_of_day'] && !$schedule['interval_hours']) ? 'checked' : ''; ?> class="form-radio text-indigo-600">
                    <span class="ml-2">Time of Day</span>
                </label>
                <label class="inline-flex items-center">
                    <input type="radio" name="schedule_type" value="interval" <?php echo ($schedule && $schedule['interval_hours'] && !$schedule['time_of_day']) ? 'checked' : ''; ?> class="form-radio text-indigo-600">
                    <span class="ml-2">Every X Hours</span>
                </label>
            </div>
            <!-- Hidden input to ensure schedule_type is always sent -->
            <input type="hidden" name="schedule_type_fallback" value="<?php echo ($schedule && $schedule['time_of_day'] && !$schedule['interval_hours']) ? 'time' : (($schedule && $schedule['interval_hours'] && !$schedule['time_of_day']) ? 'interval' : 'time'); ?>">
            <div id="time-picker" class="mt-2 <?php echo ($schedule && $schedule['interval_hours'] && !$schedule['time_of_day']) ? 'hidden' : ''; ?>">
                <input type="time" name="time_of_day" value="<?php echo htmlspecialchars($schedule['time_of_day'] ?? ''); ?>" class="border border-gray-300 rounded px-3 py-2 focus:ring-indigo-200">
            </div>
            <div id="interval-picker" class="mt-2 <?php echo ($schedule && $schedule['interval_hours'] && !$schedule['time_of_day']) ? '' : 'hidden'; ?>">
                <input type="number" name="interval_hours" min="1" value="<?php echo htmlspecialchars($schedule['interval_hours'] ?? ''); ?>" class="border border-gray-300 rounded px-3 py-2 focus:ring-indigo-200" placeholder="Interval in hours">
            </div>
        </div>
        <div class="mb-4">
            <label class="block mb-1 text-gray-700">Start Date</label>
            <input type="date" name="start_date" value="<?php echo htmlspecialchars($med['start_date']); ?>" class="border border-gray-300 rounded px-3 py-2 w-full focus:ring-indigo-200">
        </div>
        <div class="mb-4">
            <label class="block mb-1 text-gray-700">End Date</label>
            <input type="date" name="end_date" value="<?php echo htmlspecialchars($med['end_date']); ?>" class="border border-gray-300 rounded px-3 py-2 w-full focus:ring-indigo-200">
        </div>
        <div class="mb-4">
            <label class="block mb-1 text-gray-700">Notes</label>
            <textarea name="notes" rows="3" class="border border-gray-300 rounded px-3 py-2 w-full focus:ring-indigo-200"><?php echo htmlspecialchars($med['notes']); ?></textarea>
        </div>
        
        <!-- Pill Photo Upload -->
        <div class="mb-4 p-4 bg-blue-50 rounded-lg">
            <h3 class="text-lg font-medium text-blue-900 mb-3">📸 Pill Photo</h3>
            <p class="text-blue-800 text-sm mb-3">Upload a photo of your medication to help with identification.</p>
            
            <!-- Current Photo Display -->
            <?php if (!empty($med['photo_url'])): ?>
            <div class="mb-4">
                <label class="block text-sm text-gray-700 mb-2">Current Photo</label>
                <div class="relative inline-block">
                    <img src="<?php echo htmlspecialchars($med['photo_url']); ?>" alt="Current pill photo" class="max-w-xs max-h-48 rounded-lg border border-gray-300">
                    <button type="button" onclick="removeCurrentPhoto()" class="absolute top-2 right-2 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center text-sm hover:bg-red-600">
                        ×
                    </button>
                </div>
                <input type="hidden" name="remove_photo" id="remove_photo" value="0">
            </div>
            <?php endif; ?>
            
            <!-- New Photo Upload -->
            <div class="space-y-3">
                <div>
                    <label class="block text-sm text-gray-700 mb-2">Upload New Photo</label>
                    <input type="file" name="pill_photo" accept="image/jpeg,image/jpg,image/png,image/webp" 
                           class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                    <p class="text-xs text-gray-500 mt-1">Accepted formats: JPEG, PNG, WebP. Maximum size: 5MB</p>
                </div>
                <div id="photo-preview" class="hidden">
                    <label class="block text-sm text-gray-700 mb-2">Preview</label>
                    <div class="relative inline-block">
                        <img id="preview-image" src="" alt="Pill preview" class="max-w-xs max-h-48 rounded-lg border border-gray-300">
                        <button type="button" onclick="removeNewPhoto()" class="absolute top-2 right-2 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center text-sm hover:bg-red-600">
                            ×
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Reminder Settings -->
        <div class="mb-4 p-4 bg-gray-50 rounded-lg">
            <h3 class="text-lg font-medium text-gray-800 mb-3">Reminder Settings</h3>
            <div class="space-y-3">
                <div class="flex items-center">
                    <input type="checkbox" name="reminder_enabled" id="reminder_enabled" <?php echo $med['reminder_enabled'] ? 'checked' : ''; ?>
                           class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                    <label for="reminder_enabled" class="ml-2 block text-sm text-gray-900">
                        Enable email reminders for this medication
                    </label>
                </div>
                <div>
                    <label class="block text-sm text-gray-700 mb-1">Remind me</label>
                    <select name="reminder_offset" class="border border-gray-300 rounded px-3 py-2 focus:ring-indigo-200">
                        <option value="0" <?php echo ($med['reminder_offset_minutes'] ?? 0) == 0 ? 'selected' : ''; ?>>At scheduled time</option>
                        <option value="5" <?php echo ($med['reminder_offset_minutes'] ?? 0) == 5 ? 'selected' : ''; ?>>5 minutes before</option>
                        <option value="10" <?php echo ($med['reminder_offset_minutes'] ?? 0) == 10 ? 'selected' : ''; ?>>10 minutes before</option>
                        <option value="15" <?php echo ($med['reminder_offset_minutes'] ?? 0) == 15 ? 'selected' : ''; ?>>15 minutes before</option>
                        <option value="30" <?php echo ($med['reminder_offset_minutes'] ?? 0) == 30 ? 'selected' : ''; ?>>30 minutes before</option>
                        <option value="60" <?php echo ($med['reminder_offset_minutes'] ?? 0) == 60 ? 'selected' : ''; ?>>1 hour before</option>
                    </select>
                </div>
            </div>
        </div>
        
        <div class="flex space-x-2">
            <button type="submit" name="update" class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded">Update</button>
            <button type="button" onclick="document.getElementById('archive-modal').classList.remove('hidden')" class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded">Archive</button>
        </div>
        <p class="mt-4 text-sm text-gray-500 text-center"><a href="index.php?page=dashboard" class="text-indigo-600 hover:underline">Back to Dashboard</a></p>
    </form>
    
    <!-- Drug Information Modal -->
    <div id="drug-info-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-900" id="modal-title">Drug Information</h3>
                    <button onclick="closeDrugModal()" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                
                <div id="modal-content" class="space-y-4">
                    <!-- Content will be loaded here -->
                </div>
                
                <div class="flex justify-end mt-6">
                    <button onclick="closeDrugModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Archive Modal -->
<div id="archive-modal" class="fixed inset-0 bg-black bg-opacity-30 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md">
        <h3 class="text-lg font-semibold mb-2">Archive Medication</h3>
        <p class="mb-4 text-gray-600">Optionally, provide a reason for stopping this medication:</p>
        <form method="post">
            <?php echo getCSRFTokenField(); ?>
            <textarea name="stop_reason" rows="2" class="border border-gray-300 rounded px-3 py-2 w-full focus:ring-indigo-200 mb-4"></textarea>
            <div class="flex space-x-2">
                <button type="submit" name="archive" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded">Archive</button>
                <button type="button" onclick="document.getElementById('archive-modal').classList.add('hidden')" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded">Cancel</button>
            </div>
        </form>
    </div>
</div>
<script>
let searchTimeout;
let currentSearchQuery = '';

// Initialize medication search
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('medication-search');
    
    searchInput.addEventListener('input', function() {
        const query = this.value.trim();
        
        // Clear previous timeout
        clearTimeout(searchTimeout);
        
        // Hide results if query is too short
        if (query.length < 2) {
            hideSearchResults();
            return;
        }
        
        // Set new timeout for search
        searchTimeout = setTimeout(() => {
            searchMedications(query);
        }, 300);
    });
    
    // Hide results when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('#medication-search') && !e.target.closest('#search-results')) {
            hideSearchResults();
        }
    });
});

// Search medications
function searchMedications(query) {
    if (query === currentSearchQuery) return;
    
    currentSearchQuery = query;
    showSearchSpinner();
    
    fetch(`ajax/medication_search.php?q=${encodeURIComponent(query)}`)
        .then(response => response.json())
        .then(data => {
            hideSearchSpinner();
            if (data.success && data.results.length > 0) {
                displaySearchResults(data.results);
            } else {
                hideSearchResults();
            }
        })
        .catch(error => {
            hideSearchSpinner();
            console.error('Search error:', error);
            hideSearchResults();
        });
}

// Display search results
function displaySearchResults(results) {
    const resultsContainer = document.getElementById('results-list');
    const searchResults = document.getElementById('search-results');
    
    resultsContainer.innerHTML = '';
    
    results.forEach(result => {
        const resultItem = document.createElement('div');
        resultItem.className = 'p-3 bg-white border border-blue-200 rounded-lg hover:bg-blue-50 cursor-pointer';
        resultItem.innerHTML = `
            <div class="flex justify-between items-center">
                <div>
                    <div class="font-medium text-blue-900">${result.name}</div>
                    <div class="text-sm text-blue-600">Source: ${result.source}</div>
                </div>
                <div class="flex space-x-2">
                    <button onclick="selectMedication('${result.name}')" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                        Select
                    </button>
                    <button onclick="getDrugInfo('${result.name}')" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                        Info
                    </button>
                </div>
            </div>
        `;
        
        resultsContainer.appendChild(resultItem);
    });
    
    searchResults.classList.remove('hidden');
}

// Select medication for the form
function selectMedication(medicationName) {
    document.getElementById('medication-name-input').value = medicationName;
    document.getElementById('medication-search').value = medicationName;
    hideSearchResults();
}

// Get detailed drug information
function getDrugInfo(drugName) {
    showDrugModal();
    
    fetch(`ajax/drug_info.php?drug=${encodeURIComponent(drugName)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.drug_info) {
                displayDrugInfo(data.drug_info);
            } else {
                displayDrugError('Drug information not found');
            }
        })
        .catch(error => {
            console.error('Drug info error:', error);
            displayDrugError('Failed to load drug information');
        });
}

// Display drug information in modal
function displayDrugInfo(drugInfo) {
    const modalTitle = document.getElementById('modal-title');
    const modalContent = document.getElementById('modal-content');
    
    modalTitle.textContent = drugInfo.name || 'Drug Information';
    
    modalContent.innerHTML = `
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Basic Information -->
            <div class="space-y-3">
                <h4 class="font-semibold text-gray-900">Basic Information</h4>
                ${drugInfo.generic_name ? `<p><strong>Generic Name:</strong> ${drugInfo.generic_name}</p>` : ''}
                ${drugInfo.brand_name ? `<p><strong>Brand Name:</strong> ${drugInfo.brand_name}</p>` : ''}
                ${drugInfo.manufacturer ? `<p><strong>Manufacturer:</strong> ${drugInfo.manufacturer}</p>` : ''}
                ${drugInfo.active_ingredients && drugInfo.active_ingredients.length > 0 ? 
                    `<p><strong>Active Ingredients:</strong> ${drugInfo.active_ingredients.join(', ')}</p>` : ''}
            </div>
            
            <!-- Clinical Information -->
            <div class="space-y-3">
                <h4 class="font-semibold text-gray-900">Clinical Information</h4>
                ${drugInfo.indications ? `<p><strong>Indications:</strong> ${drugInfo.indications}</p>` : ''}
                ${drugInfo.dosage_instructions ? `<p><strong>Dosage:</strong> ${drugInfo.dosage_instructions}</p>` : ''}
            </div>
        </div>
        
        <!-- Safety Information -->
        <div class="mt-6 space-y-3">
            <h4 class="font-semibold text-gray-900">Safety Information</h4>
            ${drugInfo.warnings ? `<p><strong>Warnings:</strong> ${drugInfo.warnings}</p>` : ''}
            ${drugInfo.side_effects ? `<p><strong>Side Effects:</strong> ${drugInfo.side_effects}</p>` : ''}
            ${drugInfo.drug_interactions ? `<p><strong>Drug Interactions:</strong> ${drugInfo.drug_interactions}</p>` : ''}
            ${drugInfo.contraindications ? `<p><strong>Contraindications:</strong> ${drugInfo.contraindications}</p>` : ''}
            ${drugInfo.precautions ? `<p><strong>Precautions:</strong> ${drugInfo.precautions}</p>` : ''}
        </div>
        
        <div class="mt-4 p-3 bg-blue-50 border border-blue-200 rounded-lg">
            <p class="text-sm text-blue-800">
                <strong>Note:</strong> This information is for educational purposes only. 
                Always consult with your healthcare provider for medical advice.
            </p>
        </div>
    `;
}

// Display drug error
function displayDrugError(message) {
    const modalContent = document.getElementById('modal-content');
    modalContent.innerHTML = `
        <div class="text-center py-8">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
            </svg>
            <p class="mt-2 text-gray-600">${message}</p>
        </div>
    `;
}

// Show/hide search spinner
function showSearchSpinner() {
    document.getElementById('search-spinner').classList.remove('hidden');
}

function hideSearchSpinner() {
    document.getElementById('search-spinner').classList.add('hidden');
}

// Show/hide search results
function hideSearchResults() {
    document.getElementById('search-results').classList.add('hidden');
    currentSearchQuery = '';
}

// Show/hide drug modal
function showDrugModal() {
    document.getElementById('drug-info-modal').classList.remove('hidden');
}

function closeDrugModal() {
    document.getElementById('drug-info-modal').classList.add('hidden');
}

// Toggle schedule input
const timeRadio = document.querySelector('input[name="schedule_type"][value="time"]');
const intervalRadio = document.querySelector('input[name="schedule_type"][value="interval"]');
const timePicker = document.getElementById('time-picker');
const intervalPicker = document.getElementById('interval-picker');

if (timeRadio && intervalRadio && timePicker && intervalPicker) {
    // Initialize on page load
    function initializeScheduleType() {
        if (timeRadio.checked) {
            timePicker.classList.remove('hidden');
            intervalPicker.classList.add('hidden');
        } else if (intervalRadio.checked) {
            intervalPicker.classList.remove('hidden');
            timePicker.classList.add('hidden');
        } else {
            // If no radio is checked, check time by default if time value exists
            if (document.querySelector('input[name="time_of_day"]').value) {
                timeRadio.checked = true;
                timePicker.classList.remove('hidden');
                intervalPicker.classList.add('hidden');
            } else if (document.querySelector('input[name="interval_hours"]').value) {
                intervalRadio.checked = true;
                intervalPicker.classList.remove('hidden');
                timePicker.classList.add('hidden');
            } else {
                // Default to time
                timeRadio.checked = true;
                timePicker.classList.remove('hidden');
                intervalPicker.classList.add('hidden');
            }
        }
    }
    
    // Initialize on page load
    initializeScheduleType();
    
    // Ensure at least one radio button is checked
    if (!timeRadio.checked && !intervalRadio.checked) {
        // Check if there's a time value, otherwise default to time
        if (document.querySelector('input[name="time_of_day"]').value) {
            timeRadio.checked = true;
        } else {
            timeRadio.checked = true; // Default to time
        }
    }
    
    // Handle radio button changes
    timeRadio.addEventListener('change', function() {
        if (this.checked) {
            timePicker.classList.remove('hidden');
            intervalPicker.classList.add('hidden');
        }
    });
    
    intervalRadio.addEventListener('change', function() {
        if (this.checked) {
            intervalPicker.classList.remove('hidden');
            timePicker.classList.add('hidden');
        }
    });
}

// Remove photo function
function removePhoto() {
    const photoInput = document.querySelector('input[name="pill_photo"]');
    const photoPreview = document.getElementById('photo-preview');
    
    if (photoInput) {
        photoInput.value = '';
    }
    if (photoPreview) {
        photoPreview.classList.add('hidden');
    }
}

// Remove current photo function (for edit form)
function removeCurrentPhoto() {
    const removePhotoInput = document.getElementById('remove_photo');
    if (removePhotoInput) {
        removePhotoInput.value = '1';
    }
    // Hide the current photo display
    const currentPhotoContainer = removePhotoInput.closest('.mb-4');
    if (currentPhotoContainer) {
        currentPhotoContainer.style.display = 'none';
    }
}

// Remove new photo function (for edit form)
function removeNewPhoto() {
    const photoInput = document.querySelector('input[name="pill_photo"]');
    const photoPreview = document.getElementById('photo-preview');
    
    if (photoInput) {
        photoInput.value = '';
    }
    if (photoPreview) {
        photoPreview.classList.add('hidden');
    }
}
</script> 