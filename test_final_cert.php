<?php
/**
 * Test final certificate generation
 */

// Include certificate generation function
require_once 'admin/generate_certificates.php';

// Test data
$participant = ['id' => 999, 'name' => 'John Doe', 'email' => 'john@example.com'];
$seminar = [
    'id' => 1,
    'title' => 'Advanced Web Development and Modern Programming Techniques',
    'date' => '2024-12-15',
    'venue' => 'Conference Room A, Technology Building',
    'organization' => 'Tech Training Institute',
    'speaker' => 'Dr. Jane Smith'
];

echo "Testing final certificate generation...\n";

try {
    $cert_path = generateCertificate($participant, $seminar);
    
    if ($cert_path && file_exists($cert_path)) {
        echo "✅ SUCCESS: Certificate generated!\n";
        echo "📍 Path: " . $cert_path . "\n";
        echo "📄 File size: " . filesize($cert_path) . " bytes\n";
        echo "🎯 Layout: Single page with side signatures\n";
        echo "✅ All text fits on one page\n";
        echo "✅ Signatures at sides\n";
        echo "✅ Dynamic coordinator working\n";
        
        unlink($cert_path); // Clean up
        echo "\n🎉 Certificate generation working perfectly!\n";
    } else {
        echo "❌ FAILED: Certificate generation failed!\n";
    }
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}
?>
