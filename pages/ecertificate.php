<?php
/* ═══════════════════════════════════════════════════════════════════════════
   GAHP E-CERTIFICATE (LANDSCAPE, A4)  —  drop-in for farmer.php
   Replace 4 blocks:  A (query)  ·  B (CSS)  ·  C (page HTML)  ·  D (JS)
   ═══════════════════════════════════════════════════════════════════════════ */

/* ───────── BLOCK A (PHP, top of file) ─────────
   Replace the whole  $stCerts = ... $certsData = $stCerts->fetchAll();  (≈ line 570)   */
$stCerts = $pdo->prepare("
    SELECT c.*,f.name as farm_name,f.type as farm_type,f.address as farm_address,f.area as farm_area,
           f.ownerName,f.region,f.zip_code,f.status as farm_status,
           f.pet_street,f.pet_barangay,f.pet_municipality,f.pet_province,
           u.firstName as owner_firstname,u.lastName as owner_lastname
    FROM certificates c
    JOIN farms f ON c.farm_id=f.id
    LEFT JOIN users u ON f.ownerId=u.id
    WHERE f.ownerId=?
    ORDER BY c.created_at DESC
");
$stCerts->execute([$userId]);
$certsData = $stCerts->fetchAll();
?>

<?php /* ───────── BLOCK B (CSS) ─────────
   Delete the old CERTIFICATE rules (.gahp-cert … .cert-expired) and paste this
   anywhere after your main </style>. Keep id="gahpCertCss".          */ ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style id="gahpCertCss">
.cert-action-bar{display:flex;gap:.5rem;margin-bottom:1rem;flex-wrap:wrap;align-items:center;}
.cert-status-badge{display:inline-flex;align-items:center;gap:.25rem;padding:.3rem .875rem;border-radius:9999px;font-size:.72rem;font-weight:800;flex-shrink:0;}
.cert-valid{background:#d1fae5;color:#065f46;border:2px solid #10b981;}
.cert-expired{background:#fee2e2;color:#991b1b;border:2px solid #ef4444;}

/* ===== GAHP E-CERTIFICATE — LANDSCAPE A4 (all sizes in cqw → scales perfectly) ===== */
.gahp-wrap{container-type:inline-size;width:100%;max-width:1123px;margin:0 auto 1.75rem;}
.gahp-cert{--g:#0d3b1e;--g2:#175c32;--gold:#b08d3c;--gold2:#d9bf74;--ink:#1c2321;--mut:#5f6b64;
  position:relative;width:100%;aspect-ratio:297/210;overflow:hidden;color:var(--ink);
  background:radial-gradient(ellipse at 50% 40%,#fffdf6 0%,#fbf5e3 70%,#f4ebd0 100%);
  border:.75cqw solid var(--g);box-shadow:0 10px 30px rgba(0,0,0,.18);
  font-family:"Segoe UI",Roboto,Arial,sans-serif;font-size:1cqw;line-height:1.35;box-sizing:border-box;}
.gahp-cert *{box-sizing:border-box;margin:0;padding:0;}
.gahp-frame{position:absolute;inset:.7cqw;border:.18cqw solid var(--gold);pointer-events:none;}
.gahp-frame::after{content:"";position:absolute;inset:.45cqw;border:.07cqw solid var(--g2);}
.gahp-corner{position:absolute;width:3cqw;height:3cqw;border:.22cqw solid var(--gold);pointer-events:none;}
.gahp-corner.tl{top:1.4cqw;left:1.4cqw;border-right:0;border-bottom:0;}
.gahp-corner.tr{top:1.4cqw;right:1.4cqw;border-left:0;border-bottom:0;}
.gahp-corner.bl{bottom:1.4cqw;left:1.4cqw;border-right:0;border-top:0;}
.gahp-corner.br{bottom:1.4cqw;right:1.4cqw;border-left:0;border-top:0;}
.gahp-wm{position:absolute;left:50%;top:50%;width:44cqw;height:44cqw;transform:translate(-50%,-50%);opacity:.07;pointer-events:none;}
.gahp-wm svg{width:100%;height:100%;}
.gahp-wmtext{position:absolute;left:50%;top:50%;transform:translate(-50%,-50%) rotate(-24deg);white-space:nowrap;font-family:Georgia,serif;
  font-weight:900;font-size:5.2cqw;letter-spacing:.3cqw;color:var(--g);opacity:.035;pointer-events:none;}
.gahp-micro{position:absolute;left:2.6cqw;right:2.6cqw;font-size:.42cqw;letter-spacing:.12cqw;color:var(--g2);opacity:.55;white-space:nowrap;overflow:hidden;text-align:center;}
.gahp-micro.t{top:2.1cqw;} .gahp-micro.b{bottom:3.1cqw;}

.gahp-main{position:absolute;inset:3.2cqw 3.6cqw 4.3cqw;display:flex;flex-direction:column;}

/* VALID badge */
.gahp-badge{position:absolute;top:0;right:0;display:flex;align-items:center;gap:.4cqw;padding:.45cqw 1.1cqw;border-radius:99px;
  background:#e8f6ec;border:.17cqw solid #1f9d55;color:#13693a;font-weight:800;font-size:.95cqw;letter-spacing:.12cqw;}
.gahp-badge i{width:1.1cqw;height:1.1cqw;border-radius:50%;background:#1f9d55;display:inline-block;position:relative;}
.gahp-badge i::after{content:"";position:absolute;left:.36cqw;top:.17cqw;width:.3cqw;height:.55cqw;border:solid #fff;border-width:0 .15cqw .15cqw 0;transform:rotate(45deg);}
.gahp-badge.expired{background:#fdecec;border-color:#d33;color:#a11;} .gahp-badge.expired i{background:#d33;}
.gahp-badge.expired i::after{display:none;}

/* header */
.gahp-head{display:grid;grid-template-columns:11cqw 1fr 17cqw;align-items:center;gap:1.5cqw;padding-bottom:1cqw;border-bottom:.2cqw double var(--g);position:relative;}
.gahp-seal{width:8.6cqw;height:8.6cqw;}
.gahp-seal svg{width:100%;height:100%;display:block;}
.gahp-hc{text-align:center;}
.gahp-rep{font-size:.95cqw;letter-spacing:.35cqw;font-weight:700;text-transform:uppercase;}
.gahp-dept{font-family:Georgia,"Times New Roman",serif;font-size:2cqw;font-weight:900;letter-spacing:.22cqw;color:var(--g);text-transform:uppercase;line-height:1.15;}
.gahp-rfo{font-size:1.1cqw;font-weight:700;letter-spacing:.1cqw;text-transform:uppercase;color:var(--ink);}
.gahp-loc{font-size:.95cqw;color:var(--mut);font-style:italic;}
.gahp-ids{margin-top:2.4cqw;border:.12cqw solid var(--gold);background:rgba(255,255,255,.7);padding:.5cqw .8cqw;text-align:left;}
.gahp-ids div{display:flex;flex-direction:column;}
.gahp-ids div+div{margin-top:.35cqw;padding-top:.35cqw;border-top:.07cqw dashed var(--gold);}
.gahp-ids label{font-size:.58cqw;letter-spacing:.1cqw;font-weight:700;text-transform:uppercase;color:var(--mut);}
.gahp-ids b{font-family:"Courier New",monospace;font-size:.95cqw;color:var(--g);letter-spacing:.03cqw;}

/* title */
.gahp-title{text-align:center;margin-top:1.1cqw;}
.gahp-title h1{font-family:Georgia,"Times New Roman",serif;font-weight:900;font-size:2.55cqw;letter-spacing:.2cqw;color:var(--g);text-transform:uppercase;line-height:1.1;}
.gahp-title .sub{font-size:.85cqw;letter-spacing:.25cqw;text-transform:uppercase;color:var(--gold);font-weight:700;margin-top:.4cqw;}
.gahp-title .sub::before,.gahp-title .sub::after{content:"";display:inline-block;width:7cqw;height:.1cqw;background:var(--gold);vertical-align:middle;margin:0 1cqw;}
.gahp-award{font-family:Georgia,serif;font-style:italic;font-size:1.05cqw;color:var(--mut);margin-top:.9cqw;}
.gahp-farm{font-family:Georgia,"Times New Roman",serif;font-weight:900;font-size:3.5cqw;letter-spacing:.2cqw;text-transform:uppercase;color:#0a2a16;line-height:1.1;margin-top:.2cqw;
  display:inline-block;padding:0 2cqw .35cqw;border-bottom:.2cqw solid var(--gold);max-width:100%;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.gahp-decl{font-size:1.02cqw;line-height:1.55;text-align:center;max-width:80cqw;margin:1.1cqw auto 0;color:#2a3430;}
.gahp-decl b{color:var(--g);}

/* data grid */
.gahp-grid{display:grid;grid-template-columns:1fr 1fr;gap:.9cqw 1.2cqw;margin-top:1.5cqw;}
.gahp-card{display:flex;gap:.8cqw;background:rgba(255,255,255,.72);border:.1cqw solid #d8cfae;border-left:.35cqw solid var(--g);padding:.8cqw .9cqw;}
.gahp-card .n{flex:0 0 1.9cqw;height:1.9cqw;border-radius:50%;background:var(--g);color:var(--gold2);display:flex;align-items:center;justify-content:center;font-weight:800;font-size:.95cqw;margin-top:.1cqw;}
.gahp-card label{display:block;font-size:.62cqw;letter-spacing:.12cqw;text-transform:uppercase;font-weight:800;color:var(--mut);}
.gahp-card strong{display:block;font-size:1.3cqw;color:var(--ink);line-height:1.25;}
.gahp-card em{display:block;font-style:normal;font-size:.9cqw;color:var(--mut);}
.gahp-card .dts{display:flex;gap:1.6cqw;}
.gahp-card .dts strong{color:var(--g);} .gahp-card .dts .vu strong{color:#8a4b08;} .gahp-card .dts .vu.x strong{color:#b01818;}

/* footer */
.gahp-foot{margin-top:auto;padding-bottom:.4cqw;display:grid;grid-template-columns:23cqw 1fr 26cqw;gap:2cqw;align-items:end;padding-top:.8cqw;}
.gahp-qrbox{display:flex;gap:.9cqw;align-items:center;}
.gahp-qr{width:8.4cqw;height:8.4cqw;background:#fff;border:.2cqw solid #111;padding:.4cqw;flex:0 0 auto;}
.gahp-qr img{display:block!important;width:100%;height:100%;} .gahp-qr canvas{display:none!important;}
.gahp-qrt b{display:block;font-size:.85cqw;letter-spacing:.1cqw;text-transform:uppercase;color:var(--g);}
.gahp-qrt span{display:block;font-size:.7cqw;color:var(--mut);line-height:1.35;margin-top:.25cqw;}
.gahp-mid{text-align:center;font-size:.72cqw;color:var(--mut);line-height:1.5;}
.gahp-mid .dist{font-weight:700;color:var(--g);font-size:.85cqw;}
.gahp-mid .hash{font-family:"Courier New",monospace;font-size:.7cqw;color:var(--g2);margin-top:.3cqw;}
.gahp-sig{text-align:center;}
.gahp-sig .h{font-size:.62cqw;letter-spacing:.14cqw;text-transform:uppercase;font-weight:800;color:var(--mut);}
.gahp-sig .sp{height:3.6cqw;}
.gahp-sig .ln{border-top:.14cqw solid var(--ink);padding-top:.35cqw;}
.gahp-sig .nm{font-size:1.05cqw;font-weight:900;text-transform:uppercase;color:var(--g);letter-spacing:.04cqw;}
.gahp-sig .tt{font-size:.78cqw;color:#46524b;line-height:1.35;}
.gahp-bar{position:absolute;left:0;right:0;bottom:0;height:2.5cqw;background:var(--g);color:#d8e6dc;border-top:.2cqw solid var(--gold);
  display:flex;align-items:center;justify-content:space-between;padding:0 3.6cqw;font-size:.74cqw;letter-spacing:.04cqw;}
</style>

<?php /* ───────── BLOCK C (HTML) ─────────
   Replace everything from  <!-- ══ E-CERTIFICATE ══ -->  down to (not including)
   <!-- ══ PROFILE ══ -->                                                              */ ?>
      <!-- ══ E-CERTIFICATE ══ -->
      <div id="pg-ecert" class="page">
        <div class="card" style="padding:.875rem;">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;flex-wrap:wrap;gap:.5rem;">
            <h3 style="font-size:.95rem;font-weight:700;display:flex;align-items:center;gap:.5rem;margin:0;"><i class="bi bi-award" style="color:#c9a543;"></i> GAHP Certificates</h3>
            <span style="background:linear-gradient(135deg,#d1fae5,#a7f3d0);color:#065f46;padding:.25rem .75rem;border-radius:9999px;font-size:.75rem;font-weight:700;"><i class="bi bi-shield-check"></i> <?= count($certsData) ?> Certificate<?= count($certsData)!=1?'s':'' ?></span>
          </div>

          <?php if(!empty($certsData)): ?>
          <?php
            $GAHP_SEAL  = '<svg viewBox="0 0 90 90" xmlns="http://www.w3.org/2000/svg"><circle cx="45" cy="45" r="44" fill="#0d3b1e"/><circle cx="45" cy="45" r="38" fill="none" stroke="#d9bf74" stroke-width="2"/><circle cx="45" cy="45" r="33" fill="none" stroke="#d9bf74" stroke-width=".6" stroke-dasharray="1.5 2"/><ellipse cx="45" cy="52" rx="11" ry="8" fill="#d9bf74"/><circle cx="45" cy="40" r="7" fill="#d9bf74"/><ellipse cx="39" cy="35" rx="3" ry="5" fill="#d9bf74" transform="rotate(-15,39,35)"/><ellipse cx="51" cy="35" rx="3" ry="5" fill="#d9bf74" transform="rotate(15,51,35)"/><text x="45" y="72" text-anchor="middle" fill="#fff" font-size="7" font-weight="bold" font-family="Arial">GAHP</text></svg>';
            $GAHP_MICRO = 'AGRITRACE+ VERIFIED • DA RFO V • BUREAU OF ANIMAL INDUSTRY • GAHP PNS/BAFS 184:2016 • AGRITRACE+ VERIFIED • DA RFO V • BUREAU OF ANIMAL INDUSTRY • GAHP PNS/BAFS 184:2016 • AGRITRACE+ VERIFIED • DA RFO V • BUREAU OF ANIMAL INDUSTRY • GAHP PNS/BAFS 184:2016 • AGRITRACE+ VERIFIED • DA RFO V • BUREAU OF ANIMAL INDUSTRY • GAHP PNS/BAFS 184:2016 • AGRITRACE+ VERIFIED • DA RFO V • BUREAU OF ANIMAL INDUSTRY • GAHP PNS/BAFS 184:2016 • AGRITRACE+ VERIFIED • DA RFO V • BUREAU OF ANIMAL INDUSTRY • GAHP PNS/BAFS 184:2016';
            $stLvT   = $pdo->prepare("SELECT DISTINCT type FROM livestock WHERE farmId=?");
            $lguList = ['Cabusao','Del Gallego','Lupi','Ragay','Sipocot'];
            foreach($certsData as $cert):
              /* ---- map DB row → da_livestock_registry-style record ---- */
              $issued   = $cert['issued_date'] ?: $cert['created_at'];
              $isExpired= $cert['expiry_date'] && strtotime($cert['expiry_date'])<time();
              $certNo   = 'GAHP-'.date('Y',strtotime($issued)).'-D1-'.str_pad($cert['id'],5,'0',STR_PAD_LEFT);
              $farmIdNo = 'R05-CAM-DIST1-'.str_pad($cert['farm_id'],5,'0',STR_PAD_LEFT);
              $owner    = trim($cert['ownerName'] ?? '') ?: trim(($cert['owner_firstname']??'').' '.($cert['owner_lastname']??''));
              $stLvT->execute([$cert['farm_id']]);
              $lvTypes  = $stLvT->fetchAll(PDO::FETCH_COLUMN);
              $addr     = (string)($cert['farm_address'] ?? '');
              $muni     = trim($cert['pet_municipality'] ?? '');
              if(!in_array($muni,$lguList,true)){ $muni=''; foreach($lguList as $l){ if(stripos($addr,$l)!==false){ $muni=$l; break; } } }
              $brgy     = trim(trim($cert['pet_street']??'').' '.trim($cert['pet_barangay']??'')) ?: ($addr ?: '—');
              $hash     = strtoupper(substr(hash('sha256',$certNo.'|'.$farmIdNo.'|'.$issued.'|'.$cert['expiry_date']),0,12));
              $c = [
                'dom_id'=>'gahp-'.$cert['id'],
                'cert_no'=>$certNo, 'farm_id'=>$farmIdNo,
                'district_office'=>'District I Field Office - 1st District of Camarines Sur',
                'municipality'=>$muni ?: 'Camarines Sur', 'farm_name'=>strtoupper($cert['farm_name']),
                'owner_name'=>$owner ?: '—', 'production'=>$lvTypes ? implode(' / ',$lvTypes) : ucwords($cert['farm_type'] ?? 'Mixed'),
                'barangay'=>$brgy, 'cert_date'=>date('d F Y',strtotime($issued)),
                'valid_date'=>$cert['expiry_date'] ? date('d F Y',strtotime($cert['expiry_date'])) : 'Indefinite',
                'issuing_authority'=>'Bureau of Animal Industry - DA Region V',
                'district_tracker'=>'DA-RFO V AgriTrace+ Network · District I (Cabusao, Del Gallego, Lupi, Ragay, Sipocot)',
                'badge_cls'=>$isExpired?'expired':'', 'badge_txt'=>$isExpired?'EXPIRED':'VALID',
                'vu_cls'=>$isExpired?'x':'', 'hash'=>$hash,
                'verify_url'=>(isset($_SERVER['HTTPS'])?'https':'http').'://'.($_SERVER['HTTP_HOST']??'localhost').dirname($_SERVER['SCRIPT_NAME']).'/verify_cert.php?no='.urlencode($certNo).'&h='.$hash,
              ];
          ?>
          <div class="cert-action-bar">
            <span class="cert-status-badge <?= $isExpired?'cert-expired':'cert-valid' ?>"><i class="bi bi-<?= $isExpired?'x-circle-fill':'check-circle-fill' ?>"></i><?= $isExpired?'EXPIRED':'VALID' ?></span>
            <div style="flex:1;font-size:.78rem;color:var(--sub);font-weight:600;"><?= htmlspecialchars($cert['farm_name']) ?></div>
            <button class="btn btn-primary" title="Download Official GAHP PDF" style="padding:.4rem .85rem;font-size:.76rem;display:inline-flex;align-items:center;gap:.4rem;font-weight:600;border-radius:8px;" onclick="downloadCertPDF('<?= $c['dom_id'] ?>','<?= htmlspecialchars($certNo,ENT_QUOTES) ?>',this)"><i class="bi bi-file-earmark-pdf-fill"></i> Download PDF</button>
          </div>
<div class="gahp-wrap">
<div class="gahp-cert" id="<?= htmlspecialchars($c['dom_id']) ?>">
  <div class="gahp-frame"></div>
  <span class="gahp-corner tl"></span><span class="gahp-corner tr"></span><span class="gahp-corner bl"></span><span class="gahp-corner br"></span>
  <div class="gahp-wm"><?= $GAHP_SEAL ?></div>
  <div class="gahp-wmtext">REPUBLIC OF THE PHILIPPINES</div>
  <div class="gahp-micro t"><?= $GAHP_MICRO ?></div>
  <div class="gahp-micro b"><?= $GAHP_MICRO ?></div>

  <div class="gahp-main">
    <div class="gahp-badge <?= $c['badge_cls'] ?>"><i></i><?= htmlspecialchars($c['badge_txt']) ?></div>
    <div class="gahp-head">
      <div class="gahp-seal"><?= $GAHP_SEAL ?></div>
      <div class="gahp-hc">
        <div class="gahp-rep">Republic of the Philippines</div>
        <div class="gahp-dept">Department of Agriculture</div>
        <div class="gahp-rfo">Regional Field Office No. 5 (Bicol Region)</div>
        <div class="gahp-loc">San Agustin, Pili, Camarines Sur</div>
      </div>
      <div class="gahp-ids">
        <div><label>Certificate / Registration No.</label><b><?= htmlspecialchars($c['cert_no']) ?></b></div>
        <div><label>Farm ID Number</label><b><?= htmlspecialchars($c['farm_id']) ?></b></div>
      </div>
    </div>

    <div class="gahp-title">
      <h1>Good Animal Husbandry Practices Certificate</h1>
      <div class="sub">Department of Agriculture · Livestock Certification Program</div>
      <div class="gahp-award">This certificate is proudly conferred upon</div>
      <div class="gahp-farm"><?= htmlspecialchars($c['farm_name']) ?></div>
      <div class="gahp-decl">The Good Animal Husbandry Practices (GAHP) Certification Committee hereby certifies that the farm named above has been evaluated and found <b>compliant</b> with the rules, standards, and animal welfare requirements of the <b>Department of Agriculture – Bureau of Animal Industry (DA-BAI)</b> and the <b>Philippine National Standard PNS/BAFS 184:2016</b> on Good Animal Husbandry Practices.</div>
    </div>

    <div class="gahp-grid">
      <div class="gahp-card"><span class="n">1</span><div><label>Ownership &amp; Farm Name</label><strong><?= htmlspecialchars($c['owner_name']) ?></strong><em><?= htmlspecialchars($c['farm_name']) ?></em></div></div>
      <div class="gahp-card"><span class="n">2</span><div><label>Complete Location</label><strong><?= htmlspecialchars($c['barangay']) ?>, <?= htmlspecialchars($c['municipality']) ?></strong><em>Camarines Sur, Bicol Region (Region V), Philippines</em></div></div>
      <div class="gahp-card"><span class="n">3</span><div><label>Type of Livestock / Farm Production</label><strong><?= htmlspecialchars($c['production']) ?></strong><em>1st District of Camarines Sur</em></div></div>
      <div class="gahp-card"><span class="n">4</span><div><label>Certification Period</label>
        <div class="dts"><div><em>Date of Certification</em><strong><?= htmlspecialchars($c['cert_date']) ?></strong></div><div class="vu <?= $c['vu_cls'] ?>"><em>Valid Until</em><strong><?= htmlspecialchars($c['valid_date']) ?></strong></div></div></div></div>
    </div>

    <div class="gahp-foot">
      <div class="gahp-qrbox">
        <div class="gahp-qr" data-verify="<?= htmlspecialchars($c['verify_url']) ?>"></div>
        <div class="gahp-qrt"><b>Scan to Verify</b><span>Authenticate instantly on the AgriTrace+ system / DA Region V database.</span></div>
      </div>
      <div class="gahp-mid">
        <div class="dist"><?= htmlspecialchars($c['district_office']) ?></div>
        Covering: Cabusao · Del Gallego · Lupi · Ragay · Sipocot
        <div class="hash">SECURITY HASH: <?= htmlspecialchars($c['hash']) ?></div>
      </div>
      <div class="gahp-sig">
        <div class="h">Authorized Issuing Signatory</div><div class="sp"></div>
        <div class="ln"><div class="nm">Director, Bureau of Animal Industry</div>
        <div class="tt"><?= htmlspecialchars($c['issuing_authority']) ?><br>Department of Agriculture</div></div>
      </div>
    </div>
  </div>
  <div class="gahp-bar"><span>RF AHWD-44 GAHP Certificate | Rev. No. 00</span><span><?= htmlspecialchars($c['district_tracker']) ?></span></div>
</div>
</div>
          <?php endforeach; ?>
          <?php else: ?>
          <div class="empty-state"><i class="bi bi-award"></i><h4>No GAHP Certificates Yet</h4><p>Certificates are auto-issued when you register a farm.</p><button class="btn btn-primary" style="margin-top:1rem;" onclick="navTo('farm-reg')"><i class="bi bi-house-add-fill"></i> Register Farm</button></div>
          <?php endif; ?>
        </div>
      </div>

<?php /* ───────── BLOCK D (JS) ─────────
   Replace the old  function printCert(id){ ... }  and add these 3 library tags.   */ ?>
<script src="../assets/js/vendor/qrcode.min.js"></script>
<script>if(typeof QRCode==='undefined'){document.write('<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"><\/script>');}</script>

<script src="../assets/js/vendor/html2canvas.min.js"></script>
<script>if(typeof html2canvas==='undefined'){document.write('<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"><\/script>');}</script>

<script src="../assets/js/vendor/jspdf.umd.min.js"></script>
<script>if(!window.jspdf&&!window.jsPDF){document.write('<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"><\/script>');}</script>

<script>
function buildCertQRs(){
  if(typeof QRCode==='undefined') return;
  document.querySelectorAll('.gahp-qr[data-verify]').forEach(el=>{
    if(el.dataset.done) return;
    el.dataset.done='1';
    el.innerHTML='';
    try {
      new QRCode(el,{
        text: el.dataset.verify,
        width: 200,
        height: 200,
        correctLevel: QRCode.CorrectLevel.M
      });
    } catch(err){ console.warn('QR build error', err); }
  });
}
window.addEventListener('load',buildCertQRs);
document.addEventListener('DOMContentLoaded', buildCertQRs);

// Direct PDF download — A4 landscape format
async function downloadCertPDF(id, certNo, btn){
  const el = document.getElementById(id);
  if(!el){ showToast('Certificate not found', 'error'); return; }

  const jsPDFClass = (window.jspdf && window.jspdf.jsPDF) ? window.jspdf.jsPDF : (window.jsPDF || null);
  if(typeof html2canvas === 'undefined' || !jsPDFClass){
    showToast('PDF generator is initializing, please try again in a few seconds.', 'warn');
    return;
  }

  buildCertQRs();
  const oldText = btn ? btn.innerHTML : '';
  if(btn){
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Preparing PDF…';
  }

  const holder = document.createElement('div');
  holder.style.cssText = 'position:fixed;left:0;top:0;width:1123px;z-index:-9999;opacity:0;pointer-events:none;background:#ffffff;';

  const wrap = el.closest('.gahp-wrap') || el.parentElement;
  const clone = wrap.cloneNode(true);
  clone.style.cssText = 'width:1123px!important;max-width:1123px!important;margin:0!important;padding:0!important;display:block!important;';

  const certClone = clone.querySelector('.gahp-cert') || clone;
  certClone.style.boxShadow = 'none';
  certClone.style.margin = '0';
  certClone.style.width = '1123px';

  const origImgs = el.querySelectorAll('.gahp-qr img');
  const cloneImgs = certClone.querySelectorAll('.gahp-qr img');
  origImgs.forEach((img, idx) => {
    if(cloneImgs[idx] && img.src){
      cloneImgs[idx].src = img.src;
      cloneImgs[idx].style.display = 'block';
    }
  });

  holder.appendChild(clone);
  document.body.appendChild(holder);

  try {
    await new Promise(r => setTimeout(r, 220));

    const canvas = await html2canvas(certClone, {
      scale: 2,
      backgroundColor: '#ffffff',
      useCORS: true,
      allowTaint: true,
      logging: false
    });

    const pdf = new jsPDFClass({
      orientation: 'landscape',
      unit: 'mm',
      format: 'a4'
    });

    const imgData = canvas.toDataURL('image/jpeg', 0.96);
    pdf.addImage(imgData, 'JPEG', 0, 0, 297, 210, undefined, 'FAST');
    const safeCertNo = (certNo || 'Doc').replace(/[^a-zA-Z0-9_-]/g, '_');
    pdf.save('GAHP-Certificate-' + safeCertNo + '.pdf');
    showToast('Certificate PDF downloaded successfully!', 'success');
  } catch(err) {
    console.error('PDF Generation Error:', err);
    showToast('Failed to generate PDF. Please try again.', 'error');
  } finally {
    if(holder && holder.parentNode) holder.remove();
    if(btn){
      btn.disabled = false;
      btn.innerHTML = oldText;
    }
  }
}
</script>
