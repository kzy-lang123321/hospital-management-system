<?php
require_once __DIR__ . '/../core/PdfCredentials.php';

$credsFile = __DIR__ . '/management_credentials.json';
if (!file_exists($credsFile)) {
    die("Credentials file not found\n");
}

$creds = json_decode(file_get_contents($credsFile), true);
$outPath = __DIR__ . '/../exports/Hospital_Management_Credentials.pdf';

$pdf = PdfCredentials::generate($creds, $outPath);
echo "SUCCESS: PDF generated at $outPath (" . strlen($pdf) . " bytes)\n";
