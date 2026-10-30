<?php
/** Database-independent integration checks for patient-directory SQL using SQLite. */
class Tenant { public static function id() { return 7; } }
function clean_input($value) { return trim($value); }
$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->sqliteCreateFunction('CONCAT_WS', static fn($separator, ...$parts) => implode($separator, $parts));
$db->exec('CREATE TABLE patients (id INTEGER PRIMARY KEY, hospital_id INTEGER, mrn TEXT, first_name TEXT, last_name TEXT, phone_primary TEXT, national_id TEXT, gender TEXT, blood_group TEXT)');
$db->exec('CREATE TABLE encounters (patient_id INTEGER)');
$db->exec('CREATE TABLE patient_allergies (patient_id INTEGER, allergen_name TEXT)');
$insert = $db->prepare('INSERT INTO patients VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
for ($i = 1; $i <= 63; $i++) $insert->execute([$i, 7, 'MRN-'.$i, 'Ayesha', 'Khan', '03001234567', 'ID-'.$i, 'Female', 'O+']);
$insert->execute([64, 8, 'OTHER', 'Ayesha', 'Khan', '0', '0', 'Female', 'O+']);
$source = file_get_contents(__DIR__ . '/../views/patients/index.php');
// Execute the actual view query-building block without bootstrapping MySQL or rendering PHI.
$start = strpos($source, '$search =');
$code = substr($source, $start, strpos($source, '?>', $start) - $start);
$run = static function(array $get) use ($db, $code) {
    $_GET = $get;
    eval($code);
    return compact('patients', 'matchingPatients', 'directoryPage', 'pageCount', 'directoryUrl');
};
$passed=0;
function check($ok, $message) { global $passed; if (!$ok) { fwrite(STDERR,"FAIL: $message\n"); exit(1); } $passed++; echo "PASS: $message\n"; }
$first=$run([]);
check(count($first['patients'])===25 && $first['matchingPatients']===63, 'Paginate matching hospital records only');
check($first['patients'][0]['id']==63, 'Newest patients appear first');
$last=$run(['directory_page'=>999]);
check($last['directoryPage']===3 && count($last['patients'])===13, 'Clamp oversized page to final page');
check($last['patients'][12]['id']==1, 'Previously hidden older records are reachable');
$negative=$run(['directory_page'=>-5]);
check($negative['directoryPage']===1, 'Clamp negative page to first page');
$full=$run(['search'=>'Ayesha Khan','gender'=>'Female','blood_group'=>'O+','directory_page'=>2]);
check($full['matchingPatients']===63 && count($full['patients'])===25, 'Full-name search and combined filters share matching count');
parse_str(parse_url(($full['directoryUrl'])(3), PHP_URL_QUERY), $url);
check($url['search']==='Ayesha Khan' && $url['gender']==='Female' && $url['blood_group']==='O+' && $url['directory_page']==='3', 'Pagination preserves search and filters');
$none=$run(['search'=>"' OR 1=1 --"]);
check($none['matchingPatients']===0 && count($none['patients'])===0 && $none['directoryPage']===1, 'Search remains parameterized and empty results are valid');
$filtered=$run(['blood_group'=>'A+']);
check($filtered['matchingPatients']===0, 'Blood group filter excludes nonmatching records');
echo "$passed checks passed\n";

