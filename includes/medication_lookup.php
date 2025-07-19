<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/validation.php';

class MedicationLookup {
    private $openfda_api_key;
    private $cache_duration = 86400; // 24 hours
    
    public function __construct() {
        // Load OpenFDA API key from config
        $this->openfda_api_key = defined('OPENFDA_API_KEY') ? OPENFDA_API_KEY : null;
    }
    
    /**
     * Search for medications using RxNorm API
     */
    public function searchRxNorm($query, $limit = 10) {
        $query = urlencode(trim($query));
        $url = "https://rxnav.nlm.nih.gov/REST/spellingsuggestions.json?name={$query}";
        
        try {
            $response = file_get_contents($url);
            if ($response === false) {
                throw new Exception('Failed to fetch from RxNorm API');
            }
            
            $data = json_decode($response, true);
            
            if (!$data || !isset($data['suggestionGroup']['suggestionList']['suggestion'])) {
                return [];
            }
            
            $suggestions = $data['suggestionGroup']['suggestionList']['suggestion'];
            $results = [];
            
            // Handle both single suggestion and array of suggestions
            if (!is_array($suggestions)) {
                $suggestions = [$suggestions];
            }
            
            foreach (array_slice($suggestions, 0, $limit) as $suggestion) {
                // Handle both string and array formats
                if (is_string($suggestion)) {
                    $results[] = [
                        'name' => $suggestion,
                        'source' => 'RxNorm',
                        'type' => 'suggestion'
                    ];
                } elseif (is_array($suggestion) && isset($suggestion['name'])) {
                    $results[] = [
                        'name' => $suggestion['name'],
                        'source' => 'RxNorm',
                        'type' => 'suggestion'
                    ];
                }
            }
            
            return $results;
            
        } catch (Exception $e) {
            error_log('RxNorm API error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get detailed drug information from OpenFDA
     */
    public function getDrugInfo($drug_name) {
        // Check cache first
        $cached = $this->getCachedDrugInfo($drug_name);
        if ($cached) {
            return $cached;
        }
        
        $drug_name = urlencode(trim($drug_name));
        $url = "https://api.fda.gov/drug/label.json?search=openfda.brand_name:{$drug_name}+OR+openfda.generic_name:{$drug_name}&limit=1";
        
        if ($this->openfda_api_key) {
            $url .= "&api_key={$this->openfda_api_key}";
        }
        
        try {
            $response = file_get_contents($url);
            if ($response === false) {
                throw new Exception('Failed to fetch from OpenFDA API');
            }
            
            $data = json_decode($response, true);
            
            if (!$data || !isset($data['results']) || empty($data['results'])) {
                return null;
            }
            
            $drug_info = $this->parseOpenFDAResponse($data['results'][0]);
            
            // Cache the result
            $this->cacheDrugInfo($drug_name, $drug_info);
            
            return $drug_info;
            
        } catch (Exception $e) {
            error_log('OpenFDA API error: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Parse OpenFDA API response into structured data
     */
    private function parseOpenFDAResponse($result) {
        $info = [
            'name' => '',
            'generic_name' => '',
            'brand_name' => '',
            'dosage_forms' => [],
            'strengths' => [],
            'indications' => '',
            'warnings' => '',
            'side_effects' => '',
            'drug_interactions' => '',
            'dosage_instructions' => '',
            'precautions' => '',
            'contraindications' => '',
            'pregnancy_category' => '',
            'manufacturer' => '',
            'active_ingredients' => []
        ];
        
        // Basic drug information
        if (isset($result['openfda']['generic_name'][0])) {
            $info['generic_name'] = $result['openfda']['generic_name'][0];
        }
        if (isset($result['openfda']['brand_name'][0])) {
            $info['brand_name'] = $result['openfda']['brand_name'][0];
        }
        if (isset($result['openfda']['manufacturer_name'][0])) {
            $info['manufacturer'] = $result['openfda']['manufacturer_name'][0];
        }
        
        // Set primary name
        $info['name'] = $info['brand_name'] ?: $info['generic_name'];
        
        // Dosage forms and strengths
        if (isset($result['openfda']['dosage_form'])) {
            $info['dosage_forms'] = $result['openfda']['dosage_form'];
        }
        if (isset($result['openfda']['active_ingredient'])) {
            $info['active_ingredients'] = $result['openfda']['active_ingredient'];
        }
        
        // Clinical information
        if (isset($result['indications_and_usage'][0])) {
            $info['indications'] = $this->truncateText($result['indications_and_usage'][0], 500);
        }
        if (isset($result['warnings'][0])) {
            $info['warnings'] = $this->truncateText($result['warnings'][0], 300);
        }
        if (isset($result['adverse_reactions'][0])) {
            $info['side_effects'] = $this->truncateText($result['adverse_reactions'][0], 400);
        }
        if (isset($result['drug_interactions'][0])) {
            $info['drug_interactions'] = $this->truncateText($result['drug_interactions'][0], 300);
        }
        if (isset($result['dosage_and_administration'][0])) {
            $info['dosage_instructions'] = $this->truncateText($result['dosage_and_administration'][0], 400);
        }
        if (isset($result['precautions'][0])) {
            $info['precautions'] = $this->truncateText($result['precautions'][0], 300);
        }
        if (isset($result['contraindications'][0])) {
            $info['contraindications'] = $this->truncateText($result['contraindications'][0], 300);
        }
        if (isset($result['pregnancy'][0])) {
            $info['pregnancy_category'] = $this->truncateText($result['pregnancy'][0], 200);
        }
        
        return $info;
    }
    
    /**
     * Truncate text to specified length
     */
    private function truncateText($text, $length) {
        if (strlen($text) <= $length) {
            return $text;
        }
        return substr($text, 0, $length) . '...';
    }
    
    /**
     * Cache drug information
     */
    private function cacheDrugInfo($drug_name, $drug_info) {
        try {
            $cache_key = 'drug_info_' . md5(strtolower($drug_name));
            $cache_data = [
                'drug_name' => $drug_name,
                'drug_info' => $drug_info,
                'cached_at' => time()
            ];
            
            executeQuery('
                INSERT INTO api_cache (cache_key, cache_data, expires_at)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE
                cache_data = VALUES(cache_data),
                expires_at = VALUES(expires_at)
            ', [
                $cache_key,
                json_encode($cache_data),
                date('Y-m-d H:i:s', time() + $this->cache_duration)
            ]);
            
        } catch (Exception $e) {
            error_log('Failed to cache drug info: ' . $e->getMessage());
        }
    }
    
    /**
     * Get cached drug information
     */
    private function getCachedDrugInfo($drug_name) {
        try {
            $cache_key = 'drug_info_' . md5(strtolower($drug_name));
            
            $cached = fetchOne('
                SELECT cache_data FROM api_cache 
                WHERE cache_key = ? AND expires_at > NOW()
            ', [$cache_key]);
            
            if ($cached) {
                $data = json_decode($cached['cache_data'], true);
                return $data['drug_info'] ?? null;
            }
            
            return null;
            
        } catch (Exception $e) {
            error_log('Failed to get cached drug info: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Search for medications using both APIs
     */
    public function searchMedications($query, $limit = 10) {
        $results = [];
        
        // Search RxNorm for suggestions
        $rxnorm_results = $this->searchRxNorm($query, $limit);
        $results = array_merge($results, $rxnorm_results);
        
        // If we have OpenFDA API key, also search there
        if ($this->openfda_api_key) {
            $openfda_results = $this->searchOpenFDA($query, $limit);
            $results = array_merge($results, $openfda_results);
        }
        
        // Remove duplicates and limit results
        $unique_results = [];
        $seen_names = [];
        
        foreach ($results as $result) {
            $name_lower = strtolower($result['name']);
            if (!in_array($name_lower, $seen_names)) {
                $unique_results[] = $result;
                $seen_names[] = $name_lower;
            }
        }
        
        return array_slice($unique_results, 0, $limit);
    }
    
    /**
     * Search OpenFDA API
     */
    private function searchOpenFDA($query, $limit = 5) {
        $query = urlencode(trim($query));
        $url = "https://api.fda.gov/drug/label.json?search=openfda.brand_name:{$query}+OR+openfda.generic_name:{$query}&limit={$limit}";
        
        if ($this->openfda_api_key) {
            $url .= "&api_key={$this->openfda_api_key}";
        }
        
        try {
            $response = file_get_contents($url);
            if ($response === false) {
                throw new Exception('Failed to fetch from OpenFDA API');
            }
            
            $data = json_decode($response, true);
            
            if (!$data || !isset($data['results'])) {
                return [];
            }
            
            $results = [];
            foreach ($data['results'] as $result) {
                $name = '';
                if (isset($result['openfda']['brand_name'][0])) {
                    $name = $result['openfda']['brand_name'][0];
                } elseif (isset($result['openfda']['generic_name'][0])) {
                    $name = $result['openfda']['generic_name'][0];
                }
                
                if ($name) {
                    $results[] = [
                        'name' => $name,
                        'source' => 'OpenFDA',
                        'type' => 'drug_info'
                    ];
                }
            }
            
            return $results;
            
        } catch (Exception $e) {
            error_log('OpenFDA search error: ' . $e->getMessage());
            return [];
        }
    }
}
?> 