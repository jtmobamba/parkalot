<?php
/**
 * Vehicle API Configuration
 *
 * This file contains the configuration for external vehicle data APIs.
 *
 * To use the Check Car Details API:
 * 1. Register at https://api.checkcardetails.co.uk/
 * 2. Get your API key from the dashboard
 * 3. Replace 'YOUR_API_KEY_HERE' with your actual API key
 */

// Check Car Details API (https://api.checkcardetails.co.uk/)
define('VEHICLE_API_KEY', '2e9479403818bd5b4b42bfcb99aad74a');
define('VEHICLE_API_URL', 'https://api.checkcardetails.co.uk/vehicledata/vehicleregistration');

// Alternative: DVLA Vehicle Enquiry Service (VES) API
// https://developer-portal.driver-vehicle-licensing.api.gov.uk/
// define('DVLA_API_KEY', 'YOUR_DVLA_API_KEY');
// define('DVLA_API_URL', 'https://driver-vehicle-licensing.api.gov.uk/vehicle-enquiry/v1/vehicles');

/**
 * Get the configured API key
 * Returns null if not configured
 */
function getVehicleApiKey() {
    $key = VEHICLE_API_KEY;
    if ($key === 'YOUR_API_KEY_HERE' || empty($key)) {
        return null;
    }
    return $key;
}

/**
 * Check if the vehicle API is configured
 */
function isVehicleApiConfigured() {
    return getVehicleApiKey() !== null;
}
