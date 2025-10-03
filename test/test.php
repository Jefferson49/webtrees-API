<?php


// Encode cURL request
$url = 'https://127.0.0.1/webtrees/index.php?route=%2Fwebtrees/api';

// Use cURL to create an API request
$ch = curl_init();
curl_setopt($ch, CURLOPT_USERAGENT, "McpUserAgent/1.0");
curl_setopt($ch, CURLOPT_URL, $url);
#curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPGET, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER , true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Disable SSL certificate verification
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // Disable host verification
curl_setopt($ch, CURLOPT_TIMEOUT, 60); 
$response = curl_exec($ch);
$curl_error = curl_error($ch);

// Check for errors
if (curl_errno($ch)) {
    $error_message = curl_error($ch);
    $error_code = curl_errno($ch);
    echo "cURL Error: [$error_code] $error_message";
} else {
    echo 'Response: ' . $response;
}

// Close the cURL session
curl_close($ch);
