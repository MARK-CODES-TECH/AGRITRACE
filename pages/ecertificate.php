<?php
require_once __DIR__ . '/init.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['user_role'] !== 'Farmer') {
    exit('Unauthorized');
}

$userId = $_SESSION['user_id'];

// Certificates
$stmt9 = $pdo->prepare("SELECT c.*, f.name as farm_name FROM certificates c JOIN farms f ON c.farm_id=f.id WHERE f.ownerId=? AND c.status='Active' ORDER BY c.created_at DESC"); 
$stmt9->execute([$userId]);
$eCertData = $stmt9->fetchAll();

?>
<div class="card" style="background:#fff;">
    <h2 class="card-title">E-Certificates</h2>
    <?php if(!empty($eCertData)): ?>
        <div class="stats-grid" id="certContainer">
            <?php foreach($eCertData as $cert): ?>
            <div class="stat-card" style="text-align:left;position:relative;border-left:4px solid var(--acc);">
                <i class="bi bi-patch-check-fill" style="font-size:2rem;color:var(--acc);position:absolute;top:1rem;right:1rem;"></i>
                <div style="font-size:.85rem;color:var(--sub);text-transform:uppercase;font-weight:700;margin-bottom:.25rem;">Cert #<?= htmlspecialchars($cert['certificate_no'] ?? sprintf('%06d', $cert['id'])) ?></div>
                <div style="font-size:1.1rem;font-weight:700;color:var(--txt);margin-bottom:.5rem;"><?= htmlspecialchars($cert['farm_name']) ?></div>
                <div style="font-size:.85rem;color:var(--sub);margin-bottom:1rem;">
                    Issued: <?= date('M d, Y', strtotime($cert['issued_date'] ?? $cert['created_at'])) ?><br>
                    Valid until: <?= $cert['expiry_date'] ? date('M d, Y', strtotime($cert['expiry_date'])) : 'Indefinite' ?>
                </div>
                <button class="btn btn-primary" style="width:100%;font-size:.85rem;padding:.5rem;"><i class="bi bi-download"></i> Download PDF</button>
            </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <i class="bi bi-award"></i>
            <h4>No E-Certificates</h4>
            <p>Once your farm is approved, your E-Certificate will appear here.</p>
        </div>
    <?php endif; ?>
</div>