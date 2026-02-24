<?php // test2.php – to test autoloading
require __DIR__ .'/vendor/autoload.php';
use Google\Cloud\Vision\VisionClient;
$vision = new VisionClient(['key' => ' AIzaSyAfKPSV_rFROLxFtWHyV-bLDNoyie2z52E']);
echo json_encode(['message' => 'Vision Client instantiated.']);
?>
