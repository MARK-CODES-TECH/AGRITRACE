<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
chdir(dirname(__DIR__));
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['user_role'] !== 'Farmer') {
    header('Location: login.php'); exit;
}
try {
    require __DIR__ . '/db_connect.php';
} catch(PDOException $e) { die("Database error."); }

$userId    = $_SESSION['user_id'] ?? 0;
$firstName = $_SESSION['firstName'] ?? 'Farmer';
$lastName  = $_SESSION['lastName']  ?? '';
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// ── PROFILE UPDATE ────────────────────────────────────────────────────────────
if ($requestMethod === 'POST' && isset($_POST['update_profile'])) {
    header('Content-Type: application/json');
    try {
        $first = trim($_POST['firstName'] ?? '') ?: $firstName;
        $last  = trim($_POST['lastName']  ?? '') ?: $lastName;
        $email = trim($_POST['email']     ?? '');
        $mobile= trim($_POST['mobile']    ?? '');
        
        $pdo->prepare("UPDATE users SET firstName=?,lastName=?,email=?,mobile=? WHERE id=?")
            ->execute([$first,$last,$email,$mobile,$userId]);
        
        $_SESSION['firstName'] = $first;
        $_SESSION['lastName']  = $last;

        $picPath = null;
        if (!empty($_FILES['profile_pix']['tmp_name'])) {
            $ext = strtolower(pathinfo($_FILES['profile_pix']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','webp','gif'])) {
                $picPath = "uploads/profiles/{$userId}_".time().".$ext";
                @mkdir(dirname($picPath), 0755, true);
                move_uploaded_file($_FILES['profile_pix']['tmp_name'], $picPath);
            }
        }
        
        $govId = trim($_POST['gov_id'] ?? '') ?: null;
        $gender = trim($_POST['gender'] ?? '') ?: null;
        $dob = trim($_POST['dob'] ?? '') ?: null;
        $addr = trim($_POST['address'] ?? '') ?: null;
        $emg = trim($_POST['emergency_contact'] ?? '') ?: null;

        $stmt = $pdo->prepare("INSERT INTO farmer_profiles(user_id,gov_id,gender,dob,address,emergency_contact" . ($picPath ? ",profile_pix" : "") . ")
            VALUES(?,?,?,?,?,?" . ($picPath ? ",?" : "") . ")
            ON DUPLICATE KEY UPDATE gov_id=VALUES(gov_id),gender=VALUES(gender),dob=VALUES(dob),address=VALUES(address),
            emergency_contact=VALUES(emergency_contact)" . ($picPath ? ",profile_pix=VALUES(profile_pix)" : ""));
        $params = [$userId, $govId, $gender, $dob, $addr, $emg];
        if ($picPath) $params[] = $picPath;
        $stmt->execute($params);

        echo json_encode(['success' => true, 'message' => 'Profile updated successfully!']);
    } catch(Exception $e){ echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
    exit;
}

if ($requestMethod === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    header('Content-Type: application/json');
    try {
        $cur = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $conf = $_POST['confirm_password'] ?? '';
        if (strlen($new) < 6) throw new Exception('New password must be at least 6 characters.');
        if ($new !== $conf) throw new Exception('Passwords do not match.');
        
        $chk = $pdo->prepare("SELECT password FROM users WHERE id=?");
        $chk->execute([$userId]);
        $currHash = $chk->fetchColumn();
        if ($currHash && !password_verify($cur, $currHash)) {
            throw new Exception('Current password is incorrect.');
        }
        $newHash = password_hash($new, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([$newHash, $userId]);
        echo json_encode(['success'=>true, 'message'=>'Password changed successfully!']);
    } catch(Exception $e){ echo json_encode(['success'=>false, 'message'=>$e->getMessage()]); }
    exit;
}

// ── FARM REGISTRATION ─────────────────────────────────────────────────────────
if ($requestMethod === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register_farm') {
    header('Content-Type: application/json');
    try {
        $name      = trim($_POST['farm_name']    ?? '');
        $address   = trim($_POST['farm_address'] ?? '');
        $type      = $_POST['farm_type']   ?? 'Mixed';
        $area      = (float)($_POST['farm_area'] ?? 0);
        $lat       = !empty($_POST['farm_lat']) ? (float)$_POST['farm_lat'] : null;
        $lng       = !empty($_POST['farm_lng']) ? (float)$_POST['farm_lng'] : null;
        $region    = 'Region V — Bicol Region';
        $ownerName = trim($firstName.' '.$lastName);
        $petSurname  = trim($_POST['pet_surname']   ?? '');
        $petFirst    = trim($_POST['pet_firstname'] ?? '');
        $bizType   = $_POST['business_type'] ?? 'Sole Proprietor';
        $appType   = $_POST['app_type']      ?? 'New';
        $rawTypes  = trim($_POST['facility_types'] ?? '');
        $facTypes  = $rawTypes ? json_encode(array_values(array_filter(array_map('trim', explode(',', $rawTypes))))) : json_encode([]);

        if (!$name) throw new Exception('Farm name is required.');
        if (!$petSurname || !$petFirst) throw new Exception('Petitioner name is required.');

        $photoPath = null;
        if (!empty($_POST['photo_data']) && strpos($_POST['photo_data'], 'base64,') !== false) {
            $imgData = base64_decode(explode(',', $_POST['photo_data'])[1]);
            @mkdir('uploads/farms', 0755, true);
            $photoPath = 'uploads/farms/farm_' . time() . '_' . $userId . '.jpg';
            file_put_contents($photoPath, $imgData);
        }

        $pdo->prepare("INSERT INTO farms (name,ownerId,ownerName,type,address,latitude,longitude,area,status,
            petitioner_surname,petitioner_firstname,business_type,facility_types,photo_url,zip_code,region,
            pet_ext,pet_sex,pet_house_no,pet_street,pet_barangay,pet_municipality,pet_province,
            pet_dob,pet_pob,pet_civil_status,pet_spouse,pet_mothers_name,pet_education,pet_religion,
            pet_male_members,pet_female_members,hh_head,hh_head_name,pwd,fourps,indigenous,indigenous_specify,
            has_gov_id,gov_id_number,farmers_assoc,farmers_assoc_name,emergency_contact_name,emergency_contact_no,
            main_livelihood,farming_rice,farming_corn,farming_livestock,farming_poultry,farming_other,
            farming_other_specify,livestock_specify,poultry_specify,income_farming,income_non_farming)
            VALUES (?,?,?,?,?,?,?,?,'Pending',?,?,?,?,?,'4410',?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([
                $name,$userId,$ownerName,$type,$address,$lat,$lng,$area,
                $petSurname,$petFirst,$bizType,$facTypes,$photoPath,$region,
                $_POST['pet_ext']??null,$_POST['pet_sex']??null,$_POST['pet_house_no']??null,
                $_POST['pet_street']??null,$_POST['pet_barangay']??null,$_POST['pet_municipality']??null,
                $_POST['pet_province']??'Camarines Sur',$_POST['pet_dob']??null,$_POST['pet_pob']??null,
                $_POST['pet_civil_status']??null,$_POST['pet_spouse']??null,$_POST['pet_mothers_name']??null,
                $_POST['pet_education']??null,$_POST['pet_religion']??null,
                (int)($_POST['pet_male_members']??0),(int)($_POST['pet_female_members']??0),
                $_POST['hh_head']??'No',$_POST['hh_head_name']??null,
                $_POST['pwd']??'No',$_POST['fourps']??'No',
                $_POST['indigenous']??'No',$_POST['indigenous_specify']??null,
                $_POST['has_gov_id']??'No',$_POST['gov_id_number']??null,
                $_POST['farmers_assoc']??'No',$_POST['farmers_assoc_name']??null,
                $_POST['emergency_contact_name']??null,$_POST['emergency_contact_no']??null,
                $_POST['main_livelihood']??'Farmer',
                isset($_POST['farming_rice'])?1:0,isset($_POST['farming_corn'])?1:0,
                isset($_POST['farming_livestock'])?1:0,isset($_POST['farming_poultry'])?1:0,
                isset($_POST['farming_other'])?1:0,$_POST['farming_other_specify']??null,
                $_POST['livestock_specify']??null,$_POST['poultry_specify']??null,
                (float)($_POST['income_farming']??0),(float)($_POST['income_non_farming']??0)
            ]);
        $farmId = $pdo->lastInsertId();

        // Insert per-type livestock counts from dynamic rows
        $animalTypes = ['Cattle','Swine','Poultry','Goat','Carabao','Horse','Sheep','Rabbit'];
        foreach($animalTypes as $aType) {
            $qtyKey   = 'lv_qty_'.strtolower($aType);
            $tagKey   = 'lv_tag_'.strtolower($aType);
            $breedKey = 'lv_breed_'.strtolower($aType);
            $qty = (int)($_POST[$qtyKey] ?? 0);
            if ($qty > 0) {
                $tag = trim($_POST[$tagKey] ?? '');
                if (!$tag) $tag = 'TAG-'.strtoupper(substr($aType,0,3)).'-'.str_pad($farmId,4,'0',STR_PAD_LEFT);
                $breed = trim($_POST[$breedKey] ?? '') ?: null;
                $pdo->prepare("INSERT INTO livestock (farmId,type,tagId,breed,qty,healthStatus,vaccinationStatus,latitude,longitude) VALUES (?,?,?,?,?,'Healthy','None',?,?)")
                    ->execute([$farmId,$aType,$tag,$breed,$qty,$lat,$lng]);
            }
        }

        $pdo->prepare("INSERT INTO audit_log (userId,action,tableName,recordId,details,ipAddress) VALUES (?,'CREATE','farms',?,?,?)")
            ->execute([$userId,$farmId,json_encode(['farm_name'=>$name,'app_type'=>$appType]),$_SERVER['REMOTE_ADDR']??'']);

        $certNo = 'GAHP-'.date('Y').'-'.str_pad($farmId,5,'0',STR_PAD_LEFT);
        $issueDate = date('Y-m-d');
        $expiryDate = date('Y-m-d', strtotime('+1 year'));
        $pdo->prepare("INSERT INTO certificates (farm_id,cert_number,cert_type,issued_by,issued_date,expiry_date,status,notes) VALUES (?,?,'Registration','Bureau of Animal Industry - DA',?,?,'Active','Auto-issued GAHP Certificate')")
            ->execute([$farmId,$certNo,$issueDate,$expiryDate]);

        try {
            $pdo->prepare("INSERT INTO notifications(user_id,type,title,message,farm_id,priority,is_read,created_at) VALUES(?,'INFO',?,?,?,'Low',0,NOW())")
                ->execute([$userId, "📋 Farm Application Submitted", "Your farm application for '$name' was successfully submitted and certificate $certNo has been generated.", $farmId]);
        } catch(Exception $eN) {}

        echo json_encode(['success'=>true,'farm_id'=>$farmId,'message'=>"Farm '$name' registered! GAHP Certificate issued."]);
    } catch (Exception $e) {
        echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
    }
    exit;
}

// ── LIVESTOCK UPDATE ──────────────────────────────────────────────────────────
if ($requestMethod === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_livestock') {
    header('Content-Type: application/json');
    try {
        $id = (int)$_POST['livestock_id'];
        $notes = trim($_POST['notes'] ?? '') ?: null;
        $pdo->prepare("UPDATE livestock SET type=?,breed=?,age=?,weight=?,healthStatus=?,vaccinationStatus=?,qty=?,tagId=?,notes=? WHERE id=?")
            ->execute([$_POST['type'],$_POST['breed']??null,$_POST['age']??null,$_POST['weight']??null,$_POST['healthStatus'],$_POST['vaccinationStatus'],$_POST['qty'],$_POST['tagId'],$notes,$id]);
        echo json_encode(['success'=>true]);
    } catch(Exception $e){ echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
    exit;
}

// ── ADD LIVESTOCK ─────────────────────────────────────────────────────────────
if ($requestMethod === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_livestock') {
    header('Content-Type: application/json');
    try {
        $farmId = (int)$_POST['farm_id'];
        $f=$pdo->prepare("SELECT id FROM farms WHERE id=? AND ownerId=?");
        $f->execute([$farmId,$userId]);
        if(!$f->fetch()) throw new Exception('Farm not found');
        $health = $_POST['healthStatus'] ?? 'Healthy';
        $vax = $_POST['vaccinationStatus'] ?? 'None';
        $notes = trim($_POST['notes'] ?? '') ?: null;
        $pdo->prepare("INSERT INTO livestock(farmId,type,tagId,breed,age,weight,qty,healthStatus,vaccinationStatus,notes) VALUES(?,?,?,?,?,?,?,?,?,?)")
            ->execute([$farmId,$_POST['type'],$_POST['tagId'],$_POST['breed']??null,$_POST['age']??null,$_POST['weight']??null,$_POST['qty']??1,$health,$vax,$notes]);
        echo json_encode(['success'=>true]);
    } catch(Exception $e){ echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
    exit;
}

// ── DELETE LIVESTOCK ──────────────────────────────────────────────────────────
if ($requestMethod === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_livestock') {
    header('Content-Type: application/json');
    try {
        $id = (int)$_POST['livestock_id'];
        $stmt=$pdo->prepare("SELECT l.tagId,l.farmId FROM livestock l JOIN farms f ON l.farmId=f.id WHERE l.id=? AND f.ownerId=?");
        $stmt->execute([$id,$userId]);
        if(!$stmt->fetch()) throw new Exception('Unauthorized or not found');
        $pdo->prepare("DELETE FROM livestock WHERE id=?")->execute([$id]);
        echo json_encode(['success'=>true]);
    } catch(Exception $e){ echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
    exit;
}

// ── INCIDENT REPORT ───────────────────────────────────────────────────────────
if ($requestMethod === 'POST' && isset($_POST['action']) && $_POST['action'] === 'report_incident') {
    header('Content-Type: application/json');
    try {
        $type     = $_POST['incident_type'] ?? 'Others';
        $title    = trim($_POST['title']       ?? '');
        $desc     = trim($_POST['description'] ?? '');
        $farmId   = !empty($_POST['farm_id'])   ? (int)$_POST['farm_id']   : null;
        $lat      = !empty($_POST['latitude'])  ? (float)$_POST['latitude']  : null;
        $lng      = !empty($_POST['longitude']) ? (float)$_POST['longitude'] : null;
        $symptoms = trim($_POST['symptoms'] ?? '');
        $severity = $_POST['severity'] ?? 'Medium';
        $lvType   = $_POST['livestock_type'] ?? null;

        if(!$title || !$desc) throw new Exception('Title and description required');

        $disease_keywords = ['ASF','FMD','Avian Flu','African Swine Fever','Foot and Mouth','Bird Flu','PRRS','Classical'];
        $priority = 'Medium';
        $title_lower = strtolower($title.' '.$desc);
        foreach($disease_keywords as $kw) {
            if(strpos($title_lower, strtolower($kw)) !== false) { $priority='Critical'; break; }
        }
        if($severity==='High'||$severity==='Critical') $priority=$severity;

        $photoPath = null;
        if(!empty($_POST['photo_data'])&&strpos($_POST['photo_data'],'base64,')!==false){
            $imgData=base64_decode(explode(',',$_POST['photo_data'])[1]);
            @mkdir('uploads/incidents',0755,true);
            $photoPath='uploads/incidents/inc_'.time().'_'.$userId.'.jpg';
            file_put_contents($photoPath,$imgData);
        }

        // Try insert with extended columns, fallback to basic
        try {
            $pdo->prepare("INSERT INTO incidents(farmId,reporterId,type,title,description,latitude,longitude,photoUrl,status,priority,livestock_type,symptoms,severity) VALUES(?,?,?,?,?,?,?,?,'Pending',?,?,?,?)")
                ->execute([$farmId,$userId,$type,$title,$desc,$lat,$lng,$photoPath,$priority,$lvType,$symptoms,$severity]);
        } catch(PDOException $e2) {
            $pdo->prepare("INSERT INTO incidents(farmId,reporterId,type,title,description,latitude,longitude,photoUrl,status,priority) VALUES(?,?,?,?,?,?,?,?,'Pending',?)")
                ->execute([$farmId,$userId,$type,$title,$desc,$lat,$lng,$photoPath,$priority]);
        }
        $incId = $pdo->lastInsertId();

        $officers = $pdo->query("SELECT id FROM users WHERE role='Agriculture Official' AND status='Active'")->fetchAll();
        foreach($officers as $officer) {
            $alertTitle = $priority==='Critical' ? "🚨 CRITICAL DISEASE ALERT" : "⚠️ Incident Report #$incId";
            $alertMsg   = "Farmer ".htmlspecialchars($firstName.' '.$lastName)." reported: $title. Type: $type. Priority: $priority.";
            try {
                $pdo->prepare("INSERT INTO notifications(user_id,type,title,message,incident_id,priority,created_at) VALUES(?,'CRITICAL_ALERT',?,?,?,?,NOW())")
                    ->execute([$officer['id'],$alertTitle,$alertMsg,$incId,$priority]);
            } catch(PDOException $e3) {}
        }

        // Notify reporting farmer
        try {
            $pdo->prepare("INSERT INTO notifications(user_id,type,title,message,incident_id,priority,is_read,created_at) VALUES(?,'INFO',?,?,?,'Medium',0,NOW())")
                ->execute([$userId, "📋 Incident Report #$incId Submitted", "Your incident report \"$title\" has been submitted. DA Field Operations officers have been alerted.", $incId]);
        } catch(Exception $eN) {}

        // Broadcast critical alerts to all other farmers
        if ($priority === 'Critical') {
            try {
                $otherFarmers = $pdo->prepare("SELECT id FROM users WHERE role='Farmer' AND status='Active' AND id != ?");
                $otherFarmers->execute([$userId]);
                $fNotifStmt = $pdo->prepare("INSERT INTO notifications(user_id,type,title,message,incident_id,priority,is_read,created_at) VALUES(?,'OUTBREAK_WARNING',?,?,?,'Critical',0,NOW())");
                foreach ($otherFarmers->fetchAll() as $of) {
                    $fNotifStmt->execute([
                        $of['id'],
                        "🚨 CRITICAL ALERT: Disease Outbreak Reported",
                        "Urgent biosecurity advisory: A critical disease report ($type - $title) was flagged in your area. Enforce strict quarantine protocols immediately.",
                        $incId
                    ]);
                }
            } catch(Exception $eCrit) {}
        }

        // Log to audit
        try {
            $pdo->prepare("INSERT INTO audit_log(userId,action,tableName,recordId,details,ipAddress) VALUES(?,'CREATE','incidents',?,?,?)")
                ->execute([$userId,$incId,json_encode(['title'=>$title,'type'=>$type,'priority'=>$priority]),$_SERVER['REMOTE_ADDR']??'']);
        } catch(Exception $e4){}

        echo json_encode(['success'=>true,'incident_id'=>$incId,'priority'=>$priority,'notified'=>count($officers).' officers']);
    } catch(Exception $e){
        echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
    }
    exit;
}

// ── PUBLIC REPORT SUBMIT ──────────────────────────────────────────────────────
if ($requestMethod === 'POST' && isset($_POST['action']) && $_POST['action'] === 'public_report') {
    header('Content-Type: application/json');
    try {
        $reportType  = $_POST['report_type']   ?? 'Others';
        $otherType   = trim($_POST['other_type']   ?? '');
        $description = trim($_POST['description']  ?? '');
        $phone       = trim($_POST['contact_phone'] ?? '');
        $email       = trim($_POST['contact_email'] ?? '');
        $lat         = !empty($_POST['latitude'])  ? (float)$_POST['latitude']  : null;
        $lng         = !empty($_POST['longitude']) ? (float)$_POST['longitude'] : null;

        if(!$description) throw new Exception('Description is required.');
        if(!$phone) throw new Exception('Contact phone is required.');

        $idPhoto   = null;
        $facePhoto = null;

        if(!empty($_POST['id_photo']) && strpos($_POST['id_photo'],'base64,')!==false){
            $d=base64_decode(explode(',',$_POST['id_photo'])[1]);
            @mkdir('uploads/public_reports',0755,true);
            $idPhoto='uploads/public_reports/id_'.time().'.jpg';
            file_put_contents($idPhoto,$d);
        }
        if(!empty($_POST['face_photo']) && strpos($_POST['face_photo'],'base64,')!==false){
            $d=base64_decode(explode(',',$_POST['face_photo'])[1]);
            @mkdir('uploads/public_reports',0755,true);
            $facePhoto='uploads/public_reports/face_'.time().'.jpg';
            file_put_contents($facePhoto,$d);
        }

        // Enforce upload requirement: Citizen/Farmer must provide an ID photo or supporting file
        $hasUpload = $idPhoto || $facePhoto || (!empty($_FILES['id_photo']['name']) && $_FILES['id_photo']['error'] === UPLOAD_ERR_OK) || (!empty($_FILES['media_file']['name']) && $_FILES['media_file']['error'] === UPLOAD_ERR_OK);
        if (!$hasUpload) {
            throw new Exception('Upload required: Please upload an ID photo or supporting file before submitting.');
        }

        $pdo->prepare("INSERT INTO public_reports(reportType,otherType,description,contactPhone,contactEmail,idPhotoUrl,facePhotoUrl,latitude,longitude,status,createdAt)
            VALUES(?,?,?,?,?,?,?,?,?,'Pending',NOW())")
            ->execute([$reportType,$otherType,$description,$phone,$email,$idPhoto,$facePhoto,$lat,$lng]);

        $rId = $pdo->lastInsertId();

        // Notify DA officers
        $officers = $pdo->query("SELECT id FROM users WHERE role='Agriculture Official' AND status='Active'")->fetchAll();
        foreach($officers as $officer){
            try{
                $pdo->prepare("INSERT INTO notifications(user_id,type,title,message,priority,created_at) VALUES(?,'WARNING',?,?,'Medium',NOW())")
                    ->execute([$officer['id'],"📢 Public Report #$rId","Public report submitted: ".substr($description,0,80)]);
            }catch(Exception $ne){}
        }

        echo json_encode(['success'=>true,'report_id'=>$rId]);
    } catch(Exception $e){
        echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
    }
    exit;
}

// ── WEBRTC SIGNAL ─────────────────────────────────────────────────────────────
if ($requestMethod === 'POST' && isset($_POST['action']) && $_POST['action'] === 'webrtc_signal') {
    header('Content-Type: application/json');
    try {
        $toId    = (int)($_POST['to_id'] ?? 0);
        $payload = $_POST['payload'] ?? '';
        if(!$toId || !$payload) throw new Exception('Missing fields');
        $pdo->prepare("INSERT INTO webrtc_signals(sender_id,receiver_id,payload,consumed) VALUES(?,?,?,0)")
            ->execute([$userId,$toId,$payload]);
        echo json_encode(['success'=>true]);
    } catch(Exception $e){ echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
    exit;
}

if ($requestMethod === 'GET' && isset($_GET['action']) && $_GET['action'] === 'webrtc_poll') {
    header('Content-Type: application/json');
    $signals = $pdo->prepare("SELECT id,sender_id,payload FROM webrtc_signals WHERE receiver_id=? AND consumed=0 ORDER BY created_at ASC LIMIT 10");
    $signals->execute([$userId]);
    $rows = $signals->fetchAll();
    if(!empty($rows)){
        $ids = implode(',', array_column($rows,'id'));
        $pdo->query("UPDATE webrtc_signals SET consumed=1 WHERE id IN($ids)");
    }
    echo json_encode(['success'=>true,'signals'=>$rows]);
    exit;
}

// ── NOTIFICATIONS ACTIONS ───────────────────────────────────────────────────
if ($requestMethod === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_notif_read') {
    header('Content-Type: application/json');
    $notifId = (int)($_POST['notif_id'] ?? 0);
    if ($notifId > 0) {
        $pdo->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?")->execute([$notifId, $userId]);
    }
    $stUnread = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
    $stUnread->execute([$userId]);
    $rem = (int)$stUnread->fetchColumn();
    echo json_encode(['success' => true, 'unread_count' => $rem]);
    exit;
}

if ($requestMethod === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_all_notifs_read') {
    header('Content-Type: application/json');
    $pdo->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([$userId]);
    echo json_encode(['success' => true, 'unread_count' => 0]);
    exit;
}

if ($requestMethod === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_test_notif') {
    header('Content-Type: application/json');
    $testTemplates = [
        [
            'type' => 'CRITICAL_ALERT',
            'title' => '🚨 Outbreak Warning: ASF Active Buffer Zone',
            'message' => 'African Swine Fever active buffer zone established within 7km. Enforce mandatory footbaths, disinfect vehicles, and prohibit swill feeding immediately.',
            'priority' => 'Critical'
        ],
        [
            'type' => 'FARM_APPROVED',
            'title' => '✅ Farm Registration Approved',
            'message' => 'Good news! Your farm facility inspection has been completed and your registration is officially verified & approved by DA officials.',
            'priority' => 'Medium'
        ],
        [
            'type' => 'WARNING',
            'title' => '⚠️ Mandatory Livestock Deworming & Vaccination',
            'message' => 'DA Municipal technicians will conduct free Anthrax and Hemorrhagic Septicemia vaccinations in your sector next Wednesday. Please prepare logbooks.',
            'priority' => 'High'
        ],
        [
            'type' => 'INFO',
            'title' => '📄 GAHP Certificate Issued',
            'message' => 'Good Animal Husbandry Practices (GAHP) e-Certificate is active and ready for download in your E-Certificate tab.',
            'priority' => 'Low'
        ]
    ];
    $chosen = $testTemplates[array_rand($testTemplates)];
    $pdo->prepare("INSERT INTO notifications(user_id,type,title,message,priority,is_read,created_at) VALUES(?,?,?,?,?,0,NOW())")
        ->execute([$userId, $chosen['type'], $chosen['title'], $chosen['message'], $chosen['priority']]);
    $newId = (int)$pdo->lastInsertId();
    $stUnread = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
    $stUnread->execute([$userId]);
    $rem = (int)$stUnread->fetchColumn();
    echo json_encode([
        'success' => true,
        'notif' => [
            'id' => $newId,
            'type' => $chosen['type'],
            'title' => $chosen['title'],
            'message' => $chosen['message'],
            'priority' => $chosen['priority'],
            'created_at' => date('M d, Y H:i')
        ],
        'unread_count' => $rem
    ]);
    exit;
}

if ($requestMethod === 'GET' && isset($_GET['action']) && $_GET['action'] === 'poll_notifications') {
    header('Content-Type: application/json');
    $stU = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
    $stU->execute([$userId]);
    $uCount = (int)$stU->fetchColumn();

    $stN = $pdo->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 15");
    $stN->execute([$userId]);
    $list = $stN->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'unread_count' => $uCount,
        'notifications' => array_map(function($n) {
            return [
                'id' => (int)$n['id'],
                'type' => $n['type'],
                'title' => $n['title'],
                'message' => $n['message'],
                'priority' => $n['priority'],
                'is_read' => (int)$n['is_read'],
                'created_at' => date('M d, Y H:i', strtotime($n['created_at']))
            ];
        }, $list)
    ]);
    exit;
}

// ── FETCH ALL DATA ─────────────────────────────────────────────────────────────
$stFarms = $pdo->prepare("SELECT * FROM farms WHERE ownerId=? ORDER BY createdAt DESC");
$stFarms->execute([$userId]);
$farmsData = $stFarms->fetchAll();

// Per-animal-type totals for dashboard
$stLvByType = $pdo->prepare("
    SELECT l.type, COALESCE(SUM(l.qty),0) as total
    FROM livestock l JOIN farms f ON l.farmId=f.id
    WHERE f.ownerId=?
    GROUP BY l.type
    ORDER BY total DESC
");
$stLvByType->execute([$userId]);
$lvByType = $stLvByType->fetchAll(PDO::FETCH_KEY_PAIR);
$totalLivestock = array_sum($lvByType);

$stInc = $pdo->prepare("SELECT COUNT(*) FROM incidents WHERE reporterId=? AND status IN('Pending','In Progress')");
$stInc->execute([$userId]);
$activeIncidents = (int)$stInc->fetchColumn();

$stPend = $pdo->prepare("SELECT COUNT(*) FROM farms WHERE ownerId=? AND status='Pending'");
$stPend->execute([$userId]);
$pendingFarms = (int)$stPend->fetchColumn();

$stAllLv = $pdo->prepare("SELECT l.*,f.name as farmName,f.type as farmType,f.status as farmStatus,f.address as farmAddress,f.id as farmId
    FROM farms f LEFT JOIN livestock l ON l.farmId=f.id
    WHERE f.ownerId=? AND f.status!='Rejected'
    ORDER BY f.name ASC, l.createdAt DESC");
$stAllLv->execute([$userId]);
$allLvRows = $stAllLv->fetchAll();

$livestockByFarm = [];
foreach($allLvRows as $row) {
    $fid = $row['farmId'];
    if(!isset($livestockByFarm[$fid])) {
        $livestockByFarm[$fid] = [
            'farm' => ['id'=>$fid,'name'=>$row['farmName'],'type'=>$row['farmType'],'status'=>$row['farmStatus'],'address'=>$row['farmAddress']],
            'livestock' => []
        ];
    }
    if(!empty($row['tagId'])) $livestockByFarm[$fid]['livestock'][] = $row;
}

$stIncs = $pdo->prepare("SELECT i.*,f.name as farmName FROM incidents i LEFT JOIN farms f ON i.farmId=f.id WHERE i.reporterId=? ORDER BY i.createdAt DESC LIMIT 30");
$stIncs->execute([$userId]);
$incidentsData = $stIncs->fetchAll();

$stProf = $pdo->prepare("SELECT u.*,fp.gov_id,fp.gender,fp.dob,fp.address,fp.emergency_contact,fp.profile_pix FROM users u LEFT JOIN farmer_profiles fp ON u.id=fp.user_id WHERE u.id=?");
$stProf->execute([$userId]);
$profileData = $stProf->fetch();
if(!$profileData) $profileData = ['firstName'=>$firstName,'lastName'=>$lastName,'email'=>'','mobile'=>''];

foreach($farmsData as $farm) {
    $ex = $pdo->prepare("SELECT id FROM certificates WHERE farm_id=?");
    $ex->execute([$farm['id']]);
    if(!$ex->fetch()) {
        $certNo = 'GAHP-'.date('Y').'-'.str_pad($farm['id'],5,'0',STR_PAD_LEFT);
        $issueDate = $farm['createdAt'] ? date('Y-m-d',strtotime($farm['createdAt'])) : date('Y-m-d');
        $expiryDate = date('Y-m-d',strtotime($issueDate.'+1 year'));
        try {
            $pdo->prepare("INSERT INTO certificates(farm_id,cert_number,cert_type,issued_by,issued_date,expiry_date,status,notes) VALUES(?,?,'Registration','Bureau of Animal Industry - DA',?,?,'Active','Auto-issued GAHP Certificate')")
                ->execute([$farm['id'],$certNo,$issueDate,$expiryDate]);
        } catch(Exception $e) {}
    }
}

$stCerts = $pdo->prepare("
    SELECT c.*,f.name as farm_name,f.type as farm_type,f.address as farm_address,f.area as farm_area,
           f.ownerName,f.region,f.zip_code,f.status as farm_status,
           u.firstName as owner_firstname,u.lastName as owner_lastname
    FROM certificates c
    JOIN farms f ON c.farm_id=f.id
    LEFT JOIN users u ON f.ownerId=u.id
    WHERE f.ownerId=?
    ORDER BY c.created_at DESC
");
$stCerts->execute([$userId]);
$certsData = $stCerts->fetchAll();

$stUnread = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
$stUnread->execute([$userId]);
$unreadCount = (int)$stUnread->fetchColumn();

$stNotifs = $pdo->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 20");
$stNotifs->execute([$userId]);
$notificationsData = $stNotifs->fetchAll();

// Animal icons & emoji map
$animalEmoji = ['Cattle'=>'🐄','Swine'=>'🐖','Poultry'=>'🐓','Goat'=>'🐐','Carabao'=>'🐃','Horse'=>'🐴','Sheep'=>'🐑','Rabbit'=>'🐇'];
$allAnimalTypes = ['Cattle','Swine','Poultry','Goat','Carabao','Horse','Sheep','Rabbit'];

function healthBadge($s){$map=['Healthy'=>'badge-healthy','Sick'=>'badge-sick','Recovered'=>'badge-approved','Deceased'=>'badge-pending'];return $map[$s]??'badge-pending';}
function statusBadge($s){$map=['Pending'=>'badge-pending','Approved'=>'badge-approved','Rejected'=>'badge-rejected','Active'=>'badge-approved','Resolved'=>'badge-approved','In Progress'=>'badge-pending','Closed'=>'badge-pending'];return $map[$s]??'badge-pending';}
function animalIcon($type){$map=['Cattle'=>'bi-cow-fill','Swine'=>'bi-pig-fill','Poultry'=>'bi-egg-fill','Goat'=>'bi-bag-fill','Carabao'=>'bi-box2-fill','Horse'=>'bi-wind','Sheep'=>'bi-cloud-fill','Rabbit'=>'bi-circle-fill'];return $map[$type]??'bi-box-fill';}
function getAnimalTheme($type){
    $themes = [
        'Swine'   => ['bg'=>'linear-gradient(135deg,#fdf2f8,#fce7f3)','color'=>'#db2777'],
        'Cattle'  => ['bg'=>'linear-gradient(135deg,#fffbeb,#fef3c7)','color'=>'#d97706'],
        'Poultry' => ['bg'=>'linear-gradient(135deg,#fff7ed,#ffedd5)','color'=>'#ea580c'],
        'Goat'    => ['bg'=>'linear-gradient(135deg,#ecfdf5,#d1fae5)','color'=>'#059669'],
        'Carabao' => ['bg'=>'linear-gradient(135deg,#f8fafc,#e2e8f0)','color'=>'#334155'],
        'Horse'   => ['bg'=>'linear-gradient(135deg,#f5f3ff,#ede9fe)','color'=>'#7c3aed'],
        'Sheep'   => ['bg'=>'linear-gradient(135deg,#f8fafc,#f1f5f9)','color'=>'#475569'],
        'Rabbit'  => ['bg'=>'linear-gradient(135deg,#fdf4ff,#fae8ff)','color'=>'#c026d3'],
    ];
    return $themes[$type] ?? ['bg'=>'linear-gradient(135deg,#ecfdf5,#d1fae5)','color'=>'#059669'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>AgriTrace+ | Farmer Portal</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;0,9..40,800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
:root{
  --pri:#064e3b;--acc:#10b981;--acc2:#059669;--bg:#f1f5f9;
  --txt:#1e293b;--sub:#64748b;--danger:#ef4444;--warn:#f59e0b;
  --border:#e2e8f0;
  --safe-bottom:env(safe-area-inset-bottom,0px);
  --safe-top:env(safe-area-inset-top,0px);
}
*{margin:0;padding:0;box-sizing:border-box;-webkit-tap-highlight-color:transparent;}
html,body{height:100%;overflow:hidden;width:100%;max-width:100vw;-webkit-text-size-adjust:100%;text-size-adjust:100%;}
body{font-family:'DM Sans',-apple-system,sans-serif;background:var(--bg);color:var(--txt);overflow-x:hidden;}
h1,h2,h3,h4,h5,h6,p,td,th{overflow-wrap:break-word;word-wrap:break-word;}
.badge, .btn, .d-btn, .tb-chip, .drawer-item, .stat-num, .summary-num, .d-hero-date, .lang-switch-btn{white-space:nowrap;word-break:normal;overflow-wrap:normal;}
.app{display:flex;flex-direction:column;height:100vh;height:100dvh;overflow:hidden;min-width:0;width:100%;}
.top-bar{flex-shrink:0;background:#fff;border-bottom:1px solid var(--border);padding:0 1rem;height:56px;display:flex;align-items:center;justify-content:space-between;z-index:100;padding-top:var(--safe-top);}
.menu-btn{background:none;border:none;width:40px;height:40px;display:flex;align-items:center;justify-content:center;font-size:1.35rem;color:var(--pri);cursor:pointer;border-radius:10px;flex-shrink:0;}
.menu-btn:active{background:#f0fdf4;}
.page-title{font-weight:800;font-size:1.05rem;color:var(--txt);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.top-right{display:flex;align-items:center;gap:.65rem;flex-shrink:0;}
.content-area{flex:1;overflow-y:auto;overflow-x:hidden;-webkit-overflow-scrolling:touch;overscroll-behavior:contain;padding-bottom:calc(1.5rem + var(--safe-bottom));}
.content-inner{padding:1rem;}
.page{display:none;}.page.active{display:block;}
.card{background:#fff;border:1px solid var(--border);border-radius:16px;padding:1.125rem;margin-bottom:1rem;}
.card-title{font-size:1rem;font-weight:700;color:var(--txt);margin-bottom:1rem;}
.hero-card{background:linear-gradient(135deg,#064e3b,#10b981);border-radius:20px;padding:1.375rem 1.25rem;margin-bottom:1rem;color:#fff;position:relative;overflow:hidden;}
.hero-card::before{content:'';position:absolute;top:-30px;right:-30px;width:120px;height:120px;border-radius:50%;background:rgba(255,255,255,.07);}
.hero-greeting{font-size:1.25rem;font-weight:800;margin-bottom:.2rem;}
.hero-sub{font-size:.82rem;opacity:.8;}

/* DASHBOARD STATS — per animal type */
.stats-grid{display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-bottom:1rem;}
.stat-card{background:#fff;border:1px solid var(--border);border-radius:16px;padding:.875rem;text-align:center;position:relative;overflow:hidden;}
.stat-card::before{content:'';position:absolute;top:-15px;right:-15px;width:60px;height:60px;border-radius:50%;background:rgba(16,185,129,.06);}
.stat-emoji{font-size:1.5rem;display:block;margin-bottom:.2rem;line-height:1;}
.stat-num{font-size:1.75rem;font-weight:800;line-height:1;background:linear-gradient(135deg,var(--acc),var(--acc2));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;}
.stat-num.danger{background:linear-gradient(135deg,var(--danger),#dc2626);-webkit-background-clip:text;background-clip:text;}
.stat-num.warn{background:linear-gradient(135deg,var(--warn),#d97706);-webkit-background-clip:text;background-clip:text;}
.stat-num.info{background:linear-gradient(135deg,#3b82f6,#1d4ed8);-webkit-background-clip:text;background-clip:text;}
.stat-lbl{font-size:.65rem;color:var(--sub);font-weight:600;text-transform:uppercase;letter-spacing:.04em;margin-top:.2rem;}

/* Summary row */
.summary-row{display:grid;grid-template-columns:1fr 1fr 1fr;gap:.5rem;margin-bottom:1rem;}
.summary-card{background:#fff;border:1px solid var(--border);border-radius:12px;padding:.75rem;text-align:center;}
.summary-num{font-size:1.5rem;font-weight:800;line-height:1;}
.summary-num.danger{color:var(--danger);}
.summary-num.warn{color:var(--warn);}
.summary-num.pri{color:var(--acc);}
.summary-lbl{font-size:.62rem;color:var(--sub);font-weight:600;text-transform:uppercase;margin-top:.2rem;}

/* ═══ MODERN DASHBOARD ═══ */
.dash{max-width:1240px;margin:0 auto;}
.d-hero{position:relative;overflow:hidden;display:flex;align-items:center;justify-content:space-between;gap:1.5rem;flex-wrap:wrap;padding:1.75rem;border-radius:22px;color:#fff;margin-bottom:1.25rem;background:radial-gradient(circle at 85% 20%,rgba(52,211,153,.55),transparent 45%),linear-gradient(135deg,#022c22 0%,#064e3b 55%,#059669 100%);box-shadow:0 14px 34px -14px rgba(6,78,59,.55);}
.d-hero::before{content:'';position:absolute;right:-70px;bottom:-90px;width:260px;height:260px;border-radius:50%;background:rgba(255,255,255,.06);}
.d-hero::after{content:'';position:absolute;left:38%;top:-80px;width:160px;height:160px;border-radius:50%;background:rgba(255,255,255,.05);}
.d-hero-main{position:relative;z-index:1;flex:1 1 320px;min-width:0;}
.d-hero-date{display:inline-flex;align-items:center;gap:.4rem;font-size:.72rem;font-weight:600;background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.18);padding:.3rem .7rem;border-radius:9999px;backdrop-filter:blur(6px);}
.d-hero h1{font-size:clamp(1.4rem,2.6vw,2rem);font-weight:800;letter-spacing:-.02em;margin:.8rem 0 .3rem;line-height:1.15;}
.d-hero p{font-size:.9rem;opacity:.82;margin-bottom:1.1rem;}
.d-hero-actions{display:flex;flex-wrap:wrap;gap:.6rem;}
.d-btn{display:inline-flex;align-items:center;justify-content:center;gap:.45rem;border:1px solid transparent;border-radius:12px;padding:.65rem 1.05rem;font-family:inherit;font-size:.82rem;font-weight:700;cursor:pointer;transition:transform .15s,box-shadow .15s,background .15s;min-height:42px;}
.d-btn:hover{transform:translateY(-1px);}
.d-btn-light{background:#fff;color:#064e3b;box-shadow:0 6px 16px -6px rgba(0,0,0,.35);}
.d-btn-ghost{background:rgba(255,255,255,.12);color:#fff;border-color:rgba(255,255,255,.28);}
.d-btn-ghost:hover{background:rgba(255,255,255,.22);}
.d-btn-primary{background:linear-gradient(135deg,#059669,#10b981);color:#fff;box-shadow:0 6px 14px -6px rgba(16,185,129,.7);}
.d-btn-sm{padding:.5rem .85rem;font-size:.76rem;min-height:36px;border-radius:10px;}
.d-hero-side{position:relative;z-index:1;display:flex;align-items:center;gap:1rem;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.18);border-radius:18px;padding:1rem 1.25rem;backdrop-filter:blur(8px);}
.d-hero-ring{--p:0;width:84px;height:84px;border-radius:50%;flex-shrink:0;display:grid;place-items:center;background:conic-gradient(#6ee7b7 calc(var(--p)*1%),rgba(255,255,255,.2) 0);}
.d-hero-ring>div{width:66px;height:66px;border-radius:50%;background:#064e3b;display:flex;flex-direction:column;align-items:center;justify-content:center;line-height:1.05;}
.d-hero-ring strong{font-size:1.15rem;font-weight:800;}
.d-hero-ring span{font-size:.55rem;text-transform:uppercase;letter-spacing:.06em;opacity:.75;font-weight:600;}
.d-hero-side-txt{display:flex;flex-direction:column;}
.d-hero-side-txt strong{font-size:1.7rem;font-weight:800;line-height:1;}
.d-hero-side-txt span{font-size:.74rem;opacity:.8;margin-top:.25rem;max-width:130px;}

.d-kpis{display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:1.25rem;}
.d-kpi{display:flex;align-items:center;gap:.9rem;background:#fff;border:1px solid var(--border);border-radius:18px;padding:1.05rem 1.1rem;box-shadow:0 1px 2px rgba(15,23,42,.04);transition:transform .2s,box-shadow .2s;}
.d-kpi:hover{transform:translateY(-3px);box-shadow:0 14px 28px -14px rgba(15,23,42,.22);}
.d-kpi-ico{--c:#10b981;width:48px;height:48px;border-radius:14px;flex-shrink:0;display:grid;place-items:center;font-size:1.3rem;color:var(--c);background:color-mix(in srgb,var(--c) 14%,#fff);}
.d-kpi-body{display:flex;flex-direction:column;min-width:0;}
.d-kpi-body span{font-size:.7rem;font-weight:700;color:var(--sub);text-transform:uppercase;letter-spacing:.05em;}
.d-kpi-body strong{font-size:1.65rem;font-weight:800;line-height:1.15;letter-spacing:-.02em;color:var(--txt);}
.d-kpi-body small{font-size:.72rem;color:var(--sub);}

.d-grid{display:grid;grid-template-columns:minmax(0,1.55fr) minmax(0,1fr);gap:1.25rem;align-items:start;}
.d-col{display:flex;flex-direction:column;gap:1.25rem;min-width:0;}
.d-card{background:#fff;border:1px solid var(--border);border-radius:20px;padding:1.25rem;box-shadow:0 1px 2px rgba(15,23,42,.04);}
.d-card-hd{display:flex;align-items:flex-start;justify-content:space-between;gap:.75rem;margin-bottom:1.1rem;}
.d-card-hd h3{font-size:1rem;font-weight:800;letter-spacing:-.01em;color:var(--txt);}
.d-card-hd p{font-size:.74rem;color:var(--sub);margin-top:.15rem;}
.d-link{background:none;border:none;font-family:inherit;font-size:.78rem;font-weight:700;color:var(--acc2);cursor:pointer;display:inline-flex;align-items:center;gap:.3rem;padding:.3rem .5rem;border-radius:8px;white-space:nowrap;}
.d-link:hover{background:#ecfdf5;}

.d-bars{display:flex;flex-direction:column;gap:1rem;}
.d-bar-row{display:flex;align-items:center;gap:.85rem;}
.d-bar-ico{--c:#10b981;width:42px;height:42px;border-radius:12px;flex-shrink:0;display:grid;place-items:center;font-size:1.3rem;background:color-mix(in srgb,var(--c) 14%,#fff);}
.d-bar-main{flex:1;min-width:0;}
.d-bar-top{display:flex;justify-content:space-between;align-items:baseline;font-size:.82rem;font-weight:600;margin-bottom:.4rem;}
.d-bar-top strong{font-weight:800;}
.d-bar-top em{font-style:normal;font-size:.72rem;color:var(--sub);margin-left:.3rem;}
.d-bar-track{height:8px;border-radius:9999px;background:#f1f5f9;overflow:hidden;}
.d-bar-fill{height:100%;border-radius:9999px;animation:dBar .8s cubic-bezier(.2,.8,.2,1) both;transform-origin:left;}
@keyframes dBar{from{transform:scaleX(0);}to{transform:scaleX(1);}}

.d-farms{display:grid;grid-template-columns:1fr;gap:.75rem;}
.d-farm{display:flex;align-items:center;gap:.9rem;padding:.9rem 1rem;border:1px solid var(--border);border-radius:16px;background:#fff;transition:border-color .15s,box-shadow .15s;}
.d-farm:hover{border-color:#a7f3d0;box-shadow:0 8px 20px -12px rgba(16,185,129,.45);}
.d-farm-av{width:46px;height:46px;border-radius:14px;flex-shrink:0;display:grid;place-items:center;color:#fff;font-weight:800;font-size:1.1rem;background:linear-gradient(135deg,#064e3b,#10b981);}
.d-farm-info{flex:1;min-width:0;}
.d-farm-name{font-weight:800;font-size:.92rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.d-farm-meta{display:flex;flex-wrap:wrap;gap:.2rem .85rem;margin-top:.3rem;font-size:.73rem;color:var(--sub);}
.d-farm-meta i{color:var(--acc);margin-right:.2rem;}

.d-health{display:flex;align-items:center;gap:1.4rem;flex-wrap:wrap;justify-content:center;}
.d-donut{width:148px;height:148px;border-radius:50%;display:grid;place-items:center;flex-shrink:0;}
.d-donut>div{width:104px;height:104px;border-radius:50%;background:#fff;display:flex;flex-direction:column;align-items:center;justify-content:center;box-shadow:inset 0 0 0 1px var(--border);}
.d-donut strong{font-size:1.5rem;font-weight:800;line-height:1;}
.d-donut span{font-size:.62rem;text-transform:uppercase;letter-spacing:.06em;color:var(--sub);font-weight:700;margin-top:.2rem;}
.d-legend{list-style:none;flex:1;min-width:140px;display:flex;flex-direction:column;gap:.55rem;}
.d-legend li{display:flex;align-items:center;gap:.55rem;font-size:.82rem;font-weight:600;}
.d-legend li i{width:10px;height:10px;border-radius:50%;flex-shrink:0;}
.d-legend li span{flex:1;color:var(--sub);}
.d-legend li strong{font-weight:800;}

.d-list{display:flex;flex-direction:column;}
.d-item{display:flex;align-items:center;gap:.8rem;padding:.75rem 0;border-bottom:1px solid #f1f5f9;}
.d-item:last-child{border-bottom:none;padding-bottom:0;}
.d-item:first-child{padding-top:0;}
.d-item-ico{--c:#10b981;width:38px;height:38px;border-radius:11px;flex-shrink:0;display:grid;place-items:center;font-size:1rem;color:var(--c);background:color-mix(in srgb,var(--c) 14%,#fff);}
.d-item-body{flex:1;min-width:0;}
.d-item-t{font-size:.84rem;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.d-item-s{font-size:.73rem;color:var(--sub);margin-top:.12rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.d-dot{width:9px;height:9px;border-radius:50%;background:var(--danger);flex-shrink:0;}
.d-empty{text-align:center;padding:1.5rem 1rem;color:var(--sub);}
.d-empty i{font-size:2rem;display:block;margin-bottom:.5rem;opacity:.4;}
.d-empty h4{font-size:.92rem;font-weight:700;color:var(--txt);margin-bottom:.25rem;}
.d-empty p{font-size:.78rem;line-height:1.5;}

@media(max-width:1024px){
  .d-grid{grid-template-columns:1fr;}
  .d-kpis{grid-template-columns:repeat(2,1fr);}
}
@media(max-width:560px){
  .d-hero{padding:1.25rem;border-radius:18px;}
  .d-hero-side{width:100%;}
  .d-hero-actions .d-btn{flex:1 1 auto;}
  .d-kpis{gap:.7rem;}
  .d-kpi{padding:.85rem;gap:.7rem;flex-direction:column;align-items:flex-start;}
  .d-kpi-body strong{font-size:1.4rem;}
  .d-card{padding:1rem;border-radius:16px;}
  .d-donut{width:128px;height:128px;}
  .d-donut>div{width:90px;height:90px;}
}

.badge{display:inline-flex;align-items:center;gap:.25rem;padding:.25rem .65rem;border-radius:9999px;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.03em;}
.badge-pending{background:#fef3c7;color:#92400e;}
.badge-approved{background:#d1fae5;color:#065f46;}
.badge-rejected{background:#fee2e2;color:#991b1b;}
.badge-healthy{background:#d1fae5;color:#065f46;}
.badge-sick{background:#fee2e2;color:#991b1b;}
.farm-item{border:1px solid var(--border);border-radius:14px;padding:1rem;display:flex;align-items:center;gap:.875rem;background:#fff;margin-bottom:.75rem;}
.farm-item:active{background:#f0fdf4;}
.farm-avatar{width:46px;height:46px;border-radius:12px;flex-shrink:0;background:linear-gradient(135deg,var(--pri),var(--acc));color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:1.1rem;}
.farm-info{flex:1;min-width:0;}
.farm-name{font-weight:700;font-size:.9rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.farm-meta{font-size:.75rem;color:var(--sub);margin-top:.15rem;}
.form-group{margin-bottom:.875rem;}
.form-label{display:block;font-size:.8rem;font-weight:700;color:var(--sub);text-transform:uppercase;letter-spacing:.03em;margin-bottom:.4rem;}
.form-input,.form-select{width:100%;padding:.75rem 1rem;border:1.5px solid var(--border);border-radius:12px;font-size:.9rem;font-family:inherit;background:#fff;color:var(--txt);-webkit-appearance:none;appearance:none;transition:.15s;}
.form-input:focus,.form-select:focus{outline:none;border-color:var(--acc);box-shadow:0 0 0 3px rgba(16,185,129,.12);}
textarea.form-input{resize:none;line-height:1.5;}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:.4rem;padding:.75rem 1.25rem;border:none;border-radius:12px;font-weight:700;font-size:.875rem;font-family:inherit;cursor:pointer;transition:.15s;min-height:44px;}
.btn:active{transform:scale(.98);}
.btn-primary{background:var(--acc);color:#fff;}
.btn-primary:active{background:var(--acc2);}
.btn-secondary{background:#f8fafc;color:var(--txt);border:1.5px solid var(--border);}
.btn-danger-sm{background:none;border:none;color:var(--danger);cursor:pointer;font-size:1.1rem;padding:.375rem;min-width:36px;min-height:36px;display:flex;align-items:center;justify-content:center;border-radius:8px;}
.btn-danger-sm:active{background:#fee2e2;}
.btn-edit-sm{background:none;border:none;color:var(--sub);cursor:pointer;font-size:1.1rem;padding:.375rem;min-width:36px;min-height:36px;display:flex;align-items:center;justify-content:center;border-radius:8px;}
.btn-edit-sm:active{background:#f0fdf4;color:var(--acc);}
.btn-submit{background:linear-gradient(135deg,#064e3b,#10b981);color:#fff;border-radius:14px;font-size:.95rem;padding:.875rem 1.5rem;width:100%;border:none;font-weight:700;font-family:inherit;cursor:pointer;min-height:52px;display:flex;align-items:center;justify-content:center;gap:.5rem;}
.btn-submit:active{opacity:.9;}
.section-hdr{background:linear-gradient(135deg,#085041,#10b981);border-radius:16px;padding:1rem 1.125rem;display:flex;align-items:center;gap:.875rem;margin-bottom:1rem;color:#fff;}
.section-seal{width:48px;height:48px;border-radius:50%;flex-shrink:0;background:rgba(255,255,255,.15);border:2px solid rgba(255,255,255,.25);display:flex;align-items:center;justify-content:center;font-size:1.5rem;}
.section-hdr h2{font-size:.875rem;font-weight:700;margin-bottom:.15rem;}
.section-hdr p{font-size:.72rem;opacity:.75;line-height:1.4;}
.form-section{background:#fff;border:1px solid var(--border);border-radius:16px;margin-bottom:.875rem;overflow:hidden;}
.form-section-hdr{background:linear-gradient(90deg,#f0fdf4,#f8fafc);border-bottom:1px solid var(--border);padding:.625rem 1rem;display:flex;align-items:center;gap:.5rem;font-size:.72rem;font-weight:700;color:var(--pri);text-transform:uppercase;letter-spacing:.05em;}
.form-section-num{background:var(--acc);color:#fff;border-radius:50%;width:18px;height:18px;font-size:.65rem;display:flex;align-items:center;justify-content:center;font-weight:800;flex-shrink:0;}
.form-section-body{padding:1rem;}
.toggle-row{display:flex;gap:.5rem;margin-bottom:1rem;flex-wrap:wrap;}
.tog-btn{flex:1;min-width:100px;border:1.5px solid var(--border);border-radius:10px;padding:.5rem .75rem;background:#fff;cursor:pointer;display:flex;align-items:center;gap:.375rem;font-size:.78rem;font-weight:600;color:var(--sub);transition:.15s;font-family:inherit;}
.tog-btn.active{border-color:var(--acc);background:#f0fdf4;color:var(--pri);}
.biz-row{display:flex;gap:.375rem;flex-wrap:wrap;margin-top:.375rem;}
.biz-pill{border:1.5px solid var(--border);border-radius:9999px;padding:.3rem .75rem;font-size:.75rem;font-weight:600;cursor:pointer;background:#fff;color:var(--sub);transition:.15s;font-family:inherit;}
.biz-pill.active{background:var(--acc);color:#fff;border-color:var(--acc);}
.cb-grid{display:grid;grid-template-columns:1fr 1fr;gap:.375rem;margin-top:.375rem;}
.cb-item{display:flex;align-items:center;gap:.5rem;font-size:.8rem;cursor:pointer;padding:.5rem .625rem;border:1.5px solid var(--border);border-radius:8px;transition:.15s;color:var(--sub);user-select:none;}
.cb-item.checked{border-color:var(--acc);background:#d1fae5;color:var(--pri);}
.cb-item input{accent-color:var(--acc);flex-shrink:0;width:14px;height:14px;}
.gps-zone{border:2px dashed var(--border);border-radius:14px;padding:1.25rem 1rem;text-align:center;background:linear-gradient(135deg,#f9fafb,#f3f4f6);margin-bottom:.875rem;}
.gps-zone h4{font-size:.85rem;font-weight:700;color:var(--sub);margin-bottom:.75rem;}
.photo-actions{display:flex;gap:.5rem;}
.btn-cam{flex:1;padding:.625rem .875rem;border-radius:10px;font-size:.8rem;font-weight:600;cursor:pointer;border:none;transition:.15s;display:flex;align-items:center;justify-content:center;gap:.375rem;font-family:inherit;min-height:40px;}
.btn-cam.primary{background:var(--acc);color:#fff;}
.btn-cam.secondary{background:#fff;color:var(--txt);border:1.5px solid var(--border);}
.gps-badge{display:none;align-items:center;justify-content:center;gap:.375rem;background:#d1fae5;color:#065f46;border:1px solid #6ee7b7;border-radius:8px;padding:.5rem .875rem;font-size:.78rem;font-weight:600;margin-top:.75rem;}
.gps-badge.show{display:flex;}
.photo-preview{display:none;margin-top:.875rem;border-radius:12px;overflow:hidden;border:2px solid var(--acc);position:relative;}
.photo-preview.show{display:block;}
.photo-preview canvas{width:100%;display:block;}
.remove-photo{position:absolute;top:8px;right:8px;background:rgba(239,68,68,.9);color:#fff;border:none;width:28px;height:28px;border-radius:50%;cursor:pointer;font-size:.75rem;display:flex;align-items:center;justify-content:center;}

/* DYNAMIC LIVESTOCK ROWS in farm registration */
.lv-dynamic-list{display:flex;flex-direction:column;gap:.625rem;margin-bottom:.875rem;}
.lv-dynamic-row{background:#f8fafc;border:1.5px solid var(--border);border-radius:12px;padding:.875rem;position:relative;animation:fadeIn .2s ease;}
@keyframes fadeIn{from{opacity:0;transform:translateY(-6px);}to{opacity:1;transform:translateY(0);}}
.lv-dynamic-row .remove-lv-row{position:absolute;top:.625rem;right:.625rem;background:none;border:none;color:var(--danger);cursor:pointer;font-size:1rem;width:28px;height:28px;border-radius:6px;display:flex;align-items:center;justify-content:center;}
.lv-dynamic-row .remove-lv-row:active{background:#fee2e2;}
.lv-row-top{display:flex;align-items:center;gap:.5rem;margin-bottom:.625rem;}
.lv-animal-emoji{font-size:1.375rem;flex-shrink:0;}
.lv-row-type-label{font-weight:700;font-size:.9rem;color:var(--pri);}
.lv-row-fields{display:grid;grid-template-columns:1fr 1fr;gap:.5rem;}
.lv-row-fields input{padding:.5rem .75rem;border:1.5px solid var(--border);border-radius:10px;font-size:.83rem;font-family:inherit;background:#fff;width:100%;}
.lv-row-fields input:focus{outline:none;border-color:var(--acc);}
.lv-row-fields .field-full{grid-column:1/-1;}
.btn-add-animal{width:100%;border:2px dashed var(--acc);background:#f0fdf4;color:var(--pri);border-radius:12px;padding:.75rem;font-size:.85rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:.5rem;font-family:inherit;transition:.15s;}
.btn-add-animal:active{background:#d1fae5;}
.animal-picker{display:grid;grid-template-columns:repeat(4,1fr);gap:.5rem;margin-top:.875rem;}
.animal-pick-btn{border:1.5px solid var(--border);background:#fff;border-radius:10px;padding:.625rem .375rem;display:flex;flex-direction:column;align-items:center;gap:.25rem;cursor:pointer;font-size:.65rem;font-weight:700;color:var(--sub);transition:.15s;font-family:inherit;}
.animal-pick-btn:active{border-color:var(--acc);background:#f0fdf4;color:var(--pri);}
.animal-pick-btn span{font-size:1.4rem;line-height:1;}
.animal-picker-wrap{background:#fff;border:1px solid var(--border);border-radius:14px;padding:.875rem;margin-bottom:.875rem;display:none;}
.animal-picker-wrap.show{display:block;}

/* ═══════════════════════════════════════════════════════════════════════════
   MODERN LIVESTOCK WEB APP STYLES
   ═══════════════════════════════════════════════════════════════════════════ */
.lv-app-header {
  background: linear-gradient(135deg, #064e3b 0%, #047857 55%, #10b981 100%);
  border-radius: 20px;
  padding: 1.5rem 1.4rem;
  margin-bottom: 1.25rem;
  color: #fff;
  position: relative;
  overflow: hidden;
  box-shadow: 0 10px 25px -5px rgba(6, 78, 59, 0.25);
}
.lv-app-header::after {
  content: '';
  position: absolute;
  top: -45px;
  right: -45px;
  width: 170px;
  height: 170px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.08);
  pointer-events: none;
}
.lv-badge-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: rgba(255, 255, 255, 0.16);
  backdrop-filter: blur(8px);
  padding: 4px 10px;
  border-radius: 9999px;
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  margin-bottom: 0.5rem;
  border: 1px solid rgba(255, 255, 255, 0.25);
}
.lv-pulse-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: #34d399;
  box-shadow: 0 0 0 0 rgba(52, 211, 153, 0.7);
  animation: lvPulse 2s infinite;
}
@keyframes lvPulse {
  0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(52, 211, 153, 0.7); }
  70% { transform: scale(1); box-shadow: 0 0 0 7px rgba(52, 211, 153, 0); }
  100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(52, 211, 153, 0); }
}
.lv-header-content {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}
@media (min-width: 768px) {
  .lv-header-content {
    flex-direction: row;
    align-items: center;
    justify-content: space-between;
  }
}
.lv-header-actions {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
}
.lv-btn-primary {
  background: #fff;
  color: #064e3b;
  border: none;
  font-weight: 700;
  font-size: 0.84rem;
  padding: 0.65rem 1.15rem;
  border-radius: 12px;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 7px;
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  font-family: inherit;
}
.lv-btn-primary:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 16px rgba(0, 0, 0, 0.15);
  background: #f0fdf4;
}
.lv-btn-glass {
  background: rgba(255, 255, 255, 0.16);
  color: #fff;
  border: 1px solid rgba(255, 255, 255, 0.28);
  font-weight: 600;
  font-size: 0.84rem;
  padding: 0.65rem 1rem;
  border-radius: 12px;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  transition: all 0.2s;
  font-family: inherit;
  backdrop-filter: blur(8px);
}
.lv-btn-glass:hover {
  background: rgba(255, 255, 255, 0.26);
}

/* 2. Top Metric KPI Dashboard Cards */
.lv-kpi-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 0.75rem;
  margin-bottom: 1.25rem;
}
@media (min-width: 900px) {
  .lv-kpi-grid {
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
  }
}
.lv-kpi-card {
  background: #fff;
  border: 1px solid var(--border);
  border-radius: 16px;
  padding: 1rem;
  position: relative;
  overflow: hidden;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.03);
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.lv-kpi-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 18px rgba(0, 0, 0, 0.06);
}
.lv-kpi-icon {
  width: 42px;
  height: 42px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.2rem;
  margin-bottom: 0.65rem;
}
.lv-kpi-val {
  font-size: 1.6rem;
  font-weight: 800;
  line-height: 1.1;
  color: var(--txt);
  margin-bottom: 0.25rem;
  display: flex;
  align-items: baseline;
  gap: 6px;
}
.lv-kpi-lbl {
  font-size: 0.72rem;
  font-weight: 700;
  color: var(--sub);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
.lv-kpi-sub {
  font-size: 0.72rem;
  color: var(--sub);
  margin-top: 0.35rem;
  display: flex;
  align-items: center;
  gap: 4px;
}

/* 3. Species Filter Ribbon */
.lv-ribbon-wrap {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  overflow-x: auto;
  padding-bottom: 0.5rem;
  margin-bottom: 1rem;
  -webkit-overflow-scrolling: touch;
  scrollbar-width: none;
}
.lv-ribbon-wrap::-webkit-scrollbar {
  display: none;
}
.lv-chip-btn {
  background: #fff;
  border: 1.5px solid var(--border);
  padding: 0.45rem 0.95rem;
  border-radius: 9999px;
  font-size: 0.78rem;
  font-weight: 700;
  color: var(--sub);
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  white-space: nowrap;
  transition: all 0.18s ease;
  font-family: inherit;
}
.lv-chip-btn:hover {
  border-color: #cbd5e1;
  color: var(--txt);
}
.lv-chip-btn.active {
  background: #ecfdf5;
  border-color: #10b981;
  color: #064e3b;
  box-shadow: 0 2px 6px rgba(16, 185, 129, 0.15);
}
.lv-chip-count {
  background: #f1f5f9;
  color: var(--sub);
  font-size: 0.7rem;
  padding: 1px 6px;
  border-radius: 9999px;
  font-weight: 800;
}
.lv-chip-btn.active .lv-chip-count {
  background: #10b981;
  color: #fff;
}

/* 4. Toolbar: Search, Filters & View Switcher */
.lv-toolbar-card {
  background: #fff;
  border: 1px solid var(--border);
  border-radius: 16px;
  padding: 0.875rem 1rem;
  margin-bottom: 1.25rem;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
}
@media (min-width: 820px) {
  .lv-toolbar-card {
    flex-direction: row;
    align-items: center;
    justify-content: space-between;
  }
}
.lv-search-box {
  position: relative;
  flex: 1;
  max-width: 360px;
}
.lv-search-box i.bi-search {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--sub);
  font-size: 0.85rem;
}
.lv-search-input {
  width: 100%;
  padding: 0.55rem 2rem 0.55rem 2.25rem;
  border: 1.5px solid var(--border);
  border-radius: 10px;
  font-size: 0.83rem;
  background: #f8fafc;
  font-family: inherit;
  transition: all 0.2s;
}
.lv-search-input:focus {
  outline: none;
  background: #fff;
  border-color: #10b981;
  box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.12);
}
.lv-search-clear {
  position: absolute;
  right: 10px;
  top: 50%;
  transform: translateY(-50%);
  background: none;
  border: none;
  color: var(--sub);
  cursor: pointer;
  display: none;
  font-size: 0.95rem;
}
.lv-filters-row {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-wrap: wrap;
}
.lv-select {
  padding: 0.5rem 1.8rem 0.5rem 0.75rem;
  border: 1.5px solid var(--border);
  border-radius: 10px;
  background: #f8fafc url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%2364748b' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E") no-repeat calc(100% - 8px) center;
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--txt);
  cursor: pointer;
  font-family: inherit;
  appearance: none;
}
.lv-view-toggles {
  display: flex;
  background: #f1f5f9;
  padding: 3px;
  border-radius: 10px;
  border: 1px solid var(--border);
}
.lv-view-btn {
  background: none;
  border: none;
  padding: 5px 10px;
  border-radius: 7px;
  color: var(--sub);
  font-size: 0.8rem;
  font-weight: 700;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 5px;
  transition: all 0.15s;
  font-family: inherit;
}
.lv-view-btn.active {
  background: #fff;
  color: var(--txt);
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
}

/* 5. Grid View Cards */
.lv-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 1rem;
  margin-bottom: 1.5rem;
}
@media (min-width: 680px) {
  .lv-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}
@media (min-width: 1120px) {
  .lv-grid {
    grid-template-columns: repeat(3, 1fr);
  }
}
.lv-card {
  background: #fff;
  border: 1.5px solid var(--border);
  border-radius: 18px;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
  position: relative;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.02);
}
.lv-card:hover {
  transform: translateY(-3px);
  border-color: #cbd5e1;
  box-shadow: 0 10px 24px -5px rgba(0, 0, 0, 0.08);
}
.lv-card-top {
  padding: 1.15rem 1.15rem 0.85rem;
  display: flex;
  align-items: flex-start;
  gap: 0.85rem;
}
.lv-avatar {
  width: 48px;
  height: 48px;
  border-radius: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.5rem;
  flex-shrink: 0;
  box-shadow: 0 4px 10px rgba(0, 0, 0, 0.06);
}
.lv-card-main {
  flex: 1;
  min-width: 0;
}
.lv-card-title {
  font-weight: 800;
  font-size: 0.95rem;
  color: var(--txt);
  display: flex;
  align-items: center;
  gap: 6px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.lv-farm-badge {
  font-size: 0.72rem;
  color: var(--sub);
  display: flex;
  align-items: center;
  gap: 4px;
  margin-top: 2px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.lv-tag-strip {
  background: #f8fafc;
  border-top: 1px dashed var(--border);
  border-bottom: 1px dashed var(--border);
  padding: 0.5rem 1.15rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.lv-tag-chip {
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  font-size: 0.78rem;
  font-weight: 700;
  color: #064e3b;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  cursor: pointer;
  background: #ecfdf5;
  padding: 3px 8px;
  border-radius: 6px;
  border: 1px solid rgba(16, 185, 129, 0.3);
  transition: all 0.15s;
}
.lv-tag-chip:hover {
  background: #d1fae5;
}
.lv-qty-pill {
  font-size: 0.75rem;
  font-weight: 800;
  color: #047857;
  background: #e6fffa;
  padding: 3px 8px;
  border-radius: 6px;
}
.lv-vitals-grid {
  padding: 0.85rem 1.15rem;
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.5rem;
}
.lv-vital-cell {
  background: #f8fafc;
  padding: 0.45rem 0.65rem;
  border-radius: 8px;
  font-size: 0.73rem;
}
.lv-vital-lbl {
  color: var(--sub);
  font-size: 0.65rem;
  text-transform: uppercase;
  font-weight: 700;
  display: block;
  margin-bottom: 1px;
}
.lv-vital-val {
  font-weight: 700;
  color: var(--txt);
}
.lv-notes-callout {
  margin: 0 1.15rem 0.85rem;
  background: #f8fafc;
  border-left: 3px solid #10b981;
  border-radius: 0 8px 8px 0;
  padding: 0.45rem 0.65rem;
  font-size: 0.73rem;
  color: var(--sub);
  line-height: 1.4;
  font-style: italic;
}
.lv-card-footer {
  margin-top: auto;
  border-top: 1px solid #f1f5f9;
  padding: 0.65rem 1.15rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: #fafafa;
}
.lv-card-btns {
  display: flex;
  align-items: center;
  gap: 0.4rem;
}
.lv-action-btn {
  border: 1px solid var(--border);
  background: #fff;
  color: var(--txt);
  padding: 5px 9px;
  border-radius: 8px;
  font-size: 0.75rem;
  font-weight: 600;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 5px;
  transition: all 0.15s;
  font-family: inherit;
}
.lv-action-btn:hover {
  background: #f8fafc;
  border-color: #cbd5e1;
}
.lv-action-btn.danger {
  color: #ef4444;
  border-color: #fee2e2;
  background: #fef2f2;
}
.lv-action-btn.danger:hover {
  background: #fee2e2;
}
.lv-action-btn.warn {
  color: #d97706;
  border-color: #fef3c7;
  background: #fffbeb;
}
.lv-action-btn.warn:hover {
  background: #fef3c7;
}

/* 6. Modern High-Density Table View */
.lv-table-card {
  background: #fff;
  border: 1px solid var(--border);
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.03);
  margin-bottom: 1.5rem;
}
.lv-table-wrap {
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
}
.lv-table {
  width: 100%;
  border-collapse: collapse;
  text-align: left;
  font-size: 0.82rem;
}
.lv-table th {
  background: #f8fafc;
  color: var(--sub);
  font-weight: 700;
  font-size: 0.72rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  padding: 0.85rem 1rem;
  border-bottom: 1.5px solid var(--border);
  white-space: nowrap;
}
.lv-table td {
  padding: 0.85rem 1rem;
  border-bottom: 1px solid #f1f5f9;
  vertical-align: middle;
}
.lv-table tr:last-child td {
  border-bottom: none;
}
.lv-table tr:hover td {
  background: #fafafa;
}

/* 7. Status & Health Indicator Badges */
.lv-status-dot {
  display: inline-block;
  width: 8px;
  height: 8px;
  border-radius: 50%;
  margin-right: 4px;
}
.lv-status-healthy { background: #10b981; box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.2); }
.lv-status-sick { background: #ef4444; box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.2); }
.lv-status-recovered { background: #06b6d4; box-shadow: 0 0 0 2px rgba(6, 182, 212, 0.2); }
.lv-status-deceased { background: #64748b; }

.lv-pill {
  display: inline-flex;
  align-items: center;
  padding: 3px 9px;
  border-radius: 9999px;
  font-size: 0.72rem;
  font-weight: 700;
  white-space: nowrap;
}
.lv-pill-healthy { background: #ecfdf5; color: #047857; border: 1px solid rgba(16, 185, 129, 0.25); }
.lv-pill-sick { background: #fef2f2; color: #dc2626; border: 1px solid rgba(239, 68, 68, 0.25); }
.lv-pill-recovered { background: #ecfeff; color: #0891b2; border: 1px solid rgba(6, 182, 212, 0.25); }
.lv-pill-deceased { background: #f1f5f9; color: #475569; border: 1px solid rgba(100, 116, 139, 0.25); }

.lv-vax-tag {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 2px 7px;
  border-radius: 6px;
  font-size: 0.72rem;
  font-weight: 700;
}
.lv-vax-complete { background: #ecfdf5; color: #059669; }
.lv-vax-partial { background: #fef3c7; color: #b45309; }
.lv-vax-none { background: #f1f5f9; color: #64748b; }

/* 8. Farm Grouped View */
.lv-farm-card {
  background: #fff;
  border: 1px solid var(--border);
  border-radius: 18px;
  padding: 1.15rem;
  margin-bottom: 1.25rem;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.02);
}
.lv-farm-header {
  display: flex;
  align-items: center;
  gap: 0.875rem;
  padding-bottom: 0.875rem;
  margin-bottom: 1rem;
  border-bottom: 1px solid var(--border);
}
.lv-farm-avatar {
  width: 48px;
  height: 48px;
  background: linear-gradient(135deg, var(--pri), var(--acc));
  border-radius: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  font-weight: 800;
  font-size: 1.15rem;
  flex-shrink: 0;
  box-shadow: 0 4px 10px rgba(6, 78, 59, 0.15);
}
.lv-farm-title {
  font-weight: 800;
  font-size: 1rem;
  color: var(--txt);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.lv-farm-subtitle {
  display: flex;
  gap: 0.5rem;
  align-items: center;
  margin-top: 2px;
}
.lv-farm-count-badge {
  text-align: right;
  flex-shrink: 0;
}
.lv-farm-count-num {
  font-size: 1.35rem;
  font-weight: 800;
  color: var(--acc2);
}
.lv-farm-count-lbl {
  font-size: 0.65rem;
  color: var(--sub);
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

/* Legacy Support */
.livestock-card{border:1px solid var(--border);border-radius:14px;overflow:hidden;margin-bottom:.875rem;}
.livestock-row{display:flex;align-items:center;gap:.875rem;padding:.875rem 1rem;border-bottom:1px solid #f1f5f9;background:#fff;}
.livestock-row:last-child{border-bottom:none;}
.lv-icon{width:38px;height:38px;border-radius:10px;background:#f0fdf4;color:var(--acc);display:flex;align-items:center;justify-content:center;font-size:1.2rem;flex-shrink:0;}
.lv-info{flex:1;min-width:0;}
.lv-tag{font-weight:700;font-size:.85rem;color:var(--pri);}
.lv-meta{font-size:.73rem;color:var(--sub);margin-top:.1rem;}
.lv-qty{font-size:1.1rem;font-weight:800;color:var(--acc);text-align:right;white-space:nowrap;margin-right:.25rem;}
.lv-actions{display:flex;gap:.15rem;}
.incident-hdr{background:linear-gradient(135deg,#fef3c7,#fed7aa);border:1px solid #fecaca;border-radius:16px;padding:1.125rem;margin-bottom:1rem;text-align:center;}
.incident-hdr h2{font-size:1.1rem;font-weight:800;color:#92400e;margin-bottom:.25rem;}
.incident-hdr p{font-size:.78rem;color:#b45309;line-height:1.5;}
.severity-grid{display:grid;grid-template-columns:1fr 1fr;gap:.5rem;margin:.875rem 0;}
.severity-card{border:2px solid var(--border);border-radius:12px;padding:.75rem .5rem;text-align:center;cursor:pointer;transition:.15s;background:#fff;}
.severity-card.active{border-color:var(--acc);background:#ecfdf5;}
.severity-card:active{transform:scale(.97);}
.severity-icon{font-size:1.75rem;display:block;margin-bottom:.15rem;}
.severity-card div{font-weight:700;font-size:.82rem;}
.severity-card small{font-size:.68rem;color:var(--sub);}
.alert-banner{background:linear-gradient(135deg,#fef3c7,#fed7aa);border:1px solid #fecaca;border-radius:12px;padding:.875rem;margin-bottom:1rem;display:flex;gap:.75rem;align-items:flex-start;}
.capture-zone{border:2.5px dashed var(--border);border-radius:16px;padding:1.5rem 1rem;text-align:center;background:linear-gradient(135deg,#f9fafb,#f3f4f6);margin:.875rem 0;cursor:pointer;transition:.2s;}
.capture-zone:active{border-color:var(--acc);background:#f0fdf4;}
.disease-tags{display:flex;flex-wrap:wrap;gap:.375rem;margin:.75rem 0;}
.disease-tag{padding:.3rem .625rem;border-radius:20px;font-size:.75rem;font-weight:600;background:#f3f4f6;color:#6b7280;cursor:pointer;transition:.15s;}
.disease-tag.active{background:var(--acc);color:#fff;}
.notif-item {
  display: flex;
  gap: .875rem;
  padding: 1rem;
  border: 1px solid var(--border);
  border-radius: 14px;
  margin-bottom: .75rem;
  background: #fff;
  transition: all .2s ease;
  position: relative;
  cursor: pointer;
}
.notif-item:hover {
  border-color: #cbd5e1;
  box-shadow: 0 4px 12px rgba(0,0,0,0.04);
}
.notif-item.unread {
  background: #f8fafc;
  border-left: 4px solid var(--acc);
}
.notif-item.unread.is-alert {
  background: #fff5f5;
  border-left: 4px solid var(--danger);
}
.notif-icon {
  width: 42px;
  height: 42px;
  border-radius: 10px;
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  font-size: 1.15rem;
}
.notif-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--acc);
  display: inline-block;
  margin-left: 6px;
  box-shadow: 0 0 6px rgba(16,185,129,0.6);
  flex-shrink: 0;
}
.notif-dot.alert {
  background: var(--danger);
  box-shadow: 0 0 6px rgba(239,68,68,0.6);
}
.notif-tab {
  padding: .4rem .85rem;
  border-radius: 20px;
  font-size: .8rem;
  font-weight: 600;
  background: #f1f5f9;
  color: #475569;
  border: 1px solid transparent;
  cursor: pointer;
  white-space: nowrap;
  transition: all .15s ease;
}
.notif-tab:hover {
  background: #e2e8f0;
}
.notif-tab.active {
  background: var(--acc);
  color: #fff;
  border-color: var(--acc);
}
.notif-action-btn {
  background: #f1f5f9;
  border: 1px solid #e2e8f0;
  color: #475569;
  font-size: .75rem;
  font-weight: 600;
  padding: .25rem .6rem;
  border-radius: 6px;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  transition: .15s;
}
.notif-action-btn:hover {
  background: var(--acc);
  border-color: var(--acc);
  color: #fff;
}
.tb-bell.has-unread {
  border-color: #fed7aa;
}
.tb-bell.has-unread i {
  animation: bellRing 3s ease infinite;
  color: #ea580c;
}
@keyframes bellRing {
  0%, 80%, 100% { transform: rotate(0); }
  85% { transform: rotate(14deg); }
  90% { transform: rotate(-14deg); }
  95% { transform: rotate(7deg); }
}
.notif-toast-container {
  position: fixed;
  top: 70px;
  right: 20px;
  z-index: 9999;
  display: flex;
  flex-direction: column;
  gap: 10px;
  pointer-events: none;
  max-width: 380px;
  width: calc(100vw - 40px);
}
.notif-toast {
  pointer-events: auto;
  background: #ffffff;
  border-radius: 12px;
  padding: 12px 16px;
  box-shadow: 0 10px 25px -5px rgba(0,0,0,0.15), 0 8px 10px -6px rgba(0,0,0,0.1);
  border: 1px solid var(--border);
  border-left: 4px solid var(--acc);
  display: flex;
  gap: 12px;
  align-items: flex-start;
  animation: slideInToast .3s ease forwards;
  transition: opacity .3s, transform .3s;
  cursor: pointer;
}
.notif-toast.is-alert {
  border-left-color: var(--danger);
  background: #fffafa;
}
@keyframes slideInToast {
  from { transform: translateX(100%); opacity: 0; }
  to { transform: translateX(0); opacity: 1; }
}

/* ═══ MODERN AGRI CHAT SYSTEM ═══ */
#pg-chat.active {
    display: flex;
    flex-direction: column;
    height: calc(100vh - 56px);
    height: calc(100dvh - 56px);
    margin: -1rem;
    overflow: hidden;
    background: #ffffff;
}
.chat-shell {
    display: flex;
    flex-direction: row;
    height: 100%;
    width: 100%;
    background: #ffffff;
    overflow: hidden;
    position: relative;
}
.chat-sidebar {
    width: 360px;
    min-width: 310px;
    max-width: 380px;
    flex-shrink: 0;
    height: 100%;
    border-right: 1px solid var(--border);
    display: flex;
    flex-direction: column;
    background: #ffffff;
    z-index: 10;
}
.chat-sidebar-hdr {
    padding: .9rem 1.1rem;
    background: #ffffff;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .5rem;
}
.chat-hdr-title {
    font-size: 1.05rem;
    font-weight: 800;
    color: var(--txt);
    display: flex;
    align-items: center;
    gap: .5rem;
}
.chat-count-pill {
    font-size: .7rem;
    font-weight: 700;
    padding: .2rem .55rem;
    border-radius: 9999px;
    background: #ecfdf5;
    color: var(--acc2);
    border: 1px solid rgba(16, 185, 129, 0.25);
}
.chat-search-wrap {
    padding: .75rem 1rem .5rem;
    background: #ffffff;
    border-bottom: 1px solid #f1f5f9;
}
.chat-search-box {
    position: relative;
    display: flex;
    align-items: center;
}
.chat-search-box i {
    position: absolute;
    left: .85rem;
    color: var(--sub);
    font-size: .85rem;
    pointer-events: none;
}
.chat-search-input {
    width: 100%;
    padding: .55rem .85rem .55rem 2.2rem;
    border: 1px solid var(--border);
    border-radius: 12px;
    font-size: .85rem;
    background: #f8fafc;
    outline: none;
    font-family: inherit;
    transition: all .15s;
}
.chat-search-input:focus {
    background: #ffffff;
    border-color: var(--acc);
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.12);
}
.chat-tabs {
    display: flex;
    gap: .35rem;
    padding: .4rem 1rem .65rem;
    background: #ffffff;
    border-bottom: 1px solid var(--border);
    overflow-x: auto;
}
.chat-tab-btn {
    padding: .35rem .75rem;
    border-radius: 9999px;
    font-size: .75rem;
    font-weight: 700;
    font-family: inherit;
    border: 1px solid var(--border);
    background: #f8fafc;
    color: var(--sub);
    cursor: pointer;
    white-space: nowrap;
    transition: all .15s;
}
.chat-tab-btn.active {
    background: var(--pri);
    color: #ffffff;
    border-color: var(--pri);
}
.chat-farmer-list {
    flex: 1;
    overflow-y: auto;
    background: #ffffff;
}
.farmer-item {
    display: flex;
    align-items: center;
    gap: .75rem;
    padding: .85rem 1rem;
    border-bottom: 1px solid #f8fafc;
    cursor: pointer;
    transition: background .15s;
    position: relative;
}
.farmer-item:hover {
    background: #f8fafc;
}
.farmer-item.active {
    background: #ecfdf5;
    border-left: 3px solid var(--acc2);
}
.farmer-av-wrap {
    position: relative;
    flex-shrink: 0;
}
.farmer-avatar-sm {
    width: 44px;
    height: 44px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: .95rem;
    color: #ffffff;
    box-shadow: 0 2px 6px rgba(0,0,0,0.06);
}
.farmer-avatar-sm.role-official {
    background: linear-gradient(135deg, #1e3a8a, #3b82f6);
}
.farmer-avatar-sm.role-farmer {
    background: linear-gradient(135deg, #064e3b, #10b981);
}
.online-dot {
    position: absolute;
    bottom: -1px;
    right: -1px;
    width: 11px;
    height: 11px;
    border-radius: 50%;
    background: #10b981;
    border: 2px solid #ffffff;
}
.farmer-item-info {
    flex: 1;
    min-width: 0;
}
.farmer-item-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .4rem;
    margin-bottom: .2rem;
}
.farmer-name-txt {
    font-weight: 700;
    font-size: .88rem;
    color: var(--txt);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.farmer-time-txt {
    font-size: .68rem;
    color: var(--sub);
    white-space: nowrap;
    font-weight: 500;
}
.farmer-item-bottom {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .5rem;
}
.farmer-preview {
    font-size: .75rem;
    color: var(--sub);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.role-tag-badge {
    display: inline-flex;
    align-items: center;
    font-size: .62rem;
    font-weight: 700;
    padding: .1rem .45rem;
    border-radius: 6px;
    text-transform: uppercase;
    letter-spacing: .02em;
    flex-shrink: 0;
}
.role-tag-badge.official {
    background: #dbeafe;
    color: #1e40af;
}
.role-tag-badge.farmer {
    background: #d1fae5;
    color: #065f46;
}
.unread-badge {
    background: var(--danger);
    color: #ffffff;
    font-size: .65rem;
    font-weight: 800;
    min-width: 18px;
    height: 18px;
    padding: 0 5px;
    border-radius: 9999px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.chat-conv-view {
    flex: 1;
    display: flex;
    flex-direction: column;
    height: 100%;
    overflow: hidden;
    background: #f8fafc;
    position: relative;
}
.chat-no-conv {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 2rem;
    text-align: center;
    background: #f8fafc;
}
.chat-no-conv-icon {
    width: 76px;
    height: 76px;
    border-radius: 24px;
    background: #e2e8f0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.2rem;
    color: #64748b;
    margin-bottom: 1.25rem;
}
.chat-no-conv h3 {
    font-size: 1.15rem;
    font-weight: 800;
    color: var(--txt);
    margin-bottom: .4rem;
}
.chat-no-conv p {
    font-size: .84rem;
    color: var(--sub);
    max-width: 360px;
    line-height: 1.5;
}
.chat-active-pane {
    display: none;
    flex: 1;
    flex-direction: column;
    height: 100%;
    overflow: hidden;
}
.chat-active-pane.show {
    display: flex;
}
.chat-hdr-mini {
    padding: .75rem 1.25rem;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .75rem;
    background: #ffffff;
    flex-shrink: 0;
    box-shadow: 0 1px 3px rgba(0,0,0,0.03);
}
.chat-back {
    display: none;
    background: none;
    border: none;
    font-size: 1.25rem;
    color: var(--pri);
    cursor: pointer;
    padding: .35rem;
    border-radius: 10px;
    min-width: 36px;
    min-height: 36px;
    align-items: center;
    justify-content: center;
}
.chat-hdr-left {
    display: flex;
    align-items: center;
    gap: .75rem;
    min-width: 0;
    flex: 1;
}
.chat-hdr-avatar {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    background: linear-gradient(135deg, var(--pri), var(--acc));
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: .95rem;
    flex-shrink: 0;
}
.chat-hdr-avatar.role-official {
    background: linear-gradient(135deg, #1e3a8a, #3b82f6);
}
.chat-hdr-name {
    font-weight: 800;
    font-size: .96rem;
    color: var(--txt);
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: .4rem;
    min-width: 0;
}
.chat-hdr-status {
    font-size: .72rem;
    color: var(--sub);
    display: flex;
    align-items: center;
    gap: .3rem;
}
.chat-hdr-status .status-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #10b981;
}
.chat-hdr-actions {
    display: flex;
    gap: .5rem;
    flex-shrink: 0;
}
.call-btn-sm {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    border: 1px solid transparent;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: .95rem;
    transition: transform .15s, background .15s;
}
.call-btn-sm:hover {
    transform: translateY(-1px);
}
.voice-btn {
    background: #ecfdf5;
    color: #065f46;
    border-color: #a7f3d0;
}
.video-btn {
    background: #eff6ff;
    color: #1e40af;
    border-color: #bfdbfe;
}
.chat-messages-area {
    flex: 1;
    overflow-y: auto;
    padding: 1.25rem;
    background-color: #f8fafc;
    background-image: radial-gradient(#cbd5e1 0.75px, transparent 0.75px);
    background-size: 20px 20px;
    display: flex;
    flex-direction: column;
    gap: .75rem;
}
.chat-welcome-banner {
    margin: 1.5rem auto;
    max-width: 420px;
    text-align: center;
    padding: 1.25rem;
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(8px);
    border: 1px solid var(--border);
    border-radius: 18px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
}
.chat-welcome-banner i {
    font-size: 2rem;
    color: var(--acc);
    display: block;
    margin-bottom: .5rem;
}
.chat-welcome-banner h4 {
    font-size: .92rem;
    font-weight: 800;
    color: var(--txt);
    margin-bottom: .3rem;
}
.chat-welcome-banner p {
    font-size: .78rem;
    color: var(--sub);
    line-height: 1.45;
}
.msg-row {
    display: flex;
    max-width: 78%;
    align-items: flex-end;
    gap: .5rem;
}
.msg-row.them {
    align-self: flex-start;
}
.msg-row.mine {
    align-self: flex-end;
    flex-direction: row-reverse;
}
.msg-bubble {
    padding: .65rem .95rem;
    border-radius: 16px;
    font-size: .88rem;
    line-height: 1.5;
    word-break: break-word;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}
.msg-bubble.them {
    background: #ffffff;
    color: var(--txt);
    border-bottom-left-radius: 4px;
    border: 1px solid #e2e8f0;
}
.msg-bubble.mine {
    background: linear-gradient(135deg, #064e3b, #10b981);
    color: #ffffff;
    border-bottom-right-radius: 4px;
}
.msg-meta {
    font-size: .65rem;
    margin-top: .25rem;
    display: flex;
    align-items: center;
    gap: .25rem;
    padding: 0 .2rem;
}
.msg-row.them .msg-meta {
    color: var(--sub);
    justify-content: flex-start;
}
.msg-row.mine .msg-meta {
    color: var(--sub);
    justify-content: flex-end;
}
.msg-img-wrap {
    overflow: hidden;
    border-radius: 14px;
    max-width: 280px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}
.msg-img-wrap img {
    display: block;
    width: 100%;
    height: auto;
    cursor: pointer;
    transition: transform .2s;
}
.msg-img-wrap img:hover {
    transform: scale(1.02);
}
.chat-quick-tags {
    display: flex;
    gap: .4rem;
    padding: .4rem 1rem;
    background: #ffffff;
    border-top: 1px solid #f1f5f9;
    overflow-x: auto;
}
.quick-chip {
    padding: .25rem .65rem;
    border-radius: 9999px;
    font-size: .72rem;
    font-weight: 600;
    background: #f8fafc;
    border: 1px solid var(--border);
    color: var(--sub);
    cursor: pointer;
    white-space: nowrap;
    transition: all .15s;
}
.quick-chip:hover {
    background: #ecfdf5;
    color: var(--acc2);
    border-color: #a7f3d0;
}
.chat-media-preview-bar {
    display: none;
    align-items: center;
    gap: .75rem;
    padding: .5rem 1.25rem;
    background: #f8fafc;
    border-top: 1px solid var(--border);
}
.chat-media-preview-bar.show {
    display: flex;
}
.chat-preview-thumb {
    width: 50px;
    height: 50px;
    border-radius: 8px;
    object-fit: cover;
    border: 1px solid var(--border);
}
.chat-preview-info {
    flex: 1;
    font-size: .75rem;
    color: var(--sub);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.chat-preview-remove {
    background: none;
    border: none;
    color: var(--danger);
    font-size: 1.1rem;
    cursor: pointer;
    padding: .25rem;
}
.chat-input-bar {
    padding: .75rem 1.25rem;
    border-top: 1px solid var(--border);
    background: #ffffff;
    display: flex;
    gap: .65rem;
    align-items: flex-end;
    flex-shrink: 0;
}
.chat-attach-btn {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    background: #f1f5f9;
    border: 1px solid var(--border);
    color: var(--sub);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 1.15rem;
    transition: all .15s;
    flex-shrink: 0;
}
.chat-attach-btn:hover {
    background: #ecfdf5;
    color: var(--acc);
    border-color: #a7f3d0;
}
.chat-input {
    flex: 1;
    padding: .65rem 1rem;
    border: 1.5px solid var(--border);
    border-radius: 14px;
    font-size: .88rem;
    font-family: inherit;
    outline: none;
    background: #f8fafc;
    resize: none;
    max-height: 100px;
    line-height: 1.4;
    transition: border-color .15s, background .15s;
}
.chat-input:focus {
    border-color: var(--acc);
    background: #ffffff;
}
.chat-send-btn {
    width: 42px;
    height: 42px;
    background: linear-gradient(135deg, #064e3b, #10b981);
    color: #ffffff;
    border: none;
    border-radius: 12px;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 1rem;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.28);
    transition: transform .15s, box-shadow .15s;
}
.chat-send-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(16, 185, 129, 0.4);
}

@media (max-width: 768px) {
    .chat-sidebar {
        width: 100%;
        min-width: 0;
        max-width: 100%;
        border-right: none;
    }
    .chat-conv-view {
        position: absolute;
        inset: 0;
        z-index: 60;
        transform: translateX(100%);
        transition: transform .25s ease;
    }
    .chat-conv-view.show {
        transform: translateX(0);
    }
    .chat-back {
        display: flex;
    }
    .chat-no-conv {
        display: none !important;
    }
}

/* WEBRTC CALL OVERLAY */
.call-overlay{position:fixed;inset:0;background:rgba(6,78,59,.97);z-index:8000;display:none;flex-direction:column;align-items:center;justify-content:space-between;padding:3rem 2rem calc(3rem + var(--safe-bottom));color:#fff;}
.call-overlay.show{display:flex;}
.call-overlay-avatar{width:100px;height:100px;border-radius:50%;background:rgba(255,255,255,.2);border:4px solid rgba(255,255,255,.4);display:flex;align-items:center;justify-content:center;font-size:2.5rem;font-weight:800;margin-bottom:1rem;}
.call-overlay-name{font-size:1.5rem;font-weight:800;margin-bottom:.5rem;}
.call-overlay-status{font-size:.9rem;opacity:.7;margin-bottom:2rem;}
.call-local-video{position:absolute;top:1rem;right:1rem;width:100px;height:140px;border-radius:12px;object-fit:cover;border:2px solid rgba(255,255,255,.3);background:#000;display:none;}
.call-remote-video{width:100%;max-height:40vh;border-radius:16px;object-fit:cover;background:#000;display:none;}
.call-btn-row{display:flex;gap:1.5rem;align-items:center;}
.call-action-btn{width:60px;height:60px;border-radius:50%;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:1.4rem;transition:.15s;}
.call-action-btn:active{transform:scale(.93);}
.call-action-btn.mute{background:rgba(255,255,255,.2);color:#fff;}
.call-action-btn.mute.active{background:#fff;color:#064e3b;}
.call-action-btn.cam-toggle{background:rgba(255,255,255,.2);color:#fff;}
.call-action-btn.cam-toggle.active{background:#fff;color:#064e3b;}
.call-action-btn.hangup{background:#ef4444;color:#fff;width:70px;height:70px;font-size:1.6rem;}
.call-action-btn.answer{background:#10b981;color:#fff;width:70px;height:70px;font-size:1.6rem;}
.incoming-call-banner{position:fixed;top:0;left:0;right:0;background:linear-gradient(135deg,#064e3b,#10b981);color:#fff;z-index:7900;padding:1rem 1.25rem;display:none;align-items:center;gap:1rem;box-shadow:0 4px 20px rgba(0,0,0,.3);}
.incoming-call-banner.show{display:flex;}
.incoming-info{flex:1;}
.incoming-title{font-weight:800;font-size:.95rem;}
.incoming-sub{font-size:.75rem;opacity:.8;}
.incoming-btns{display:flex;gap:.5rem;}
.perm-error-banner{background:#fee2e2;border:1px solid #fecaca;border-radius:12px;padding:.875rem;margin:1rem;display:none;gap:.75rem;align-items:flex-start;font-size:.82rem;color:#991b1b;}
.perm-error-banner.show{display:flex;}

/* CERTIFICATE */
.gahp-cert{background:#fff;border:2.5px solid #1a5c38;border-radius:4px;overflow:hidden;font-family:'Times New Roman',Georgia,serif;margin-bottom:1.5rem;}
.gahp-inner{border:1.5px solid #c9a543;margin:5px;border-radius:2px;}
.gahp-top{background:#fff;padding:1.25rem 1rem .875rem;text-align:center;border-bottom:2px solid #1a5c38;}
.gahp-logo{width:78px;height:78px;border-radius:50%;margin:0 auto .625rem;background:linear-gradient(135deg,#1a5c38,#2d8a57);border:3px solid #c9a543;display:flex;align-items:center;justify-content:center;overflow:hidden;}
.gahp-org{font-size:.68rem;font-weight:700;color:#1a5c38;letter-spacing:.08em;text-transform:uppercase;}
.gahp-cert-title{font-size:clamp(1.15rem, 5vw, 1.9rem);font-weight:900;color:#1a5c38;letter-spacing:.08em;text-transform:uppercase;margin:.2rem 0 .1rem;}
.gahp-nos{font-size:.72rem;color:#333;margin-top:.375rem;}
.gahp-nos span{color:#1a5c38;font-weight:700;}
.gahp-body{padding:.875rem 1rem 1rem;}
.gahp-issued{text-align:center;font-size:.75rem;color:#555;font-style:italic;margin-bottom:.2rem;}
.gahp-farm-name{text-align:center;font-size:1.3rem;font-weight:900;color:#1a5c38;text-transform:uppercase;border-bottom:2px solid #1a5c38;display:inline-block;padding:0 .5rem .2rem;margin:.15rem auto .3rem;}
.gahp-farm-addr{text-align:center;font-size:.75rem;color:#555;margin-bottom:.875rem;}
.gahp-declaration{font-size:.72rem;line-height:1.65;color:#222;text-align:justify;margin-bottom:.875rem;}
.gahp-declaration strong{color:#1a5c38;}
.gahp-table{width:100%;border-collapse:collapse;margin:.625rem 0;font-size:.75rem;}
.gahp-table th{background:#1a5c38;color:#fff;padding:.375rem .75rem;text-align:left;font-weight:600;}
.gahp-table td{padding:.375rem .75rem;border:1px solid #c8dfc8;background:#f9fff9;}
.gahp-validity{background:#fff8e1;border:1.5px solid #f0c040;border-radius:4px;padding:.375rem .875rem;display:flex;justify-content:space-between;margin:.625rem 0;font-size:.75rem;}
.gahp-footer{display:grid;grid-template-columns:auto 1fr;gap:.875rem;padding:.875rem 1rem 1rem;border-top:1px solid #d4edda;background:#f9f9f9;}
.gahp-sig{text-align:right;}
.gahp-sig-line{border-top:1.5px solid #333;padding-top:.25rem;margin-top:2rem;}
.gahp-sig-name{font-size:.75rem;font-weight:700;color:#1a5c38;text-transform:uppercase;}
.gahp-sig-title{font-size:.65rem;color:#555;line-height:1.4;}
.gahp-stamp{background:#1a5c38;color:rgba(255,255,255,.75);padding:.25rem .875rem;font-size:.58rem;display:flex;justify-content:space-between;gap:.5rem;flex-wrap:wrap;}
.cert-action-bar{display:flex;gap:.5rem;margin-bottom:1rem;flex-wrap:wrap;align-items:center;}
.cert-status-badge{display:inline-flex;align-items:center;gap:.25rem;padding:.3rem .875rem;border-radius:9999px;font-size:.72rem;font-weight:800;flex-shrink:0;}
.cert-valid{background:#d1fae5;color:#065f46;border:2px solid #10b981;}
.cert-expired{background:#fee2e2;color:#991b1b;border:2px solid #ef4444;}
/* ═══════════════════════════════════════════════════════════════════════════
   MODERN SAAS PROFILE CARD STYLES
   ═══════════════════════════════════════════════════════════════════════════ */
#pg-profile {
  min-height: 80vh;
  display: flex;
  align-items: flex-start;
  justify-content: center;
  background: radial-gradient(circle at 50% 12%, rgba(16, 185, 129, 0.06) 0%, transparent 65%);
  padding: 1.5rem 1rem 3rem;
}
.profile-card-wrapper {
  max-width: 560px;
  width: 100%;
  margin: 1rem auto 2.5rem;
  background: #ffffff;
  border-radius: 24px;
  box-shadow: 0 16px 40px -12px rgba(0, 0, 0, 0.09), 0 0 0 1px rgba(0, 0, 0, 0.05);
  overflow: hidden;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.profile-card-wrapper:hover {
  box-shadow: 0 20px 48px -12px rgba(0, 0, 0, 0.12), 0 0 0 1px rgba(0, 0, 0, 0.06);
}
.profile-card-header {
  background: linear-gradient(145deg, #064e3b 0%, #047857 45%, #059669 100%);
  padding: 34px 24px 26px;
  text-align: center;
  position: relative;
  color: #ffffff;
  overflow: hidden;
}
.profile-card-header::before {
  content: '';
  position: absolute;
  top: -50px;
  right: -50px;
  width: 180px;
  height: 180px;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(255,255,255,0.18) 0%, transparent 70%);
  pointer-events: none;
}
.profile-card-header::after {
  content: '';
  position: absolute;
  bottom: -40px;
  left: -30px;
  width: 140px;
  height: 140px;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
  pointer-events: none;
}
.profile-avatar-circle {
  width: 78px;
  height: 78px;
  margin: 0 auto 12px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.18);
  backdrop-filter: blur(10px);
  -webkit-backdrop-filter: blur(10px);
  border: 3px solid rgba(255, 255, 255, 0.45);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.9rem;
  font-weight: 800;
  color: #ffffff;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.18);
  overflow: hidden;
  position: relative;
  z-index: 1;
}
.profile-avatar-circle img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  border-radius: 50%;
}
.profile-header-name {
  font-family: 'Syne', sans-serif;
  font-size: 1.35rem;
  font-weight: 800;
  color: #ffffff;
  margin: 0 0 3px;
  line-height: 1.25;
  letter-spacing: -0.01em;
  position: relative;
  z-index: 1;
}
.profile-header-role {
  font-size: 0.86rem;
  font-weight: 600;
  color: rgba(255, 255, 255, 0.85);
  margin: 0;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  position: relative;
  z-index: 1;
}
.profile-verified-chip {
  background: #10b981;
  color: #fff;
  font-size: 0.68rem;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 9999px;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  box-shadow: 0 2px 6px rgba(16, 185, 129, 0.4);
  margin-left: 6px;
  letter-spacing: 0.02em;
  vertical-align: middle;
}
.profile-copy-btn {
  opacity: 0;
  background: none;
  border: none;
  color: #059669;
  cursor: pointer;
  font-size: 0.8rem;
  padding: 2px 5px;
  border-radius: 4px;
  transition: opacity 0.15s ease, background 0.15s ease, transform 0.15s;
  display: inline-flex;
  align-items: center;
  margin-left: 4px;
  vertical-align: middle;
}
.profile-info-row:hover .profile-copy-btn {
  opacity: 1;
}
.profile-copy-btn:hover {
  background: #ecfdf5;
  transform: scale(1.1);
}
.profile-card-body {
  padding: 24px 28px 28px;
}
.profile-sec-heading {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.92rem;
  font-weight: 700;
  color: #047857;
  margin-top: 1.5rem;
  margin-bottom: 0.65rem;
}
.profile-sec-heading:first-of-type {
  margin-top: 0;
}
.profile-sec-heading i {
  font-size: 1.05rem;
  color: #059669;
}
.profile-info-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 11px 4px;
  border-bottom: 1px solid #f1f5f9;
  transition: background 0.15s ease, padding 0.15s ease;
}
.profile-info-row:hover {
  background: #f8fafc;
  padding-left: 8px;
  padding-right: 8px;
  border-radius: 8px;
}
.profile-info-label {
  font-size: 0.86rem;
  font-weight: 500;
  color: #64748b;
}
.profile-info-value {
  font-size: 0.88rem;
  font-weight: 600;
  color: #0f172a;
  text-align: right;
  max-width: 60%;
  word-break: break-word;
}
.profile-card-actions {
  display: flex;
  justify-content: center;
  gap: 12px;
  margin-top: 26px;
  padding-top: 6px;
}
.profile-btn-edit {
  background: #059669;
  color: #ffffff;
  font-weight: 700;
  font-size: 0.9rem;
  padding: 10px 28px;
  border-radius: 9999px;
  border: none;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  box-shadow: 0 4px 14px rgba(5, 150, 105, 0.35);
  transition: all 0.2s ease;
  font-family: inherit;
}
.profile-btn-edit:hover {
  background: #047857;
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(5, 150, 105, 0.45);
}
.profile-btn-pw {
  background: #f8fafc;
  color: #334155;
  font-weight: 700;
  font-size: 0.9rem;
  padding: 10px 22px;
  border-radius: 9999px;
  border: 1px solid #e2e8f0;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 7px;
  transition: all 0.2s ease;
  font-family: inherit;
}
.profile-btn-pw:hover {
  background: #e2e8f0;
  color: #0f172a;
  transform: translateY(-2px);
}
.pf-preview-avatar {
  width: 72px;
  height: 72px;
  border-radius: 18px;
  background: #f1f5f9;
  border: 2px dashed #cbd5e1;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  font-size: 1.5rem;
  color: var(--sub);
  flex-shrink: 0;
}
.pf-preview-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

/* Modal Desktop Centering Enhancement */
@media (min-width: 640px) {
  .modal-wrap {
    align-items: center;
    padding: 1.5rem;
  }
  .modal-box {
    border-radius: 24px;
    max-width: 580px;
    width: 100%;
    animation: modalPop 0.22s cubic-bezier(0.16, 1, 0.3, 1);
  }
}
@keyframes modalPop {
  from { transform: scale(0.96) translateY(8px); opacity: 0; }
  to { transform: scale(1) translateY(0); opacity: 1; }
}
.drawer-overlay{position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1500;opacity:0;visibility:hidden;transition:.25s;}
.drawer-overlay.show{opacity:1;visibility:visible;}
.drawer{position:fixed;left:-100%;top:0;width:80%;max-width:300px;height:100vh;background:var(--pri);z-index:2000;transition:.3s cubic-bezier(.4,0,.2,1);display:flex;flex-direction:column;padding-top:var(--safe-top);}
.drawer.open{left:0;}
.drawer-hdr{padding:1.5rem 1.25rem 1.25rem;border-bottom:1px solid rgba(255,255,255,.1);}
.brand{font-size:1.5rem;font-weight:800;color:#fff;}.brand span{color:var(--acc);}
.portal-lbl{font-size:.65rem;color:rgba(255,255,255,.5);text-transform:uppercase;letter-spacing:.08em;margin-top:.2rem;display:block;}
.drawer, .drawer-nav, #drawer { scrollbar-width: none !important; -ms-overflow-style: none !important; }
.drawer::-webkit-scrollbar, .drawer-nav::-webkit-scrollbar, #drawer::-webkit-scrollbar { display: none !important; width: 0 !important; height: 0 !important; background: transparent !important; -webkit-appearance: none !important; }
.drawer::-webkit-scrollbar-thumb, .drawer-nav::-webkit-scrollbar-thumb, #drawer::-webkit-scrollbar-thumb { display: none !important; width: 0 !important; height: 0 !important; background: transparent !important; }
.drawer::-webkit-scrollbar-track, .drawer-nav::-webkit-scrollbar-track, #drawer::-webkit-scrollbar-track { display: none !important; background: transparent !important; }
.drawer::-webkit-scrollbar-button, .drawer-nav::-webkit-scrollbar-button, #drawer::-webkit-scrollbar-button { display: none !important; width: 0 !important; height: 0 !important; }
.drawer-nav{flex:1;padding:.75rem 0;overflow-y:auto;overflow-x:hidden;scrollbar-width: none !important; -ms-overflow-style: none !important;}
.drawer-item{display:flex;align-items:center;gap:.625rem;padding:.75rem 1.125rem;margin:.15rem .625rem;color:rgba(255,255,255,.75);text-decoration:none;border-radius:.75rem;font-weight:500;font-size:.875rem;cursor:pointer;transition:.15s;}
.drawer-item i{font-size:1.05rem;width:1.125rem;text-align:center;flex-shrink:0;}
.drawer-item:active,.drawer-item.active{background:var(--acc);color:#fff;}
.drawer-footer{padding:1.125rem;border-top:1px solid rgba(255,255,255,.1);}
.drawer-user{display:flex;align-items:center;gap:.625rem;}
.drawer-avatar{width:36px;height:36px;background:var(--acc);color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.9rem;}
.modal-wrap{position:fixed;inset:0;background:rgba(15,23,42,.6);backdrop-filter:blur(4px);z-index:5000;display:none;align-items:flex-end;justify-content:center;}
.modal-wrap.open{display:flex;}
.modal-box{background:#fff;border-radius:24px 24px 0 0;width:100%;max-height:90vh;overflow-y:auto;padding-bottom:var(--safe-bottom);animation:slideUp .25s ease;}
@keyframes slideUp{from{transform:translateY(100%);}to{transform:translateY(0);}}
.modal-hdr{padding:1.125rem 1.25rem;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid var(--border);position:sticky;top:0;background:#fff;z-index:1;}
.modal-hdr h3{font-size:1rem;font-weight:700;color:var(--pri);}
.modal-close{background:#f1f5f9;border:none;width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.1rem;color:var(--sub);cursor:pointer;}
.modal-body{padding:1.125rem 1.25rem;}
.modal-footer{padding:.875rem 1.25rem;border-top:1px solid var(--border);display:flex;gap:.5rem;background:#f8fafc;border-radius:0 0 24px 24px;}
.modal-footer .btn{flex:1;}
.cam-modal{position:fixed;inset:0;background:rgba(15,23,42,.8);z-index:9000;display:none;align-items:flex-end;justify-content:center;}
.cam-modal.open{display:flex;}
.cam-box{background:#111;border-radius:24px 24px 0 0;width:100%;overflow:hidden;padding-bottom:var(--safe-bottom);}
.cam-hdr-bar{background:#1a1a2e;padding:.875rem 1.125rem;display:flex;justify-content:space-between;align-items:center;color:#fff;}
.cam-video{width:100%;max-height:55vh;display:block;object-fit:cover;}
.cam-ctrl-bar{padding:.875rem;display:flex;gap:1rem;justify-content:center;align-items:center;background:#111;}
.cam-btn{width:56px;height:56px;border-radius:50%;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:1.3rem;}
.cam-btn.capture{background:var(--acc);color:#fff;width:64px;height:64px;font-size:1.5rem;}
.cam-btn.flip{background:rgba(255,255,255,.15);color:#fff;}
.cam-btn.close-cam{background:rgba(255,255,255,.1);color:#ccc;}
.toast{position:fixed;top:.875rem;left:50%;transform:translateX(-50%) translateY(-120px);z-index:9999;padding:.875rem 1.125rem;border-radius:14px;color:#fff;font-weight:600;font-size:.875rem;box-shadow:0 8px 24px rgba(0,0,0,.2);transition:transform .3s ease;pointer-events:none;text-align:center;white-space:nowrap;max-width:90vw;}
.toast.show{transform:translateX(-50%) translateY(0);}
.toast-success{background:var(--acc);}
.toast-error{background:var(--danger);}
.toast-warn{background:var(--warn);}
.empty-state{text-align:center;padding:3rem 1.5rem;color:var(--sub);}
.empty-state i{font-size:3rem;display:block;margin-bottom:1rem;opacity:.35;}
.empty-state h4{font-size:1rem;font-weight:700;margin-bottom:.5rem;color:var(--txt);}
.empty-state p{font-size:.85rem;line-height:1.5;}

/* ═══ MODERN SHELL (desktop sidebar + glass top bar) ═══════════════════ */
body{background:radial-gradient(1200px 500px at 90% -10%,rgba(16,185,129,.10),transparent 60%),var(--bg);}
.top-bar{background:rgba(255,255,255,.88);backdrop-filter:saturate(180%) blur(14px);-webkit-backdrop-filter:saturate(180%) blur(14px);}
.page-title{font-size:1.05rem;font-weight:800;letter-spacing:-.01em;}
.top-right{display:flex;align-items:center;gap:.65rem;flex-shrink:0;}
.lang-switch-btn{display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border-radius:9999px;background:#ffffff;border:1.5px solid var(--border);color:var(--txt);cursor:pointer;font-size:0.8rem;font-weight:700;font-family:inherit;transition:all 0.15s ease;box-shadow:0 1px 2px rgba(0,0,0,0.04);white-space:nowrap;line-height:1;}
.lang-switch-btn:hover{background:#f0fdf4;border-color:var(--acc);color:var(--pri);}
.lang-switch-btn:active{transform:scale(.97);}
.tb-chip{display:flex;align-items:center;gap:.55rem;padding:.25rem .75rem .25rem .25rem;border:1px solid var(--border);border-radius:999px;background:#fff;white-space:nowrap;}
.tb-chip .tb-av{width:32px;height:32px;border-radius:50%;display:grid;place-items:center;font-weight:800;font-size:.82rem;color:#fff;background:linear-gradient(135deg,#10b981,#065f46);flex-shrink:0;}
.tb-chip span{font-size:.82rem;font-weight:700;color:var(--txt);max-width:140px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.tb-bell{position:relative;width:38px;height:38px;border-radius:50%;border:1px solid var(--border);background:#fff;display:grid;place-items:center;color:var(--pri);cursor:pointer;font-size:1.05rem;flex-shrink:0;transition:.15s;}
.tb-bell:hover{background:#f0fdf4;border-color:var(--acc);}
.tb-bell b{position:absolute;top:-3px;right:-3px;min-width:17px;height:17px;border-radius:999px;background:var(--danger);color:#fff;font-size:.62rem;display:grid;place-items:center;padding:0 4px;}
.drawer{background:linear-gradient(180deg,#022c22 0%,#064e3b 100%);box-shadow:20px 0 50px -20px rgba(0,0,0,.4);}
.drawer-hdr{padding:1.6rem 1.35rem 1.25rem;}
.drawer-item{display:flex;align-items:center;gap:.75rem;padding:.78rem 1.15rem;margin:.22rem .75rem;color:rgba(255,255,255,.8);text-decoration:none;border-radius:12px;font-weight:600;font-size:.875rem;cursor:pointer;transition:all .15s ease;white-space:nowrap;}
.drawer-item i{font-size:1.1rem;width:1.25rem;text-align:center;flex-shrink:0;}
.drawer-item:hover{background:rgba(255,255,255,.09);color:#fff;}
.drawer-item.active{background:linear-gradient(135deg,#10b981,#059669);color:#fff;box-shadow:0 4px 14px -3px rgba(16,185,129,.6);}
.drawer-footer{background:rgba(0,0,0,.18);}
.d-hero{border-radius:26px;box-shadow:0 22px 44px -18px rgba(5,150,105,.55);}
.d-kpi{border-radius:20px;}
.d-card{border-radius:22px;box-shadow:0 8px 24px -14px rgba(15,23,42,.18);}
/* ── AUTO-HIDE & FULL VIEW MODE (Farmer Panel) ─────────────────── */
body.sidebar-autohide .drawer {
  position: fixed !important;
  left: 0 !important;
  top: 0 !important;
  bottom: 0 !important;
  width: 270px !important;
  min-width: 270px !important;
  max-width: 270px !important;
  height: 100vh !important;
  transform: translateX(-100%) !important;
  z-index: 1000 !important;
  box-shadow: 20px 0 50px rgba(0,0,0,0.45) !important;
  transition: transform 0.28s cubic-bezier(0.16, 1, 0.3, 1) !important;
}

body.sidebar-autohide .drawer.hover-open,
body.sidebar-autohide .drawer:hover,
body.sidebar-autohide .drawer.open {
  transform: translateX(0) !important;
}

body.sidebar-autohide .app {
  width: 100vw !important;
  max-width: 100vw !important;
  flex: 1 1 100% !important;
  margin-left: 0 !important;
}

.menu-btn {
  display: flex !important;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}

/* Left Edge Hover Detection Zone */
.farmer-hover-zone {
  display: none;
  position: fixed;
  left: 0;
  top: 0;
  bottom: 0;
  width: 20px;
  z-index: 998;
}
body.sidebar-autohide .farmer-hover-zone {
  display: block;
}

/* Floating Edge Trigger Tab */
.farmer-edge-trigger {
  display: none;
  position: fixed;
  top: 50%;
  left: 0;
  transform: translateY(-50%);
  width: 22px;
  height: 54px;
  background: linear-gradient(180deg, #065f46, #022c22);
  color: #34d399;
  border-radius: 0 10px 10px 0;
  box-shadow: 2px 0 12px rgba(0, 0, 0, 0.3);
  z-index: 999;
  cursor: pointer;
  place-items: center;
  font-size: 0.85rem;
  border: 1px solid rgba(52, 211, 153, 0.3);
  border-left: none;
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
  user-select: none;
}
.farmer-edge-trigger:hover {
  width: 28px;
  background: #059669;
  color: #ffffff;
}
body.sidebar-autohide .farmer-edge-trigger {
  display: grid;
}

.btn-drawer-pin {
  margin-left: auto;
  width: 30px;
  height: 30px;
  border-radius: 8px;
  background: rgba(255, 255, 255, 0.08);
  border: 1px solid rgba(255, 255, 255, 0.12);
  color: #a7f3d0;
  display: grid;
  place-items: center;
  font-size: 0.9rem;
  cursor: pointer;
  transition: all 0.2s ease;
}
.btn-drawer-pin:hover {
  background: rgba(255, 255, 255, 0.2);
  color: #fff;
}
body:not(.sidebar-autohide) .btn-drawer-pin {
  color: #34d399;
  background: rgba(52, 211, 153, 0.18);
  border-color: rgba(52, 211, 153, 0.35);
}

@media (min-width:1024px){
  body{display:flex;overflow:hidden;}
  .drawer{position:static;left:0;width:264px;min-width:264px;max-width:264px;height:100vh;flex-shrink:0;transition:transform 0.28s cubic-bezier(0.16, 1, 0.3, 1);}
  .drawer-overlay{display:none !important;}
  .app{margin-left:0;flex:1;min-width:0;width:auto;height:100vh;overflow:hidden;transition:all 0.28s ease;}
  .top-bar{height:64px;padding:0 1.75rem;}
  .content-inner{padding:1.5rem 1.75rem;}
  .modal-wrap{align-items:center;}
  .modal-box{border-radius:24px;max-width:560px;}
  .modal-footer{border-radius:0 0 24px 24px;}
}
@media (max-width:1023px){ 
  .tb-chip span{display:none;} 
  .tb-chip{padding:.25rem;} 
  .content-inner{padding: 1.15rem 1rem;}

  /* Clean App-Like Experience: Remove Visible Scrollbars on Mobile */
  * {
    scrollbar-width: none !important;
    -ms-overflow-style: none !important;
  }
  *::-webkit-scrollbar,
  html::-webkit-scrollbar,
  body::-webkit-scrollbar,
  .content-area::-webkit-scrollbar,
  ::-webkit-scrollbar {
    display: none !important;
    width: 0 !important;
    height: 0 !important;
    background: transparent !important;
    -webkit-appearance: none !important;
  }
  *::-webkit-scrollbar-thumb,
  *::-webkit-scrollbar-track,
  *::-webkit-scrollbar-button,
  *::-webkit-scrollbar-corner,
  ::-webkit-scrollbar-thumb,
  ::-webkit-scrollbar-track {
    display: none !important;
    width: 0 !important;
    height: 0 !important;
    background: transparent !important;
  }
}
@media (max-width:768px){
  .top-bar{padding:0 .75rem;height:56px;}
  .page-title{font-size:clamp(0.92rem, 3.5vw, 1.05rem);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:180px;}
  .content-inner{padding: 1rem .75rem;}
  .d-hero{padding: 1.35rem 1.1rem;}
  .d-hero-greet{font-size: clamp(1.2rem, 4.5vw, 1.6rem);}
  .d-kpis{gap: .6rem;}
  .d-kpi{padding: .75rem; border-radius: 14px;}
  .form-input, .form-select, .form-group input, .form-group select, textarea, .modal-body input{font-size:16px !important;}
  .modal-box{border-radius:20px 20px 0 0;max-height:92vh;}
}
@media (max-width:480px){
  .content-inner{padding:.75rem .5rem;}
  .top-bar{padding:0 .5rem;}
  .page-title{max-width:130px;}
  .lang-switch-btn .lang-label{display:none;}
  .lang-switch-btn{padding:4px 8px;}
  .d-kpis{grid-template-columns:1fr 1fr;gap:.5rem;}
  .stats-grid{grid-template-columns:1fr 1fr;gap:.5rem;}
  .stat-card{padding:.65rem;}
  .stat-num{font-size:1.4rem;}
  div[style*="grid-template-columns:1fr 1fr"]{grid-template-columns:1fr !important;}
  .animal-picker{grid-template-columns:repeat(3,1fr);gap:.4rem;}
  .lv-row-fields{grid-template-columns:1fr;}
  .gahp-footer{grid-template-columns:1fr;text-align:center;}
  .gahp-sig{text-align:center;margin-top:1rem;}
  .btn-row{flex-direction:column;}
  .btn-row .btn{width:100%;}
}
</style>
</head>
<body class="sidebar-autohide">

<div class="farmer-hover-zone" id="farmerHoverZone" aria-hidden="true"></div>
<div class="farmer-edge-trigger" id="farmerEdgeTrigger" title="Hover or click for navigation">
  <i class="bi bi-chevron-right"></i>
</div>

<div class="drawer-overlay" id="drawerOverlay" onclick="closeDrawer()"></div>
<aside class="drawer" id="drawer">
  <div class="drawer-hdr">
    <div class="brand">Agri<span>Trace+</span></div>
    <span class="portal-lbl">Farmer Portal</span>
    <button type="button" class="btn-drawer-pin" id="farmerPinBtn" title="Pin / Unpin Sidebar">
      <i class="bi bi-pin-angle"></i>
    </button>
  </div>
  <nav class="drawer-nav">
    <div class="drawer-item active" data-page="dashboard"><i class="bi bi-grid-1x2-fill"></i>Dashboard</div>
    <div class="drawer-item" data-page="farm-reg"><i class="bi bi-house-add-fill"></i>Farm Registration</div>
    <div class="drawer-item" data-page="livestock"><i class="bi bi-clipboard2-pulse-fill"></i>Livestock</div>
    <div class="drawer-item" data-page="incident"><i class="bi bi-exclamation-triangle-fill"></i>Report Incident</div>
    <div class="drawer-item" data-page="notifications"><i class="bi bi-bell-fill"></i>Notifications<span id="drawerNotifBadge" style="<?= $unreadCount>0?'':'display:none;' ?>background:var(--danger);color:#fff;border-radius:9999px;padding:.1rem .45rem;font-size:.65rem;font-weight:700;margin-left:auto;"><?= $unreadCount ?></span></div>
    <div class="drawer-item" data-page="chat"><i class="bi bi-chat-dots-fill"></i>Farmer Chat</div>
    <div class="drawer-item" data-page="ecert"><i class="bi bi-patch-check-fill"></i>E-Certificate</div>
    <div class="drawer-item" data-page="profile"><i class="bi bi-person-circle"></i>My Profile</div>
    <div style="padding:.75rem 1.25rem;"><hr style="border:none;border-top:1px solid rgba(255,255,255,.1);"></div>
    <a href="logout.php" class="drawer-item" style="color:#fbbf24;"><i class="bi bi-box-arrow-right"></i>Logout</a>
  </nav>
  <div class="drawer-footer">
    <div class="drawer-user">
      <div class="drawer-avatar"><?= strtoupper(substr($firstName,0,1)) ?></div>
      <div>
        <div style="font-size:.875rem;font-weight:700;color:#fff;"><?= htmlspecialchars($firstName.' '.$lastName) ?></div>
        <div style="font-size:.72rem;color:rgba(255,255,255,.6);">Farmer</div>
      </div>
    </div>
  </div>
</aside>

<!-- INCOMING CALL BANNER -->
<div class="incoming-call-banner" id="incomingCallBanner">
  <div style="font-size:1.5rem;">📞</div>
  <div class="incoming-info">
    <div class="incoming-title" id="incomingCallerName">Incoming Call</div>
    <div class="incoming-sub" id="incomingCallType">Voice Call</div>
  </div>
  <div class="incoming-btns">
    <button class="call-action-btn hangup" style="width:44px;height:44px;font-size:1.2rem;" onclick="rejectCall()"><i class="bi bi-telephone-x-fill"></i></button>
    <button class="call-action-btn answer" style="width:44px;height:44px;font-size:1.2rem;" onclick="answerCall()"><i class="bi bi-telephone-fill"></i></button>
  </div>
</div>

<!-- WEBRTC CALL OVERLAY -->
<div class="call-overlay" id="callOverlay">
  <div style="text-align:center;">
    <div class="call-overlay-avatar" id="callRemoteAvatar">?</div>
    <div class="call-overlay-name" id="callRemoteName">—</div>
    <div class="call-overlay-status" id="callStatus">Calling…</div>
    <div id="permErrorMsg" class="perm-error-banner" style="border-radius:12px;font-size:.8rem;padding:.625rem .875rem;margin-top:.75rem;text-align:left;display:none;">
      <i class="bi bi-mic-mute-fill" style="flex-shrink:0;"></i>
      <div>
        <strong>Microphone/Camera access denied.</strong><br>
        Please allow permissions in your browser settings and try again.
        <button onclick="cleanupCall()" style="display:block;margin-top:.5rem;background:var(--danger);color:#fff;border:none;padding:.3rem .75rem;border-radius:8px;font-size:.78rem;cursor:pointer;font-family:inherit;">Close Call</button>
      </div>
    </div>
  </div>
  <video class="call-remote-video" id="remoteVideo" autoplay playsinline></video>
  <video class="call-local-video" id="localVideo" autoplay playsinline muted></video>
  <div class="call-btn-row">
    <button class="call-action-btn mute" id="muteBtn" onclick="toggleMute()"><i class="bi bi-mic-fill"></i></button>
    <button class="call-action-btn hangup" onclick="hangupCall()"><i class="bi bi-telephone-x-fill"></i></button>
    <button class="call-action-btn cam-toggle" id="camToggleBtn" onclick="toggleCallCam()"><i class="bi bi-camera-video-fill"></i></button>
  </div>
</div>

<div class="app">
  <header class="top-bar">
    <button class="menu-btn" onclick="openDrawer()"><i class="bi bi-list"></i></button>
    <div class="page-title" id="pageTitle">Dashboard</div>
    <div class="top-right">
      <button type="button" class="lang-switch-btn" onclick="toggleLanguage()" title="Palitan ang Wika / Switch Language"><span class="lang-flag">🇵🇭</span> <span class="lang-label">Tagalog</span></button>
      <button class="tb-bell <?= $unreadCount>0?'has-unread':'' ?>" id="topbarBellBtn" onclick="navTo('notifications')" aria-label="Notifications" title="Notifications"><i class="bi bi-bell-fill"></i><b id="topbarBellBadge" style="<?= $unreadCount>0?'':'display:none;' ?>"><?= (int)$unreadCount ?></b></button>
      <div class="tb-chip"><div class="tb-av"><?= strtoupper(substr($firstName,0,1)) ?></div><span><?= htmlspecialchars($firstName) ?></span></div>
    </div>
  </header>

  <main class="content-area" id="contentArea">
    <div class="content-inner">

      <!-- ══ DASHBOARD ══ -->
      <div id="pg-dashboard" class="page active">
<?php
  // ── Dashboard view-model ────────────────────────────────────────────────────
  $hr = (int)date('G');
  $greetWord = $hr < 12 ? 'Good morning' : ($hr < 18 ? 'Good afternoon' : 'Good evening');

  $healthTotals = ['Healthy'=>0,'Sick'=>0,'Recovered'=>0,'Deceased'=>0];
  foreach ($allLvRows as $r) {
      if (empty($r['tagId'])) continue;
      $hs = $r['healthStatus'] ?? 'Healthy';
      if (!isset($healthTotals[$hs])) $hs = 'Healthy';
      $healthTotals[$hs] += max(1, (int)($r['qty'] ?? 1));
  }
  $healthSum  = array_sum($healthTotals);
  $healthyPct = $healthSum > 0 ? round($healthTotals['Healthy'] / $healthSum * 100) : 0;
  $healthColors = ['Healthy'=>'#10b981','Sick'=>'#ef4444','Recovered'=>'#3b82f6','Deceased'=>'#94a3b8'];
  $stops = []; $acc = 0;
  foreach ($healthTotals as $k => $v) {
      if ($healthSum <= 0 || $v <= 0) continue;
      $from = $acc; $acc += $v / $healthSum * 100;
      $stops[] = $healthColors[$k].' '.round($from,2).'% '.round($acc,2).'%';
  }
  $donutBg = $stops ? 'conic-gradient('.implode(',', $stops).')' : 'conic-gradient(#e2e8f0 0 100%)';

  $approvedFarms = count(array_filter($farmsData, fn($f) => $f['status'] === 'Approved'));
  $activeCerts   = count(array_filter($certsData, fn($c) => ($c['status'] ?? '') === 'Active'));
  $animalColors  = ['Cattle'=>'#f59e0b','Swine'=>'#ec4899','Poultry'=>'#f97316','Goat'=>'#8b5cf6','Carabao'=>'#64748b','Horse'=>'#92400e','Sheep'=>'#0ea5e9','Rabbit'=>'#14b8a6'];
  $animalEmojis  = ['Cattle'=>'🐄','Swine'=>'🐖','Poultry'=>'🐓','Goat'=>'🐐','Carabao'=>'🐃','Horse'=>'🐴','Sheep'=>'🐑','Rabbit'=>'🐇'];
  $maxType = !empty($lvByType) ? max($lvByType) : 1;
  $recentIncidents = array_slice($incidentsData, 0, 4);
  $recentNotifs    = array_slice($notificationsData, 0, 4);
?>
        <div class="dash">

          <!-- HERO -->
          <section class="d-hero">
            <div class="d-hero-main">
              <span class="d-hero-date"><i class="bi bi-calendar3"></i> <?= date('l, F j, Y') ?></span>
              <h1><?= $greetWord ?>, <?= htmlspecialchars($firstName) ?>!</h1>
              <p>Here's what's happening across your farms today.</p>
              <div class="d-hero-actions">
                <button class="d-btn d-btn-light" onclick="navTo('farm-reg')"><i class="bi bi-house-add-fill"></i> Register Farm</button>
                <button class="d-btn d-btn-ghost" onclick="navTo('livestock')"><i class="bi bi-clipboard2-pulse-fill"></i> Livestock</button>
                <button class="d-btn d-btn-ghost" onclick="navTo('incident')"><i class="bi bi-exclamation-triangle-fill"></i> Report Incident</button>
              </div>
            </div>
            <div class="d-hero-side">
              <div class="d-hero-ring" style="--p:<?= (int)$healthyPct ?>;"><div><strong><?= (int)$healthyPct ?>%</strong><span>Healthy</span></div></div>
              <div class="d-hero-side-txt">
                <strong><?= number_format($totalLivestock) ?></strong>
                <span>animals across <?= count($farmsData) ?> farm<?= count($farmsData)===1?'':'s' ?></span>
              </div>
            </div>
          </section>

          <!-- KPI CARDS -->
          <section class="d-kpis">
            <div class="d-kpi">
              <div class="d-kpi-ico" style="--c:#10b981;"><i class="bi bi-house-heart-fill"></i></div>
              <div class="d-kpi-body"><span>My Farms</span><strong><?= count($farmsData) ?></strong><small><?= $approvedFarms ?> approved</small></div>
            </div>
            <div class="d-kpi">
              <div class="d-kpi-ico" style="--c:#3b82f6;"><i class="bi bi-clipboard2-pulse-fill"></i></div>
              <div class="d-kpi-body"><span>Total Livestock</span><strong><?= number_format($totalLivestock) ?></strong><small><?= count($lvByType) ?> animal type<?= count($lvByType)===1?'':'s' ?></small></div>
            </div>
            <div class="d-kpi">
              <div class="d-kpi-ico" style="--c:#ef4444;"><i class="bi bi-exclamation-triangle-fill"></i></div>
              <div class="d-kpi-body"><span>Active Incidents</span><strong><?= $activeIncidents ?></strong><small><?= $activeIncidents ? 'Needs follow-up' : 'All clear' ?></small></div>
            </div>
            <div class="d-kpi">
              <div class="d-kpi-ico" style="--c:#f59e0b;"><i class="bi bi-hourglass-split"></i></div>
              <div class="d-kpi-body"><span>Pending Approvals</span><strong><?= $pendingFarms ?></strong><small><?= $activeCerts ?> active certificate<?= $activeCerts===1?'':'s' ?></small></div>
            </div>
          </section>

          <div class="d-grid">
            <!-- LEFT COLUMN -->
            <div class="d-col">

              <div class="d-card">
                <div class="d-card-hd">
                  <div><h3>Livestock by Type</h3><p>Distribution of your herd</p></div>
                  <button class="d-link" onclick="navTo('livestock')">Manage <i class="bi bi-arrow-right"></i></button>
                </div>
                <?php if(!empty($lvByType)): ?>
                <div class="d-bars">
                  <?php foreach($lvByType as $aType => $aCount):
                    $pct = $totalLivestock > 0 ? round($aCount / $totalLivestock * 100) : 0;
                    $w   = max(4, round($aCount / $maxType * 100));
                    $col = $animalColors[$aType] ?? '#10b981'; ?>
                  <div class="d-bar-row">
                    <div class="d-bar-ico" style="--c:<?= $col ?>;"><?= $animalEmojis[$aType] ?? '🐾' ?></div>
                    <div class="d-bar-main">
                      <div class="d-bar-top"><span><?= htmlspecialchars($aType) ?></span><span><strong><?= number_format($aCount) ?></strong> <em><?= $pct ?>%</em></span></div>
                      <div class="d-bar-track"><div class="d-bar-fill" style="width:<?= $w ?>%;background:<?= $col ?>;"></div></div>
                    </div>
                  </div>
                  <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="d-empty"><i class="bi bi-clipboard2-pulse"></i><h4>No livestock recorded</h4><p>Register a farm and add animals to see your herd breakdown.</p></div>
                <?php endif; ?>
              </div>

              <div class="d-card">
                <div class="d-card-hd">
                  <div><h3>My Farms</h3><p><?= count($farmsData) ?> registered</p></div>
                  <button class="d-btn d-btn-primary d-btn-sm" onclick="navTo('farm-reg')"><i class="bi bi-plus-lg"></i> Add Farm</button>
                </div>
                <?php if(!empty($farmsData)): ?>
                <div class="d-farms">
                  <?php foreach($farmsData as $farm):
                    $fHeads = 0;
                    if (isset($livestockByFarm[$farm['id']])) foreach ($livestockByFarm[$farm['id']]['livestock'] as $lvr) $fHeads += max(1,(int)($lvr['qty'] ?? 1));
                    $addr = trim((string)($farm['address'] ?? '')); ?>
                  <div class="d-farm">
                    <div class="d-farm-av"><?= strtoupper(substr($farm['name'],0,1)) ?></div>
                    <div class="d-farm-info">
                      <div class="d-farm-name"><?= htmlspecialchars($farm['name']) ?></div>
                      <div class="d-farm-meta">
                        <span><i class="bi bi-tag-fill"></i> <?= htmlspecialchars($farm['type']) ?></span>
                        <span><i class="bi bi-geo-alt-fill"></i> <?= htmlspecialchars($addr !== '' ? (strlen($addr) > 34 ? substr($addr,0,34).'…' : $addr) : 'No address') ?></span>
                        <span><i class="bi bi-clipboard2-pulse-fill"></i> <?= number_format($fHeads) ?> head</span>
                      </div>
                    </div>
                    <span class="badge <?= statusBadge($farm['status']) ?>"><?= htmlspecialchars($farm['status']) ?></span>
                  </div>
                  <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="d-empty"><i class="bi bi-house"></i><h4>No farms yet</h4><p>Register your first farm to get started.</p>
                  <button class="d-btn d-btn-primary" style="margin-top:1rem;" onclick="navTo('farm-reg')"><i class="bi bi-house-add-fill"></i> Register Farm</button></div>
                <?php endif; ?>
              </div>
            </div>

            <!-- RIGHT COLUMN -->
            <div class="d-col">

              <div class="d-card">
                <div class="d-card-hd"><div><h3>Herd Health</h3><p>Current condition of your animals</p></div></div>
                <?php if($healthSum > 0): ?>
                <div class="d-health">
                  <div class="d-donut" style="background:<?= $donutBg ?>;"><div><strong><?= (int)$healthyPct ?>%</strong><span>Healthy</span></div></div>
                  <ul class="d-legend">
                    <?php foreach($healthTotals as $k => $v): ?>
                    <li><i style="background:<?= $healthColors[$k] ?>;"></i><span><?= $k ?></span><strong><?= number_format($v) ?></strong></li>
                    <?php endforeach; ?>
                  </ul>
                </div>
                <?php else: ?>
                <div class="d-empty"><i class="bi bi-heart-pulse"></i><h4>No health data</h4><p>Add livestock records to track herd health.</p></div>
                <?php endif; ?>
              </div>

              <div class="d-card">
                <div class="d-card-hd">
                  <div><h3>Recent Incidents</h3><p>Your latest reports</p></div>
                  <button class="d-link" onclick="navTo('incident')">View all <i class="bi bi-arrow-right"></i></button>
                </div>
                <?php if(!empty($recentIncidents)): ?>
                <div class="d-list">
                  <?php foreach($recentIncidents as $inc): $res = $inc['status']==='Resolved'; ?>
                  <div class="d-item">
                    <div class="d-item-ico" style="--c:<?= $res ? '#10b981' : '#ef4444' ?>;"><i class="bi bi-<?= $res ? 'check-circle-fill' : 'exclamation-triangle-fill' ?>"></i></div>
                    <div class="d-item-body">
                      <div class="d-item-t"><?= htmlspecialchars($inc['title']) ?></div>
                      <div class="d-item-s"><?= htmlspecialchars($inc['farmName'] ?? 'Farm') ?> · <?= date('M d, Y', strtotime($inc['createdAt'])) ?></div>
                    </div>
                    <span class="badge <?= statusBadge($inc['status']) ?>"><?= htmlspecialchars($inc['status']) ?></span>
                  </div>
                  <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="d-empty"><i class="bi bi-shield-check"></i><h4>No incidents</h4><p>Your farms are looking good.</p></div>
                <?php endif; ?>
              </div>

              <div class="d-card">
                <div class="d-card-hd">
                  <div><h3>Notifications</h3><p id="dashNotifUnreadText"><?= $unreadCount ?> unread</p></div>
                  <button class="d-link" onclick="navTo('notifications')">View all <i class="bi bi-arrow-right"></i></button>
                </div>
                <?php if(!empty($recentNotifs)): ?>
                <div class="d-list" id="dashNotifFeed">
                  <?php foreach($recentNotifs as $notif): $crit = in_array($notif['type'],['CRITICAL_ALERT','OUTBREAK_WARNING']); ?>
                  <div class="d-item" onclick="navTo('notifications'); markNotifRead(<?= $notif['id'] ?>);" style="cursor:pointer;" title="Click to view notification">
                    <div class="d-item-ico" style="--c:<?= $crit ? '#ef4444' : '#10b981' ?>;"><i class="bi bi-<?= $crit ? 'exclamation-triangle-fill' : 'bell-fill' ?>"></i></div>
                    <div class="d-item-body">
                      <div class="d-item-t"><?= htmlspecialchars($notif['title']) ?></div>
                      <div class="d-item-s"><?= htmlspecialchars(substr($notif['message'],0,60)).(strlen($notif['message'])>60?'…':'') ?></div>
                    </div>
                    <?php if(empty($notif['is_read'])): ?><span class="d-dot" id="dash-dot-<?= $notif['id'] ?>"></span><?php endif; ?>
                  </div>
                  <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="d-empty"><i class="bi bi-bell-slash"></i><h4>No notifications yet</h4></div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ══ FARM REGISTRATION ══ -->
      <div id="pg-farm-reg" class="page">
        <div class="section-hdr">
          <div class="section-seal">🌾</div>
          <div>
            <h2>Farm Registration</h2>
            <p>DA Camarines Sur · RA 8485 Animal Welfare Act<br>Auto-issues GAHP Certificate upon submission</p>
          </div>
        </div>
        <div class="toggle-row">
          <button class="tog-btn active" id="btnNew" onclick="setAppType('new')"><i class="bi bi-file-earmark-plus"></i> New</button>
          <button class="tog-btn" id="btnRenew" onclick="setAppType('renewal')"><i class="bi bi-arrow-repeat"></i> Renewal</button>
          <button class="tog-btn" id="btnGovt" onclick="setOwnership('government')"><i class="bi bi-building"></i> Govt</button>
          <button class="tog-btn active" id="btnPriv" onclick="setOwnership('private')"><i class="bi bi-person"></i> Private</button>
        </div>
        <form id="farmForm" autocomplete="off">
          <input type="hidden" name="action" value="register_farm">
          <input type="hidden" id="fPhoto" name="photo_data">
          <input type="hidden" id="fLat" name="farm_lat">
          <input type="hidden" id="fLng" name="farm_lng">
          <input type="hidden" id="appTypeVal" name="app_type" value="New">
          <input type="hidden" id="ownershipVal" name="ownership" value="Private">
          <input type="hidden" id="facilityTypesVal" name="facility_types">
          <input type="hidden" id="bizTypeVal" name="business_type" value="Sole Proprietor">

          <!-- SECTION I -->
          <div class="form-section">
            <div class="form-section-hdr"><span class="form-section-num">I</span> Petitioner Information</div>
            <div class="form-section-body">
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
                <div class="form-group"><label class="form-label">Surname *</label><input type="text" class="form-input" name="pet_surname" placeholder="Last name" required></div>
                <div class="form-group"><label class="form-label">First Name *</label><input type="text" class="form-input" name="pet_firstname" value="<?= htmlspecialchars($firstName) ?>" required></div>
              </div>
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
                <div class="form-group"><label class="form-label">Middle Name</label><input type="text" class="form-input" name="pet_middle" placeholder="Middle name"></div>
                <div class="form-group"><label class="form-label">Extension Name</label><input type="text" class="form-input" name="pet_ext" placeholder="Jr., Sr., III…"></div>
              </div>
              <div class="form-group"><label class="form-label">Sex *</label>
                <div class="biz-row" id="sexRow">
                  <button type="button" class="biz-pill" onclick="toggleSex(this,'Male')">Male</button>
                  <button type="button" class="biz-pill" onclick="toggleSex(this,'Female')">Female</button>
                </div>
                <input type="hidden" id="petSexVal" name="pet_sex" value="">
              </div>
              <div class="form-group"><label class="form-label">House/Lot/Bldg No.</label><input type="text" class="form-input" name="pet_house_no" placeholder="House/Lot/Bldg No."></div>
              <div class="form-group"><label class="form-label">Street / Sitio / Subdv.</label><input type="text" class="form-input" name="pet_street" placeholder="Street / Sitio / Subdv."></div>
              <div class="form-group"><label class="form-label">Barangay *</label><input type="text" class="form-input" name="pet_barangay" placeholder="Barangay" required></div>
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
                <div class="form-group"><label class="form-label">Municipality/City *</label><input type="text" class="form-input" name="pet_municipality" placeholder="Municipality/City" required></div>
                <div class="form-group"><label class="form-label">Province</label><input type="text" class="form-input" name="pet_province" value="Camarines Sur" readonly></div>
              </div>
              <div class="form-group"><label class="form-label">Region</label><input type="text" class="form-input" name="pet_region" value="Region V — Bicol Region" readonly></div>
              <div class="form-group"><label class="form-label">Contact Number *</label><input type="tel" class="form-input" name="pet_phone" placeholder="+63 9XX XXX XXXX" value="<?= htmlspecialchars($profileData['mobile']??'') ?>" required></div>
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
                <div class="form-group"><label class="form-label">Date of Birth *</label><input type="date" class="form-input" name="pet_dob" value="<?= htmlspecialchars($profileData['dob']??'') ?>" required></div>
                <div class="form-group"><label class="form-label">Place of Birth</label><input type="text" class="form-input" name="pet_pob" placeholder="City/Municipality"></div>
              </div>
              <div class="form-group"><label class="form-label">Civil Status *</label>
                <select class="form-select" name="pet_civil_status" required>
                  <option value="">Select…</option>
                  <option>Single</option><option>Married</option><option>Widowed</option><option>Separated</option>
                </select>
              </div>
              <div class="form-group"><label class="form-label">Spouse's Name (if married)</label><input type="text" class="form-input" name="pet_spouse" placeholder="Full name of spouse"></div>
              <div class="form-group"><label class="form-label">Mother's Maiden Name</label><input type="text" class="form-input" name="pet_mothers_name" placeholder="Mother's maiden name"></div>
              <div class="form-group"><label class="form-label">Highest Formal Education</label>
                <select class="form-select" name="pet_education">
                  <option value="">Select…</option>
                  <option>None</option><option>Elementary</option><option>High School</option>
                  <option>Vocational</option><option>College</option><option>Post Graduate</option>
                </select>
              </div>
              <div class="form-group"><label class="form-label">Religion</label><input type="text" class="form-input" name="pet_religion" placeholder="Religion"></div>
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
                <div class="form-group"><label class="form-label">Male Household Members</label><input type="number" class="form-input" name="pet_male_members" value="0" min="0"></div>
                <div class="form-group"><label class="form-label">Female Household Members</label><input type="number" class="form-input" name="pet_female_members" value="0" min="0"></div>
              </div>
              <div class="form-group"><label class="form-label">Household Head?</label>
                <div class="biz-row"><button type="button" class="biz-pill" onclick="togglePill(this,'hh_head','Yes')">Yes</button><button type="button" class="biz-pill" onclick="togglePill(this,'hh_head','No')">No</button></div>
                <input type="hidden" id="hhHeadVal" name="hh_head" value="No">
              </div>
              <div class="form-group"><label class="form-label">If no, household head name &amp; relationship</label><input type="text" class="form-input" name="hh_head_name" placeholder="Name — Relationship"></div>
              <div class="form-group"><label class="form-label">Person with Disability (PWD)?</label>
                <div class="biz-row"><button type="button" class="biz-pill" onclick="togglePill(this,'pwd','Yes')">Yes</button><button type="button" class="biz-pill" onclick="togglePill(this,'pwd','No')">No</button></div>
                <input type="hidden" id="pwdVal" name="pwd" value="No">
              </div>
              <div class="form-group"><label class="form-label">4P's Beneficiary?</label>
                <div class="biz-row"><button type="button" class="biz-pill" onclick="togglePill(this,'fourps','Yes')">Yes</button><button type="button" class="biz-pill" onclick="togglePill(this,'fourps','No')">No</button></div>
                <input type="hidden" id="fourpsVal" name="fourps" value="No">
              </div>
              <div class="form-group"><label class="form-label">Member of Indigenous Group?</label>
                <div class="biz-row"><button type="button" class="biz-pill" onclick="togglePill(this,'indigenous','Yes')">Yes</button><button type="button" class="biz-pill" onclick="togglePill(this,'indigenous','No')">No</button></div>
                <input type="hidden" id="indigenousVal" name="indigenous" value="No">
                <input type="text" class="form-input" name="indigenous_specify" placeholder="If yes, specify…" style="margin-top:.4rem;">
              </div>
              <div class="form-group"><label class="form-label">With Government ID?</label>
                <div class="biz-row"><button type="button" class="biz-pill" onclick="togglePill(this,'gov_id','Yes')">Yes</button><button type="button" class="biz-pill" onclick="togglePill(this,'gov_id','No')">No</button></div>
                <input type="hidden" id="govIdVal" name="has_gov_id" value="No">
                <input type="text" class="form-input" name="gov_id_number" placeholder="Specify ID number if yes" style="margin-top:.4rem;">
              </div>
              <div class="form-group"><label class="form-label">Member of Farmers Association / Cooperative?</label>
                <div class="biz-row"><button type="button" class="biz-pill" onclick="togglePill(this,'farmers_assoc','Yes')">Yes</button><button type="button" class="biz-pill" onclick="togglePill(this,'farmers_assoc','No')">No</button></div>
                <input type="hidden" id="farmersAssocVal" name="farmers_assoc" value="No">
                <input type="text" class="form-input" name="farmers_assoc_name" placeholder="If yes, specify association…" style="margin-top:.4rem;">
              </div>
              <div class="form-group"><label class="form-label">Emergency Contact Person</label><input type="text" class="form-input" name="emergency_contact_name" value="<?= htmlspecialchars($profileData['emergency_contact']??'') ?>" placeholder="Full name"></div>
              <div class="form-group"><label class="form-label">Emergency Contact Number</label><input type="tel" class="form-input" name="emergency_contact_no" placeholder="+63 9XX XXX XXXX"></div>
            </div>
          </div>

          <!-- SECTION II -->
          <div class="form-section">
            <div class="form-section-hdr"><span class="form-section-num">II</span> Farm Profile &amp; Main Livelihood</div>
            <div class="form-section-body">
              <div class="form-group"><label class="form-label">Farm Name *</label><input type="text" class="form-input" name="farm_name" placeholder="e.g. Dela Cruz Livestock Farm" required></div>
              <div class="form-group"><label class="form-label">Farm Address *</label><input type="text" class="form-input" id="farmAddress" name="farm_address" placeholder="Complete farm address" required></div>
              <div class="form-group"><label class="form-label">Farm Type *</label>
                <select class="form-select" name="farm_type" required>
                  <option value="Mixed">Mixed</option>
                  <option value="Cattle">Cattle</option>
                  <option value="Swine">Swine</option>
                  <option value="Poultry">Poultry</option>
                  <option value="Goat">Goat</option>
                </select>
              </div>
              <div class="form-group"><label class="form-label">Farm Area (hectares)</label><input type="number" class="form-input" name="farm_area" placeholder="0.00" step="0.01" min="0"></div>
              <div class="form-group"><label class="form-label">Business Type</label>
                <div class="biz-row" id="bizRow">
                  <button type="button" class="biz-pill active" onclick="toggleBiz(this)">Sole Proprietor</button>
                  <button type="button" class="biz-pill" onclick="toggleBiz(this)">Partnership</button>
                  <button type="button" class="biz-pill" onclick="toggleBiz(this)">Corporation</button>
                  <button type="button" class="biz-pill" onclick="toggleBiz(this)">Cooperative</button>
                </div>
              </div>
              <div class="form-group"><label class="form-label">Main Livelihood *</label>
                <div class="biz-row" id="livelihoodRow">
                  <button type="button" class="biz-pill active" onclick="toggleLivelihood(this,'Farmer')">Farmer</button>
                  <button type="button" class="biz-pill" onclick="toggleLivelihood(this,'Farmworker/Laborer')">Farmworker/Laborer</button>
                  <button type="button" class="biz-pill" onclick="toggleLivelihood(this,'Fisherfolk')">Fisherfolk</button>
                </div>
                <input type="hidden" id="livelihoodVal" name="main_livelihood" value="Farmer">
              </div>
              <div class="form-group"><label class="form-label">Type of Farming Activity</label>
                <div class="cb-grid" id="farmingTypeCbs">
                  <label class="cb-item" onclick="syncFarmCb(this)"><input type="checkbox" name="farming_rice" value="1"> Rice</label>
                  <label class="cb-item" onclick="syncFarmCb(this)"><input type="checkbox" name="farming_corn" value="1"> Corn</label>
                  <label class="cb-item" onclick="syncFarmCb(this)"><input type="checkbox" name="farming_livestock" value="1"> Livestock</label>
                  <label class="cb-item" onclick="syncFarmCb(this)"><input type="checkbox" name="farming_poultry" value="1"> Poultry</label>
                  <label class="cb-item" onclick="syncFarmCb(this)" style="grid-column:1/-1"><input type="checkbox" name="farming_other" value="1"> Other crops</label>
                </div>
                <input type="text" class="form-input" name="farming_other_specify" placeholder="Other crops — please specify" style="margin-top:.4rem;">
              </div>
              <div class="form-group"><label class="form-label">Livestock Details</label><input type="text" class="form-input" name="livestock_specify" placeholder="e.g. Cattle, Swine, Goat…"></div>
              <div class="form-group"><label class="form-label">Poultry Details</label><input type="text" class="form-input" name="poultry_specify" placeholder="e.g. Broiler, Layer, Native…"></div>
              <div class="form-group"><label class="form-label">Gross Annual Income Last Year</label>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
                  <div><label class="form-label" style="font-size:.72rem;">Farming (₱)</label><input type="number" class="form-input" name="income_farming" placeholder="0.00" min="0" step="0.01"></div>
                  <div><label class="form-label" style="font-size:.72rem;">Non-farming (₱)</label><input type="number" class="form-input" name="income_non_farming" placeholder="0.00" min="0" step="0.01"></div>
                </div>
              </div>
              <div class="gps-zone">
                <h4><i class="bi bi-camera-video"></i> Farm Photo + GPS Location</h4>
                <div class="photo-actions">
                  <button type="button" class="btn-cam primary" onclick="openFarmCam()"><i class="bi bi-camera-fill"></i> Take Photo</button>
                  <label class="btn-cam secondary" style="cursor:pointer;">
                    <i class="bi bi-upload"></i> Upload
                    <input type="file" accept="image/*" style="display:none;" onchange="handleFarmUpload(event)">
                  </label>
                </div>
                <div class="gps-badge" id="farmGpsBadge"><i class="bi bi-geo-alt-fill"></i> <span id="farmGpsText">GPS Captured</span></div>
                <div class="photo-preview" id="farmPhotoPreview">
                  <canvas id="farmStampCanvas"></canvas>
                  <button type="button" class="remove-photo" onclick="removeFarmPhoto()">✕</button>
                </div>
              </div>
            </div>
          </div>

          <!-- SECTION III: Dynamic Livestock -->
          <div class="form-section">
            <div class="form-section-hdr"><span class="form-section-num">III</span> Livestock Inventory</div>
            <div class="form-section-body">
              <p style="font-size:.78rem;color:var(--sub);margin-bottom:1rem;">Tap "Add Animal Type" to add livestock. Only enter the types you actually have.</p>

              <!-- Dynamic rows container -->
              <div class="lv-dynamic-list" id="lvDynamicList"></div>

              <!-- Animal picker (hidden by default) -->
              <div class="animal-picker-wrap" id="animalPickerWrap">
                <p style="font-size:.78rem;font-weight:700;color:var(--sub);margin-bottom:.625rem;text-transform:uppercase;letter-spacing:.05em;">Select animal type</p>
                <div class="animal-picker">
                  <?php foreach($allAnimalTypes as $at): ?>
                  <button type="button" class="animal-pick-btn" onclick="addAnimalRow('<?= $at ?>')" data-type="<?= $at ?>">
                    <span><?= $animalEmoji[$at] ?></span><?= $at ?>
                  </button>
                  <?php endforeach; ?>
                </div>
                <button type="button" onclick="toggleAnimalPicker()" style="width:100%;margin-top:.75rem;padding:.5rem;border:none;background:none;color:var(--sub);font-size:.82rem;font-weight:600;cursor:pointer;">Cancel</button>
              </div>

              <button type="button" class="btn-add-animal" id="btnAddAnimal" onclick="toggleAnimalPicker()">
                <i class="bi bi-plus-circle-fill"></i> Add Animal Type
              </button>
            </div>
          </div>

          <button type="submit" class="btn-submit" id="farmSubmitBtn">
            <i class="bi bi-send-fill"></i> Submit &amp; Get Certificate
          </button>
        </form>
      </div>

      <!-- ══ LIVESTOCK ══ -->
      <div id="pg-livestock" class="page">
        <?php
        $flatLivestock = [];
        $totalHeadCount = 0;
        $healthyCount = 0;
        $sickCount = 0;
        $vaxCompleteCount = 0;

        foreach($allLvRows as $r) {
            if (!empty($r['tagId'])) {
                $flatLivestock[] = $r;
                $q = (int)($r['qty'] ?? 1);
                $totalHeadCount += $q;
                $st = $r['healthStatus'] ?? 'Healthy';
                if ($st === 'Healthy') $healthyCount += $q;
                elseif ($st === 'Sick') $sickCount += $q;
                if (($r['vaccinationStatus'] ?? '') === 'Complete') $vaxCompleteCount += $q;
            }
        }
        $healthRate = $totalHeadCount > 0 ? round(($healthyCount / $totalHeadCount) * 100) : 100;
        $vaxRate = $totalHeadCount > 0 ? round(($vaxCompleteCount / $totalHeadCount) * 100) : 0;
        ?>

        <!-- 1. Modern Web App Header -->
        <div class="lv-app-header">
          <div class="lv-header-content">
            <div>
              <div class="lv-badge-pill">
                <span class="lv-pulse-dot"></span>
                <span>Live Herd Registry &bull; <?= count($livestockByFarm) ?> <?= count($livestockByFarm) === 1 ? 'Farm' : 'Farms' ?> Connected</span>
              </div>
              <h1 style="font-size:1.6rem;font-weight:800;letter-spacing:-0.02em;margin:0 0 0.35rem;line-height:1.2;">
                Livestock Management
              </h1>
              <p style="margin:0;font-size:0.84rem;opacity:0.92;max-width:620px;line-height:1.45;">
                Track animal health records, vaccination schedules, ear tag credentials, and herd productivity across all your registered farm facilities.
              </p>
            </div>
            <div class="lv-header-actions">
              <?php if(!empty($farmsData)): ?>
              <button type="button" class="lv-btn-primary" onclick="openModal('lvAddModal')">
                <i class="bi bi-plus-circle-fill"></i> Add Animal
              </button>
              <?php endif; ?>
              <button type="button" class="lv-btn-glass" onclick="exportLivestockCSV()">
                <i class="bi bi-download"></i> Export CSV
              </button>
              <button type="button" class="lv-btn-glass" onclick="navTo('incident')">
                <i class="bi bi-exclamation-triangle"></i> Report Issue
              </button>
            </div>
          </div>
        </div>

        <!-- 2. Metric KPI Dashboard Cards -->
        <div class="lv-kpi-grid">
          <div class="lv-kpi-card">
            <div class="lv-kpi-icon" style="background:#ecfdf5;color:#059669;">
              <i class="bi bi-boxes"></i>
            </div>
            <div class="lv-kpi-val">
              <span id="kpiTotalAnimals"><?= number_format($totalHeadCount) ?></span>
              <span style="font-size:0.8rem;color:var(--sub);font-weight:600;">heads</span>
            </div>
            <div class="lv-kpi-lbl">Total Head Count</div>
            <div class="lv-kpi-sub">
              <i class="bi bi-upc-scan" style="color:var(--acc);"></i>
              <span><?= count($flatLivestock) ?> tagged lots / records</span>
            </div>
          </div>

          <div class="lv-kpi-card">
            <div class="lv-kpi-icon" style="background:rgba(16,185,129,0.12);color:#10b981;">
              <i class="bi bi-heart-pulse-fill"></i>
            </div>
            <div class="lv-kpi-val">
              <span><?= $healthRate ?>%</span>
            </div>
            <div class="lv-kpi-lbl">Health Index</div>
            <div class="lv-kpi-sub">
              <span class="lv-status-dot lv-status-healthy"></span>
              <span><?= number_format($healthyCount) ?> in optimal condition</span>
            </div>
          </div>

          <div class="lv-kpi-card">
            <div class="lv-kpi-icon" style="background:#fef2f2;color:#ef4444;">
              <i class="bi bi-shield-exclamation"></i>
            </div>
            <div class="lv-kpi-val" style="color:<?= $sickCount > 0 ? '#ef4444' : 'var(--txt)' ?>;">
              <span><?= number_format($sickCount) ?></span>
              <span style="font-size:0.8rem;color:var(--sub);font-weight:600;">head<?= $sickCount === 1 ? '' : 's' ?></span>
            </div>
            <div class="lv-kpi-lbl">Requires Attention</div>
            <div class="lv-kpi-sub" style="color:<?= $sickCount > 0 ? '#dc2626' : 'var(--sub)' ?>;">
              <?= $sickCount > 0 ? '<i class="bi bi-exclamation-circle-fill"></i> Flagged for treatment' : '<i class="bi bi-check-circle-fill" style="color:#10b981;"></i> No critical alerts' ?>
            </div>
          </div>

          <div class="lv-kpi-card">
            <div class="lv-kpi-icon" style="background:#eff6ff;color:#3b82f6;">
              <i class="bi bi-shield-check"></i>
            </div>
            <div class="lv-kpi-val">
              <span><?= $vaxRate ?>%</span>
            </div>
            <div class="lv-kpi-lbl">Vaccination Coverage</div>
            <div class="lv-kpi-sub">
              <i class="bi bi-check2-circle" style="color:#3b82f6;"></i>
              <span><?= number_format($vaxCompleteCount) ?> head<?= $vaxCompleteCount === 1 ? '' : 's' ?> fully vaccinated</span>
            </div>
          </div>
        </div>

        <?php if(empty($flatLivestock)): ?>
        <div class="card" style="padding:2.5rem 1.5rem;text-align:center;border-radius:18px;">
          <div style="width:72px;height:72px;margin:0 auto 1.25rem;border-radius:20px;background:#ecfdf5;color:#059669;display:flex;align-items:center;justify-content:center;font-size:2.2rem;">
            <i class="bi bi-shield-plus"></i>
          </div>
          <h3 style="font-weight:800;color:var(--txt);margin-bottom:0.5rem;font-size:1.2rem;">No Livestock Recorded Yet</h3>
          <p style="font-size:0.85rem;color:var(--sub);max-width:440px;margin:0 auto 1.5rem;line-height:1.5;">
            <?= empty($farmsData) ? 'Please register your farm first. Once approved, you can start tracking and monitoring your animal registry.' : 'Your farm registry is ready. Add your first animal to track vaccinations, health condition, and weight records.' ?>
          </p>
          <?php if(empty($farmsData)): ?>
          <button class="btn btn-primary" onclick="navTo('farm-reg')"><i class="bi bi-house-add-fill"></i> Register Farm First</button>
          <?php else: ?>
          <button class="btn btn-primary" onclick="openModal('lvAddModal')"><i class="bi bi-plus-lg"></i> Add First Animal</button>
          <?php endif; ?>
        </div>
        <?php else: ?>

        <!-- 3. Species Filter Ribbon -->
        <div class="lv-ribbon-wrap" id="lvSpeciesRibbon">
          <button type="button" class="lv-chip-btn active" data-species="all" onclick="filterBySpecies('all', this)">
            <span>🐾 All Species</span>
            <span class="lv-chip-count"><?= count($flatLivestock) ?></span>
          </button>
          <?php foreach($allAnimalTypes as $at): 
            $atCount = 0;
            foreach($flatLivestock as $fl) {
              if (strcasecmp($fl['type'], $at) === 0) $atCount++;
            }
            if ($atCount === 0) continue;
            $ico = $animalEmoji[$at] ?? '🐾';
          ?>
          <button type="button" class="lv-chip-btn" data-species="<?= strtolower($at) ?>" onclick="filterBySpecies('<?= strtolower($at) ?>', this)">
            <span><?= $ico ?> <?= $at ?></span>
            <span class="lv-chip-count"><?= $atCount ?></span>
          </button>
          <?php endforeach; ?>
        </div>

        <!-- 4. Interactive Toolbar -->
        <div class="lv-toolbar-card">
          <div class="lv-search-box">
            <i class="bi bi-search"></i>
            <input type="text" class="lv-search-input" id="lvSearchInput" placeholder="Search Tag ID, breed, farm, notes…" oninput="filterLivestock()" autocomplete="off">
            <button type="button" class="lv-search-clear" id="lvSearchClear" onclick="clearLvSearch()" title="Clear search">
              <i class="bi bi-x-circle-fill"></i>
            </button>
          </div>

          <div class="lv-filters-row">
            <select class="lv-select" id="lvFarmFilter" onchange="filterLivestock()">
              <option value="all">All Farms (<?= count($farmsData) ?>)</option>
              <?php foreach($farmsData as $f): ?>
              <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['name']) ?></option>
              <?php endforeach; ?>
            </select>

            <select class="lv-select" id="lvHealthFilter" onchange="filterLivestock()">
              <option value="all">All Health Conditions</option>
              <option value="Healthy">🟢 Healthy</option>
              <option value="Sick">🔴 Sick / Treatment</option>
              <option value="Recovered">🔵 Recovered</option>
              <option value="Deceased">⚪ Deceased</option>
            </select>

            <div class="lv-view-toggles">
              <button type="button" class="lv-view-btn active" id="lvBtnGrid" onclick="setLvView('grid')" title="Card Grid View">
                <i class="bi bi-grid-fill"></i> Cards
              </button>
              <button type="button" class="lv-view-btn" id="lvBtnTable" onclick="setLvView('table')" title="Dense Table View">
                <i class="bi bi-table"></i> Table
              </button>
              <button type="button" class="lv-view-btn" id="lvBtnFarm" onclick="setLvView('farm')" title="Grouped by Farm">
                <i class="bi bi-folder2-open"></i> By Farm
              </button>
            </div>
          </div>
        </div>

        <!-- 5. View A: Card Grid View -->
        <div id="lvGridView" class="lv-grid">
          <?php foreach($flatLivestock as $lv): 
            $theme = getAnimalTheme($lv['type']);
            $h = $lv['healthStatus'] ?? 'Healthy';
            $v = $lv['vaccinationStatus'] ?? 'None';
            $pillClass = 'lv-pill-' . strtolower($h);
            $vClass = 'lv-vax-' . strtolower($v);
          ?>
          <div class="lv-card"
               data-id="<?= $lv['id'] ?>"
               data-tag="<?= htmlspecialchars(strtolower($lv['tagId'])) ?>"
               data-tag-raw="<?= htmlspecialchars($lv['tagId']) ?>"
               data-species="<?= htmlspecialchars(strtolower($lv['type'])) ?>"
               data-species-raw="<?= htmlspecialchars($lv['type']) ?>"
               data-breed="<?= htmlspecialchars(strtolower($lv['breed']??'')) ?>"
               data-breed-raw="<?= htmlspecialchars($lv['breed']??'') ?>"
               data-farm-id="<?= $lv['farmId'] ?>"
               data-farm-name="<?= htmlspecialchars(strtolower($lv['farmName'])) ?>"
               data-farm-name-raw="<?= htmlspecialchars($lv['farmName']) ?>"
               data-health="<?= htmlspecialchars($h) ?>"
               data-notes="<?= htmlspecialchars(strtolower($lv['notes']??'')) ?>"
               data-notes-raw="<?= htmlspecialchars($lv['notes']??'') ?>"
               data-qty="<?= (int)$lv['qty'] ?>"
               data-age="<?= htmlspecialchars($lv['age']??'') ?>"
               data-weight="<?= htmlspecialchars($lv['weight']??'') ?>">
            
            <div class="lv-card-top">
              <div class="lv-avatar" style="background:<?= $theme['bg'] ?>;color:<?= $theme['color'] ?>;">
                <?= $animalEmoji[$lv['type']] ?? '🐾' ?>
              </div>
              <div class="lv-card-main">
                <div class="lv-card-title" title="<?= htmlspecialchars($lv['breed'] ?: $lv['type']) ?>">
                  <span><?= htmlspecialchars($lv['breed'] ?: $lv['type']) ?></span>
                  <span style="font-size:0.7rem;font-weight:600;color:var(--sub);">&bull; <?= htmlspecialchars($lv['type']) ?></span>
                </div>
                <div class="lv-farm-badge">
                  <i class="bi bi-geo-alt-fill" style="color:var(--acc);font-size:0.75rem;"></i>
                  <span><?= htmlspecialchars($lv['farmName']) ?></span>
                  <span class="badge <?= statusBadge($lv['farmStatus']) ?>" style="font-size:0.6rem;padding:1px 5px;"><?= $lv['farmStatus'] ?></span>
                </div>
              </div>
            </div>

            <div class="lv-tag-strip">
              <span class="lv-tag-chip" onclick="copyTagId('<?= htmlspecialchars(addslashes($lv['tagId'])) ?>', this)" title="Click to copy Tag ID">
                <i class="bi bi-tag-fill"></i>
                <?= htmlspecialchars($lv['tagId']) ?>
                <i class="bi bi-copy" style="font-size:0.7rem;opacity:0.6;margin-left:2px;"></i>
              </span>
              <span class="lv-qty-pill">
                <i class="bi bi-collection"></i> <?= number_format($lv['qty']) ?> <?= (int)$lv['qty'] === 1 ? 'Head' : 'Heads' ?>
              </span>
            </div>

            <div class="lv-vitals-grid">
              <div class="lv-vital-cell">
                <span class="lv-vital-lbl">Condition</span>
                <span class="lv-pill <?= $pillClass ?>">
                  <span class="lv-status-dot lv-status-<?= strtolower($h) ?>"></span>
                  <?= htmlspecialchars($h) ?>
                </span>
              </div>
              <div class="lv-vital-cell">
                <span class="lv-vital-lbl">Vaccine</span>
                <span class="lv-vax-tag <?= $vClass ?>">
                  <i class="bi <?= $v === 'Complete' ? 'bi-shield-check' : ($v === 'Partial' ? 'bi-shield-shaded' : 'bi-shield-x') ?>"></i>
                  <?= htmlspecialchars($v) ?>
                </span>
              </div>
              <div class="lv-vital-cell">
                <span class="lv-vital-lbl">Age</span>
                <span class="lv-vital-val"><?= !empty($lv['age']) ? ((float)$lv['age'] . ' yrs') : '<span style="color:var(--sub);font-weight:400;">—</span>' ?></span>
              </div>
              <div class="lv-vital-cell">
                <span class="lv-vital-lbl">Weight</span>
                <span class="lv-vital-val"><?= (!empty($lv['weight']) && (float)$lv['weight'] > 0) ? (number_format((float)$lv['weight'], 1) . ' kg') : '<span style="color:var(--sub);font-weight:400;">—</span>' ?></span>
              </div>
            </div>

            <?php if(!empty($lv['notes'])): ?>
            <div class="lv-notes-callout" title="Medical and observation notes">
              <i class="bi bi-journal-medical" style="color:#059669;margin-right:4px;"></i>
              <?= htmlspecialchars($lv['notes']) ?>
            </div>
            <?php endif; ?>

            <div class="lv-card-footer">
              <div style="font-size:0.7rem;color:var(--sub);">
                <i class="bi bi-clock-history"></i> <?= !empty($lv['createdAt']) ? date('M j, Y', strtotime($lv['createdAt'])) : 'Active' ?>
              </div>
              <div class="lv-card-btns">
                <?php if($h === 'Sick'): ?>
                <button type="button" class="lv-action-btn warn" onclick="reportIssueForAnimal(<?= $lv['farmId'] ?>, '<?= $lv['type'] ?>', '<?= htmlspecialchars(addslashes($lv['tagId'])) ?>', '<?= htmlspecialchars(addslashes($lv['breed']??'')) ?>')" title="Report health incident to DA">
                  <i class="bi bi-exclamation-triangle-fill"></i> Alert
                </button>
                <?php endif; ?>
                <button type="button" class="lv-action-btn" onclick="openEditLv(<?= $lv['id'] ?>,'<?= htmlspecialchars(addslashes($lv['tagId'])) ?>','<?= $lv['type'] ?>','<?= htmlspecialchars(addslashes($lv['breed']??'')) ?>',<?= (int)$lv['qty'] ?>,<?= (float)($lv['age']??0) ?>,<?= (float)($lv['weight']??0) ?>,'<?= $lv['healthStatus'] ?>','<?= $lv['vaccinationStatus'] ?>','<?= htmlspecialchars(addslashes($lv['notes']??'')) ?>')" title="Edit details">
                  <i class="bi bi-pencil-square"></i> Edit
                </button>
                <button type="button" class="lv-action-btn danger" onclick="deleteLv(<?= $lv['id'] ?>,'<?= htmlspecialchars(addslashes($lv['tagId'])) ?>')" title="Delete animal record">
                  <i class="bi bi-trash"></i>
                </button>
              </div>
            </div>

          </div>
          <?php endforeach; ?>
        </div>

        <!-- 6. View B: High-Density Table View -->
        <div id="lvTableView" class="lv-table-card" style="display:none;">
          <div class="lv-table-wrap">
            <table class="lv-table">
              <thead>
                <tr>
                  <th>Tag ID</th>
                  <th>Animal &amp; Breed</th>
                  <th>Farm</th>
                  <th style="text-align:center;">Qty</th>
                  <th>Condition</th>
                  <th>Vaccination</th>
                  <th>Age / Weight</th>
                  <th>Notes</th>
                  <th style="text-align:right;">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach($flatLivestock as $lv): 
                  $theme = getAnimalTheme($lv['type']);
                  $h = $lv['healthStatus'] ?? 'Healthy';
                  $v = $lv['vaccinationStatus'] ?? 'None';
                ?>
                <tr class="lv-tbl-row"
                    data-tag="<?= htmlspecialchars(strtolower($lv['tagId'])) ?>"
                    data-species="<?= htmlspecialchars(strtolower($lv['type'])) ?>"
                    data-breed="<?= htmlspecialchars(strtolower($lv['breed']??'')) ?>"
                    data-farm-id="<?= $lv['farmId'] ?>"
                    data-farm-name="<?= htmlspecialchars(strtolower($lv['farmName'])) ?>"
                    data-health="<?= htmlspecialchars($h) ?>"
                    data-notes="<?= htmlspecialchars(strtolower($lv['notes']??'')) ?>">
                  <td>
                    <span class="lv-tag-chip" onclick="copyTagId('<?= htmlspecialchars(addslashes($lv['tagId'])) ?>', this)" title="Click to copy">
                      <?= htmlspecialchars($lv['tagId']) ?>
                      <i class="bi bi-copy" style="font-size:0.68rem;opacity:0.6;margin-left:2px;"></i>
                    </span>
                  </td>
                  <td>
                    <div style="display:flex;align-items:center;gap:8px;">
                      <span style="font-size:1.15rem;"><?= $animalEmoji[$lv['type']] ?? '🐾' ?></span>
                      <div>
                        <div style="font-weight:700;color:var(--txt);"><?= htmlspecialchars($lv['breed'] ?: $lv['type']) ?></div>
                        <div style="font-size:0.7rem;color:var(--sub);"><?= htmlspecialchars($lv['type']) ?></div>
                      </div>
                    </div>
                  </td>
                  <td>
                    <div style="font-weight:600;font-size:0.8rem;color:var(--txt);"><?= htmlspecialchars($lv['farmName']) ?></div>
                    <div style="font-size:0.7rem;color:var(--sub);"><?= htmlspecialchars($lv['farmType']) ?></div>
                  </td>
                  <td style="text-align:center;font-weight:800;color:var(--acc2);">
                    <?= number_format($lv['qty']) ?>
                  </td>
                  <td>
                    <span class="lv-pill lv-pill-<?= strtolower($h) ?>">
                      <span class="lv-status-dot lv-status-<?= strtolower($h) ?>"></span>
                      <?= htmlspecialchars($h) ?>
                    </span>
                  </td>
                  <td>
                    <span class="lv-vax-tag lv-vax-<?= strtolower($v) ?>">
                      <?= htmlspecialchars($v) ?>
                    </span>
                  </td>
                  <td style="font-size:0.75rem;color:var(--sub);">
                    <div><?= !empty($lv['age']) ? ((float)$lv['age'] . ' yrs') : '—' ?></div>
                    <div><?= (!empty($lv['weight']) && (float)$lv['weight'] > 0) ? (number_format((float)$lv['weight'], 1) . ' kg') : '—' ?></div>
                  </td>
                  <td style="max-width:180px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-size:0.73rem;color:var(--sub);">
                    <?= !empty($lv['notes']) ? htmlspecialchars($lv['notes']) : '<span style="opacity:0.4;">—</span>' ?>
                  </td>
                  <td style="text-align:right;">
                    <div style="display:inline-flex;gap:4px;">
                      <?php if($h === 'Sick'): ?>
                      <button type="button" class="lv-action-btn warn" onclick="reportIssueForAnimal(<?= $lv['farmId'] ?>, '<?= $lv['type'] ?>', '<?= htmlspecialchars(addslashes($lv['tagId'])) ?>', '<?= htmlspecialchars(addslashes($lv['breed']??'')) ?>')" title="Alert">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                      </button>
                      <?php endif; ?>
                      <button type="button" class="lv-action-btn" onclick="openEditLv(<?= $lv['id'] ?>,'<?= htmlspecialchars(addslashes($lv['tagId'])) ?>','<?= $lv['type'] ?>','<?= htmlspecialchars(addslashes($lv['breed']??'')) ?>',<?= (int)$lv['qty'] ?>,<?= (float)($lv['age']??0) ?>,<?= (float)($lv['weight']??0) ?>,'<?= $lv['healthStatus'] ?>','<?= $lv['vaccinationStatus'] ?>','<?= htmlspecialchars(addslashes($lv['notes']??'')) ?>')">
                        <i class="bi bi-pencil-square"></i>
                      </button>
                      <button type="button" class="lv-action-btn danger" onclick="deleteLv(<?= $lv['id'] ?>,'<?= htmlspecialchars(addslashes($lv['tagId'])) ?>')">
                        <i class="bi bi-trash"></i>
                      </button>
                    </div>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- 7. View C: Farm Grouped View -->
        <div id="lvFarmView" style="display:none;">
          <?php foreach($livestockByFarm as $fId => $fData):
            $fAnimals = $fData['livestock'];
            $farmTotal = array_sum(array_column($fAnimals, 'qty'));
          ?>
          <div class="lv-farm-card" data-farm-id="<?= $fId ?>">
            <div class="lv-farm-header">
              <div class="lv-farm-avatar">
                <?= strtoupper(substr($fData['farm']['name'], 0, 1)) ?>
              </div>
              <div style="flex:1;min-width:0;">
                <div class="lv-farm-title"><?= htmlspecialchars($fData['farm']['name']) ?></div>
                <div class="lv-farm-subtitle">
                  <span class="badge <?= statusBadge($fData['farm']['status']) ?>"><?= $fData['farm']['status'] ?></span>
                  <span style="font-size:0.73rem;color:var(--sub);"><?= htmlspecialchars($fData['farm']['type']) ?></span>
                  <?php if(!empty($fData['farm']['address'])): ?>
                  <span style="font-size:0.73rem;color:var(--sub);">&bull; <?= htmlspecialchars($fData['farm']['address']) ?></span>
                  <?php endif; ?>
                </div>
              </div>
              <div class="lv-farm-count-badge">
                <div class="lv-farm-count-num"><?= number_format($farmTotal) ?></div>
                <div class="lv-farm-count-lbl">Head<?= $farmTotal === 1 ? '' : 's' ?></div>
              </div>
            </div>

            <?php if(!empty($fAnimals)): ?>
            <div class="lv-grid" style="margin-bottom:0;">
              <?php foreach($fAnimals as $lv): 
                $theme = getAnimalTheme($lv['type']);
                $h = $lv['healthStatus'] ?? 'Healthy';
                $v = $lv['vaccinationStatus'] ?? 'None';
              ?>
              <div class="lv-card"
                   data-id="<?= $lv['id'] ?>"
                   data-tag="<?= htmlspecialchars(strtolower($lv['tagId'])) ?>"
                   data-species="<?= htmlspecialchars(strtolower($lv['type'])) ?>"
                   data-breed="<?= htmlspecialchars(strtolower($lv['breed']??'')) ?>"
                   data-farm-id="<?= $fId ?>"
                   data-farm-name="<?= htmlspecialchars(strtolower($fData['farm']['name'])) ?>"
                   data-health="<?= htmlspecialchars($h) ?>"
                   data-notes="<?= htmlspecialchars(strtolower($lv['notes']??'')) ?>">
                
                <div class="lv-card-top">
                  <div class="lv-avatar" style="background:<?= $theme['bg'] ?>;color:<?= $theme['color'] ?>;">
                    <?= $animalEmoji[$lv['type']] ?? '🐾' ?>
                  </div>
                  <div class="lv-card-main">
                    <div class="lv-card-title"><?= htmlspecialchars($lv['breed'] ?: $lv['type']) ?></div>
                    <div style="font-size:0.72rem;color:var(--sub);"><?= htmlspecialchars($lv['type']) ?></div>
                  </div>
                </div>

                <div class="lv-tag-strip">
                  <span class="lv-tag-chip" onclick="copyTagId('<?= htmlspecialchars(addslashes($lv['tagId'])) ?>', this)">
                    <?= htmlspecialchars($lv['tagId']) ?>
                  </span>
                  <span class="lv-qty-pill"><?= number_format($lv['qty']) ?> Head</span>
                </div>

                <div class="lv-vitals-grid">
                  <div class="lv-vital-cell">
                    <span class="lv-vital-lbl">Condition</span>
                    <span class="lv-pill lv-pill-<?= strtolower($h) ?>">
                      <span class="lv-status-dot lv-status-<?= strtolower($h) ?>"></span>
                      <?= htmlspecialchars($h) ?>
                    </span>
                  </div>
                  <div class="lv-vital-cell">
                    <span class="lv-vital-lbl">Vaccine</span>
                    <span class="lv-vax-tag lv-vax-<?= strtolower($v) ?>"><?= htmlspecialchars($v) ?></span>
                  </div>
                </div>

                <div class="lv-card-footer">
                  <span style="font-size:0.7rem;color:var(--sub);">Lot #<?= $lv['id'] ?></span>
                  <div class="lv-card-btns">
                    <button type="button" class="lv-action-btn" onclick="openEditLv(<?= $lv['id'] ?>,'<?= htmlspecialchars(addslashes($lv['tagId'])) ?>','<?= $lv['type'] ?>','<?= htmlspecialchars(addslashes($lv['breed']??'')) ?>',<?= (int)$lv['qty'] ?>,<?= (float)($lv['age']??0) ?>,<?= (float)($lv['weight']??0) ?>,'<?= $lv['healthStatus'] ?>','<?= $lv['vaccinationStatus'] ?>','<?= htmlspecialchars(addslashes($lv['notes']??'')) ?>')">
                      <i class="bi bi-pencil-square"></i>
                    </button>
                    <button type="button" class="lv-action-btn danger" onclick="deleteLv(<?= $lv['id'] ?>,'<?= htmlspecialchars(addslashes($lv['tagId'])) ?>')">
                      <i class="bi bi-trash"></i>
                    </button>
                  </div>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div style="text-align:center;padding:1.5rem 1rem;background:#f8fafc;border-radius:12px;border:1px dashed var(--border);">
              <p style="font-size:0.8rem;color:var(--sub);margin-bottom:0.75rem;">No livestock assigned to this farm yet.</p>
              <button type="button" class="btn btn-primary" style="font-size:0.78rem;padding:0.45rem 0.9rem;" onclick="openAddLvForFarm(<?= $fId ?>)">
                <i class="bi bi-plus-circle"></i> Add Animal to This Farm
              </button>
            </div>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>

        <!-- 8. No Matching Results State -->
        <div id="lvNoResults" class="card" style="display:none;text-align:center;padding:2.5rem 1rem;border-radius:18px;">
          <div style="width:60px;height:60px;margin:0 auto 1rem;background:#f1f5f9;border-radius:50%;display:flex;align-items:center;justify-content:center;color:var(--sub);font-size:1.6rem;">
            <i class="bi bi-search"></i>
          </div>
          <h4 style="font-weight:800;color:var(--txt);margin-bottom:0.35rem;">No Matching Animals Found</h4>
          <p style="font-size:0.82rem;color:var(--sub);max-width:380px;margin:0 auto 1.25rem;">
            No animals matched your search query or active filters. Try adjusting your search term or reset all filters.
          </p>
          <button type="button" class="btn btn-primary" style="font-size:0.8rem;padding:0.5rem 1rem;" onclick="resetLvFilters()">
            <i class="bi bi-arrow-counterclockwise"></i> Reset Filters
          </button>
        </div>

        <?php endif; ?>
      </div>

      <!-- ══ INCIDENT ══ -->
      <div id="pg-incident" class="page">
        <div class="incident-hdr">
          <h2>🚨 Report Livestock Incident</h2>
          <p>Your report will automatically alert DA officers. Critical disease reports also notify nearby farms.</p>
        </div>
        <div class="alert-banner">
          <i class="bi bi-lightning-charge" style="font-size:1.35rem;color:#d97706;flex-shrink:0;"></i>
          <div style="font-size:.8rem;color:#92400e;line-height:1.5;"><strong>Auto-Alert:</strong> ALL incidents notify DA officers immediately. Critical reports (ASF, FMD, Avian Flu) also alert farms within 10km.</div>
        </div>
        <form id="incidentForm" autocomplete="off">
          <input type="hidden" name="action" value="report_incident">
          <input type="hidden" id="incPhoto" name="photo_data">
          <input type="hidden" id="incLat" name="latitude">
          <input type="hidden" id="incLng" name="longitude">
          <input type="hidden" id="incSeverityVal" name="severity" value="Low">
          <div class="card">
            <h3 class="card-title"><i class="bi bi-house-check"></i> Farm &amp; Incident Type</h3>
            <div class="form-group"><label class="form-label">Farm *</label>
              <select class="form-select" name="farm_id" required>
                <option value="">Select your farm...</option>
                <?php foreach($farmsData as $f): ?>
                <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['name']) ?> (<?= $f['type'] ?>)</option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group"><label class="form-label">Incident Type *</label>
              <select class="form-select" name="incident_type" required onchange="updateDiseaseTags(this.value)">
                <option value="">Select type...</option>
                <option value="Sick">🐄 Sick Animals</option>
                <option value="Dead">💀 Dead Animals</option>
                <option value="Disease">🦠 Disease Outbreak</option>
                <option value="Stray">🐖 Stray Animals</option>
                <option value="Others">📋 Others</option>
              </select>
            </div>
            <div class="disease-tags" id="diseaseTags" style="display:none;">
              <span class="disease-tag" onclick="toggleDTag(this,'ASF')">ASF</span>
              <span class="disease-tag" onclick="toggleDTag(this,'FMD')">FMD</span>
              <span class="disease-tag" onclick="toggleDTag(this,'Avian Flu')">Avian Flu</span>
              <span class="disease-tag" onclick="toggleDTag(this,'PRRS')">PRRS</span>
              <span class="disease-tag" onclick="toggleDTag(this,'Classical Swine Fever')">Classical Swine Fever</span>
            </div>
          </div>
          <div class="card">
            <h3 class="card-title"><i class="bi bi-exclamation-triangle"></i> Severity &amp; Details</h3>
            <div class="form-group"><label class="form-label">Severity Level *</label>
              <div class="severity-grid">
                <div class="severity-card active" onclick="selectSeverity(this,'Low')"><span class="severity-icon">🟢</span><div>Low</div><small>Minor issue</small></div>
                <div class="severity-card" onclick="selectSeverity(this,'Medium')"><span class="severity-icon">🟡</span><div>Medium</div><small>Moderate</small></div>
                <div class="severity-card" onclick="selectSeverity(this,'High')"><span class="severity-icon">🟠</span><div>High</div><small>Serious</small></div>
                <div class="severity-card" onclick="selectSeverity(this,'Critical')"><span class="severity-icon">🔴</span><div>Critical</div><small>Emergency</small></div>
              </div>
            </div>
            <div class="form-group"><label class="form-label">Affected Livestock Type</label>
              <select class="form-select" name="livestock_type">
                <option value="">All types</option>
                <?php foreach($allAnimalTypes as $at): ?>
                <option><?= $at ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group"><label class="form-label">Symptoms Observed</label><textarea class="form-input" name="symptoms" rows="2" placeholder="Fever, coughing, diarrhea…"></textarea></div>
            <div class="form-group"><label class="form-label">Title *</label><input type="text" class="form-input" name="title" required placeholder="e.g. '5 pigs died suddenly with fever'"></div>
            <div class="form-group"><label class="form-label">Detailed Description *</label><textarea class="form-input" name="description" rows="3" required placeholder="Describe what you observed, when it started…"></textarea></div>
          </div>
          <div class="card">
            <h3 class="card-title"><i class="bi bi-geo-alt"></i> Location &amp; Evidence</h3>
            <div class="capture-zone" onclick="openIncCam()">
              <i class="bi bi-camera-video" style="font-size:2.5rem;color:#6b7280;display:block;margin-bottom:.75rem;"></i>
              <h4 style="color:#374151;margin-bottom:.375rem;font-size:.9rem;">Capture Photo + GPS 📸📍</h4>
              <p style="color:#6b7280;font-size:.8rem;margin:0;">Tap to take photo of affected animals/location</p>
              <div id="incGpsBadge" style="display:none;margin-top:.75rem;background:#d1fae5;color:#065f46;padding:.4rem .875rem;border-radius:8px;font-size:.78rem;font-weight:600;align-items:center;gap:.375rem;justify-content:center;"><i class="bi bi-geo-alt-fill"></i> <span id="incGpsText">GPS Captured</span></div>
            </div>
            <div style="text-align:center;margin-top:-.25rem;">
              <button type="button" class="btn btn-secondary" style="font-size:.82rem;" onclick="document.getElementById('incFileUpload').click()"><i class="bi bi-upload"></i> Upload Photo Instead</button>
              <input type="file" id="incFileUpload" accept="image/*" style="display:none;" onchange="handleIncUpload(event)">
            </div>
          </div>
          <div style="display:flex;gap:.75rem;margin-top:.5rem;">
            <button type="button" class="btn btn-secondary" style="flex:1;" onclick="resetIncForm()"><i class="bi bi-arrow-counterclockwise"></i> Reset</button>
            <button type="submit" class="btn-submit" id="incSubmitBtn" style="flex:2;background:linear-gradient(135deg,var(--danger),#dc2626);">
              <i class="bi bi-send-check"></i> Send Alert
            </button>
          </div>
        </form>
      </div>

      <!-- ══ NOTIFICATIONS ══ -->
      <div id="pg-notifications" class="page">
        <div class="card" style="box-shadow:0 1px 3px rgba(0,0,0,0.06);">
          <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:1.25rem;">
            <div>
              <h3 class="card-title" style="margin-bottom:4px;display:flex;align-items:center;gap:8px;">
                <span>Notifications & Advisories</span>
                <span id="notifUnreadBadgePill" class="badge" style="<?= $unreadCount > 0 ? '' : 'display:none;' ?>background:var(--danger);color:#fff;font-size:.72rem;padding:.2rem .55rem;border-radius:999px;"><?= $unreadCount ?> Unread</span>
              </h3>
              <p style="font-size:.8rem;color:var(--sub);margin:0;">Real-time biosecurity disease alerts, DA farm certifications, and veterinary advisories.</p>
            </div>
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
              <button type="button" class="btn" onclick="sendTestNotification()" style="background:#f1f5f9;color:#334155;border:1px solid #cbd5e1;font-size:.78rem;padding:.45rem .85rem;border-radius:10px;display:inline-flex;align-items:center;gap:6px;cursor:pointer;">
                <i class="bi bi-bell-plus"></i> <span>Send Test Alert</span>
              </button>
              <button type="button" class="btn btn-primary" onclick="markAllNotifsRead()" style="font-size:.78rem;padding:.45rem .85rem;border-radius:10px;display:inline-flex;align-items:center;gap:6px;cursor:pointer;">
                <i class="bi bi-check2-all"></i> <span>Mark All as Read</span>
              </button>
            </div>
          </div>

          <!-- Filter tabs -->
          <div class="notif-filters" style="display:flex;gap:8px;margin-bottom:1.25rem;overflow-x:auto;padding-bottom:4px;">
            <button type="button" class="notif-tab active" data-filter="all" onclick="filterNotifs('all')">All (<span id="notifCountAll"><?= count($notificationsData) ?></span>)</button>
            <button type="button" class="notif-tab" data-filter="unread" onclick="filterNotifs('unread')">Unread (<span id="notifCountUnread"><?= $unreadCount ?></span>)</button>
            <button type="button" class="notif-tab" data-filter="alerts" onclick="filterNotifs('alerts')"><i class="bi bi-exclamation-triangle-fill" style="color:var(--danger);"></i> Disease Alerts</button>
            <button type="button" class="notif-tab" data-filter="farms" onclick="filterNotifs('farms')"><i class="bi bi-patch-check-fill" style="color:var(--acc);"></i> Farm Approvals</button>
          </div>

          <div id="notifListContainer">
            <?php if(!empty($notificationsData)): ?>
              <?php foreach($notificationsData as $notif):
                $isCrit = in_array($notif['type'],['CRITICAL_ALERT','OUTBREAK_WARNING']);
                $iconClass = $isCrit ? 'exclamation-triangle-fill' : ($notif['type']==='FARM_APPROVED' ? 'patch-check-fill' : ($notif['type']==='FARM_REJECTED' ? 'x-circle-fill' : 'bell-fill'));
                $iconBg = $isCrit ? 'var(--danger)' : ($notif['type']==='FARM_APPROVED' ? 'var(--acc)' : ($notif['type']==='FARM_REJECTED' ? '#ea580c' : '#3b82f6'));
                $badgeBg = $isCrit ? 'background:#fee2e2;color:#991b1b;' : ($notif['priority']==='High' ? 'background:#ffedd5;color:#9a3412;' : 'background:#e0f2fe;color:#075985;');
              ?>
              <div class="notif-item <?= empty($notif['is_read']) ? 'unread' : 'read' ?> <?= $isCrit ? 'is-alert' : '' ?>" id="notif-item-<?= $notif['id'] ?>" data-id="<?= $notif['id'] ?>" data-read="<?= $notif['is_read'] ?>" data-type="<?= $notif['type'] ?>" onclick="markNotifRead(<?= $notif['id'] ?>)">
                <div class="notif-icon" style="background:<?= $iconBg ?>;">
                  <i class="bi bi-<?= $iconClass ?>"></i>
                </div>
                <div style="flex:1;min-width:0;">
                  <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap;">
                    <div style="font-weight:700;font-size:.875rem;color:var(--pri);display:flex;align-items:center;gap:6px;">
                      <span><?= htmlspecialchars($notif['title']) ?></span>
                      <?php if(empty($notif['is_read'])): ?><span class="notif-dot <?= $isCrit ? 'alert' : '' ?>"></span><?php endif; ?>
                    </div>
                    <span class="badge" style="<?= $badgeBg ?>font-size:.65rem;font-weight:700;padding:.15rem .45rem;border-radius:6px;"><?= htmlspecialchars($notif['priority'] ?: 'Normal') ?></span>
                  </div>
                  <div style="font-size:.8rem;color:var(--text);margin-top:.3rem;line-height:1.45;word-break:break-word;">
                    <?= nl2br(htmlspecialchars($notif['message'])) ?>
                  </div>
                  <div style="display:flex;align-items:center;justify-content:space-between;margin-top:.5rem;gap:8px;">
                    <div style="font-size:.7rem;color:var(--sub);"><i class="bi bi-clock"></i> <?= date('M d, Y h:i A', strtotime($notif['created_at'])) ?></div>
                    <?php if(empty($notif['is_read'])): ?>
                    <button type="button" class="notif-action-btn mark-read-btn" onclick="markNotifRead(<?= $notif['id'] ?>, event)" title="Mark as Read"><i class="bi bi-check2"></i> Mark read</button>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
              <?php endforeach; ?>
            <?php else: ?>
              <div class="empty-state" id="notifEmptyState"><i class="bi bi-bell-slash"></i><h4>No notifications yet</h4><p style="font-size:.8rem;color:var(--sub);">You will receive updates when disease alerts are issued or farm applications are processed.</p></div>
            <?php endif; ?>
          </div>
          <div id="notifFilteredEmpty" class="empty-state" style="display:none;padding:2rem 1rem;"><i class="bi bi-funnel"></i><h4>No notifications matching this filter</h4></div>
        </div>
      </div>

      <!-- ══ CHAT ══ -->
      <div id="pg-chat" class="page">
        <div class="chat-shell">
          <!-- LEFT SIDEBAR: CONTACTS -->
          <aside class="chat-sidebar" id="chatListView">
            <div class="chat-sidebar-hdr">
              <div class="chat-hdr-title">
                <i class="bi bi-chat-dots-fill" style="color:var(--acc);"></i>
                <span>Agri Community</span>
              </div>
              <span class="chat-count-pill" id="chatContactsCount">0 contacts</span>
            </div>
            
            <div class="chat-search-wrap">
              <div class="chat-search-box">
                <i class="bi bi-search"></i>
                <input type="text" id="farmerSearch" class="chat-search-input" placeholder="Search officials or farmers…" oninput="filterFarmers(this.value)">
              </div>
            </div>

            <!-- Filter tabs -->
            <div class="chat-tabs">
              <button type="button" class="chat-tab-btn active" data-tab="all" onclick="setChatTab('all')">All</button>
              <button type="button" class="chat-tab-btn" data-tab="official" onclick="setChatTab('official')"><i class="bi bi-shield-check"></i> DA Officials</button>
              <button type="button" class="chat-tab-btn" data-tab="farmer" onclick="setChatTab('farmer')"><i class="bi bi-people-fill"></i> Farmers</button>
            </div>

            <div class="chat-farmer-list" id="farmerList">
              <div style="padding:2.5rem 1rem;text-align:center;color:var(--sub);">
                <i class="bi bi-arrow-repeat" style="font-size:2rem;display:block;margin-bottom:.5rem;"></i>
                <p style="font-size:.85rem;">Loading contacts…</p>
              </div>
            </div>
          </aside>

          <!-- RIGHT PANE: CONVERSATION -->
          <section class="chat-conv-view" id="chatConvView">
            <!-- No Conversation Selected (Desktop Placeholder) -->
            <div class="chat-no-conv" id="chatNoConvView">
              <div class="chat-no-conv-icon">
                <i class="bi bi-chat-quote-fill"></i>
              </div>
              <h3>Select a Conversation</h3>
              <p>Chat directly with Camarines Sur Agriculture Officials for technical support or share experiences with fellow farmers.</p>
            </div>

            <!-- Active Conversation Container -->
            <div class="chat-active-pane" id="chatActivePane">
              <div class="chat-hdr-mini">
                <div class="chat-hdr-left">
                  <button type="button" class="chat-back" onclick="closeChatConv()" title="Back to contacts"><i class="bi bi-arrow-left"></i></button>
                  <div class="chat-hdr-avatar" id="convAvatar">?</div>
                  <div style="min-width:0;">
                    <div class="chat-hdr-name">
                      <span id="convName">—</span>
                      <span class="role-tag-badge" id="convRoleBadge"></span>
                    </div>
                    <div class="chat-hdr-status">
                      <span class="status-dot"></span>
                      <span id="convStatus">Active now</span>
                    </div>
                  </div>
                </div>
                <div class="chat-hdr-actions">
                  <button type="button" class="call-btn-sm voice-btn" onclick="initiateCall('voice')" title="Voice call"><i class="bi bi-telephone-fill"></i></button>
                  <button type="button" class="call-btn-sm video-btn" onclick="initiateCall('video')" title="Video call"><i class="bi bi-camera-video-fill"></i></button>
                </div>
              </div>

              <!-- Messages Container -->
              <div class="chat-messages-area" id="convMessages"></div>

              <!-- Quick tags -->
              <div class="chat-quick-tags">
                <button type="button" class="quick-chip" onclick="quickInsertTag('🌾 Farm update: ')">🌾 Farm update</button>
                <button type="button" class="quick-chip" onclick="quickInsertTag('🚨 Animal Health Concern: ')">🚨 Health concern</button>
                <button type="button" class="quick-chip" onclick="quickInsertTag('📋 Inquiry on GAHP: ')">📋 GAHP Inquiry</button>
                <button type="button" class="quick-chip" onclick="quickInsertTag('👋 Good day! ')">👋 Good day</button>
              </div>

              <!-- Media Preview Bar (when image is chosen) -->
              <div class="chat-media-preview-bar" id="chatMediaPreviewBar">
                <img id="chatMediaThumb" class="chat-preview-thumb" src="" alt="Selected photo">
                <div class="chat-preview-info">
                  <strong id="chatMediaName" style="color:var(--txt);display:block;">Photo attached</strong>
                  <span>Ready to send with your message</span>
                </div>
                <button type="button" class="chat-preview-remove" onclick="removePendingMedia()" title="Remove image">
                  <i class="bi bi-x-circle-fill"></i>
                </button>
              </div>

              <!-- Input Bar -->
              <div class="chat-input-bar">
                <input type="file" id="chatFileInput" accept="image/*" style="display:none;" onchange="handleChatMediaSelect(event)">
                <button type="button" class="chat-attach-btn" onclick="triggerChatMedia()" title="Attach photo">
                  <i class="bi bi-image"></i>
                </button>
                <textarea class="chat-input" id="chatInput" rows="1" placeholder="Type a message…" onkeydown="handleChatKey(event)" oninput="autoResize(this)"></textarea>
                <button type="button" class="chat-send-btn" onclick="sendChatMessage()" title="Send message">
                  <i class="bi bi-send-fill"></i>
                </button>
              </div>
            </div>
          </section>
        </div>
      </div>

      <!-- ══ E-CERTIFICATE ══ -->
      <div id="pg-ecert" class="page">
        <div class="card" style="padding:.875rem;">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;flex-wrap:wrap;gap:.5rem;">
            <h3 style="font-size:.95rem;font-weight:700;display:flex;align-items:center;gap:.5rem;margin:0;"><i class="bi bi-award" style="color:#c9a543;"></i> GAHP Certificates</h3>
            <span style="background:linear-gradient(135deg,#d1fae5,#a7f3d0);color:#065f46;padding:.25rem .75rem;border-radius:9999px;font-size:.75rem;font-weight:700;"><i class="bi bi-shield-check"></i> <?= count($certsData) ?> Certificate<?= count($certsData)!=1?'s':'' ?></span>
          </div>
          <?php if(!empty($certsData)): ?>
          <?php foreach($certsData as $cert):
            $issueDate  = $cert['issued_date']  ? date('d F Y',strtotime($cert['issued_date']))  : date('d F Y',strtotime($cert['created_at']));
            $expiryDate = $cert['expiry_date']   ? date('d F Y',strtotime($cert['expiry_date']))  : 'Indefinite';
            $isExpired  = $cert['expiry_date'] && strtotime($cert['expiry_date'])<time();
            $certNo     = $cert['cert_number'] ?: 'GAHP-'.date('Y').'-'.str_pad($cert['id'],5,'0',STR_PAD_LEFT);
            $farmId5    = 'R07-BOAI-'.str_pad($cert['farm_id'],5,'0',STR_PAD_LEFT);
            $farmType   = ucwords($cert['farm_type']??'Mixed');
            $ownerFull  = trim(($cert['owner_firstname']??'').' '.($cert['owner_lastname']??$cert['ownerName']??''));
          ?>
          <div class="cert-action-bar">
            <span class="cert-status-badge <?= $isExpired?'cert-expired':'cert-valid' ?>"><i class="bi bi-<?= $isExpired?'x-circle-fill':'check-circle-fill' ?>"></i><?= $isExpired?'EXPIRED':'VALID' ?></span>
            <div style="flex:1;font-size:.78rem;color:var(--sub);font-weight:600;"><?= htmlspecialchars($cert['farm_name']) ?></div>
            <button class="btn btn-secondary" style="padding:.375rem .625rem;font-size:.72rem;" onclick="printCert('gahp-<?= $cert['id'] ?>')"><i class="bi bi-printer"></i></button>
            <button class="btn btn-primary" style="padding:.375rem .625rem;font-size:.72rem;" onclick="showToast('Use Print › Save as PDF to download','success')"><i class="bi bi-download"></i></button>
          </div>
          <div class="gahp-cert" id="gahp-<?= $cert['id'] ?>">
            <div class="gahp-inner">
              <div class="gahp-top">
                <div class="gahp-logo">
                  <svg viewBox="0 0 90 90" xmlns="http://www.w3.org/2000/svg" style="width:60px;height:60px;">
                    <circle cx="45" cy="45" r="44" fill="#1a5c38"/>
                    <circle cx="45" cy="45" r="38" fill="none" stroke="#c9a543" stroke-width="2"/>
                    <ellipse cx="45" cy="52" rx="11" ry="8" fill="#c9a543"/>
                    <circle cx="45" cy="40" r="7" fill="#c9a543"/>
                    <ellipse cx="39" cy="35" rx="3" ry="5" fill="#c9a543" transform="rotate(-15,39,35)"/>
                    <ellipse cx="51" cy="35" rx="3" ry="5" fill="#c9a543" transform="rotate(15,51,35)"/>
                    <text x="45" y="72" text-anchor="middle" fill="#fff" font-size="7" font-weight="bold" font-family="Arial">GAHP</text>
                  </svg>
                </div>
                <div class="gahp-org">Good Animal Husbandry Practices</div>
                <div class="gahp-cert-title">CERTIFICATE</div>
                <div class="gahp-nos">Certificate number: <span><?= htmlspecialchars($certNo) ?></span><br>Farm ID number: <span><?= htmlspecialchars($farmId5) ?></span></div>
              </div>
              <div class="gahp-body">
                <div class="gahp-issued">Issued to</div>
                <div style="text-align:center;"><div class="gahp-farm-name"><?= htmlspecialchars(strtoupper($cert['farm_name'])) ?></div></div>
                <div class="gahp-farm-addr"><?= htmlspecialchars($cert['farm_address']??'Camarines Sur, Region V') ?></div>
                <div class="gahp-declaration">The Good Animal Husbandry Practices (GAHP) Certification Committee declares that the farm mentioned on this certificate has been found to be compliant with the requirements of<br><strong>DEPARTMENT OF AGRICULTURE</strong><br><strong>Good Animal Husbandry Practices (GAHP) — PNS/BAFS 184:2016</strong></div>
                <table class="gahp-table"><thead><tr><th>Farm Production</th><th>Date of Certification</th></tr></thead><tbody><tr><td><?= htmlspecialchars($farmType) ?></td><td style="font-weight:700;color:#1a5c38;"><?= htmlspecialchars($issueDate) ?></td></tr></tbody></table>
                <div class="gahp-validity"><span style="font-weight:600;color:#555;">Valid until:</span><span style="font-weight:700;color:#b45309;"><?= htmlspecialchars($expiryDate) ?></span></div>
              </div>
              <div class="gahp-footer">
                <div>
                  <div style="width:68px;height:68px;background:#000;border-radius:4px;display:flex;align-items:center;justify-content:center;">
                    <svg width="60" height="60" viewBox="0 0 72 72" xmlns="http://www.w3.org/2000/svg"><rect width="72" height="72" fill="#fff"/><rect x="4" y="4" width="22" height="22" fill="#000"/><rect x="7" y="7" width="16" height="16" fill="#fff"/><rect x="10" y="10" width="10" height="10" fill="#000"/><rect x="46" y="4" width="22" height="22" fill="#000"/><rect x="49" y="7" width="16" height="16" fill="#fff"/><rect x="52" y="10" width="10" height="10" fill="#000"/><rect x="4" y="46" width="22" height="22" fill="#000"/><rect x="7" y="49" width="16" height="16" fill="#fff"/><rect x="10" y="52" width="10" height="10" fill="#000"/><rect x="30" y="4" width="4" height="4" fill="#000"/><rect x="36" y="10" width="4" height="4" fill="#000"/><rect x="30" y="30" width="4" height="4" fill="#000"/><rect x="36" y="36" width="4" height="4" fill="#000"/><rect x="42" y="30" width="4" height="4" fill="#000"/><rect x="30" y="42" width="4" height="4" fill="#000"/><rect x="36" y="48" width="4" height="4" fill="#000"/><rect x="48" y="36" width="4" height="4" fill="#000"/><rect x="54" y="42" width="4" height="4" fill="#000"/><rect x="60" y="36" width="4" height="4" fill="#000"/><rect x="30" y="60" width="4" height="4" fill="#000"/><rect x="42" y="54" width="4" height="4" fill="#000"/><rect x="54" y="60" width="4" height="4" fill="#000"/></svg>
                  </div>
                  <div style="font-size:.6rem;color:#888;margin-top:.25rem;">Scan to verify</div>
                </div>
                <div class="gahp-sig">
                  <div style="font-size:.65rem;font-weight:700;color:#333;">AUTHORIZED BY:</div>
                  <div class="gahp-sig-line">
                    <div class="gahp-sig-name"><?= htmlspecialchars($cert['issued_by']??'Bureau of Animal Industry - DA') ?></div>
                    <div class="gahp-sig-title">Officer-in-Charge, Director<br>Bureau of Animal Industry<br>Department of Agriculture</div>
                  </div>
                </div>
              </div>
              <div class="gahp-stamp"><span>RF AHWD-44 GAHP Certificate | Rev. No. 00</span><span>AgriTrace+ | <?= date('Y') ?></span></div>
            </div>
          </div>
          <?php endforeach; ?>
          <?php else: ?>
          <div class="empty-state"><i class="bi bi-award"></i><h4>No GAHP Certificates Yet</h4><p>Certificates are auto-issued when you register a farm.</p><button class="btn btn-primary" style="margin-top:1rem;" onclick="navTo('farm-reg')"><i class="bi bi-house-add-fill"></i> Register Farm</button></div>
          <?php endif; ?>
        </div>
      </div>

      <!-- ══ PROFILE ══ -->
      <div id="pg-profile" class="page">
        <div class="profile-card-wrapper">
          
          <!-- Top Green Header -->
          <div class="profile-card-header">
            <div class="profile-avatar-circle" id="pfAvatarDisplay">
              <?php if(!empty($profileData['profile_pix']) && file_exists($profileData['profile_pix'])): ?>
                <img src="<?= htmlspecialchars($profileData['profile_pix']) ?>" alt="Profile Photo">
              <?php else: ?>
                <?= strtoupper(substr($firstName,0,1)) ?>
              <?php endif; ?>
            </div>
            <h2 class="profile-header-name"><?= htmlspecialchars($firstName.' '.$lastName) ?></h2>
            <div class="profile-header-role">
              <span>Registered Farmer</span>
              <span class="profile-verified-chip"><i class="bi bi-patch-check-fill"></i> Verified</span>
            </div>
          </div>

          <!-- Card Body -->
          <div class="profile-card-body">
            
            <!-- Personal Information -->
            <div class="profile-sec-heading">
              <i class="bi bi-person-fill"></i> Personal Information
            </div>
            <div class="profile-info-row">
              <span class="profile-info-label">Email</span>
              <span class="profile-info-value">
                <?= !empty($profileData['email']) ? htmlspecialchars($profileData['email']) : '—' ?>
                <?php if(!empty($profileData['email'])): ?>
                  <button type="button" class="profile-copy-btn" onclick="copyTagId('<?= htmlspecialchars(addslashes($profileData['email'])) ?>', this)" title="Copy Email"><i class="bi bi-copy"></i></button>
                <?php endif; ?>
              </span>
            </div>
            <div class="profile-info-row">
              <span class="profile-info-label">Mobile</span>
              <span class="profile-info-value">
                <?= !empty($profileData['mobile']) ? htmlspecialchars($profileData['mobile']) : '—' ?>
                <?php if(!empty($profileData['mobile'])): ?>
                  <button type="button" class="profile-copy-btn" onclick="copyTagId('<?= htmlspecialchars(addslashes($profileData['mobile'])) ?>', this)" title="Copy Mobile"><i class="bi bi-copy"></i></button>
                <?php endif; ?>
              </span>
            </div>
            <div class="profile-info-row">
              <span class="profile-info-label">Gender</span>
              <span class="profile-info-value"><?= !empty($profileData['gender']) ? htmlspecialchars(ucfirst($profileData['gender'])) : '—' ?></span>
            </div>
            <div class="profile-info-row">
              <span class="profile-info-label">Date of Birth</span>
              <span class="profile-info-value"><?= !empty($profileData['dob']) ? date('M d, Y', strtotime($profileData['dob'])) : '—' ?></span>
            </div>

            <!-- Farm & Identification -->
            <div class="profile-sec-heading">
              <i class="bi bi-briefcase-fill"></i> Farm &amp; Identification
            </div>
            <div class="profile-info-row">
              <span class="profile-info-label">RSBSA / Farmer ID</span>
              <span class="profile-info-value">
                <?= !empty($profileData['gov_id']) ? htmlspecialchars($profileData['gov_id']) : '—' ?>
                <?php if(!empty($profileData['gov_id'])): ?>
                  <button type="button" class="profile-copy-btn" onclick="copyTagId('<?= htmlspecialchars(addslashes($profileData['gov_id'])) ?>', this)" title="Copy RSBSA ID"><i class="bi bi-copy"></i></button>
                <?php endif; ?>
              </span>
            </div>
            <div class="profile-info-row">
              <span class="profile-info-label">Account Status</span>
              <span class="profile-info-value" style="color:#059669;font-weight:700;">● Active &amp; Verified</span>
            </div>

            <!-- Residence & Emergency Contact -->
            <div class="profile-sec-heading">
              <i class="bi bi-geo-alt-fill"></i> Location &amp; Emergency Contact
            </div>
            <div class="profile-info-row">
              <span class="profile-info-label">Permanent Address</span>
              <span class="profile-info-value"><?= !empty($profileData['address']) ? htmlspecialchars($profileData['address']) : '—' ?></span>
            </div>
            <div class="profile-info-row">
              <span class="profile-info-label">Emergency Contact</span>
              <span class="profile-info-value"><?= !empty($profileData['emergency_contact']) ? htmlspecialchars($profileData['emergency_contact']) : '—' ?></span>
            </div>

            <!-- Actions -->
            <div class="profile-card-actions">
              <button type="button" class="profile-btn-edit" onclick="openModal('profileModal')">
                <i class="bi bi-pencil-square"></i> Edit Profile
              </button>
              <button type="button" class="profile-btn-pw" onclick="openModal('farmerPasswordModal')">
                <i class="bi bi-shield-lock"></i> Change Password
              </button>
            </div>

          </div>
        </div>
      </div>

    </div>
  </main>
</div>

<!-- MODALS -->
<div id="profileModal" class="modal-wrap">
  <div class="modal-box">
    <div class="modal-hdr">
      <h3><i class="bi bi-person-gear"></i> Edit Profile Information</h3>
      <button class="modal-close" onclick="closeModal('profileModal')">✕</button>
    </div>
    <form id="profileForm" method="POST" enctype="multipart/form-data">
      <input type="hidden" name="update_profile" value="1">
      <div class="modal-body">
        
        <!-- Live Avatar Selection Row -->
        <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.25rem;padding:0.85rem;background:#f8fafc;border-radius:14px;border:1px solid var(--border);">
          <div class="pf-preview-avatar" id="pfEditLiveAvatar">
            <?php if(!empty($profileData['profile_pix']) && file_exists($profileData['profile_pix'])): ?>
            <img src="<?= htmlspecialchars($profileData['profile_pix']) ?>" alt="Preview">
            <?php else: ?>
            <i class="bi bi-person-fill"></i>
            <?php endif; ?>
          </div>
          <div style="flex:1;">
            <label class="form-label" style="margin-bottom:3px;font-size:0.8rem;">Profile Photo</label>
            <input type="file" class="form-input" id="pfPhotoInput" name="profile_pix" accept="image/*" onchange="previewProfilePhoto(this)" style="padding:0.4rem;font-size:0.78rem;">
            <div style="font-size:0.68rem;color:var(--sub);margin-top:3px;">JPG, PNG, WebP up to 5MB</div>
          </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;margin-bottom:0.75rem;">
          <div class="form-group" style="margin-bottom:0;">
            <label class="form-label">First Name *</label>
            <input type="text" class="form-input" name="firstName" value="<?= htmlspecialchars($firstName) ?>" required>
          </div>
          <div class="form-group" style="margin-bottom:0;">
            <label class="form-label">Last Name *</label>
            <input type="text" class="form-input" name="lastName" value="<?= htmlspecialchars($lastName) ?>" required>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">RSBSA / Farmer Registry ID</label>
          <input type="text" class="form-input" name="gov_id" value="<?= htmlspecialchars($profileData['gov_id']??'') ?>" placeholder="e.g. 05-17-01-000-001234">
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;margin-bottom:0.75rem;">
          <div class="form-group" style="margin-bottom:0;">
            <label class="form-label">Email Address *</label>
            <input type="email" class="form-input" name="email" value="<?= htmlspecialchars($profileData['email']??'') ?>" required>
          </div>
          <div class="form-group" style="margin-bottom:0;">
            <label class="form-label">Mobile Contact</label>
            <input type="tel" class="form-input" name="mobile" value="<?= htmlspecialchars($profileData['mobile']??'') ?>" placeholder="+63 9XX XXX XXXX">
          </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;margin-bottom:0.75rem;">
          <div class="form-group" style="margin-bottom:0;">
            <label class="form-label">Gender</label>
            <select class="form-select" name="gender">
              <option value="">Select Gender</option>
              <option value="Male" <?= ($profileData['gender']??'')==='Male'?'selected':'' ?>>Male</option>
              <option value="Female" <?= ($profileData['gender']??'')==='Female'?'selected':'' ?>>Female</option>
              <option value="Other" <?= ($profileData['gender']??'')==='Other'?'selected':'' ?>>Other</option>
            </select>
          </div>
          <div class="form-group" style="margin-bottom:0;">
            <label class="form-label">Date of Birth</label>
            <input type="date" class="form-input" name="dob" value="<?= htmlspecialchars($profileData['dob']??'') ?>">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Home / Permanent Address</label>
          <textarea class="form-input" name="address" rows="2" placeholder="Barangay, Municipality, Province"><?= htmlspecialchars($profileData['address']??'') ?></textarea>
        </div>

        <div class="form-group">
          <label class="form-label">Emergency Contact Person &amp; Mobile</label>
          <input type="text" class="form-input" name="emergency_contact" value="<?= htmlspecialchars($profileData['emergency_contact']??'') ?>" placeholder="e.g. Maria Santos (0917-123-4567)">
        </div>

      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('profileModal')">Cancel</button>
        <button type="submit" class="btn btn-primary" id="btnSaveProfile"><i class="bi bi-check2"></i> Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- Change Password Modal -->
<div id="farmerPasswordModal" class="modal-wrap">
  <div class="modal-box" style="max-width:460px;">
    <div class="modal-hdr">
      <h3><i class="bi bi-shield-lock"></i> Change Password</h3>
      <button class="modal-close" onclick="closeModal('farmerPasswordModal')">✕</button>
    </div>
    <form id="farmerPwForm" autocomplete="off">
      <input type="hidden" name="action" value="change_password">
      <div class="modal-body">
        <div class="form-group" style="margin-bottom:1rem;">
          <label class="form-label">Current Password *</label>
          <input type="password" class="form-input" name="current_password" required placeholder="Enter current password">
        </div>
        <div class="form-group" style="margin-bottom:1rem;">
          <label class="form-label">New Password * (minimum 6 characters)</label>
          <input type="password" class="form-input" name="new_password" required minlength="6" placeholder="Enter new strong password">
        </div>
        <div class="form-group" style="margin-bottom:1.25rem;">
          <label class="form-label">Confirm New Password *</label>
          <input type="password" class="form-input" name="confirm_password" required minlength="6" placeholder="Repeat new password">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('farmerPasswordModal')">Cancel</button>
        <button type="submit" class="btn btn-primary" id="btnUpdatePw"><i class="bi bi-key-fill"></i> Update Password</button>
      </div>
    </form>
  </div>
</div>

<div id="lvEditModal" class="modal-wrap">
  <div class="modal-box">
    <div class="modal-hdr"><h3 id="lvEditTitle"><i class="bi bi-pencil-square"></i> Edit Livestock</h3><button class="modal-close" onclick="closeModal('lvEditModal')">✕</button></div>
    <form id="lvEditForm">
      <input type="hidden" id="lvEditId" name="livestock_id">
      <div class="modal-body">
        <div class="form-group"><label class="form-label">Tag ID *</label><input type="text" class="form-input" id="lvEditTag" name="tagId" required></div>
        <div class="form-group"><label class="form-label">Type *</label>
          <select class="form-select" id="lvEditType" name="type" required>
            <?php foreach($allAnimalTypes as $at): ?><option><?= $at ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label class="form-label">Breed</label><input type="text" class="form-input" id="lvEditBreed" name="breed" placeholder="e.g. Landrace, Holstein, Native"></div>
        <div class="form-group"><label class="form-label">Quantity *</label><input type="number" class="form-input" id="lvEditQty" name="qty" min="1" required></div>
        <div class="form-group"><label class="form-label">Age (years)</label><input type="number" class="form-input" id="lvEditAge" name="age" min="0" step="0.1"></div>
        <div class="form-group"><label class="form-label">Weight (kg)</label><input type="number" class="form-input" id="lvEditWeight" name="weight" step="0.1"></div>
        <div class="form-group"><label class="form-label">Health Status</label>
          <select class="form-select" id="lvEditHealth" name="healthStatus">
            <option value="Healthy">Healthy</option>
            <option value="Sick">Sick</option>
            <option value="Recovered">Recovered</option>
            <option value="Deceased">Deceased</option>
          </select>
        </div>
        <div class="form-group"><label class="form-label">Vaccination</label>
          <select class="form-select" id="lvEditVacc" name="vaccinationStatus">
            <option value="None">None</option>
            <option value="Partial">Partial</option>
            <option value="Complete">Complete</option>
          </select>
        </div>
        <div class="form-group"><label class="form-label">Medical Notes / Treatment History</label>
          <textarea class="form-input" id="lvEditNotes" name="notes" rows="2" placeholder="e.g. Dewormed, medications, observation notes..."></textarea>
        </div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('lvEditModal')">Cancel</button><button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Save Changes</button></div>
    </form>
  </div>
</div>

<div id="lvAddModal" class="modal-wrap">
  <div class="modal-box">
    <div class="modal-hdr"><h3><i class="bi bi-plus-circle"></i> Add Livestock</h3><button class="modal-close" onclick="closeModal('lvAddModal')">✕</button></div>
    <form id="lvAddForm">
      <div class="modal-body">
        <div class="form-group"><label class="form-label">Farm *</label>
          <select class="form-select" name="farm_id" id="lvAddFarm" required>
            <option value="">Select farm...</option>
            <?php foreach($farmsData as $f): ?>
            <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['name']) ?> (<?= $f['type'] ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label class="form-label">Type *</label>
          <select class="form-select" id="lvAddType" name="type" required onchange="autoFillTagId()">
            <option value="">Select...</option>
            <?php foreach($allAnimalTypes as $at): ?><option><?= $at ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label class="form-label">Tag ID * <span style="font-size:.7rem;color:var(--sub);font-weight:400;">(auto-filled, editable)</span></label>
          <div style="display:flex;gap:6px;">
            <input type="text" class="form-input" id="lvAddTagId" name="tagId" required placeholder="Select animal type or click generate">
            <button type="button" class="btn btn-secondary" style="padding:0.45rem 0.8rem;font-size:0.78rem;flex-shrink:0;" onclick="generateRandomTagId()" title="Generate Random Tag ID">
              <i class="bi bi-arrow-clockwise"></i> Gen
            </button>
          </div>
        </div>
        <div class="form-group"><label class="form-label">Breed</label><input type="text" class="form-input" name="breed" placeholder="e.g. Landrace, Holstein, Native"></div>
        <div class="form-group"><label class="form-label">Quantity *</label><input type="number" class="form-input" name="qty" value="1" min="1" required></div>
        <div class="form-group"><label class="form-label">Age (years)</label><input type="number" class="form-input" name="age" min="0" step="0.1" placeholder="e.g. 1.5"></div>
        <div class="form-group"><label class="form-label">Weight (kg)</label><input type="number" class="form-input" name="weight" step="0.1" placeholder="e.g. 75.0"></div>
        <div class="form-group"><label class="form-label">Health Status</label>
          <select class="form-select" name="healthStatus">
            <option value="Healthy" selected>Healthy</option>
            <option value="Sick">Sick</option>
            <option value="Recovered">Recovered</option>
            <option value="Deceased">Deceased</option>
          </select>
        </div>
        <div class="form-group"><label class="form-label">Vaccination Status</label>
          <select class="form-select" name="vaccinationStatus">
            <option value="None" selected>None</option>
            <option value="Partial">Partial</option>
            <option value="Complete">Complete</option>
          </select>
        </div>
        <div class="form-group"><label class="form-label">Medical Notes / Remarks</label>
          <textarea class="form-input" name="notes" rows="2" placeholder="e.g. Dewormed, booster schedule, vitamins..."></textarea>
        </div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('lvAddModal')">Cancel</button><button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Add Livestock</button></div>
    </form>
  </div>
</div>

<div class="cam-modal" id="camModal">
  <div class="cam-box">
    <div class="cam-hdr-bar">
      <span style="font-weight:700;font-size:.9rem;" id="camTitle">Capture Photo + GPS</span>
      <button style="background:none;border:none;color:#aaa;font-size:1.4rem;cursor:pointer;" onclick="closeCam()">×</button>
    </div>
    <video id="camVideo" class="cam-video" autoplay playsinline></video>
    <div class="cam-ctrl-bar">
      <button class="cam-btn flip" onclick="switchCam()"><i class="bi bi-arrow-repeat" style="color:#fff;font-size:1.2rem;"></i></button>
      <button class="cam-btn capture" onclick="capturePhoto()"><i class="bi bi-camera-fill"></i></button>
      <button class="cam-btn close-cam" onclick="closeCam()"><i class="bi bi-x-lg" style="color:#ccc;font-size:1.2rem;"></i></button>
    </div>
  </div>
</div>

<div id="toast" class="toast"></div>

<script>
const CURRENT_USER_ID = <?= json_encode((int)$userId) ?>;
const CURRENT_NAME    = <?= json_encode(trim($firstName.' '.$lastName)) ?>;

let mediaStream=null, camFacing='environment', camMode='farm', currentGPS=null;

// ── NAVIGATION ─────────────────────────────────────────────────────────────────
function navTo(page){
  document.querySelectorAll('.page').forEach(p=>p.classList.remove('active'));
  document.querySelectorAll('.drawer-item[data-page]').forEach(t=>t.classList.remove('active'));
  const pg=document.getElementById('pg-'+page);
  if(pg) pg.classList.add('active');
  document.querySelector('.drawer-item[data-page="'+page+'"]')?.classList.add('active');
  const titles={dashboard:'Dashboard','farm-reg':'Farm Registration',livestock:'Livestock',incident:'Incident Report',notifications:'Notifications',chat:'Farmer Chat',ecert:'E-Certificate',profile:'My Profile'};
  document.getElementById('pageTitle').textContent=titles[page]||'AgriTrace+';
  document.getElementById('contentArea').scrollTop=0;
  closeDrawer();
  if(page==='chat'){loadFarmerList();pollUnread();}
}
document.querySelectorAll('.drawer-item[data-page]').forEach(t=>t.addEventListener('click',()=>navTo(t.dataset.page)));

function openDrawer(){
  const d = document.getElementById('drawer');
  if (window.innerWidth >= 1024 && document.body.classList.contains('sidebar-autohide')) {
    d.classList.toggle('hover-open');
  } else {
    d.classList.add('open');
    document.getElementById('drawerOverlay').classList.add('show');
  }
}
function closeDrawer(){
  const d = document.getElementById('drawer');
  d.classList.remove('open', 'hover-open');
  document.getElementById('drawerOverlay').classList.remove('show');
}

// ── AUTO-HIDE & FULL VIEW CONTROLLER ──────────────────────────────────────────
const farmerDrawer = document.getElementById('drawer');
const farmerHoverZone = document.getElementById('farmerHoverZone');
const farmerEdgeTrigger = document.getElementById('farmerEdgeTrigger');
const farmerPinBtn = document.getElementById('farmerPinBtn');

let isFarmerAutoHide = localStorage.getItem('agritrace_farmer_autohide') !== 'false';

function applyFarmerAutoHide(enabled) {
  isFarmerAutoHide = enabled;
  localStorage.setItem('agritrace_farmer_autohide', enabled ? 'true' : 'false');
  if (enabled) {
    document.body.classList.add('sidebar-autohide');
    if (farmerPinBtn) farmerPinBtn.innerHTML = '<i class="bi bi-pin-angle" title="Click to Pin Sidebar Open"></i>';
  } else {
    document.body.classList.remove('sidebar-autohide');
    farmerDrawer?.classList.remove('hover-open');
    if (farmerPinBtn) farmerPinBtn.innerHTML = '<i class="bi bi-pin-fill" title="Click to Auto-Hide for Full View"></i>';
  }
}
applyFarmerAutoHide(isFarmerAutoHide);

farmerPinBtn?.addEventListener('click', (e) => {
  e.stopPropagation();
  applyFarmerAutoHide(!isFarmerAutoHide);
});

let farmerHoverTimer = null;
function openFarmerHover() {
  if (!document.body.classList.contains('sidebar-autohide') || window.innerWidth < 1024) return;
  clearTimeout(farmerHoverTimer);
  farmerDrawer?.classList.add('hover-open');
}
function closeFarmerHover() {
  if (!document.body.classList.contains('sidebar-autohide') || window.innerWidth < 1024) return;
  clearTimeout(farmerHoverTimer);
  farmerHoverTimer = setTimeout(() => {
    farmerDrawer?.classList.remove('hover-open');
  }, 300);
}

farmerHoverZone?.addEventListener('mouseenter', openFarmerHover);
farmerEdgeTrigger?.addEventListener('mouseenter', openFarmerHover);
farmerEdgeTrigger?.addEventListener('click', (e) => {
  e.stopPropagation();
  farmerDrawer?.classList.toggle('hover-open');
});
farmerDrawer?.addEventListener('mouseenter', () => clearTimeout(farmerHoverTimer));
farmerDrawer?.addEventListener('mouseleave', closeFarmerHover);

document.addEventListener('mousemove', (e) => {
  if (!document.body.classList.contains('sidebar-autohide') || window.innerWidth < 1024) return;
  if (e.clientX <= 14) openFarmerHover();
});
function openModal(id){document.getElementById(id).classList.add('open');document.body.style.overflow='hidden';}
function closeModal(id){document.getElementById(id).classList.remove('open');document.body.style.overflow='';}
function showToast(msg,type='success'){const t=document.getElementById('toast');t.textContent=msg;t.className='toast toast-'+type+' show';clearTimeout(t._t);t._t=setTimeout(()=>t.classList.remove('show'),3200);}

// ── GPS ─────────────────────────────────────────────────────────────────────────
function getGPS(){
  return new Promise(resolve=>{
    if(!navigator.geolocation){resolve({lat:13.6234,lng:123.1945,acc:999});return;}
    navigator.geolocation.getCurrentPosition(
      pos=>{currentGPS={lat:pos.coords.latitude,lng:pos.coords.longitude,acc:Math.round(pos.coords.accuracy)};resolve(currentGPS);},
      ()=>resolve({lat:13.6234,lng:123.1945,acc:999}),
      {enableHighAccuracy:true,timeout:15000,maximumAge:0}
    );
  });
}
async function reverseGeocode(lat,lng){
  try{const r=await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=18&addressdetails=1&accept-language=en`);const d=await r.json();return d.display_name||`${lat.toFixed(6)}, ${lng.toFixed(6)}`;}catch(e){return `${lat.toFixed(6)}, ${lng.toFixed(6)}`;}
}
async function stampGPS(canvas,gps){
  const ctx=canvas.getContext('2d');
  const ts=new Date().toLocaleString('en-PH',{timeZone:'Asia/Manila',year:'numeric',month:'short',day:'numeric',hour:'2-digit',minute:'2-digit'});
  const h=Math.floor(canvas.height*.18);const fs=Math.max(14,Math.floor(canvas.width/40));
  ctx.fillStyle='rgba(0,0,0,0.82)';ctx.fillRect(0,canvas.height-h,canvas.width,h);
  ctx.fillStyle='#fff';ctx.font=`bold ${fs}px sans-serif`;ctx.shadowColor='rgba(0,0,0,.7)';ctx.shadowBlur=3;
  ctx.fillText(`📍 ${gps.lat.toFixed(6)}, ${gps.lng.toFixed(6)} (±${gps.acc}m)`,16,canvas.height-h+fs+8);
  ctx.fillText(`⏰ ${ts}`,16,canvas.height-h+fs*2+14);
  ctx.font=`${Math.floor(fs*.85)}px sans-serif`;ctx.fillText('AgriTrace+ | Camarines Sur DA',16,canvas.height-h+fs*3+18);ctx.shadowBlur=0;
}

// ── CAMERA ──────────────────────────────────────────────────────────────────────
async function startCam(){
  stopCam();
  try{
    mediaStream=await navigator.mediaDevices.getUserMedia({video:{facingMode:camFacing,width:{ideal:1280},height:{ideal:720}},audio:false});
    document.getElementById('camVideo').srcObject=mediaStream;
    document.getElementById('camModal').classList.add('open');
    getGPS();
  }catch(e){
    let msg='Camera access denied.';
    if(e.name==='NotAllowedError') msg='Camera permission denied. Please allow camera access in browser settings.';
    else if(e.name==='NotFoundError') msg='No camera found on this device.';
    else if(e.name==='NotReadableError') msg='Camera is in use by another app.';
    showToast(msg,'error');
  }
}
function stopCam(){if(mediaStream){mediaStream.getTracks().forEach(t=>t.stop());mediaStream=null;}}
function switchCam(){camFacing=camFacing==='environment'?'user':'environment';startCam();}
function closeCam(){stopCam();document.getElementById('camModal').classList.remove('open');}
function openFarmCam(){camMode='farm';document.getElementById('camTitle').textContent='Capture Farm Photo + GPS';startCam();}
function openIncCam(){camMode='incident';document.getElementById('camTitle').textContent='Capture Incident Photo + GPS';startCam();}

async function capturePhoto(){
  const video=document.getElementById('camVideo');
  const canvas=document.createElement('canvas');canvas.width=video.videoWidth||640;canvas.height=video.videoHeight||480;
  canvas.getContext('2d').drawImage(video,0,0);
  const gps=currentGPS||await getGPS();
  await stampGPS(canvas,gps);
  if(camMode==='farm') await applyFarmPhoto(canvas,gps);
  else await applyIncPhoto(canvas,gps);
  closeCam();
}
async function applyFarmPhoto(canvas,gps){
  const dest=document.getElementById('farmStampCanvas');dest.width=640;dest.height=Math.round(canvas.height*(640/canvas.width));
  dest.getContext('2d').drawImage(canvas,0,0,640,dest.height);
  document.getElementById('farmPhotoPreview').classList.add('show');
  document.getElementById('fPhoto').value=canvas.toDataURL('image/jpeg',.9);
  document.getElementById('fLat').value=gps.lat;document.getElementById('fLng').value=gps.lng;
  const badge=document.getElementById('farmGpsBadge');badge.classList.add('show');
  document.getElementById('farmGpsText').textContent=`${gps.lat.toFixed(6)}, ${gps.lng.toFixed(6)} (±${gps.acc}m)`;
  showToast('📍 Photo & GPS captured!','success');
  try{const addr=await reverseGeocode(gps.lat,gps.lng);const f=document.getElementById('farmAddress');if(f&&!f.value){f.value=addr;}}catch(e){}
}
async function applyIncPhoto(canvas,gps){
  document.getElementById('incPhoto').value=canvas.toDataURL('image/jpeg',.9);
  document.getElementById('incLat').value=gps.lat;document.getElementById('incLng').value=gps.lng;
  const b=document.getElementById('incGpsBadge');b.style.display='flex';
  document.getElementById('incGpsText').textContent=`${gps.lat.toFixed(5)}, ${gps.lng.toFixed(5)} (±${gps.acc}m)`;
  showToast('📍 Incident photo captured!','success');
}
async function handleFarmUpload(e){const file=e.target.files[0];if(!file)return;const img=new Image();img.onload=async()=>{const canvas=document.createElement('canvas');canvas.width=img.width;canvas.height=img.height;canvas.getContext('2d').drawImage(img,0,0);const gps=await getGPS();await stampGPS(canvas,gps);await applyFarmPhoto(canvas,gps);};img.src=URL.createObjectURL(file);e.target.value='';}
async function handleIncUpload(e){const file=e.target.files[0];if(!file)return;const img=new Image();img.onload=async()=>{const canvas=document.createElement('canvas');canvas.width=img.width;canvas.height=img.height;canvas.getContext('2d').drawImage(img,0,0);const gps=await getGPS();await stampGPS(canvas,gps);await applyIncPhoto(canvas,gps);};img.src=URL.createObjectURL(file);e.target.value='';}
function removeFarmPhoto(){document.getElementById('farmPhotoPreview').classList.remove('show');document.getElementById('farmGpsBadge').classList.remove('show');document.getElementById('fPhoto').value='';document.getElementById('fLat').value='';document.getElementById('fLng').value='';}

// ── FARM REG HELPERS ──────────────────────────────────────────────────────────
function setAppType(t){document.getElementById('btnNew').classList.toggle('active',t==='new');document.getElementById('btnRenew').classList.toggle('active',t==='renewal');document.getElementById('appTypeVal').value=t==='new'?'New':'Renewal';}
function setOwnership(t){document.getElementById('btnGovt').classList.toggle('active',t==='government');document.getElementById('btnPriv').classList.toggle('active',t==='private');document.getElementById('ownershipVal').value=t==='government'?'Government':'Private';}
function toggleBiz(btn){document.querySelectorAll('#bizRow .biz-pill').forEach(b=>b.classList.remove('active'));btn.classList.add('active');document.getElementById('bizTypeVal').value=btn.textContent.trim();}
function syncFarmCb(label){const cb=label.querySelector('input[type="checkbox"]');if(cb){cb.checked=!cb.checked;}label.classList.toggle('checked',label.querySelector('input')?.checked);}

// ── DYNAMIC LIVESTOCK ROWS ────────────────────────────────────────────────────
const addedAnimalTypes = new Set();
const animalEmojis = {Cattle:'🐄',Swine:'🐖',Poultry:'🐓',Goat:'🐐',Carabao:'🐃',Horse:'🐴',Sheep:'🐑',Rabbit:'🐇'};

function toggleAnimalPicker(){
  const wrap=document.getElementById('animalPickerWrap');
  const btn=document.getElementById('btnAddAnimal');
  const isOpen=wrap.classList.contains('show');
  wrap.classList.toggle('show',!isOpen);
  btn.style.display=isOpen?'flex':'none';
  // Update picker: disable already-added types
  document.querySelectorAll('.animal-pick-btn').forEach(b=>{
    const t=b.dataset.type;
    b.disabled=addedAnimalTypes.has(t);
    b.style.opacity=addedAnimalTypes.has(t)?'.35':'1';
    b.style.cursor=addedAnimalTypes.has(t)?'not-allowed':'pointer';
  });
}

function addAnimalRow(type){
  if(addedAnimalTypes.has(type)){showToast(type+' already added','warn');return;}
  addedAnimalTypes.add(type);
  const key=type.toLowerCase();
  const emoji=animalEmojis[type]||'🐾';
  const tagDefault=`TAG-${type.substring(0,3).toUpperCase()}-${String(Date.now()).slice(-4)}`;
  const row=document.createElement('div');
  row.className='lv-dynamic-row';
  row.dataset.type=type;
  row.innerHTML=`
    <button type="button" class="remove-lv-row" onclick="removeAnimalRow(this,'${type}')" title="Remove"><i class="bi bi-x-lg"></i></button>
    <div class="lv-row-top">
      <span class="lv-animal-emoji">${emoji}</span>
      <span class="lv-row-type-label">${type}</span>
      <input type="hidden" name="lv_type_${key}" value="${type}">
    </div>
    <div class="lv-row-fields">
      <div>
        <label style="font-size:.72rem;font-weight:700;color:var(--sub);text-transform:uppercase;letter-spacing:.03em;display:block;margin-bottom:.3rem;">No. of Heads *</label>
        <input type="number" name="lv_qty_${key}" value="1" min="1" max="99999" style="width:100%;">
      </div>
      <div>
        <label style="font-size:.72rem;font-weight:700;color:var(--sub);text-transform:uppercase;letter-spacing:.03em;display:block;margin-bottom:.3rem;">Tag ID</label>
        <input type="text" name="lv_tag_${key}" value="${tagDefault}" style="width:100%;">
      </div>
      <div class="field-full">
        <label style="font-size:.72rem;font-weight:700;color:var(--sub);text-transform:uppercase;letter-spacing:.03em;display:block;margin-bottom:.3rem;">Breed (optional)</label>
        <input type="text" name="lv_breed_${key}" placeholder="e.g. Holstein, Landrace…" style="width:100%;">
      </div>
    </div>`;
  document.getElementById('lvDynamicList').appendChild(row);
  // Hide picker, show button
  document.getElementById('animalPickerWrap').classList.remove('show');
  document.getElementById('btnAddAnimal').style.display='flex';
}

function removeAnimalRow(btn,type){
  const row=btn.closest('.lv-dynamic-row');
  row.style.animation='none';row.style.opacity='0';row.style.transform='translateY(-6px)';
  setTimeout(()=>{row.remove();addedAnimalTypes.delete(type);},200);
}

// ── AUTO-FILL & GENERATE TAG ID ──────────────────────────────────────────────
function autoFillTagId(){
  const type=document.getElementById('lvAddType').value;
  if(!type){document.getElementById('lvAddTagId').value='';return;}
  const prefix=type.substring(0,3).toUpperCase();
  const ts=String(Date.now()).slice(-4);
  document.getElementById('lvAddTagId').value=`TAG-${prefix}-${ts}`;
}
function generateRandomTagId(){
  const typeEl=document.getElementById('lvAddType');
  const type=(typeEl && typeEl.value) ? typeEl.value : 'LV';
  const prefix=type.substring(0,3).toUpperCase();
  const rand=Math.floor(1000 + Math.random()*9000);
  document.getElementById('lvAddTagId').value=`TAG-${prefix}-${rand}`;
}

// ── INCIDENT HELPERS ──────────────────────────────────────────────────────────
function updateDiseaseTags(val){document.getElementById('diseaseTags').style.display=val==='Disease'?'flex':'none';}
function toggleDTag(el,d){el.classList.toggle('active');const f=document.querySelector('[name="title"]');if(el.classList.contains('active')&&f&&!f.value.includes(d))f.value=d+(f.value?' - '+f.value:'');}
function selectSeverity(card,val){document.querySelectorAll('.severity-card').forEach(c=>c.classList.remove('active'));card.classList.add('active');document.getElementById('incSeverityVal').value=val;}
function resetIncForm(){document.getElementById('incidentForm').reset();selectSeverity(document.querySelector('.severity-card'),'Low');document.getElementById('diseaseTags').style.display='none';document.getElementById('incGpsBadge').style.display='none';document.getElementById('incPhoto').value='';document.getElementById('incLat').value='';document.getElementById('incLng').value='';}

// ── MODERN LIVESTOCK APP CONTROLLER ───────────────────────────────────────────
let currentSpeciesFilter = 'all';
let currentLvView = 'grid';

function setLvView(mode){
  currentLvView = mode;
  document.querySelectorAll('.lv-view-btn').forEach(b => b.classList.remove('active'));
  const btn = document.getElementById('lvBtn' + mode.charAt(0).toUpperCase() + mode.slice(1));
  if(btn) btn.classList.add('active');
  
  const gView = document.getElementById('lvGridView');
  const tView = document.getElementById('lvTableView');
  const fView = document.getElementById('lvFarmView');
  
  if(gView) gView.style.display = mode === 'grid' ? 'grid' : 'none';
  if(tView) tView.style.display = mode === 'table' ? 'block' : 'none';
  if(fView) fView.style.display = mode === 'farm' ? 'block' : 'none';
  
  filterLivestock();
}

function filterBySpecies(species, btn){
  currentSpeciesFilter = (species || 'all').toLowerCase();
  document.querySelectorAll('#lvSpeciesRibbon .lv-chip-btn').forEach(b => b.classList.remove('active'));
  if(btn) btn.classList.add('active');
  filterLivestock();
}

function clearLvSearch(){
  const inp = document.getElementById('lvSearchInput');
  if(inp) inp.value = '';
  const clr = document.getElementById('lvSearchClear');
  if(clr) clr.style.display = 'none';
  filterLivestock();
}

function resetLvFilters(){
  const inp = document.getElementById('lvSearchInput');
  if(inp) inp.value = '';
  const clr = document.getElementById('lvSearchClear');
  if(clr) clr.style.display = 'none';
  const farmSel = document.getElementById('lvFarmFilter');
  if(farmSel) farmSel.value = 'all';
  const healthSel = document.getElementById('lvHealthFilter');
  if(healthSel) healthSel.value = 'all';
  const allChip = document.querySelector('#lvSpeciesRibbon .lv-chip-btn[data-species="all"]');
  filterBySpecies('all', allChip);
}

function filterLivestock(){
  const searchInput = document.getElementById('lvSearchInput');
  const query = searchInput ? searchInput.value.toLowerCase().trim() : '';
  const clearBtn = document.getElementById('lvSearchClear');
  if(clearBtn) clearBtn.style.display = query.length > 0 ? 'block' : 'none';
  
  const farmFilter = document.getElementById('lvFarmFilter') ? document.getElementById('lvFarmFilter').value : 'all';
  const healthFilter = document.getElementById('lvHealthFilter') ? document.getElementById('lvHealthFilter').value : 'all';
  
  let visibleCount = 0;
  
  // 1. Grid Cards Filter
  const cards = document.querySelectorAll('#lvGridView .lv-card');
  cards.forEach(card => {
    const tag = (card.dataset.tag || '').toLowerCase();
    const species = (card.dataset.species || '').toLowerCase();
    const breed = (card.dataset.breed || '').toLowerCase();
    const farmId = card.dataset.farmId || '';
    const farmName = (card.dataset.farmName || '').toLowerCase();
    const health = card.dataset.health || '';
    const notes = (card.dataset.notes || '').toLowerCase();
    
    const matchesSearch = !query || tag.includes(query) || species.includes(query) || breed.includes(query) || farmName.includes(query) || notes.includes(query);
    const matchesSpecies = currentSpeciesFilter === 'all' || species === currentSpeciesFilter;
    const matchesFarm = farmFilter === 'all' || farmId === farmFilter;
    const matchesHealth = healthFilter === 'all' || health === healthFilter;
    
    const show = matchesSearch && matchesSpecies && matchesFarm && matchesHealth;
    card.style.display = show ? 'flex' : 'none';
    if(show) visibleCount++;
  });
  
  // 2. Table Rows Filter
  const rows = document.querySelectorAll('#lvTableView tbody tr.lv-tbl-row');
  rows.forEach(tr => {
    const tag = (tr.dataset.tag || '').toLowerCase();
    const species = (tr.dataset.species || '').toLowerCase();
    const breed = (tr.dataset.breed || '').toLowerCase();
    const farmId = tr.dataset.farmId || '';
    const farmName = (tr.dataset.farmName || '').toLowerCase();
    const health = tr.dataset.health || '';
    const notes = (tr.dataset.notes || '').toLowerCase();
    
    const matchesSearch = !query || tag.includes(query) || species.includes(query) || breed.includes(query) || farmName.includes(query) || notes.includes(query);
    const matchesSpecies = currentSpeciesFilter === 'all' || species === currentSpeciesFilter;
    const matchesFarm = farmFilter === 'all' || farmId === farmFilter;
    const matchesHealth = healthFilter === 'all' || health === healthFilter;
    
    tr.style.display = (matchesSearch && matchesSpecies && matchesFarm && matchesHealth) ? '' : 'none';
  });

  // 3. Farm Grouped View Filter
  const farmGroups = document.querySelectorAll('#lvFarmView .lv-farm-card');
  farmGroups.forEach(group => {
    const gFarmId = group.dataset.farmId || '';
    const matchesFarm = farmFilter === 'all' || gFarmId === farmFilter;
    let groupVisibleItems = 0;
    
    const gCards = group.querySelectorAll('.lv-card');
    gCards.forEach(c => {
      const tag = (c.dataset.tag || '').toLowerCase();
      const species = (c.dataset.species || '').toLowerCase();
      const breed = (c.dataset.breed || '').toLowerCase();
      const health = c.dataset.health || '';
      const notes = (c.dataset.notes || '').toLowerCase();
      
      const matchesSearch = !query || tag.includes(query) || species.includes(query) || breed.includes(query) || notes.includes(query);
      const matchesSpecies = currentSpeciesFilter === 'all' || species === currentSpeciesFilter;
      const matchesHealth = healthFilter === 'all' || health === healthFilter;
      
      const show = matchesSearch && matchesSpecies && matchesHealth;
      c.style.display = show ? 'flex' : 'none';
      if(show) groupVisibleItems++;
    });
    
    group.style.display = (matchesFarm && (groupVisibleItems > 0 || gCards.length === 0)) ? 'block' : 'none';
  });
  
  // 4. No Results Alert
  const noRes = document.getElementById('lvNoResults');
  if(noRes) {
    noRes.style.display = visibleCount === 0 ? 'block' : 'none';
  }
}

function copyTagId(tagId, el){
  if(navigator.clipboard && navigator.clipboard.writeText){
    navigator.clipboard.writeText(tagId).then(()=>{
      showToast(`Copied Tag ID: ${tagId}`, 'success');
      if(el){
        const orig = el.innerHTML;
        el.innerHTML = '<i class="bi bi-check2"></i> Copied!';
        setTimeout(()=>{ el.innerHTML = orig; }, 1800);
      }
    }).catch(()=>{ fallbackCopy(tagId); });
  } else {
    fallbackCopy(tagId);
  }
}
function fallbackCopy(text){
  const ta = document.createElement('textarea');
  ta.value = text;
  document.body.appendChild(ta);
  ta.select();
  document.execCommand('copy');
  document.body.removeChild(ta);
  showToast(`Copied Tag ID: ${text}`, 'success');
}

function reportIssueForAnimal(farmId, type, tagId, breed){
  navTo('incident');
  const farmSel = document.querySelector('#incidentForm select[name="farm_id"]');
  if(farmSel) farmSel.value = farmId;
  const incTypeSel = document.querySelector('#incidentForm select[name="incident_type"]');
  if(incTypeSel) {
    incTypeSel.value = 'Sick';
    if(typeof updateDiseaseTags === 'function') updateDiseaseTags('Sick');
  }
  const lvTypeSel = document.querySelector('#incidentForm select[name="livestock_type"]');
  if(lvTypeSel) lvTypeSel.value = type;
  const titleInput = document.querySelector('#incidentForm input[name="title"]');
  if(titleInput) titleInput.value = `Health issue reported for ${type} (${tagId}${breed ? ' - ' + breed : ''})`;
  const sevCard = document.querySelectorAll('.severity-card');
  if(sevCard && sevCard[1]) selectSeverity(sevCard[1], 'Medium');
  showToast(`Incident draft started for ${tagId}`, 'info');
}

function exportLivestockCSV(){
  const cards = document.querySelectorAll('#lvGridView .lv-card');
  if(!cards.length){
    showToast('No livestock records to export', 'warn');
    return;
  }
  const headers = ['Tag ID', 'Species', 'Breed', 'Farm', 'Quantity', 'Age (yrs)', 'Weight (kg)', 'Health Status', 'Notes'];
  const rows = [];
  cards.forEach(c => {
    const tag = c.dataset.tagRaw || c.dataset.tag || '';
    const species = c.dataset.speciesRaw || c.dataset.species || '';
    const breed = c.dataset.breedRaw || c.dataset.breed || '';
    const farm = c.dataset.farmNameRaw || c.dataset.farmName || '';
    const qty = c.dataset.qty || '1';
    const age = c.dataset.age || '';
    const weight = c.dataset.weight || '';
    const health = c.dataset.health || '';
    const notes = c.dataset.notesRaw || c.dataset.notes || '';
    rows.push([
      `"${tag.replace(/"/g, '""')}"`,
      `"${species.replace(/"/g, '""')}"`,
      `"${breed.replace(/"/g, '""')}"`,
      `"${farm.replace(/"/g, '""')}"`,
      qty,
      `"${age}"`,
      `"${weight}"`,
      `"${health}"`,
      `"${notes.replace(/"/g, '""')}"`
    ]);
  });
  const csvContent = 'data:text/csv;charset=utf-8,\uFEFF' + [headers.join(','), ...rows.map(r=>r.join(','))].join('\n');
  const encodedUri = encodeURI(csvContent);
  const link = document.createElement('a');
  link.setAttribute('href', encodedUri);
  link.setAttribute('download', `AgriTrace_Livestock_${new Date().toISOString().slice(0,10)}.csv`);
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  showToast('Livestock CSV exported successfully!', 'success');
}

// ── LIVESTOCK MODALS ──────────────────────────────────────────────────────────
function openEditLv(id,tagId,type,breed,qty,age,weight,health,vacc,notes){
  document.getElementById('lvEditTitle').innerHTML='<i class="bi bi-pencil-square"></i> '+tagId;
  document.getElementById('lvEditId').value=id;document.getElementById('lvEditTag').value=tagId;
  document.getElementById('lvEditType').value=type;document.getElementById('lvEditBreed').value=breed||'';
  document.getElementById('lvEditQty').value=qty;document.getElementById('lvEditAge').value=age||'';
  document.getElementById('lvEditWeight').value=weight||'';document.getElementById('lvEditHealth').value=health;
  document.getElementById('lvEditVacc').value=vacc;
  const noteEl = document.getElementById('lvEditNotes');
  if(noteEl) noteEl.value = notes || '';
  openModal('lvEditModal');
}
function openAddLvForFarm(farmId){document.getElementById('lvAddForm').reset();document.getElementById('lvAddFarm').value=farmId;document.getElementById('lvAddTagId').value='';openModal('lvAddModal');}
function deleteLv(id,tagId){
  if(!confirm(`Delete livestock ${tagId}? This cannot be undone.`))return;
  const fd=new FormData();fd.append('action','delete_livestock');fd.append('livestock_id',id);
  fetch('',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
    if(d.success){showToast(tagId+' deleted','success');setTimeout(()=>location.reload(),900);}
    else showToast(d.message||'Delete failed','error');
  }).catch(()=>showToast('Network error','error'));
}

// ── FORM SUBMISSIONS ──────────────────────────────────────────────────────────
document.getElementById('farmForm').addEventListener('submit',async function(e){
  e.preventDefault();
  const btn=document.getElementById('farmSubmitBtn');btn.disabled=true;btn.innerHTML='<i class="bi bi-hourglass-split"></i> Submitting…';
  try{
    const fd=new FormData(this);
    const res=await fetch('',{method:'POST',body:fd});const d=await res.json();
    if(d.success){showToast(d.message||'Farm registered!','success');setTimeout(()=>location.reload(),1400);}
    else showToast(d.message||'Registration failed','error');
  }catch(err){showToast('Network error. Please try again.','error');}
  finally{btn.disabled=false;btn.innerHTML='<i class="bi bi-send-fill"></i> Submit &amp; Get Certificate';}
});

document.getElementById('incidentForm').addEventListener('submit',async function(e){
  e.preventDefault();
  const btn=document.getElementById('incSubmitBtn');btn.disabled=true;btn.innerHTML='<i class="bi bi-hourglass-split"></i> Sending…';
  try{
    const fd=new FormData(this);
    const res=await fetch('',{method:'POST',body:fd});const d=await res.json();
    if(d.success){showToast(`Alert sent! Priority: ${d.priority}. Notified: ${d.notified}`,'success');resetIncForm();}
    else showToast(d.message||'Failed','error');
  }catch(err){showToast('Network error','error');}
  finally{btn.disabled=false;btn.innerHTML='<i class="bi bi-send-check"></i> Send Alert';}
});

document.getElementById('lvEditForm').addEventListener('submit',async function(e){
  e.preventDefault();
  const fd=new FormData(this);fd.append('action','update_livestock');
  const res=await fetch('',{method:'POST',body:fd});const d=await res.json();
  if(d.success){showToast('Livestock updated!','success');closeModal('lvEditModal');setTimeout(()=>location.reload(),900);}
  else showToast(d.message||'Update failed','error');
});

document.getElementById('lvAddForm').addEventListener('submit',async function(e){
  e.preventDefault();
  const fd=new FormData(this);fd.append('action','add_livestock');
  const res=await fetch('',{method:'POST',body:fd});const d=await res.json();
  if(d.success){showToast('Livestock added!','success');closeModal('lvAddModal');setTimeout(()=>location.reload(),900);}
  else showToast(d.message||'Failed','error');
});

// ── FARMER PROFILE CONTROLLER ─────────────────────────────────────────────────
function switchProfileTab(tabName){
  document.querySelectorAll('#pfTabsNav .pf-tab-btn').forEach(btn => {
    btn.classList.toggle('active', btn.dataset.tab === tabName);
  });
  const tabIds = {
    details: 'pfTabDetails',
    farms: 'pfTabFarms',
    security: 'pfTabSecurity',
    activity: 'pfTabActivity'
  };
  Object.keys(tabIds).forEach(k => {
    const el = document.getElementById(tabIds[k]);
    if(el) el.classList.toggle('active', k === tabName);
  });
}

function previewProfilePhoto(input){
  if(input.files && input.files[0]){
    const file = input.files[0];
    if(file.size > 5 * 1024 * 1024){
      showToast('Photo is too large (max 5MB)', 'warn');
      input.value = '';
      return;
    }
    const reader = new FileReader();
    reader.onload = function(e){
      const preview = document.getElementById('pfEditLiveAvatar');
      if(preview){
        preview.innerHTML = `<img src="${e.target.result}" alt="Preview" style="width:100%;height:100%;object-fit:cover;">`;
      }
    };
    reader.readAsDataURL(file);
  }
}

const farmerPwForm = document.getElementById('farmerPwForm');
if(farmerPwForm){
  farmerPwForm.addEventListener('submit', async function(e){
    e.preventDefault();
    const btn = document.getElementById('btnUpdatePw');
    if(btn){ btn.disabled = true; btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Updating...'; }
    try{
      const fd = new FormData(this);
      const res = await fetch('', { method: 'POST', body: fd });
      const d = await res.json();
      if(d.success){
        showToast(d.message || 'Password changed successfully!', 'success');
        this.reset();
        closeModal('farmerPasswordModal');
      } else {
        showToast(d.message || 'Password change failed', 'error');
      }
    } catch(err){
      showToast('Network error while changing password', 'error');
    } finally {
      if(btn){ btn.disabled = false; btn.innerHTML = '<i class="bi bi-key-fill"></i> Update Password'; }
    }
  });
}

document.getElementById('profileForm').addEventListener('submit',async function(e){
  e.preventDefault();
  const btn = document.getElementById('btnSaveProfile');
  if(btn){ btn.disabled = true; btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Saving...'; }
  try {
    const fd = new FormData(this);
    const res = await fetch('', { method: 'POST', body: fd });
    const d = await res.json();
    if(d.success){
      showToast(d.message || 'Profile updated successfully!', 'success');
      closeModal('profileModal');
      setTimeout(() => location.reload(), 900);
    } else {
      showToast(d.message || 'Update failed', 'error');
    }
  } catch(err){
    showToast('Network error while saving profile', 'error');
  } finally {
    if(btn){ btn.disabled = false; btn.innerHTML = '<i class="bi bi-check2"></i> Save Changes'; }
  }
});

// ── CHAT ─────────────────────────────────────────────────────────────────────
// ── CHAT ─────────────────────────────────────────────────────────────────────
const chatState = {
  currentId: null,
  currentName: '',
  currentInit: '?',
  currentRole: '',
  lastId: 0,
  poll: null,
  farmers: [],
  currentTab: 'all',
  searchQuery: '',
  pendingMedia: null
};

async function loadFarmerList() {
  try {
    const res = await fetch('../api/chat.php?action=list_farmers');
    if (!res.ok) throw new Error('Network error');
    const d = await res.json();
    if (d.success) {
      chatState.farmers = d.farmers || [];
      const countEl = document.getElementById('chatContactsCount');
      if (countEl) countEl.textContent = `${chatState.farmers.length} contact${chatState.farmers.length === 1 ? '' : 's'}`;
      applyChatFilter();
    } else {
      throw new Error(d.message || 'Server error');
    }
  } catch (e) {
    const el = document.getElementById('farmerList');
    if (el) {
      el.innerHTML = '<div style="padding:2.5rem 1rem;text-align:center;color:var(--sub);font-size:.85rem;"><i class="bi bi-exclamation-circle" style="font-size:1.8rem;color:var(--danger);display:block;margin-bottom:.5rem;"></i>Could not load contacts.<br><button type="button" class="btn btn-secondary" style="margin-top:.75rem;padding:.4rem .9rem;font-size:.8rem;" onclick="loadFarmerList()"><i class="bi bi-arrow-clockwise"></i> Retry</button></div>';
    }
  }
}

function setChatTab(tab) {
  chatState.currentTab = tab;
  document.querySelectorAll('.chat-tab-btn').forEach(b => {
    b.classList.toggle('active', b.dataset.tab === tab);
  });
  applyChatFilter();
}

function filterFarmers(q) {
  chatState.searchQuery = (q || '').trim().toLowerCase();
  applyChatFilter();
}

function applyChatFilter() {
  let list = chatState.farmers || [];
  if (chatState.currentTab === 'official') {
    list = list.filter(f => f.role === 'Agriculture Official');
  } else if (chatState.currentTab === 'farmer') {
    list = list.filter(f => f.role === 'Farmer');
  }
  if (chatState.searchQuery) {
    const q = chatState.searchQuery;
    list = list.filter(f => {
      const full = `${f.firstName || ''} ${f.lastName || ''}`.toLowerCase();
      const pos = (f.officer_position || '').toLowerCase();
      const muni = (f.officer_muni || '').toLowerCase();
      const mob = (f.mobile || '').toLowerCase();
      return full.includes(q) || pos.includes(q) || muni.includes(q) || mob.includes(q);
    });
  }
  renderFarmerList(list);
}

function formatChatTime(raw) {
  if (!raw) return '';
  const d = new Date(raw.replace(/-/g, '/'));
  if (isNaN(d.getTime())) return '';
  const now = new Date();
  const isToday = d.toDateString() === now.toDateString();
  if (isToday) {
    return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
  }
  return d.toLocaleDateString([], { month: 'short', day: 'numeric' });
}

function renderFarmerList(farmers) {
  const list = document.getElementById('farmerList');
  if (!list) return;
  if (!farmers || !farmers.length) {
    list.innerHTML = '<div style="padding:2.5rem 1rem;text-align:center;color:var(--sub);font-size:.85rem;"><i class="bi bi-people" style="font-size:2rem;display:block;margin-bottom:.5rem;opacity:.4;"></i>No matching contacts found</div>';
    return;
  }
  list.innerHTML = farmers.map(f => {
    const isOfficial = f.role === 'Agriculture Official';
    const init = (((f.firstName || '?')[0] || '') + ((f.lastName || '')[0] || '')).toUpperCase();
    const name = `${f.firstName || ''} ${f.lastName || ''}`.trim();
    const roleBadge = isOfficial 
      ? '<span class="role-tag-badge official"><i class="bi bi-shield-check" style="margin-right:2px;"></i> Official</span>'
      : '<span class="role-tag-badge farmer">Farmer</span>';
    
    let subline = '';
    if (f.last_message) {
      subline = escHtml(f.last_message);
    } else if (isOfficial) {
      subline = escHtml(f.officer_position || 'Agriculture Official') + (f.officer_muni ? ` · ${escHtml(f.officer_muni)}` : '');
    } else {
      subline = 'Tap to message';
    }

    const timeStr = formatChatTime(f.last_msg_time);
    const unread = f.unread > 0 ? `<div class="unread-badge">${f.unread}</div>` : '';
    const safeName = name.replace(/\\/g, '\\\\').replace(/'/g, "\\'");
    const roleClass = isOfficial ? 'role-official' : 'role-farmer';
    const isActive = chatState.currentId === f.id ? 'active' : '';

    return `<div class="farmer-item ${isActive}" data-id="${f.id}" onclick="openChatWith(${f.id},'${safeName}','${init}','${f.role || ''}','${escHtml(f.officer_position || '')}')">
      <div class="farmer-av-wrap">
        <div class="farmer-avatar-sm ${roleClass}">${init}</div>
        <span class="online-dot"></span>
      </div>
      <div class="farmer-item-info">
        <div class="farmer-item-top">
          <div class="farmer-name-txt">${name}</div>
          ${timeStr ? `<div class="farmer-time-txt">${timeStr}</div>` : ''}
        </div>
        <div class="farmer-item-bottom">
          <div class="farmer-preview">${subline}</div>
          <div style="display:flex;align-items:center;gap:.35rem;">
            ${roleBadge}
            ${unread}
          </div>
        </div>
      </div>
    </div>`;
  }).join('');
}

function openChatWith(id, name, initials, role = '', position = '') {
  chatState.currentId = id;
  chatState.currentName = name;
  chatState.currentInit = initials;
  chatState.currentRole = role;
  chatState.lastId = 0;
  removePendingMedia();

  // Highlight in sidebar
  document.querySelectorAll('.farmer-item').forEach(el => {
    el.classList.toggle('active', parseInt(el.dataset.id) === id);
    if (parseInt(el.dataset.id) === id) {
      const b = el.querySelector('.unread-badge');
      if (b) b.remove();
    }
  });

  // Switch view from empty placeholder to active chat
  const noConv = document.getElementById('chatNoConvView');
  const activePane = document.getElementById('chatActivePane');
  if (noConv) noConv.style.display = 'none';
  if (activePane) activePane.classList.add('show');

  // Mobile drawer slide-in
  const convView = document.getElementById('chatConvView');
  if (convView) convView.classList.add('show');

  // Header data
  const av = document.getElementById('convAvatar');
  if (av) {
    av.textContent = initials;
    av.className = 'chat-hdr-avatar ' + (role === 'Agriculture Official' ? 'role-official' : '');
  }
  document.getElementById('convName').textContent = name;
  
  const roleBadge = document.getElementById('convRoleBadge');
  if (roleBadge) {
    if (role === 'Agriculture Official') {
      roleBadge.className = 'role-tag-badge official';
      roleBadge.innerHTML = `<i class="bi bi-shield-check" style="margin-right:2px;"></i> ${position || 'DA Official'}`;
      roleBadge.style.display = 'inline-flex';
    } else {
      roleBadge.className = 'role-tag-badge farmer';
      roleBadge.textContent = 'Farmer';
      roleBadge.style.display = 'inline-flex';
    }
  }

  document.getElementById('convStatus').textContent = 'Online';

  // Set welcome card in message box
  const box = document.getElementById('convMessages');
  box.innerHTML = `
    <div class="chat-welcome-banner">
      <i class="bi ${role === 'Agriculture Official' ? 'bi-shield-check' : 'bi-chat-heart-fill'}"></i>
      <h4>Connected with ${escHtml(name)}</h4>
      <p>${role === 'Agriculture Official' 
        ? 'Reach out for veterinary consultations, farm inspections, disease reporting, or GAHP certification.' 
        : 'Connect, share agricultural advice, compare livestock feed, and coordinate with peer farmers.'}</p>
    </div>
  `;

  loadMessages(true);
  clearInterval(chatState.poll);
  chatState.poll = setInterval(() => loadMessages(false), 2500);
}

function closeChatConv() {
  const convView = document.getElementById('chatConvView');
  if (convView) convView.classList.remove('show');
  clearInterval(chatState.poll);
}

async function loadMessages(scrollBottom = false) {
  if (!chatState.currentId) return;
  try {
    const res = await fetch(`../api/chat.php?action=get_messages&other_id=${chatState.currentId}&last_id=${chatState.lastId}`);
    if (!res.ok) return;
    const d = await res.json();
    if (!d.success || !d.messages || !d.messages.length) return;
    
    const box = document.getElementById('convMessages');
    const wasBottom = box.scrollHeight - box.clientHeight <= box.scrollTop + 80;
    d.messages.forEach(msg => {
      chatState.lastId = Math.max(chatState.lastId, parseInt(msg.id) || 0);
      appendMsg(msg);
    });
    if (scrollBottom || wasBottom) {
      box.scrollTop = box.scrollHeight;
    }
  } catch (e) {}
}

function appendMsg(msg) {
  const box = document.getElementById('convMessages');
  if (!box) return;
  
  // Remove starter banner if present
  const welcome = box.querySelector('.chat-welcome-banner');
  if (welcome) welcome.remove();

  const mine = parseInt(msg.sender_id) === CURRENT_USER_ID;
  const time = new Date(msg.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
  const div = document.createElement('div');
  div.className = 'msg-row ' + (mine ? 'mine' : 'them');

  let mediaHtml = '';
  if (msg.media_url) {
    const src = msg.media_url.startsWith('data:') || msg.media_url.startsWith('http')
      ? msg.media_url
      : ('../' + msg.media_url.replace(/^\.\.\//, ''));
    mediaHtml = `
      <div class="msg-img-wrap" style="margin-bottom:${msg.message ? '6px' : '0'};">
        <img src="${src}" alt="Attached photo" onclick="window.open(this.src,'_blank')" loading="lazy">
      </div>`;
  }

  let textHtml = '';
  if (msg.message && msg.message.trim() !== '') {
    textHtml = `<div class="msg-bubble ${mine ? 'mine' : 'them'}">${escHtml(msg.message)}</div>`;
  }

  div.innerHTML = `
    <div style="display:flex; flex-direction:column; ${mine ? 'align-items:flex-end;' : 'align-items:flex-start;'}">
      ${mediaHtml}
      ${textHtml}
      <div class="msg-meta">
        <span>${time}</span>
        ${mine ? '<i class="bi bi-check2-all" style="color:var(--acc);margin-left:2px;"></i>' : ''}
      </div>
    </div>
  `;
  box.appendChild(div);
}

function escHtml(s) {
  return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function triggerChatMedia() {
  const fileInput = document.getElementById('chatFileInput');
  if (fileInput) fileInput.click();
}

function handleChatMediaSelect(e) {
  const file = e.target.files ? e.target.files[0] : null;
  if (!file) return;
  if (file.size > 8 * 1024 * 1024) {
    showToast('Photo is too large (max 8MB)', 'warn');
    return;
  }
  const reader = new FileReader();
  reader.onload = function(evt) {
    chatState.pendingMedia = {
      data: evt.target.result,
      type: 'image',
      name: file.name
    };
    document.getElementById('chatMediaThumb').src = evt.target.result;
    document.getElementById('chatMediaName').textContent = file.name;
    document.getElementById('chatMediaPreviewBar').classList.add('show');
  };
  reader.readAsDataURL(file);
}

function removePendingMedia() {
  chatState.pendingMedia = null;
  const fileInput = document.getElementById('chatFileInput');
  if (fileInput) fileInput.value = '';
  const bar = document.getElementById('chatMediaPreviewBar');
  if (bar) bar.classList.remove('show');
}

function quickInsertTag(tag) {
  const input = document.getElementById('chatInput');
  if (!input) return;
  input.value = tag + input.value;
  input.focus();
  autoResize(input);
}

async function sendChatMessage() {
  const input = document.getElementById('chatInput');
  const text = input ? input.value.trim() : '';
  const media = chatState.pendingMedia;
  if (!text && !media) return;
  if (!chatState.currentId) {
    showToast('Please select a contact first', 'warn');
    return;
  }

  input.value = '';
  input.style.height = '';
  removePendingMedia();

  const payload = {
    receiver_id: chatState.currentId,
    message: text
  };
  if (media) {
    payload.media_data = media.data;
    payload.media_type = media.type;
  }

  try {
    const res = await fetch('../api/chat.php?action=send_message', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const d = await res.json();
    if (d.success) {
      const now = new Date().toISOString();
      appendMsg({
        id: d.message_id || Date.now(),
        sender_id: CURRENT_USER_ID,
        message: text,
        media_url: d.media_url || (media ? media.data : null),
        created_at: now
      });
      chatState.lastId = Math.max(chatState.lastId, d.message_id || 0);
      const box = document.getElementById('convMessages');
      if (box) box.scrollTop = box.scrollHeight;
    } else {
      showToast(d.message || 'Failed to send message', 'error');
    }
  } catch (e) {
    showToast('Chat service unavailable', 'error');
  }
}

function handleChatKey(e) {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault();
    sendChatMessage();
  }
}

function autoResize(el) {
  el.style.height = 'auto';
  el.style.height = Math.min(el.scrollHeight, 100) + 'px';
}

async function pollUnread() {
  try {
    const res = await fetch('../api/chat.php?action=unread_count');
    if (!res.ok) return;
    const d = await res.json();
    const badge = document.getElementById('chatBadge');
    if (badge) {
      badge.style.display = d.count > 0 ? '' : 'none';
      badge.textContent = d.count;
    }
  } catch (e) {}
}
setInterval(pollUnread, 7000);

// ── WEBRTC ────────────────────────────────────────────────────────────────────
let peerConn=null, localStream=null, callType='voice', callTargetId=null, callPollTimer=null, isMuted=false, isCamOff=false, incomingOffer=null, incomingCallerId=null;
const RTC_CONFIG={iceServers:[{urls:'stun:stun.l.google.com:19302'},{urls:'stun:stun1.l.google.com:19302'}]};

async function getMediaStream(type){
  // Try ideal constraints first, fall back gracefully
  const constraints = type==='video'
    ? {audio:true,video:{facingMode:'user',width:{ideal:640},height:{ideal:480}}}
    : {audio:true,video:false};
  try{
    return await navigator.mediaDevices.getUserMedia(constraints);
  }catch(err){
    if(err.name==='NotAllowedError'||err.name==='PermissionDeniedError'){
      throw new Error('PERMISSION_DENIED');
    }else if(err.name==='NotFoundError'){
      // Try audio only if video failed
      if(type==='video'){
        try{return await navigator.mediaDevices.getUserMedia({audio:true,video:false});}catch(e2){throw new Error('NO_DEVICE');}
      }
      throw new Error('NO_DEVICE');
    }else if(err.name==='NotReadableError'||err.name==='AbortError'){
      throw new Error('DEVICE_BUSY');
    }
    throw new Error('UNKNOWN:'+err.message);
  }
}

function showCallPermError(msg){
  const el=document.getElementById('permErrorMsg');
  el.querySelector('div').innerHTML=`<strong>${msg}</strong><button onclick="cleanupCall()" style="display:block;margin-top:.5rem;background:var(--danger);color:#fff;border:none;padding:.3rem .75rem;border-radius:8px;font-size:.78rem;cursor:pointer;font-family:inherit;">Close</button>`;
  el.style.display='flex';
}

async function initiateCall(type){
  if(!chatState.currentId){showToast('Open a chat first','warn');return;}
  callType=type;callTargetId=chatState.currentId;

  showCallOverlay(chatState.currentName,chatState.currentInit,'Requesting permissions…',type);
  document.getElementById('permErrorMsg').style.display='none';

  try{
    localStream=await getMediaStream(type);
  }catch(err){
    const msgs={PERMISSION_DENIED:'Microphone/camera access denied. Please allow permissions in your browser settings.',NO_DEVICE:'No microphone/camera found on this device.',DEVICE_BUSY:'Microphone/camera is being used by another app. Close other apps and try again.'};
    showCallPermError(msgs[err.message]||'Could not access media device: '+err.message);
    return;
  }

  setupPeerConnection(type);
  if(type==='video'&&localStream.getVideoTracks().length>0){
    const lv=document.getElementById('localVideo');lv.srcObject=localStream;lv.style.display='block';
  }

  const offer=await peerConn.createOffer();
  await peerConn.setLocalDescription(offer);
  await sendSignal(callTargetId,{type:'offer',sdp:peerConn.localDescription,callType:type,callerName:CURRENT_NAME});
  document.getElementById('callStatus').textContent='Calling…';
  startSignalPoll();
}

async function answerCall(){
  document.getElementById('incomingCallBanner').classList.remove('show');
  if(!incomingOffer||!incomingCallerId)return;
  callTargetId=incomingCallerId;

  showCallOverlay(document.getElementById('incomingCallerName').textContent,'?','Connecting…',callType);
  document.getElementById('permErrorMsg').style.display='none';

  try{
    localStream=await getMediaStream(callType);
  }catch(err){
    const msgs={PERMISSION_DENIED:'Microphone/camera access denied. Please allow permissions in your browser settings.',NO_DEVICE:'No microphone/camera found on this device.',DEVICE_BUSY:'Microphone/camera is being used by another app.'};
    showCallPermError(msgs[err.message]||'Could not access media: '+err.message);
    sendSignal(incomingCallerId,{type:'reject'}).catch(()=>{});
    return;
  }

  setupPeerConnection(callType);
  if(callType==='video'&&localStream.getVideoTracks().length>0){
    const lv=document.getElementById('localVideo');lv.srcObject=localStream;lv.style.display='block';
  }

  await peerConn.setRemoteDescription(new RTCSessionDescription(incomingOffer));
  const answer=await peerConn.createAnswer();
  await peerConn.setLocalDescription(answer);
  await sendSignal(callTargetId,{type:'answer',sdp:peerConn.localDescription});
  document.getElementById('callStatus').textContent='Connected';
  startSignalPoll();
}

function setupPeerConnection(type){
  peerConn=new RTCPeerConnection(RTC_CONFIG);
  localStream.getTracks().forEach(t=>peerConn.addTrack(t,localStream));
  peerConn.onicecandidate=e=>{if(e.candidate)sendSignal(callTargetId,{type:'ice',candidate:e.candidate});};
  peerConn.ontrack=e=>{
    const rv=document.getElementById('remoteVideo');rv.srcObject=e.streams[0];rv.style.display='block';
    document.getElementById('callStatus').textContent='Connected';
  };
  peerConn.onconnectionstatechange=()=>{
    const s=peerConn?.connectionState;
    if(s==='disconnected'||s==='failed'||s==='closed')hangupCall();
  };
}

function rejectCall(){document.getElementById('incomingCallBanner').classList.remove('show');if(incomingCallerId)sendSignal(incomingCallerId,{type:'reject'});incomingOffer=null;incomingCallerId=null;}
async function hangupCall(){if(callTargetId)sendSignal(callTargetId,{type:'hangup'}).catch(()=>{});cleanupCall();}

function cleanupCall(){
  clearInterval(callPollTimer);callPollTimer=null;
  if(peerConn){peerConn.close();peerConn=null;}
  if(localStream){localStream.getTracks().forEach(t=>t.stop());localStream=null;}
  document.getElementById('callOverlay').classList.remove('show');
  const rv=document.getElementById('remoteVideo');rv.srcObject=null;rv.style.display='none';
  const lv=document.getElementById('localVideo');lv.srcObject=null;lv.style.display='none';
  document.getElementById('permErrorMsg').style.display='none';
  isMuted=false;isCamOff=false;callTargetId=null;incomingOffer=null;incomingCallerId=null;
}

function showCallOverlay(name,initials,status,type){
  document.getElementById('callRemoteAvatar').textContent=(initials||'?').substring(0,2);
  document.getElementById('callRemoteName').textContent=name;
  document.getElementById('callStatus').textContent=status;
  document.getElementById('camToggleBtn').style.display=type==='video'?'flex':'none';
  document.getElementById('callOverlay').classList.add('show');
}
function toggleMute(){isMuted=!isMuted;localStream?.getAudioTracks().forEach(t=>t.enabled=!isMuted);const btn=document.getElementById('muteBtn');btn.innerHTML=isMuted?'<i class="bi bi-mic-mute-fill"></i>':'<i class="bi bi-mic-fill"></i>';btn.classList.toggle('active',isMuted);}
function toggleCallCam(){isCamOff=!isCamOff;localStream?.getVideoTracks().forEach(t=>t.enabled=!isCamOff);const btn=document.getElementById('camToggleBtn');btn.innerHTML=isCamOff?'<i class="bi bi-camera-video-off-fill"></i>':'<i class="bi bi-camera-video-fill"></i>';btn.classList.toggle('active',isCamOff);}

async function sendSignal(toId,payload){
  const fd=new FormData();fd.append('action','webrtc_signal');fd.append('to_id',toId);fd.append('payload',JSON.stringify(payload));
  try{await fetch('',{method:'POST',body:fd});}catch(e){}
}
function startSignalPoll(){if(callPollTimer)clearInterval(callPollTimer);callPollTimer=setInterval(pollSignals,1500);}

async function pollSignals(){
  try{const res=await fetch('?action=webrtc_poll');if(!res.ok)return;const d=await res.json();if(!d.success||!d.signals)return;for(const sig of d.signals){let p;try{p=JSON.parse(sig.payload);}catch(e){continue;}await handleSignal(p,parseInt(sig.sender_id));}}catch(e){}
}
setInterval(async()=>{
  if(callTargetId)return;
  try{const res=await fetch('?action=webrtc_poll');if(!res.ok)return;const d=await res.json();if(!d.success||!d.signals)return;for(const sig of d.signals){let p;try{p=JSON.parse(sig.payload);}catch(e){continue;}await handleSignal(p,parseInt(sig.sender_id));}}catch(e){}
},2000);

async function handleSignal(payload,senderId){
  if(payload.type==='offer'){
    if(callTargetId){sendSignal(senderId,{type:'busy'});return;}
    incomingOffer=payload.sdp;incomingCallerId=senderId;callType=payload.callType||'voice';
    const banner=document.getElementById('incomingCallBanner');
    document.getElementById('incomingCallerName').textContent=payload.callerName||'Incoming Call';
    document.getElementById('incomingCallType').textContent=callType==='video'?'📹 Video Call':'📞 Voice Call';
    banner.classList.add('show');
    setTimeout(()=>{if(!callTargetId)banner.classList.remove('show');},30000);
  }else if(payload.type==='answer'){
    if(peerConn&&peerConn.signalingState!=='stable'){
      await peerConn.setRemoteDescription(new RTCSessionDescription(payload.sdp));
      document.getElementById('callStatus').textContent='Connected';
    }
  }else if(payload.type==='ice'){
    if(peerConn&&payload.candidate)try{await peerConn.addIceCandidate(new RTCIceCandidate(payload.candidate));}catch(e){}
  }else if(payload.type==='hangup'||payload.type==='reject'){
    if(payload.type==='reject')showToast('Call declined','warn');
    cleanupCall();
  }else if(payload.type==='busy'){showToast('User is busy','warn');cleanupCall();}
}

// ── CERT PRINT ────────────────────────────────────────────────────────────────
function printCert(id){
  const el=document.getElementById(id);if(!el){showToast('Certificate not found','error');return;}
  const win=window.open('','_blank','width=750,height=900');
  win.document.write(`<!DOCTYPE html><html><head><title>GAHP Certificate</title><style>*{margin:0;padding:0;box-sizing:border-box;}body{font-family:'Times New Roman',Georgia,serif;background:#fff;padding:20px;}.gahp-cert{border:3px solid #1a5c38;border-radius:4px;overflow:hidden;}.gahp-inner{border:1.5px solid #c9a543;margin:5px;border-radius:2px;}</style></head><body>${el.outerHTML}</body></html>`);
  win.document.close();setTimeout(()=>win.print(),500);
}

// ── FORM PILL HELPERS ─────────────────────────────────────────────────────────
function toggleSex(btn,val){document.querySelectorAll('#sexRow .biz-pill').forEach(b=>b.classList.remove('active'));btn.classList.add('active');document.getElementById('petSexVal').value=val;}
function togglePill(btn,key,val){const row=btn.closest('.biz-row');row.querySelectorAll('.biz-pill').forEach(b=>b.classList.remove('active'));btn.classList.add('active');const map={hh_head:'hhHeadVal',pwd:'pwdVal',fourps:'fourpsVal',indigenous:'indigenousVal',gov_id:'govIdVal',farmers_assoc:'farmersAssocVal'};if(map[key])document.getElementById(map[key]).value=val;}
function toggleLivelihood(btn,val){document.querySelectorAll('#livelihoodRow .biz-pill').forEach(b=>b.classList.remove('active'));btn.classList.add('active');document.getElementById('livelihoodVal').value=val;}

// ── NOTIFICATIONS MANAGEMENT ────────────────────────────────────────────────
let currentNotifFilter = 'all';

function updateNotifBadges(unreadCount) {
  const topBell = document.getElementById('topbarBellBtn');
  const bellBadge = document.getElementById('topbarBellBadge');
  const drawerBadge = document.getElementById('drawerNotifBadge');
  const pillBadge = document.getElementById('notifUnreadBadgePill');
  const dashUnreadText = document.getElementById('dashNotifUnreadText');
  const filterUnreadCount = document.getElementById('notifCountUnread');

  if (unreadCount > 0) {
    if (topBell) topBell.classList.add('has-unread');
    if (bellBadge) { bellBadge.textContent = unreadCount; bellBadge.style.display = ''; }
    if (drawerBadge) { drawerBadge.textContent = unreadCount; drawerBadge.style.display = ''; }
    if (pillBadge) { pillBadge.textContent = unreadCount + ' Unread'; pillBadge.style.display = ''; }
    if (dashUnreadText) dashUnreadText.textContent = unreadCount + ' unread';
    if (filterUnreadCount) filterUnreadCount.textContent = unreadCount;
  } else {
    if (topBell) topBell.classList.remove('has-unread');
    if (bellBadge) bellBadge.style.display = 'none';
    if (drawerBadge) drawerBadge.style.display = 'none';
    if (pillBadge) pillBadge.style.display = 'none';
    if (dashUnreadText) dashUnreadText.textContent = '0 unread';
    if (filterUnreadCount) filterUnreadCount.textContent = '0';
  }
}

async function markNotifRead(id, e) {
  if (e) e.stopPropagation();
  try {
    const fd = new FormData();
    fd.append('action', 'mark_notif_read');
    fd.append('notif_id', id);
    const res = await fetch('farmer.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) {
      updateNotifBadges(data.unread_count);
      const item = document.getElementById('notif-item-' + id);
      if (item) {
        item.classList.remove('unread');
        item.classList.add('read');
        item.dataset.read = '1';
        const dot = item.querySelector('.notif-dot');
        if (dot) dot.remove();
        const readBtn = item.querySelector('.mark-read-btn');
        if (readBtn) readBtn.remove();
      }
      const dashDot = document.getElementById('dash-dot-' + id);
      if (dashDot) dashDot.remove();
    }
  } catch(err) {
    console.error('Error marking notification read:', err);
  }
}

async function markAllNotifsRead() {
  try {
    const fd = new FormData();
    fd.append('action', 'mark_all_notifs_read');
    const res = await fetch('farmer.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) {
      updateNotifBadges(0);
      document.querySelectorAll('#notifListContainer .notif-item.unread').forEach(el => {
        el.classList.remove('unread');
        el.classList.add('read');
        el.dataset.read = '1';
        el.querySelector('.notif-dot')?.remove();
        el.querySelector('.mark-read-btn')?.remove();
      });
      document.querySelectorAll('#dashNotifFeed .d-dot').forEach(el => el.remove());
      showToast('All notifications marked as read', 'success');
    }
  } catch(err) {
    console.error('Error marking all notifications read:', err);
  }
}

async function sendTestNotification() {
  try {
    const fd = new FormData();
    fd.append('action', 'send_test_notif');
    const res = await fetch('farmer.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success && data.notif) {
      updateNotifBadges(data.unread_count);
      showNotificationToast(data.notif);
      showToast('New notification received: ' + data.notif.title, 'success');
      appendNotificationToDOM(data.notif);
      const countAll = document.getElementById('notifCountAll');
      if (countAll) countAll.textContent = parseInt(countAll.textContent || '0') + 1;
    }
  } catch(err) {
    console.error('Error sending test notification:', err);
  }
}

function filterNotifs(type) {
  currentNotifFilter = type;
  document.querySelectorAll('.notif-tab').forEach(t => {
    t.classList.toggle('active', t.dataset.filter === type);
  });
  const items = document.querySelectorAll('#notifListContainer .notif-item');
  let visibleCount = 0;
  items.forEach(item => {
    const isRead = item.dataset.read === '1';
    const notifType = item.dataset.type || '';
    let show = true;
    if (type === 'unread') {
      show = !isRead;
    } else if (type === 'alerts') {
      show = (notifType === 'CRITICAL_ALERT' || notifType === 'OUTBREAK_WARNING');
    } else if (type === 'farms') {
      show = (notifType === 'FARM_APPROVED' || notifType === 'FARM_REJECTED');
    }
    item.style.display = show ? 'flex' : 'none';
    if (show) visibleCount++;
  });

  const emptyMsg = document.getElementById('notifFilteredEmpty');
  if (emptyMsg) {
    emptyMsg.style.display = (visibleCount === 0) ? 'block' : 'none';
  }
}

function showNotificationToast(notif) {
  let container = document.getElementById('notifToastContainer');
  if (!container) {
    container = document.createElement('div');
    container.id = 'notifToastContainer';
    container.className = 'notif-toast-container';
    document.body.appendChild(container);
  }
  const isAlert = notif.type === 'CRITICAL_ALERT' || notif.type === 'OUTBREAK_WARNING';
  const toast = document.createElement('div');
  toast.className = 'notif-toast' + (isAlert ? ' is-alert' : '');
  toast.innerHTML = `
    <div style="font-size:1.4rem;">${isAlert ? '🚨' : (notif.type === 'FARM_APPROVED' ? '✅' : '🔔')}</div>
    <div style="flex:1;min-width:0;">
      <div style="font-weight:700;font-size:.85rem;color:var(--text);">${notif.title}</div>
      <div style="font-size:.76rem;color:var(--sub);margin-top:2px;line-height:1.3;">${notif.message.substring(0, 90)}${notif.message.length > 90 ? '…' : ''}</div>
    </div>
    <button type="button" style="background:none;border:none;color:var(--sub);cursor:pointer;font-size:1rem;" onclick="this.parentElement.remove()">&times;</button>
  `;
  toast.onclick = () => {
    navTo('notifications');
    toast.remove();
  };
  container.prepend(toast);
  setTimeout(() => {
    if (toast.parentElement) {
      toast.style.opacity = '0';
      toast.style.transform = 'translateY(-10px)';
      setTimeout(() => toast.remove(), 300);
    }
  }, 5000);
}

function appendNotificationToDOM(notif) {
  const container = document.getElementById('notifListContainer');
  const emptyState = document.getElementById('notifEmptyState');
  if (emptyState) emptyState.style.display = 'none';

  const isAlert = notif.type === 'CRITICAL_ALERT' || notif.type === 'OUTBREAK_WARNING';
  const icon = isAlert ? 'exclamation-triangle-fill' : (notif.type === 'FARM_APPROVED' ? 'patch-check-fill' : 'bell-fill');
  const iconBg = isAlert ? 'var(--danger)' : (notif.type === 'FARM_APPROVED' ? 'var(--acc)' : '#3b82f6');
  const badgeBg = isAlert ? 'background:#fee2e2;color:#991b1b;' : 'background:#ffedd5;color:#9a3412;';

  const div = document.createElement('div');
  div.id = 'notif-item-' + notif.id;
  div.className = 'notif-item unread' + (isAlert ? ' is-alert' : '');
  div.dataset.id = notif.id;
  div.dataset.read = '0';
  div.dataset.type = notif.type;
  div.onclick = () => markNotifRead(notif.id);

  div.innerHTML = `
    <div class="notif-icon" style="background:${iconBg};"><i class="bi bi-${icon}"></i></div>
    <div style="flex:1;min-width:0;">
      <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap;">
        <div style="font-weight:700;font-size:.875rem;color:var(--pri);display:flex;align-items:center;gap:6px;">
          <span>${notif.title}</span>
          <span class="notif-dot ${isAlert ? 'alert' : ''}"></span>
        </div>
        <span class="badge" style="${badgeBg}font-size:.65rem;font-weight:700;padding:.15rem .45rem;border-radius:6px;">${notif.priority || 'Medium'}</span>
      </div>
      <div style="font-size:.8rem;color:var(--text);margin-top:.3rem;line-height:1.45;word-break:break-word;">${notif.message}</div>
      <div style="display:flex;align-items:center;justify-content:space-between;margin-top:.5rem;gap:8px;">
        <div style="font-size:.7rem;color:var(--sub);"><i class="bi bi-clock"></i> ${notif.created_at}</div>
        <button type="button" class="notif-action-btn mark-read-btn" onclick="markNotifRead(${notif.id}, event)" title="Mark as Read"><i class="bi bi-check2"></i> Mark read</button>
      </div>
    </div>
  `;

  if (container) {
    container.prepend(div);
  }
}

let lastUnreadNotifCount = <?= (int)$unreadCount ?>;
async function pollNotifications() {
  try {
    const res = await fetch('farmer.php?action=poll_notifications');
    const data = await res.json();
    if (data.success) {
      if (data.unread_count > lastUnreadNotifCount && data.notifications && data.notifications.length > 0) {
        const latest = data.notifications[0];
        if (latest && !latest.is_read) {
          showNotificationToast(latest);
          appendNotificationToDOM(latest);
        }
      }
      lastUnreadNotifCount = data.unread_count;
      updateNotifBadges(data.unread_count);
    }
  } catch(err) {}
}
setInterval(pollNotifications, 20000);
</script>
<script src="../assets/js/translator.js"></script>
</body>
</html>