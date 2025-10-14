<?php

/**
 * MapService Class
 * Handles geocoding and location-related operations
 */
class MapService {
    private $geocodeApiKey;
    private $reverseGeocodeApiKey;
    
    public function __construct($apiKey = null) {
        // You can set API keys for services like Google Maps, OpenStreetMap Nominatim, etc.
        $this->geocodeApiKey = $apiKey ?? null;
        $this->reverseGeocodeApiKey = $apiKey ?? null;
    }
    
    /**
     * Geocode an address to get coordinates
     * @param string $address - Address to geocode
     * @return object|null - Location object with address, latitude, longitude
     */
    public function geocode($address) {
        try {
            // Clean and validate address
            $address = trim($address);
            if (empty($address)) {
                return null;
            }
            
            // Try multiple geocoding methods
            $result = $this->geocodeWithNominatim($address);
            
            // If Nominatim fails and we have a Google API key, try Google
            if (!$result && $this->geocodeApiKey) {
                $result = $this->geocodeWithGoogle($address);
            }
            
            // If both fail, return a default location for demonstration
            if (!$result) {
                return $this->getDefaultLocation($address);
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("MapService geocode error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Reverse geocode coordinates to get address
     * @param float $latitude - Latitude
     * @param float $longitude - Longitude
     * @return string|null - Formatted address
     */
    public function reverseGeocode($latitude, $longitude) {
        try {
            // Validate coordinates
            if (!is_numeric($latitude) || !is_numeric($longitude)) {
                return null;
            }
            
            if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
                return null;
            }
            
            // Try reverse geocoding with Nominatim
            $address = $this->reverseGeocodeWithNominatim($latitude, $longitude);
            
            // If Nominatim fails and we have a Google API key, try Google
            if (!$address && $this->reverseGeocodeApiKey) {
                $address = $this->reverseGeocodeWithGoogle($latitude, $longitude);
            }
            
            return $address;
        } catch (Exception $e) {
            error_log("MapService reverseGeocode error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Generate embedded map URL or HTML for a location
     * @param object $location - Location object with latitude and longitude
     * @param int $width - Map width in pixels
     * @param int $height - Map height in pixels
     * @param int $zoom - Zoom level (1-20)
     * @return string - Map embed HTML or URL
     */
    public function generateEmbedMap($location, $width = 400, $height = 300, $zoom = 15) {
        try {
            if (!$location || !isset($location->latitude) || !isset($location->longitude)) {
                return '';
            }
            
            $lat = $location->latitude;
            $lng = $location->longitude;
            
            // Generate OpenStreetMap embed using Leaflet
            $mapHtml = "
            <div id='map-{$lat}-{$lng}' style='width: {$width}px; height: {$height}px; border: 1px solid #ccc; border-radius: 8px;'></div>
            <script src='https://unpkg.com/leaflet@1.7.1/dist/leaflet.js'></script>
            <link rel='stylesheet' href='https://unpkg.com/leaflet@1.7.1/dist/leaflet.css' />
            <script>
                var map = L.map('map-{$lat}-{$lng}').setView([{$lat}, {$lng}], {$zoom});
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap contributors'
                }).addTo(map);
                L.marker([{$lat}, {$lng}]).addTo(map)
                    .bindPopup('" . htmlspecialchars($location->address ?? 'Report Location') . "')
                    .openPopup();
            </script>";
            
            return $mapHtml;
        } catch (Exception $e) {
            error_log("MapService generateEmbedMap error: " . $e->getMessage());
            return '';
        }
    }
    
    /**
     * Get distance between two locations in kilometers
     * @param float $lat1 - First location latitude
     * @param float $lng1 - First location longitude  
     * @param float $lat2 - Second location latitude
     * @param float $lng2 - Second location longitude
     * @return float - Distance in kilometers
     */
    public function getDistance($lat1, $lng1, $lat2, $lng2) {
        try {
            // Haversine formula to calculate distance
            $earthRadius = 6371; // Earth's radius in kilometers
            
            $dLat = deg2rad($lat2 - $lat1);
            $dLng = deg2rad($lng2 - $lng1);
            
            $a = sin($dLat/2) * sin($dLat/2) +
                 cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * 
                 sin($dLng/2) * sin($dLng/2);
                 
            $c = 2 * atan2(sqrt($a), sqrt(1-$a));
            
            $distance = $earthRadius * $c;
            
            return round($distance, 2);
        } catch (Exception $e) {
            error_log("MapService getDistance error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Geocode using OpenStreetMap Nominatim (free service)
     * @param string $address - Address to geocode
     * @return object|null - Location object
     */
    private function geocodeWithNominatim($address) {
        try {
            $url = 'https://nominatim.openstreetmap.org/search';
            $params = [
                'q' => $address,
                'format' => 'json',
                'limit' => 1,
                'countrycodes' => 'bd', // Restrict to Bangladesh, change as needed
                'addressdetails' => 1
            ];
            
            $response = $this->makeHttpRequest($url . '?' . http_build_query($params));
            
            if ($response) {
                $data = json_decode($response, true);
                
                if (!empty($data) && isset($data[0]['lat']) && isset($data[0]['lon'])) {
                    return (object) [
                        'address' => $data[0]['display_name'] ?? $address,
                        'latitude' => (float)$data[0]['lat'],
                        'longitude' => (float)$data[0]['lon']
                    ];
                }
            }
            
            return null;
        } catch (Exception $e) {
            error_log("MapService geocodeWithNominatim error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Geocode using Google Maps API (requires API key)
     * @param string $address - Address to geocode
     * @return object|null - Location object
     */
    private function geocodeWithGoogle($address) {
        try {
            if (!$this->geocodeApiKey) {
                return null;
            }
            
            $url = 'https://maps.googleapis.com/maps/api/geocode/json';
            $params = [
                'address' => $address,
                'key' => $this->geocodeApiKey
            ];
            
            $response = $this->makeHttpRequest($url . '?' . http_build_query($params));
            
            if ($response) {
                $data = json_decode($response, true);
                
                if (isset($data['results'][0]['geometry']['location'])) {
                    $location = $data['results'][0]['geometry']['location'];
                    
                    return (object) [
                        'address' => $data['results'][0]['formatted_address'] ?? $address,
                        'latitude' => (float)$location['lat'],
                        'longitude' => (float)$location['lng']
                    ];
                }
            }
            
            return null;
        } catch (Exception $e) {
            error_log("MapService geocodeWithGoogle error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Reverse geocode using Nominatim
     * @param float $latitude - Latitude
     * @param float $longitude - Longitude
     * @return string|null - Address
     */
    private function reverseGeocodeWithNominatim($latitude, $longitude) {
        try {
            $url = 'https://nominatim.openstreetmap.org/reverse';
            $params = [
                'lat' => $latitude,
                'lon' => $longitude,
                'format' => 'json',
                'addressdetails' => 1
            ];
            
            $response = $this->makeHttpRequest($url . '?' . http_build_query($params));
            
            if ($response) {
                $data = json_decode($response, true);
                
                if (isset($data['display_name'])) {
                    return $data['display_name'];
                }
            }
            
            return null;
        } catch (Exception $e) {
            error_log("MapService reverseGeocodeWithNominatim error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Reverse geocode using Google Maps API
     * @param float $latitude - Latitude
     * @param float $longitude - Longitude
     * @return string|null - Address
     */
    private function reverseGeocodeWithGoogle($latitude, $longitude) {
        try {
            if (!$this->reverseGeocodeApiKey) {
                return null;
            }
            
            $url = 'https://maps.googleapis.com/maps/api/geocode/json';
            $params = [
                'latlng' => "$latitude,$longitude",
                'key' => $this->reverseGeocodeApiKey
            ];
            
            $response = $this->makeHttpRequest($url . '?' . http_build_query($params));
            
            if ($response) {
                $data = json_decode($response, true);
                
                if (isset($data['results'][0]['formatted_address'])) {
                    return $data['results'][0]['formatted_address'];
                }
            }
            
            return null;
        } catch (Exception $e) {
            error_log("MapService reverseGeocodeWithGoogle error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get default location for demonstration purposes
     * @param string $address - Original address
     * @return object - Default location object
     */
    private function getDefaultLocation($address) {
        // Default to Dhaka, Bangladesh coordinates for demonstration
        return (object) [
            'address' => $address . ' (Approximate Location - Dhaka, Bangladesh)',
            'latitude' => 23.8103,
            'longitude' => 90.4125
        ];
    }
    
    /**
     * Make HTTP request with timeout and error handling
     * @param string $url - URL to request
     * @param int $timeout - Request timeout in seconds
     * @return string|null - Response body or null on failure
     */
    private function makeHttpRequest($url, $timeout = 10) {
        try {
            // Use cURL if available
            if (function_exists('curl_init')) {
                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => $url,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => $timeout,
                    CURLOPT_USERAGENT => 'CivicVoice/1.0',
                    CURLOPT_SSL_VERIFYPEER => false, // For development only
                    CURLOPT_FOLLOWLOCATION => true
                ]);
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($httpCode === 200 && $response !== false) {
                    return $response;
                }
            }
            // Fallback to file_get_contents
            else if (ini_get('allow_url_fopen')) {
                $context = stream_context_create([
                    'http' => [
                        'timeout' => $timeout,
                        'user_agent' => 'CivicVoice/1.0'
                    ]
                ]);
                
                $response = file_get_contents($url, false, $context);
                
                if ($response !== false) {
                    return $response;
                }
            }
            
            return null;
        } catch (Exception $e) {
            error_log("MapService makeHttpRequest error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Validate location coordinates
     * @param float $latitude - Latitude to validate
     * @param float $longitude - Longitude to validate
     * @return bool - Coordinates are valid
     */
    public function validateCoordinates($latitude, $longitude) {
        return is_numeric($latitude) && 
               is_numeric($longitude) && 
               $latitude >= -90 && 
               $latitude <= 90 && 
               $longitude >= -180 && 
               $longitude <= 180;
    }
    
    /**
     * Format coordinates for display
     * @param float $latitude - Latitude
     * @param float $longitude - Longitude
     * @return string - Formatted coordinates
     */
    public function formatCoordinates($latitude, $longitude) {
        if (!$this->validateCoordinates($latitude, $longitude)) {
            return '';
        }
        
        return sprintf("%.6f, %.6f", $latitude, $longitude);
    }
}