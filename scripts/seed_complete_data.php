<?php
/**
 * seed_complete_data.php
 * Comprehensive seeder to make all user, official, farmer, farm, and livestock data complete.
 */
require_once __DIR__ . '/../init.php';

echo "=== STARTING COMPREHENSIVE DATA SEEDING ===\n\n";

// 1. UPDATE USER DETAILS (Admin & Officials)
echo "1. Checking and updating user accounts...\n";

// Ensure System Admin details
$pdo->prepare("UPDATE users SET mobile = '+63 999 000 0001', status = 'Active', emailVerified = 1 WHERE id = 1")->execute();

// Ensure Mark Almonte mobile and details
$pdo->prepare("UPDATE users SET mobile = '09630972289', status = 'Active', emailVerified = 1 WHERE id = 6")->execute();

// Ensure all other officials have valid mobile numbers and Active status
$officials = [
    9  => '09171234001',
    10 => '09171234002',
    11 => '09171234003',
    12 => '09171234004',
    13 => '09171234005',
    14 => '09171234006',
    15 => '09171234007',
    16 => '09171234008',
    17 => '09171234009',
    18 => '09171234010',
];
foreach ($officials as $offId => $mob) {
    $pdo->prepare("UPDATE users SET mobile = ?, status = 'Active', emailVerified = 1 WHERE id = ?")->execute([$mob, $offId]);
}
echo "✓ Users accounts updated.\n";

// 2. COMPLETE OFFICER PROFILES
echo "2. Completing officer profiles for all 11 DA officials...\n";
$officerProfiles = [
    6  => [
        'gov_id'          => 'DA-RO5-2024-0006',
        'department'      => 'Livestock & Disease Surveillance Unit',
        'position'        => 'Supervising Agriculturist & Regional Coordinator',
        'office'          => 'Department of Agriculture - Region V Field Office',
        'assigned_region' => 'Region V (Bicol)',
        'municipality'    => '1st District Coordinator',
        'province'        => 'Camarines Sur'
    ],
    9  => [
        'gov_id'          => 'DA-RO5-2024-0009',
        'department'      => 'Livestock Extension Division',
        'position'        => 'Municipal Agricultural Officer',
        'office'          => 'Municipal Agriculture Office - Ragay',
        'assigned_region' => 'Region V (Bicol)',
        'municipality'    => 'Ragay',
        'province'        => 'Camarines Sur'
    ],
    10 => [
        'gov_id'          => 'DA-RO5-2024-0010',
        'department'      => 'Veterinary Quarantine Unit',
        'position'        => 'Senior Livestock Inspector',
        'office'          => 'Municipal Agriculture Office - Sipocot',
        'assigned_region' => 'Region V (Bicol)',
        'municipality'    => 'Sipocot',
        'province'        => 'Camarines Sur'
    ],
    11 => [
        'gov_id'          => 'DA-RO5-2024-0011',
        'department'      => 'Animal Health Section',
        'position'        => 'Agricultural Technologist',
        'office'          => 'Municipal Agriculture Office - Lupi',
        'assigned_region' => 'Region V (Bicol)',
        'municipality'    => 'Lupi',
        'province'        => 'Camarines Sur'
    ],
    12 => [
        'gov_id'          => 'DA-RO5-2024-0012',
        'department'      => 'Surveillance & Disease Control',
        'position'        => 'Veterinary Officer II',
        'office'          => 'Municipal Agriculture Office - Del Gallego',
        'assigned_region' => 'Region V (Bicol)',
        'municipality'    => 'Del Gallego',
        'province'        => 'Camarines Sur'
    ],
    13 => [
        'gov_id'          => 'DA-RO5-2024-0013',
        'department'      => 'Meat Inspection Bureau',
        'position'        => 'Municipal Meat Inspector',
        'office'          => 'Municipal Agriculture Office - Cabusao',
        'assigned_region' => 'Region V (Bicol)',
        'municipality'    => 'Cabusao',
        'province'        => 'Camarines Sur'
    ],
    14 => [
        'gov_id'          => 'DA-RO5-2024-0014',
        'department'      => 'Livestock Breeding Program',
        'position'        => 'Agricultural Extension Specialist',
        'office'          => 'Municipal Agriculture Office - Ragay',
        'assigned_region' => 'Region V (Bicol)',
        'municipality'    => 'Ragay',
        'province'        => 'Camarines Sur'
    ],
    15 => [
        'gov_id'          => 'DA-RO5-2024-0015',
        'department'      => 'Animal Disease Eradication',
        'position'        => 'Livestock Field Coordinator',
        'office'          => 'Municipal Agriculture Office - Sipocot',
        'assigned_region' => 'Region V (Bicol)',
        'municipality'    => 'Sipocot',
        'province'        => 'Camarines Sur'
    ],
    16 => [
        'gov_id'          => 'DA-RO5-2024-0016',
        'department'      => 'Bio-Security Taskforce',
        'position'        => 'Veterinary Epidemiologist',
        'office'          => 'Municipal Agriculture Office - Lupi',
        'assigned_region' => 'Region V (Bicol)',
        'municipality'    => 'Lupi',
        'province'        => 'Camarines Sur'
    ],
    17 => [
        'gov_id'          => 'DA-RO5-2024-0017',
        'department'      => 'Port & Boundary Checkpoints',
        'position'        => 'Animal Quarantine Officer',
        'office'          => 'Municipal Agriculture Office - Del Gallego',
        'assigned_region' => 'Region V (Bicol)',
        'municipality'    => 'Del Gallego',
        'province'        => 'Camarines Sur'
    ],
    18 => [
        'gov_id'          => 'DA-RO5-2024-0018',
        'department'      => 'Regional Field Operations',
        'position'        => 'Senior Agriculturist',
        'office'          => 'Municipal Agriculture Office - Cabusao',
        'assigned_region' => 'Region V (Bicol)',
        'municipality'    => 'Cabusao',
        'province'        => 'Camarines Sur'
    ],
];

foreach ($officerProfiles as $uId => $prof) {
    $chk = $pdo->prepare("SELECT id FROM officer_profiles WHERE user_id = ?");
    $chk->execute([$uId]);
    if ($chk->fetch()) {
        $stmt = $pdo->prepare("UPDATE officer_profiles SET gov_id=?, department=?, position=?, office=?, assigned_region=?, municipality=?, province=? WHERE user_id=?");
        $stmt->execute([$prof['gov_id'], $prof['department'], $prof['position'], $prof['office'], $prof['assigned_region'], $prof['municipality'], $prof['province'], $uId]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO officer_profiles (user_id, gov_id, department, position, office, assigned_region, municipality, province) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$uId, $prof['gov_id'], $prof['department'], $prof['position'], $prof['office'], $prof['assigned_region'], $prof['municipality'], $prof['province']]);
    }
}
echo "✓ All officer profiles populated.\n";

// 3. COMPLETE FARMER PROFILES FOR ALL 32 FARMERS
echo "3. Completing farmer profiles for all 32 farmers...\n";
$farmerDetails = [
    7  => ['gender' => 'Female', 'dob' => '1988-04-15', 'muni' => 'Ragay', 'brgy' => 'Lower Sta. Cruz', 'em_name' => 'Michael Lisor (Brother)', 'em_no' => '0917-555-7001'],
    8  => ['gender' => 'Female', 'dob' => '1992-09-21', 'muni' => 'Cabusao', 'brgy' => 'San George', 'em_name' => 'Ferdinand Bandala (Spouse)', 'em_no' => '0917-555-8002'],
    19 => ['gender' => 'Male',   'dob' => '1975-03-12', 'muni' => 'Ragay', 'brgy' => 'Apad', 'em_name' => 'Maria Cruz (Spouse)', 'em_no' => '0917-555-1901'],
    20 => ['gender' => 'Male',   'dob' => '1982-11-28', 'muni' => 'Ragay', 'brgy' => 'Upper Sta. Cruz', 'em_name' => 'Elena Villanueva (Spouse)', 'em_no' => '0917-555-2002'],
    21 => ['gender' => 'Male',   'dob' => '1978-06-19', 'muni' => 'Ragay', 'brgy' => 'Banga Cuevas', 'em_name' => 'Clara Castillo (Spouse)', 'em_no' => '0917-555-2103'],
    22 => ['gender' => 'Male',   'dob' => '1985-02-14', 'muni' => 'Ragay', 'brgy' => 'Cale', 'em_name' => 'Luz Salazar (Spouse)', 'em_no' => '0917-555-2204'],
    23 => ['gender' => 'Female', 'dob' => '1980-08-30', 'muni' => 'Ragay', 'brgy' => 'Banga Cuevas', 'em_name' => 'Rogelio Navarro (Spouse)', 'em_no' => '0917-555-2305'],
    24 => ['gender' => 'Male',   'dob' => '1979-12-05', 'muni' => 'Ragay', 'brgy' => 'Apad', 'em_name' => 'Teresa Morales (Spouse)', 'em_no' => '0917-555-2406'],
    25 => ['gender' => 'Female', 'dob' => '1973-05-17', 'muni' => 'Sipocot', 'brgy' => 'Bolo Norte', 'em_name' => 'Arthur Aquino (Spouse)', 'em_no' => '0917-555-2507'],
    26 => ['gender' => 'Female', 'dob' => '1986-10-23', 'muni' => 'Sipocot', 'brgy' => 'North Centro', 'em_name' => 'Danilo Tolentino (Spouse)', 'em_no' => '0917-555-2608'],
    27 => ['gender' => 'Male',   'dob' => '1981-01-11', 'muni' => 'Sipocot', 'brgy' => 'South Centro', 'em_name' => 'Gloria Mercado (Spouse)', 'em_no' => '0917-555-2709'],
    28 => ['gender' => 'Male',   'dob' => '1984-07-25', 'muni' => 'Sipocot', 'brgy' => 'Tara', 'em_name' => 'Anita Gutierrez (Spouse)', 'em_no' => '0917-555-2810'],
    29 => ['gender' => 'Female', 'dob' => '1976-04-09', 'muni' => 'Sipocot', 'brgy' => 'Tara', 'em_name' => 'Bernardo Soriano (Spouse)', 'em_no' => '0917-555-2911'],
    30 => ['gender' => 'Male',   'dob' => '1983-09-15', 'muni' => 'Sipocot', 'brgy' => 'South Centro', 'em_name' => 'Carmen Valdez (Spouse)', 'em_no' => '0917-555-3012'],
    31 => ['gender' => 'Male',   'dob' => '1987-12-20', 'muni' => 'Lupi', 'brgy' => 'Barrera Jr.', 'em_name' => 'Lorena Espiritu (Spouse)', 'em_no' => '0917-555-3113'],
    32 => ['gender' => 'Male',   'dob' => '1972-03-08', 'muni' => 'Lupi', 'brgy' => 'San Pedro', 'em_name' => 'Marissa Aguilar (Spouse)', 'em_no' => '0917-555-3214'],
    33 => ['gender' => 'Female', 'dob' => '1981-06-14', 'muni' => 'Lupi', 'brgy' => 'Colacling', 'em_name' => 'Eduardo Pascual (Spouse)', 'em_no' => '0917-555-3315'],
    34 => ['gender' => 'Male',   'dob' => '1977-11-03', 'muni' => 'Lupi', 'brgy' => 'Colacling', 'em_name' => 'Mercedes Delos Reyes (Spouse)', 'em_no' => '0917-555-3416'],
    35 => ['gender' => 'Female', 'dob' => '1989-02-18', 'muni' => 'Lupi', 'brgy' => 'Bulawan Jr.', 'em_name' => 'Cesar Miranda (Spouse)', 'em_no' => '0917-555-3517'],
    36 => ['gender' => 'Male',   'dob' => '1974-08-27', 'muni' => 'Lupi', 'brgy' => 'Colacling', 'em_name' => 'Patricia Cordero (Spouse)', 'em_no' => '0917-555-3618'],
    37 => ['gender' => 'Female', 'dob' => '1985-05-31', 'muni' => 'Del Gallego', 'brgy' => 'Sinuknipan I', 'em_name' => 'Victor Ignacio (Spouse)', 'em_no' => '0917-555-3719'],
    38 => ['gender' => 'Male',   'dob' => '1980-10-12', 'muni' => 'Del Gallego', 'brgy' => 'Mabini', 'em_name' => 'Rosario Padilla (Spouse)', 'em_no' => '0917-555-3820'],
    39 => ['gender' => 'Male',   'dob' => '1983-04-04', 'muni' => 'Del Gallego', 'brgy' => 'Sinuknipan I', 'em_name' => 'Norma Villanueva (Spouse)', 'em_no' => '0917-555-3921'],
    40 => ['gender' => 'Female', 'dob' => '1978-01-22', 'muni' => 'Del Gallego', 'brgy' => 'Pinamalisan', 'em_name' => 'Jose David (Spouse)', 'em_no' => '0917-555-4022'],
    41 => ['gender' => 'Male',   'dob' => '1971-09-09', 'muni' => 'Del Gallego', 'brgy' => 'Sinuknipan I', 'em_name' => 'Remedios Ferrer (Spouse)', 'em_no' => '0917-555-4123'],
    42 => ['gender' => 'Female', 'dob' => '1984-12-17', 'muni' => 'Del Gallego', 'brgy' => 'Mabini', 'em_name' => 'Francis Tan (Spouse)', 'em_no' => '0917-555-4224'],
    43 => ['gender' => 'Male',   'dob' => '1976-07-06', 'muni' => 'Cabusao', 'brgy' => 'New Poblacion', 'em_name' => 'Lilia Manalo (Spouse)', 'em_no' => '0917-555-4325'],
    44 => ['gender' => 'Female', 'dob' => '1982-03-29', 'muni' => 'Cabusao', 'brgy' => 'San Pedro', 'em_name' => 'Romeo Abad (Spouse)', 'em_no' => '0917-555-4426'],
    45 => ['gender' => 'Male',   'dob' => '1979-11-14', 'muni' => 'Cabusao', 'brgy' => 'Castillo', 'em_name' => 'Evelyn Corpuz (Spouse)', 'em_no' => '0917-555-4527'],
    46 => ['gender' => 'Female', 'dob' => '1986-06-02', 'muni' => 'Cabusao', 'brgy' => 'Pandan', 'em_name' => 'Nestor Evangelista (Spouse)', 'em_no' => '0917-555-4628'],
    47 => ['gender' => 'Male',   'dob' => '1973-10-18', 'muni' => 'Cabusao', 'brgy' => 'Barcelonita', 'em_name' => 'Imelda Santiago (Spouse)', 'em_no' => '0917-555-4729'],
    48 => ['gender' => 'Female', 'dob' => '1980-05-25', 'muni' => 'Cabusao', 'brgy' => 'Barcelonita', 'em_name' => 'Gerardo De Leon (Spouse)', 'em_no' => '0917-555-4830'],
];

$muniCodes = ['Cabusao' => '01', 'Del Gallego' => '02', 'Lupi' => '03', 'Ragay' => '04', 'Sipocot' => '05'];

foreach ($farmerDetails as $uId => $fInfo) {
    $code = $muniCodes[$fInfo['muni']] ?? '01';
    $rsbsa = sprintf("RSBSA-05-17-%s-%05d", $code, $uId);
    $addr = sprintf("Zone 2, Brgy. %s, %s, Camarines Sur", $fInfo['brgy'], $fInfo['muni']);

    $chk = $pdo->prepare("SELECT id FROM farmer_profiles WHERE user_id = ?");
    $chk->execute([$uId]);
    if ($chk->fetch()) {
        $stmt = $pdo->prepare("UPDATE farmer_profiles SET gov_id=?, gender=?, dob=?, address=?, emergency_contact=? WHERE user_id=?");
        $stmt->execute([$rsbsa, $fInfo['gender'], $fInfo['dob'], $addr, $fInfo['em_name'] . ' - ' . $fInfo['em_no'], $uId]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO farmer_profiles (user_id, gov_id, gender, dob, address, emergency_contact) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$uId, $rsbsa, $fInfo['gender'], $fInfo['dob'], $addr, $fInfo['em_name'] . ' - ' . $fInfo['em_no']]);
    }
}
echo "✓ All 32 farmer profiles populated.\n";

// 4. ENSURE ALL 32 FARMERS HAVE APPROVED FARMS IN 1ST DISTRICT
echo "4. Checking and populating registered farms...\n";

// Check which farmers have no farms yet
$missingFarms = [
    8  => ['name' => 'Bandala Pastoral Cattle Ranch',    'type' => 'Cattle',  'muni' => 'Cabusao',     'brgy' => 'San George',   'lat' => 13.7380, 'lng' => 123.1180, 'area' => 14.50],
    22 => ['name' => 'Salazar Prime Swine Farm',         'type' => 'Swine',   'muni' => 'Ragay',       'brgy' => 'Cale',         'lat' => 13.8210, 'lng' => 122.7840, 'area' => 11.20],
    28 => ['name' => 'Gutierrez Bio-Poultry Station',    'type' => 'Poultry', 'muni' => 'Sipocot',     'brgy' => 'Tara',         'lat' => 13.7650, 'lng' => 122.9780, 'area' => 8.50],
    34 => ['name' => 'Delos Reyes Dairy & Goat Station', 'type' => 'Goat',    'muni' => 'Lupi',        'brgy' => 'Colacling',    'lat' => 13.7840, 'lng' => 122.8750, 'area' => 16.00],
    40 => ['name' => 'David Heritage Cattle Range',      'type' => 'Cattle',  'muni' => 'Del Gallego', 'brgy' => 'Pinamalisan', 'lat' => 13.9210, 'lng' => 122.5850, 'area' => 22.00],
    46 => ['name' => 'Evangelista Mixed Livestock Farm', 'type' => 'Mixed',   'muni' => 'Cabusao',     'brgy' => 'Pandan',       'lat' => 13.7220, 'lng' => 123.1250, 'area' => 10.80],
];

foreach ($missingFarms as $ownerId => $mf) {
    $exists = $pdo->prepare("SELECT id FROM farms WHERE ownerId = ?");
    $exists->execute([$ownerId]);
    if (!$exists->fetch()) {
        $u = $pdo->prepare("SELECT firstName, lastName FROM users WHERE id = ?");
        $u->execute([$ownerId]);
        $ud = $u->fetch();
        $ownerName = trim(($ud['firstName'] ?? '') . ' ' . ($ud['lastName'] ?? ''));

        $stmt = $pdo->prepare("
            INSERT INTO farms (
                name, ownerId, ownerName, type, address, latitude, longitude, area, status,
                petitioner_surname, petitioner_firstname, pet_sex, pet_barangay, pet_municipality, pet_province,
                zip_code, region, business_type, facility_types, approved_by, approved_at
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, 'Approved',
                ?, ?, 'Male', ?, ?, 'Camarines Sur',
                '4400', 'Region V – Bicol Region', 'Commercial Farm', '[\"Quarantine Pen\",\"Waste Biogas Digester\"]', 6, CURRENT_TIMESTAMP
            )
        ");
        $fullAddr = sprintf("Brgy. %s, %s, Camarines Sur", $mf['brgy'], $mf['muni']);
        $stmt->execute([
            $mf['name'], $ownerId, $ownerName, $mf['type'], $fullAddr, $mf['lat'], $mf['lng'], $mf['area'],
            $ud['lastName'], $ud['firstName'], $mf['brgy'], $mf['muni']
        ]);
        echo "  + Created farm for {$ownerName}: {$mf['name']}\n";
    }
}

// Ensure all existing farms have pet_municipality, petitioner details, and Approved status
$allFarms = $pdo->query("SELECT id, name, address, ownerId, ownerName FROM farms")->fetchAll();
foreach ($allFarms as $f) {
    $addr = strtolower($f['address'] ?? '');
    $muni = 'Ragay';
    if (str_contains($addr, 'cabusao'))     $muni = 'Cabusao';
    elseif (str_contains($addr, 'del gallego')) $muni = 'Del Gallego';
    elseif (str_contains($addr, 'lupi'))        $muni = 'Lupi';
    elseif (str_contains($addr, 'sipocot'))     $muni = 'Sipocot';
    elseif (str_contains($addr, 'ragay'))       $muni = 'Ragay';

    $u = $pdo->prepare("SELECT firstName, lastName FROM users WHERE id = ?");
    $u->execute([$f['ownerId']]);
    $ud = $u->fetch() ?: ['firstName' => 'Juan', 'lastName' => 'Dela Cruz'];

    $pdo->prepare("
        UPDATE farms SET 
            status = 'Approved',
            pet_municipality = ?,
            petitioner_surname = COALESCE(NULLIF(petitioner_surname, ''), ?),
            petitioner_firstname = COALESCE(NULLIF(petitioner_firstname, ''), ?),
            pet_province = 'Camarines Sur',
            region = 'Region V – Bicol Region',
            approved_by = 6,
            approved_at = COALESCE(approved_at, CURRENT_TIMESTAMP)
        WHERE id = ?
    ")->execute([$muni, $ud['lastName'], $ud['firstName'], $f['id']]);
}
echo "✓ All farms updated and approved.\n";

// 5. POPULATE COMPLETE LIVESTOCK PROFILES & DETAILS
echo "5. Updating and enriching livestock profiles...\n";

// A. Update all existing livestock records so age, weight, notes, and coordinates are complete
$existingLivestock = $pdo->query("
    SELECT l.id, l.farmId, l.type, l.tagId, l.breed, l.qty, f.latitude, f.longitude, f.address
    FROM livestock l
    JOIN farms f ON l.farmId = f.id
")->fetchAll();

$breedWeights = [
    'Cattle'  => ['age' => 24, 'weight' => 450.00, 'notes' => 'Vaccinated against Hemorrhagic Septicemia and FMD. Bi-annual vitamin supplementation applied.'],
    'Swine'   => ['age' => 8,  'weight' => 95.50,  'notes' => 'Hog Cholera & PRV vaccinated. Strict bio-security level 2 maintained.'],
    'Poultry' => ['age' => 3,  'weight' => 2.40,   'notes' => 'Newcastle Disease (NCD) and IBD vaccines administered. Standard feed conversion profile.'],
    'Goat'    => ['age' => 14, 'weight' => 38.00,  'notes' => 'Dewormed and tagged. Certified healthy by municipal veterinary inspection.'],
    'Sheep'   => ['age' => 16, 'weight' => 42.00,  'notes' => 'Routine biometric health screening passed. Pasture rotational grazing.'],
];

foreach ($existingLivestock as $el) {
    $info = $breedWeights[$el['type']] ?? $breedWeights['Cattle'];
    $randAge = max(2, $info['age'] + rand(-2, 4));
    $randWeight = round($info['weight'] * (1 + (rand(-10, 15) / 100)), 2);

    $pdo->prepare("
        UPDATE livestock SET
            age = COALESCE(age, ?),
            weight = COALESCE(weight, ?),
            notes = COALESCE(NULLIF(notes, ''), ?),
            latitude = COALESCE(latitude, ?),
            longitude = COALESCE(longitude, ?),
            updatedAt = CURRENT_TIMESTAMP
        WHERE id = ?
    ")->execute([$randAge, $randWeight, $info['notes'], $el['latitude'], $el['longitude'], $el['id']]);
}
echo "  ✓ Existing livestock records enriched with age, weight, and veterinary notes.\n";

// B. Check farms that have NO livestock records and add comprehensive records for them
$farmsWithoutLivestock = $pdo->query("
    SELECT f.id, f.name, f.type, f.latitude, f.longitude, f.pet_municipality
    FROM farms f
    LEFT JOIN livestock l ON l.farmId = f.id
    GROUP BY f.id
    HAVING COUNT(l.id) = 0
")->fetchAll();

$muniTagPrefix = [
    'Cabusao'     => 'CAB',
    'Del Gallego' => 'DEL',
    'Lupi'        => 'LUP',
    'Ragay'       => 'RAG',
    'Sipocot'     => 'SIP'
];

$livestockPresets = [
    'Cattle' => [
        ['type' => 'Cattle', 'breed' => 'Brahman Cross',      'age' => 28, 'weight' => 480.0, 'qty' => 8,  'health' => 'Healthy', 'vax' => 'Complete', 'notes' => 'Vaccinated against FMD & Hemorrhagic Septicemia. Routine ear-tagging.'],
        ['type' => 'Cattle', 'breed' => 'Native Batangas',    'age' => 18, 'weight' => 360.0, 'qty' => 12, 'health' => 'Healthy', 'vax' => 'Complete', 'notes' => 'Pasture-fed breeding stock. Bi-monthly veterinary health monitoring.'],
    ],
    'Swine' => [
        ['type' => 'Swine',  'breed' => 'Landrace',           'age' => 7,  'weight' => 92.0,  'qty' => 24, 'health' => 'Healthy', 'vax' => 'Complete', 'notes' => 'Hog Cholera & PRRS vaccinated. Feed conversion efficiency verified.'],
        ['type' => 'Swine',  'breed' => 'Large White',        'age' => 9,  'weight' => 105.0, 'qty' => 18, 'health' => 'Healthy', 'vax' => 'Complete', 'notes' => 'Certified ASF biosecurity compliant. Automated waterers & waste digester active.'],
    ],
    'Poultry' => [
        ['type' => 'Poultry','breed' => 'Broiler Cobb 500',   'age' => 2,  'weight' => 2.1,   'qty' => 150,'health' => 'Healthy', 'vax' => 'Complete', 'notes' => 'Vaccinated against Newcastle Disease (NCD) & Gumboro. High bio-security tunnel.'],
        ['type' => 'Poultry','breed' => 'Sasso Chicken',      'age' => 4,  'weight' => 2.8,   'qty' => 95, 'health' => 'Healthy', 'vax' => 'Complete', 'notes' => 'Free-range dual purpose poultry flock. Routine deworming accomplished.'],
    ],
    'Goat' => [
        ['type' => 'Goat',   'breed' => 'Anglo-Nubian',       'age' => 14, 'weight' => 42.0,  'qty' => 15, 'health' => 'Healthy', 'vax' => 'Complete', 'notes' => 'PPR vaccinated and ear-tagged. Registered dairy and breeding doe stock.'],
        ['type' => 'Goat',   'breed' => 'Boer Cross',         'age' => 12, 'weight' => 46.5,  'qty' => 18, 'health' => 'Healthy', 'vax' => 'Complete', 'notes' => 'Meat-type breeding buck & doe herd. Elevated slatted flooring maintained.'],
    ],
    'Mixed' => [
        ['type' => 'Swine',  'breed' => 'Duroc Jersey',       'age' => 8,  'weight' => 98.0,  'qty' => 16, 'health' => 'Healthy', 'vax' => 'Complete', 'notes' => 'Purebred breeding stock. Negative for ASF and PRRS on recent DA screening.'],
        ['type' => 'Goat',   'breed' => 'Philippine Native',  'age' => 11, 'weight' => 28.0,  'qty' => 20, 'health' => 'Healthy', 'vax' => 'Complete', 'notes' => 'Native hardy stock adapted to local pasture. Regular parasite control.'],
        ['type' => 'Poultry','breed' => 'Native Tagalog',     'age' => 5,  'weight' => 1.8,   'qty' => 80, 'health' => 'Healthy', 'vax' => 'Complete', 'notes' => 'Organic free-range flock. Biosecurity buffer established.'],
    ]
];

$insStmt = $pdo->prepare("
    INSERT INTO livestock (farmId, type, tagId, breed, age, weight, healthStatus, vaccinationStatus, latitude, longitude, qty, notes)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

foreach ($farmsWithoutLivestock as $fw) {
    $muni = $fw['pet_municipality'] ?: 'Ragay';
    $pfx = $muniTagPrefix[$muni] ?? 'RAG';
    $fType = $fw['type'] ?? 'Mixed';
    $presets = $livestockPresets[$fType] ?? $livestockPresets['Mixed'];

    foreach ($presets as $p) {
        $tag = sprintf("%s-%s-%04d", $pfx, strtoupper(substr($p['type'], 0, 3)), rand(1000, 9999));
        $insStmt->execute([
            $fw['id'],
            $p['type'],
            $tag,
            $p['breed'],
            $p['age'],
            $p['weight'],
            $p['health'],
            $p['vax'],
            $fw['latitude'],
            $fw['longitude'],
            $p['qty'],
            $p['notes']
        ]);
        echo "  + Added {$p['qty']}x {$p['breed']} ({$tag}) to farm #{$fw['id']} ({$fw['name']})\n";
    }
}
echo "✓ All farms now possess complete livestock profiles.\n";

// 6. POPULATE CERTIFICATES FOR ALL APPROVED FARMS
echo "6. Ensuring GAHP Certificates for all approved farms...\n";
$approvedFarms = $pdo->query("SELECT id FROM farms WHERE status = 'Approved'")->fetchAll(PDO::FETCH_COLUMN);
$certStmt = $pdo->prepare("
    INSERT INTO certificates (farm_id, cert_number, cert_type, issued_by, issued_date, expiry_date, status, notes)
    VALUES (?, ?, 'Registration', 'Bureau of Animal Industry - DA Region V', '2026-01-15', '2027-01-15', 'Active', 'Official GAHP & Biosecurity Compliance Certificate')
    ON DUPLICATE KEY UPDATE status = 'Active'
");
foreach ($approvedFarms as $farmId) {
    $chkCert = $pdo->prepare("SELECT id FROM certificates WHERE farm_id = ?");
    $chkCert->execute([$farmId]);
    if (!$chkCert->fetch()) {
        $certNum = sprintf("GAHP-2026-%05d", $farmId);
        $certStmt->execute([$farmId, $certNum]);
    }
}
echo "✓ GAHP Certificates verified.\n";

// 7. POPULATE DISTRICT INCIDENTS
echo "7. Checking and populating district incidents...\n";
$incCount = $pdo->query("SELECT COUNT(*) FROM incidents")->fetchColumn();
if ($incCount == 0) {
    $incidentsSeed = [
        [
            'farmId' => 6,
            'reporterId' => 20,
            'type' => 'Sick',
            'title' => 'Mild Hoof Lesion & Lameness in 2 Cattle',
            'desc' => 'Two adult crossbred cattle showing signs of minor hoof lesion and temporary lameness. Isolated in quarantine pen and local disinfectant applied.',
            'lat' => 13.7950, 'lng' => 122.7861,
            'status' => 'Resolved', 'priority' => 'Medium', 'lv_type' => 'Cattle',
            'symptoms' => 'Hoof inflammation, limping, normal temperature', 'severity' => 'Low'
        ],
        [
            'farmId' => 9,
            'reporterId' => 24,
            'type' => 'Disease',
            'title' => 'Preventive ASF Surveillance & Swab Testing',
            'desc' => 'Routine border-proximity swine biosecurity screening conducted by DA Taskforce. All laboratory PCR test results returned negative for African Swine Fever.',
            'lat' => 13.7979, 'lng' => 122.8026,
            'status' => 'Resolved', 'priority' => 'High', 'lv_type' => 'Swine',
            'symptoms' => 'None, preventative routine surveillance screening', 'severity' => 'Low'
        ],
        [
            'farmId' => 14,
            'reporterId' => 30,
            'type' => 'Sick',
            'title' => 'Suspected Tick-Borne Anaplasmosis in Sipocot Range',
            'desc' => 'Three steers observed with reduced feed intake and elevated body temperature. Blood smear examined by DA veterinary officer, treatment administered.',
            'lat' => 13.7661, 'lng' => 122.9616,
            'status' => 'In Progress', 'priority' => 'Medium', 'lv_type' => 'Cattle',
            'symptoms' => 'Fever, mild anemia, tick infestation on ears', 'severity' => 'Medium'
        ],
        [
            'farmId' => 19,
            'reporterId' => 36,
            'type' => 'Disease',
            'title' => 'Respiratory Wheezing Surveillance in Poultry Shed B',
            'desc' => 'Flock exhibiting mild sneezing and coughing symptoms following sudden monsoon rain temperature drop. Electrolytes and bronchodilators provided.',
            'lat' => 13.7735, 'lng' => 122.8697,
            'status' => 'In Progress', 'priority' => 'High', 'lv_type' => 'Poultry',
            'symptoms' => 'Coughing, nasal discharge, reduced feed intake', 'severity' => 'High'
        ],
        [
            'farmId' => 24,
            'reporterId' => 42,
            'type' => 'Others',
            'title' => 'External Parasite & Scabies Management in Goat Yard',
            'desc' => 'Localized hair loss and itching noted on two Anglo-Nubian kids. Ivermectin pour-on solution applied; herd isolated and recovered successfully.',
            'lat' => 13.9029, 'lng' => 122.5678,
            'status' => 'Resolved', 'priority' => 'Low', 'lv_type' => 'Goat',
            'symptoms' => 'Itching, alopecia, skin crusting', 'severity' => 'Low'
        ],
        [
            'farmId' => 26,
            'reporterId' => 44,
            'type' => 'Sick',
            'title' => 'Post-Vaccination Low-Grade Fever in Nursery Swine',
            'desc' => 'Mild lethargy and transient fever following routine Hog Cholera booster vaccination. Recovered within 24 hours under supervised hydration.',
            'lat' => 13.7351, 'lng' => 123.1338,
            'status' => 'Resolved', 'priority' => 'Medium', 'lv_type' => 'Swine',
            'symptoms' => 'Transient fever, mild lethargy', 'severity' => 'Low'
        ],
        [
            'farmId' => 20,
            'reporterId' => 37,
            'type' => 'Stray',
            'title' => 'Unregistered Livestock Sighting Near Del Gallego Perimeter',
            'desc' => 'Two stray carabao sighted grazing near agricultural buffer zone without ownership tag. Reported to barangay tanod and municipal officer for verification.',
            'lat' => 13.9159, 'lng' => 122.5916,
            'status' => 'Pending', 'priority' => 'Medium', 'lv_type' => 'Others',
            'symptoms' => 'No ear tags, roaming roadside buffer', 'severity' => 'Medium'
        ]
    ];

    $insInc = $pdo->prepare("
        INSERT INTO incidents (farmId, reporterId, type, title, description, latitude, longitude, status, priority, livestock_type, symptoms, severity, createdAt)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW() - INTERVAL FLOOR(RAND()*14) DAY)
    ");
    foreach ($incidentsSeed as $inc) {
        $insInc->execute([
            $inc['farmId'], $inc['reporterId'], $inc['type'], $inc['title'], $inc['desc'],
            $inc['lat'], $inc['lng'], $inc['status'], $inc['priority'], $inc['lv_type'],
            $inc['symptoms'], $inc['severity']
        ]);
    }
    echo "  ✓ 7 district incidents populated.\n";
} else {
    echo "  (Incidents already present: {$incCount} records)\n";
}

echo "\n=== DATA SEEDING COMPLETE! ===\n";
