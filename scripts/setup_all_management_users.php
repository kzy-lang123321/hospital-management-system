<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

$db = Database::getConnection();


function generateNamePassword($name) {
    $clean = preg_replace('/^(?:prof\.?\s*|dr\.?\s*|mr\.?\s*|ms\.?\s*|mrs\.?\s*)+/i', '', trim($name));
    $parts = explode(' ', trim($clean));
    $baseName = ucfirst(strtolower($parts[0] ?? 'User'));
    
    $symbols = ['@', '#', '$', '%', '&', '!'];
    $sym = $symbols[array_rand($symbols)];
    $num = rand(1000, 9999);
    
    return $baseName . $sym . $num;
}


$existingRoles = $db->query("SELECT slug, id FROM roles")->fetchAll(PDO::FETCH_KEY_PAIR);


$managementUsers = [
    [
        'username' => 'superadmin',
        'full_name' => 'Dr. Arshad Khan',
        'role_slug' => 'super_admin',
        'dept_code' => 'ADM',
        'designation' => 'Executive Hospital Director & CEO',
        'email' => 'director.arshad@hayatmedicare.org',
        'phone' => '+92 300 1110001',
        'fee' => 0.00
    ],
    [
        'username' => 'admin.hashim',
        'full_name' => 'Hashim Ali',
        'role_slug' => 'hospital_admin',
        'dept_code' => 'ADM',
        'designation' => 'Chief Operating Officer (COO) / Operations Director',
        'email' => 'hashim.ali@hayatmedicare.org',
        'phone' => '+92 300 1110002',
        'fee' => 0.00
    ],
    [
        'username' => 'dr.asif',
        'full_name' => 'Prof. Dr. Asif Mehmood',
        'role_slug' => 'hospital_admin',
        'dept_code' => 'ADM',
        'designation' => 'Medical Superintendent (MS) / Clinical Director',
        'email' => 'ms.asif@hayatmedicare.org',
        'phone' => '+92 300 1110003',
        'fee' => 2500.00
    ],
    [
        'username' => 'lab.usman',
        'full_name' => 'Dr. Usman Tariq',
        'role_slug' => 'lab_technician',
        'dept_code' => 'LAB',
        'designation' => 'Head of Pathology & Laboratory Manager',
        'email' => 'usman.pathology@hayatmedicare.org',
        'phone' => '+92 300 2220001',
        'fee' => 0.00
    ],
    [
        'username' => 'pharm.kamran',
        'full_name' => 'Kamran Bashir',
        'role_slug' => 'pharmacist',
        'dept_code' => 'PHARM',
        'designation' => 'Chief Pharmacist & Pharmacy Director',
        'email' => 'kamran.pharmacy@hayatmedicare.org',
        'phone' => '+92 300 3330001',
        'fee' => 0.00
    ],
    [
        'username' => 'radio.farooq',
        'full_name' => 'Dr. Farooq Khan',
        'role_slug' => 'radiologist',
        'dept_code' => 'RAD',
        'designation' => 'Head of Radiology & Imaging / PACS Director',
        'email' => 'farooq.radiology@hayatmedicare.org',
        'phone' => '+92 300 4440001',
        'fee' => 2500.00
    ],
    [
        'username' => 'nurse.fatima',
        'full_name' => 'Fatima Noor',
        'role_slug' => 'nurse',
        'dept_code' => 'GENW',
        'designation' => 'Nursing Superintendent & Wards / IPD Manager',
        'email' => 'fatima.nursing@hayatmedicare.org',
        'phone' => '+92 300 5550001',
        'fee' => 0.00
    ],
    [
        'username' => 'dr.imran',
        'full_name' => 'Prof. Dr. Imran Malik',
        'role_slug' => 'doctor',
        'dept_code' => 'SURG',
        'designation' => 'Director of Surgery & OT Operations Lead',
        'email' => 'imran.surgery@hayatmedicare.org',
        'phone' => '+92 300 6660001',
        'fee' => 3000.00
    ],
    [
        'username' => 'dr.tariq',
        'full_name' => 'Dr. Tariq Jameel',
        'role_slug' => 'doctor',
        'dept_code' => 'CARD',
        'designation' => 'Chief of OPD & Consultant Cardiologist',
        'email' => 'tariq.opd@hayatmedicare.org',
        'phone' => '+92 300 7770001',
        'fee' => 2000.00
    ],
    [
        'username' => 'dr.noman',
        'full_name' => 'Dr. Noman Akram',
        'role_slug' => 'doctor',
        'dept_code' => 'EMER',
        'designation' => 'Head of Emergency & Trauma Care Unit',
        'email' => 'noman.er@hayatmedicare.org',
        'phone' => '+92 300 8880001',
        'fee' => 2000.00
    ],
    [
        'username' => 'dr.sumaira',
        'full_name' => 'Dr. Sumaira Naz',
        'role_slug' => 'lab_technician',
        'designation' => 'Blood Bank & Transfusion Medicine Officer',
        'dept_code' => 'LAB',
        'email' => 'sumaira.bloodbank@hayatmedicare.org',
        'phone' => '+92 300 9990001',
        'fee' => 0.00
    ],
    [
        'username' => 'cashier.hassan',
        'full_name' => 'Hassan Raza',
        'role_slug' => 'cashier',
        'dept_code' => 'ACC',
        'designation' => 'Finance Controller & Central Billing Manager',
        'email' => 'hassan.finance@hayatmedicare.org',
        'phone' => '+92 300 1230001',
        'fee' => 0.00
    ],
    [
        'username' => 'reception.sana',
        'full_name' => 'Sana Sheikh',
        'role_slug' => 'receptionist',
        'dept_code' => 'REC',
        'designation' => 'Patient Access & Front Desk Reception Manager',
        'email' => 'sana.reception@hayatmedicare.org',
        'phone' => '+92 300 2340001',
        'fee' => 0.00
    ],
    [
        'username' => 'store.zubair',
        'full_name' => 'Zubair Ahmed',
        'role_slug' => 'pharmacist',
        'dept_code' => 'PHARM',
        'designation' => 'Central Stores & Procurement Manager',
        'email' => 'zubair.procurement@hayatmedicare.org',
        'phone' => '+92 300 3450001',
        'fee' => 0.00
    ]
];


$output = [];

foreach ($managementUsers as $m) {
    $roleId = $existingRoles[$m['role_slug']] ?? null;
    if (!$roleId) continue;
    
    $deptStmt = $db->prepare("SELECT id FROM departments WHERE code = ? LIMIT 1");
    $deptStmt->execute([$m['dept_code']]);
    $deptId = $deptStmt->fetchColumn() ?: null;
    
    $password = generateNamePassword($m['ufull_name'] ?? $m['full_name']);
    $hash = password_hash($password, PASSWORD_BCRYPT);
    
    $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$m['username']]);
    $existingId = $stmt->fetchColumn();
    
    if ($existingId) {
        $update = $db->prepare("
            UPDATE users 
            SET full_name = ?, email = ?, password_hash = ?, role_id = ?, department_id = ?, 
                designation = ?, consultation_fee = ?, status = 'Active', phone = ?
            WHERE id = ?
        ");
        $update->execute([
            $m['full_name'], $m['email'], $hash, $roleId, $deptId,
            $m['designation'], $m['fee'], $m['phone'], $existingId
        ]);
        $userId = $existingId;
    } else {
        $insert = $db->prepare("
            INSERT INTO users 
            (username, email, password_hash, full_name, phone, role_id, department_id, designation, consultation_fee, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active')
        ");
        $insert->execute([
            $m['username'], $m['email'], $hash, $m['full_name'], $m['phone'],
            $roleId, $deptId, $m['designation'], $m['fee']
        ]);
        $userId = $db->lastInsertId();
    }
    
    $output[] = [
        'id' => $userId,
        'username' => $m['username'],
        'full_name' => $m['full_name'],
        'designation' => $m['designation'],
        'role_slug' => $m['role_slug'],
        'email' => $m['email'],
        'phone' => $m['phone'],
        'password' => $password
    ];
}

file_put_contents(__DIR__ . '/management_credentials.json', json_encode($output, JSON_PRETTY_PRINT));
echo "SUCCESS. Created/Updated " . count($output) . " Management Users with Name-Based Random Passwords.\n";
