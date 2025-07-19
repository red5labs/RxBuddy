<?php
// This is a partial view for medication lookup functionality
// It can be included in medication forms to provide drug search capabilities
?>

<!-- Medication Lookup Section -->
<div class="bg-gray-50 rounded-lg p-4 mb-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">Search for Medication Information</h3>
    
    <!-- Search Input -->
    <div class="relative mb-4">
        <input type="text" 
               id="medication-search" 
               placeholder="Search for a medication (e.g., aspirin, ibuprofen, acetaminophen)"
               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
        <div id="search-spinner" class="absolute right-3 top-2.5 hidden">
            <svg class="animate-spin h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
        </div>
    </div>
    
    <!-- Search Results -->
    <div id="search-results" class="hidden">
        <h4 class="font-medium text-gray-900 mb-2">Search Results:</h4>
        <div id="results-list" class="space-y-2 max-h-60 overflow-y-auto"></div>
    </div>
    
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
        resultItem.className = 'p-3 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 cursor-pointer';
        resultItem.innerHTML = `
            <div class="flex justify-between items-center">
                <div>
                    <div class="font-medium text-gray-900">${result.name}</div>
                    <div class="text-sm text-gray-500">Source: ${result.source}</div>
                </div>
                <button onclick="getDrugInfo('${result.name}')" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium">
                    View Info
                </button>
            </div>
        `;
        
        // Also allow clicking on the item to fill the search field
        resultItem.addEventListener('click', function(e) {
            if (!e.target.closest('button')) {
                document.getElementById('medication-search').value = result.name;
                hideSearchResults();
            }
        });
        
        resultsContainer.appendChild(resultItem);
    });
    
    searchResults.classList.remove('hidden');
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
</script> 