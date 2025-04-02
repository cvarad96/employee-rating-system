<?php
/**
 * VAPID Key Generator Script for Web Push Notifications
 * 
 * This script generates VAPID keys required for implementing Web Push Notifications
 * in the Employee Rating System.
 * 
 * Requirements:
 * - PHP 7.4 or higher
 * - OpenSSL extension
 * 
 * Usage:
 * - From command line: php generate-vapid-keys.php
 * - The generated keys will be displayed in the terminal
 * - Copy these keys to your .env file or configuration
 */

echo "VAPID Key Generator for Web Push Notifications\n";
echo "---------------------------------------------\n\n";

// Check OpenSSL extension
if (!extension_loaded('openssl')) {
    echo "Error: OpenSSL extension is required but not available.\n";
    exit(1);
}

// Generate VAPID keys
echo "Generating VAPID keys...\n";

try {
    // Create ECDSA key with P-256 curve (required for VAPID)
    $res = openssl_pkey_new([
        'curve_name' => 'prime256v1',
        'private_key_type' => OPENSSL_KEYTYPE_EC,
    ]);
    
    if (!$res) {
        throw new Exception("Failed to generate key pair: " . openssl_error_string());
    }
    
    // Get private key
    $privKey = '';
    openssl_pkey_export($res, $privKey);
    
    // Get public key
    $pubKey = openssl_pkey_get_details($res);
    $pubKey = $pubKey["key"];
    
    // Convert to uncompressed point format
    $ec_key_details = openssl_pkey_get_details($res);
    $x = bin2hex($ec_key_details['ec']['x']);
    $y = bin2hex($ec_key_details['ec']['y']);
    
    // VAPID requires URL-safe base64 encoding
    $private_key = urlsafe_base64_encode(openssl_pkey_get_private($privKey));
    $public_key = urlsafe_base64_encode(hex2bin('04' . $x . $y));
    
    echo "\nVAPID Keys generated successfully!\n\n";
    echo "Public Key:\n$public_key\n\n";
    echo "Private Key:\n$private_key\n\n";
    
    echo "Add these to your server configuration.\n";
    echo "For example, in your PHP code:\n\n";
    
    echo '$auth = [' . "\n";
    echo '    "VAPID" => [' . "\n";
    echo '        "subject" => "mailto:admin@yoursite.com",' . "\n";
    echo '        "publicKey" => "' . $public_key . '",' . "\n";
    echo '        "privateKey" => "' . $private_key . '"' . "\n";
    echo '    ]' . "\n";
    echo '];' . "\n\n";
    
    echo "Replace 'mailto:admin@yoursite.com' with your actual contact email.\n";
    
    echo "\nThen update the public key in your push-notifications.js file:\n\n";
    echo "const VAPID_PUBLIC_KEY = '$public_key';\n\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

/**
 * URL-safe Base64 encoding
 */
function urlsafe_base64_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}
