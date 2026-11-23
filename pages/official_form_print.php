<!-- =====================================================================
  OFFICIAL FORM PRINT SHEETS  (RSBSA + PCIC LIV-UPI-1)
  Paper: 8.5in x 13in (Folio / Long bond), zero @page margin; the margin
  lives inside each sheet so print preview and PDF are identical.
  Include this file in farmer.php just before </body>.
  ===================================================================== -->
<style>
@page{size:8.5in 13in;margin:0;}
#officialPrintWrap{position:fixed;left:-12000px;top:0;width:8.5in;z-index:-1;}
#officialPrintHost{width:8.5in;background:#fff;}
.of-sheet{width:8.5in;height:12.96in;padding:.28in .42in .2in;box-sizing:border-box;background:#fff;color:#000;
  font-family:Arial,Helvetica,sans-serif;font-size:7.8pt;line-height:1.12;overflow:hidden;position:relative;
  page-break-after:always;break-after:page;-webkit-print-color-adjust:exact;print-color-adjust:exact;}
.of-sheet:last-child{page-break-after:auto;break-after:auto;}
.of-sheet *{box-sizing:border-box;}
.of-sheet table{border-collapse:collapse;width:100%;}
.of-sheet td,.of-sheet th{vertical-align:top;font-size:inherit;line-height:inherit;font-family:inherit;text-align:left;}
.of-sheet td.of-c,.of-sheet th.of-c,.of-sheet tr.of-c>td{text-align:center;}
.of-b{font-weight:700;} .of-i{font-style:italic;} .of-c{text-align:center;} .of-u{text-decoration:underline;}
.of-cb{display:inline-block;width:8px;height:8px;border:1px solid #000;margin-right:3px;vertical-align:-1px;position:relative;background:#fff;}
.of-cb.on::after{content:'';position:absolute;left:1.5px;top:-2px;width:3px;height:7px;border:solid #000;border-width:0 1.6px 1.6px 0;transform:rotate(40deg);}
.of-val{display:block;border-bottom:1px solid #000;min-height:13px;padding:0 2px;font-weight:700;font-size:8.5pt;white-space:nowrap;overflow:hidden;text-overflow:clip;}
.of-cap{display:block;text-align:center;font-style:italic;font-size:7.5pt;color:#222;}
.of-flex{display:flex;gap:12px;align-items:flex-end;}
.of-flex>div{flex:1;min-width:0;}
.of-ibox{display:inline-block;border:1px solid #000;width:15px;height:17px;text-align:center;font-weight:700;line-height:15px;margin-right:-1px;}

/* ---------- PCIC ---------- */
.pc-hdr{display:flex;justify-content:space-between;align-items:flex-start;}
.pc-logo{width:46px;height:46px;border-radius:50%;border:3px solid #1d7a3e;background:#e7f3ea;margin-right:5px;flex-shrink:0;}
.pc-brand{display:flex;align-items:center;}
.pc-rep{font-size:10pt;line-height:1;}
.pc-agency{font-size:15.5pt;font-weight:700;color:#1d6b3a;font-family:Georgia,'Times New Roman',serif;letter-spacing:.2px;line-height:1.05;}
.pc-ro{font-size:10pt;line-height:1.1;}
.pc-code{text-align:right;font-size:6.5pt;font-weight:700;line-height:1.2;}
.pc-title{text-align:center;font-size:10.5pt;font-weight:700;text-decoration:underline;margin:3px 0 3px;}
.pc-t td,.pc-t th{border:1px solid #000;padding:1px 5px;}
.pc-bar{background:#e6e6e6;font-weight:700;border:1px solid #000;padding:0 5px;font-size:8pt;margin-top:0;}
.pc-box{border:1px solid #000;border-top:0;padding:3px 5px;}
.pc-cell{padding:2px 5px;}
.pc-c{border:1px solid #000;border-top:0;display:flex;}
.pc-c>div{padding:2px 6px;}

/* ---------- RSBSA ---------- */
.rs-top{display:flex;justify-content:space-between;align-items:flex-start;}
.rs-title{font-size:20pt;font-weight:900;color:#0a7d3e;line-height:1;letter-spacing:-.3px;}
.rs-title small{display:block;font-size:11pt;color:#222;letter-spacing:0;font-weight:800;margin-top:2px;}
.rs-sub{font-size:6.5pt;font-weight:700;color:#444;}
.rs-pic{width:.95in;height:.95in;border:1px solid #000;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;font-weight:700;font-size:7pt;line-height:1.15;}
.rs-pic b{font-size:11pt;}
.rs-ban{background:#000;color:#fff;font-weight:900;font-size:8pt;padding:3px 5px;text-transform:uppercase;margin-top:10px;}
.rs-t td{border:1px solid #000;padding:3px 4px;}
.rs-l{display:block;font-size:6.2pt;font-weight:800;text-transform:uppercase;line-height:1.1;color:#111;}
.rs-v{display:block;background:#FDECD2;min-height:24px;line-height:20px;font-weight:700;font-size:8pt;padding:1px 3px;margin-top:1px;white-space:nowrap;overflow:hidden;}
.rs-sm{font-size:7pt;}
.rs-opt{display:inline-block;margin-right:8px;white-space:nowrap;}
.rs-live td{border:1px solid #000;padding:6px 6px;font-size:7.4pt;line-height:1.6;background:#FDECD2;}
.rs-live .sub{display:block;margin-left:10px;}
.rs-cut{border-top:1.5px dashed #000;margin:7px 0 5px;position:relative;}
.rs-cut span{position:absolute;top:-8px;left:0;background:#fff;font-size:11px;}
.rs-p th{background:#000;color:#fff;font-size:6.6pt;font-weight:800;border:1px solid #000;padding:2px 3px;text-align:center;}
.rs-p td{border:1px solid #000;padding:3px 4px;font-size:7.5pt;height:1.05in;}
.rs-sig{border-top:1px solid #000;text-align:center;font-weight:800;font-size:7.5pt;padding-top:1px;text-transform:uppercase;}
.rs-sig small{display:block;font-weight:700;font-size:6pt;color:#333;text-transform:none;}
.rs-note{font-size:6.8pt;font-style:italic;line-height:1.25;}

/* ---------- Print mode ---------- */
@media print{
  html:has(body.print-official),body.print-official{height:auto!important;overflow:visible!important;display:block!important;background:#fff!important;}
  body.print-official>*:not(#officialPrintWrap){display:none!important;}
  body.print-official #officialPrintWrap{position:static!important;left:auto!important;width:8.5in!important;z-index:auto!important;display:block!important;}
  body.print-official #officialPrintWrap,body.print-official #officialPrintWrap *{visibility:visible!important;}
}
</style>

<div id="officialPrintWrap" aria-hidden="true"><div id="officialPrintHost"></div></div>

<script>
(function(){
  const esc = s => String(s==null?'':s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  const FD = id => { const f=document.getElementById(id); return f?new FormData(f):new FormData(); };
  const g  = (fd,k) => esc(fd.get(k)||'');
  const has= (fd,k) => fd.has(k) && fd.get(k)!=='' && fd.get(k)!=='0';
  const eq = (fd,k,v) => String(fd.get(k)||'').toLowerCase()===String(v).toLowerCase();
  const dt = s => { const m=/^(\d{4})-(\d{2})-(\d{2})/.exec(s||''); return m?`${m[2]}/${m[3]}/${m[1]}`:esc(s||''); };
  const cb = on => `<span class="of-cb${on?' on':''}"></span>`;
  const opt= (on,t) => `<span class="rs-opt">${cb(on)}${t}</span>`;
  const V  = (v,cap,st) => `<div><span class="of-val"${st?` style="${st}"`:''}>${v||'&nbsp;'}</span>${cap?`<span class="of-cap">${cap}</span>`:''}</div>`;
  const R  = (lbl,v,st) => `<span class="rs-l">${lbl}</span><span class="rs-v"${st?` style="${st}"`:''}>${v||'&nbsp;'}</span>`;
  const parts = (s,n) => { const a=String(s||'').split(',').map(x=>x.trim()); const out=a.slice(0,n); while(out.length<n)out.push(''); return out.map(esc); };

  /* =========================== PCIC LIV-UPI-1 =========================== */
  function buildPCIC(){
    const f=FD('pcicApplicationForm');
    const today=new Date(); const todayStr=`${String(today.getMonth()+1).padStart(2,'0')}/${String(today.getDate()).padStart(2,'0')}/${today.getFullYear()}`;
    const contact=String(f.get('contact_no')||'').replace(/\D/g,'').padEnd(11,' ').slice(0,11).split('');
    const addr=parts(f.get('applicant_address'),4), farm=parts(f.get('farm_location'),4);
    const at=String(f.get('animal_type')||'');
    const sx=eq(f,'sex','Male'), sxF=eq(f,'sex','Female');
    const cs=String(f.get('civil_status')||'').toLowerCase();
    const ph=(v)=>v?esc(v):'';
    let rows='';
    for(let i=1;i<=6;i++){
      rows+=`<tr style="height:.2in"><td>${g(f,'animal_tag_'+i)}</td><td class="of-c">${g(f,'animal_m_'+i)}</td><td class="of-c">${g(f,'animal_f_'+i)}</td>
      <td class="of-c">${g(f,'animal_age_'+i)}</td><td class="of-c">${dt(f.get('animal_dob_'+i))}</td><td class="of-c">${g(f,'animal_breed_'+i)}</td>
      <td class="of-c">${g(f,'animal_color_'+i)}</td><td class="of-c">${g(f,'animal_val_'+i)}</td></tr>`;
    }
    const boxes=contact.map(c=>`<span class="of-ibox">${esc(c.trim())}</span>`).join('');
    return `
<div class="of-sheet">
  <div class="pc-hdr">
    <div class="pc-brand"><div class="pc-logo"></div><div>
      <div class="pc-rep">Republic of the Philippines</div>
      <div class="pc-agency">PHILIPPINE CROP INSURANCE CORPORATION</div>
      <div class="pc-ro">Regional Office No. <u>&nbsp;&nbsp;&nbsp;V&nbsp;&nbsp;&nbsp;</u></div></div></div>
    <div class="pc-code">LIV-UPI-1<br>Rev. 2025/Sep</div>
  </div>
  <div class="pc-title">APPLICATION FOR LIVESTOCK MORTALITY INSURANCE</div>
  <div class="of-b" style="font-size:8.5pt;">Kindly fill out all entries and tick all boxes (&#10003;) as appropriate</div>

  <table class="pc-t">
    <tr class="of-c of-b" style="background:#e6e6e6;"><td style="width:19%">APPLICATION TYPE</td><td>FARMER CATEGORY</td><td style="width:23%">DATE OF APPLICATION</td></tr>
    <tr style="height:.34in"><td>${cb(eq(f,'application_type','New'))}New Application<br style="line-height:2">${cb(eq(f,'application_type','Renewal'))}Renewal</td>
      <td>${cb(eq(f,'farmer_category','Self-Financed'))}Self-Financed<br>${cb(eq(f,'farmer_category','Borrowing'))}Borrowing - Lender: <u>${g(f,'lender_name')||'&nbsp;'.repeat(60)}</u></td>
      <td style="vertical-align:bottom"><span style="display:inline-block;width:1.25in;border-bottom:1px solid #000;font-weight:700;text-align:center">${todayStr}</span>(mm/dd/yyyy)</td></tr>
  </table>

  <div class="pc-bar">A.&nbsp; BASIC APPLICANT INFORMATION</div>
  <table class="pc-t" style="border-top:0">
    <tr><td style="width:73%;border-top:0;height:.46in"><b>A.1 Name</b>
        <div class="of-flex" style="margin-top:5px;">${V(g(f,'last_name'),'Last Name')}${V(g(f,'first_name'),'First Name')}${V(g(f,'middle_name'),'Middle Name')}${V(g(f,'suffix'),'Suffix (Jr., Sr., III)')}</div></td>
      <td style="border-top:0"><b>A.2 Contact Number</b><div style="margin-top:5px;white-space:nowrap">${boxes}</div></td></tr>
    <tr><td style="height:.46in"><b>A.3 Address</b>
        <div class="of-flex" style="margin-top:5px;">${V(addr[0],'No. &amp; Street/Sitio')}${V(addr[1],'Barangay')}${V(addr[2],'Municipality/City')}${V(addr[3],'Province')}</div></td>
      <td style="vertical-align:bottom"><b>A.4 Date of Birth</b> <span style="display:block;border-bottom:1px solid #000;font-weight:700;text-align:center;margin-top:10px">${dt(f.get('dob'))||'&nbsp;'}</span><span class="of-cap">(mm/dd/yyyy)</span></td></tr>
  </table>
  <table class="pc-t" style="border-top:0">
    <tr><td style="width:27%;border-top:0;height:.46in"><b>A.5 Sex</b><br>${cb(sx)}Male &nbsp;&nbsp;&nbsp;${cb(sxF)}Female</td>
      <td style="border-top:0"><b>A.6&nbsp; Are you part of a special sector? Please tick (&#10003;) as many as necessary</b><br>
        ${cb(has(f,'sector_pwd'))}PWD &nbsp;&nbsp;&nbsp;&nbsp;${cb(has(f,'sector_sc'))}Senior Citizen &nbsp;&nbsp;&nbsp;&nbsp;${cb(has(f,'sector_youth'))}Youth (18 &ndash; 30 y/o) &nbsp;&nbsp;&nbsp;&nbsp;${cb(has(f,'sector_ip'))}Indigenous People (please indicate tribe)<u>${'&nbsp;'.repeat(16)}</u></td></tr>
  </table>
  <div class="pc-box"><b>A.7 Civil Status</b> &nbsp;${cb(cs==='single')}Single &nbsp;&nbsp;&nbsp;${cb(cs==='married')}Married &nbsp;&nbsp;&nbsp;${cb(cs==='widow'||cs==='widower'||cs==='widowed')}Widow/er &nbsp;&nbsp;&nbsp;${cb(cs==='separated')}Separated &nbsp;<i>(If married, Name of Spouse</i><u>${'&nbsp;'.repeat(62)}</u><i>)</i></div>

  <div class="pc-bar" style="border-top:0">A.8 Name of Legal Beneficiary (In case of death benefit, as applicable)</div>
  <table class="pc-t" style="border-top:0">
    <tr><td style="width:73%;height:.4in"><b>Primary Beneficiary</b> <span style="display:inline-block;width:5.1in;border-bottom:1px solid #000;font-weight:700">${g(f,'bene_primary_name')}&nbsp;</span>
        <div class="of-flex" style="margin-top:0"><div style="text-align:center;font-style:italic;font-size:7.5pt">Last Name</div><div style="text-align:center;font-style:italic;font-size:7.5pt">First Name</div><div style="text-align:center;font-style:italic;font-size:7.5pt">Middle Name</div><div style="text-align:center;font-style:italic;font-size:7.5pt">Suffix (Jr., Sr., III)</div></div></td>
      <td>Relationship to Applicant <u style="font-weight:700">${g(f,'bene_primary_rel')||'&nbsp;'.repeat(14)}</u><br>Birthdate <u>${'&nbsp;'.repeat(16)}</u></td></tr>
    <tr><td style="height:.4in"><b>Guardian</b><br><span style="font-size:6pt;font-style:italic">(if beneficiary is a minor)</span> <span style="display:inline-block;width:4.4in;border-bottom:1px solid #000;font-weight:700">${g(f,'bene_contingent_name')}&nbsp;</span>
        <div class="of-flex"><div style="text-align:center;font-style:italic;font-size:7.5pt">Last Name</div><div style="text-align:center;font-style:italic;font-size:7.5pt">First Name</div><div style="text-align:center;font-style:italic;font-size:7.5pt">Middle Name</div><div style="text-align:center;font-style:italic;font-size:7.5pt">Suffix (Jr., Sr., III)</div></div></td>
      <td>Relationship to Applicant <u style="font-weight:700">${g(f,'bene_contingent_rel')||'&nbsp;'.repeat(14)}</u><br>Birthdate <u>${'&nbsp;'.repeat(16)}</u></td></tr>
  </table>

  <div class="pc-bar" style="border-top:0">A.9 In the event of claim, what is your preferred method of receiving indemnity payment? (Kindly indicate account number as applicable)</div>
  <table class="pc-t" style="border-top:0">
    <tr><td style="width:17%;border-top:0;white-space:nowrap">${cb(0)}LandBank or DBP</td><td style="width:30%;border-top:0">&nbsp;</td><td style="width:20%;border-top:0">${cb(0)}Palawan Express</td><td style="border-top:0">&nbsp;</td></tr>
    <tr><td>${cb(0)}GCash</td><td>&nbsp;</td><td>${cb(0)}Others <span style="font-size:7pt">(Please Specify)</span><u>${'&nbsp;'.repeat(8)}</u></td><td>&nbsp;</td></tr>
  </table>

  <div class="pc-bar" style="border-top:0;margin-top:0">B.&nbsp; FARM AND LIVESTOCK INFORMATION</div>
  <div class="pc-box" style="height:.5in"><b>B.1&nbsp; Farm Address</b>
    <div class="of-flex" style="margin-top:5px;">${V(farm[0],'No. &amp; Street/Sitio')}${V(farm[1],'Barangay')}${V(farm[2],'Municipality/City')}${V(farm[3],'Province')}</div></div>
  <div class="pc-box" style="padding:2px 5px"><b>B.2&nbsp; Type and Purpose of Animal (Choose only one option each for type and for purpose)</b> <sup>1</sup></div>
  <table class="pc-t" style="border-top:0;font-size:8pt">
    <tr><td style="border-top:0;width:12%">${cb(at==='Swine')}Swine</td><td style="border-top:0;width:11%">${cb(at==='Goat/Sheep')}Goat &nbsp;${cb(at==='Goat/Sheep')}Sheep</td>
      <td style="border-top:0" colspan="1">${cb(at==='Poultry')}Poultry</td><td style="border-top:0">${cb(at==='Cattle')}Cattle</td><td style="border-top:0">${cb(at==='Carabao')}Carabao</td><td style="border-top:0">${cb(at==='Horse/Others')}Horse</td><td style="border-top:0;width:15%">${cb(0)}Others Specify</td></tr>
    <tr style="height:.6in"><td>${cb(0)}Fattening<br>${cb(0)}Breeding (select below):<br>${cb(0)}<u>F1</u> ${cb(0)}<u>PS</u> ${cb(0)}<u>GPS</u></td>
      <td>${cb(0)}Fattening<br>${cb(0)}Breeding</td>
      <td>${cb(0)}Broiler<br>&nbsp;&nbsp;&nbsp;${cb(0)}I. Grower<br>&nbsp;&nbsp;&nbsp;${cb(0)}C. Grower<br>&nbsp;&nbsp;&nbsp;${cb(0)}Integrator<br>${cb(0)}Pullets &nbsp;${cb(0)}Chicken Layers<br>${cb(0)}Duck Layers &nbsp;${cb(0)}Quail Layers</td>
      <td>${cb(0)}Draft<br>${cb(0)}Dairy<br>${cb(0)}Fattening<br>${cb(0)}Breeding</td><td>${cb(0)}Draft<br>${cb(0)}Dairy<br>${cb(0)}Fattening<br>${cb(0)}Breeding</td><td>${cb(0)}Draft</td><td><u>${'&nbsp;'.repeat(18)}</u><br><br><u>${'&nbsp;'.repeat(18)}</u><br><br><u>${'&nbsp;'.repeat(18)}</u></td></tr>
  </table>

  <div class="pc-box" style="padding:2px 5px"><b>B.3&nbsp; Description of Animals to be insured</b><sup>2</sup></div>
  <table class="pc-t" style="border-top:0;font-size:8pt">
    <tr class="of-c" style="height:.34in"><td rowspan="2" style="border-top:0;width:22%;vertical-align:middle">Earmark/Tag/Bldg. Number<sup>3</sup></td><td colspan="2" style="border-top:0;padding:0">Total Number</td>
      <td rowspan="2" style="border-top:0;vertical-align:middle;width:11%">Age/Age group</td><td rowspan="2" style="border-top:0;vertical-align:middle;width:15%">Date of Birth<br>(mm/dd/yyyy)</td><td rowspan="2" style="border-top:0;vertical-align:middle;width:13%">Breed</td><td rowspan="2" style="border-top:0;vertical-align:middle;width:10%">Basic Color</td><td rowspan="2" style="border-top:0;vertical-align:middle">Purchase Price/Book Value per Head/Bird</td></tr>
    <tr class="of-c"><td style="width:6%;padding:0">Male</td><td style="width:6%;padding:0">Female</td></tr>
    ${rows}
  </table>
  <table class="pc-t" style="border-top:0">
    <tr><td style="width:36%;border-top:0;height:.3in"><b>B.4&nbsp; Total number of heads/ birds for enrollment</b> <u style="font-weight:700">${g(f,'head_count')||'&nbsp;'.repeat(10)}</u></td>
      <td style="width:30%;border-top:0"><b>B.5&nbsp; Date of Purchase</b> <i>(mm/dd/yyyy)</i><br><u style="font-weight:700">${dt(f.get('purchase_date'))||'&nbsp;'.repeat(18)}</u></td>
      <td style="border-top:0"><b>B.6&nbsp; Source of Stock</b> &nbsp;${cb(eq(f,'stock_source','Island-Born'))}Island-Born <u>${'&nbsp;'.repeat(10)}</u><br>${cb(eq(f,'stock_source','Imported'))}Imported (Country)<u>${'&nbsp;'.repeat(12)}</u></td></tr>
    <tr><td style="height:.3in"><b>B.7&nbsp; No. of Housing Units</b> <u style="font-weight:700">${g(f,'housing_units')}&nbsp;&nbsp;</u></td><td colspan="2"><b>B.8&nbsp; No. of Heads/Birds per Housing Units</b> <u style="font-weight:700">${g(f,'heads_per_unit')}&nbsp;&nbsp;</u></td></tr>
  </table>

  <div class="pc-bar" style="border-top:0">C.&nbsp; CERTIFICATION AND DATA PRIVACY CONSENT STATEMENT (Kindly check the boxes if you agree)</div>
  <div class="pc-c" style="font-size:7.1pt;line-height:1.2">
    <div style="flex:2.55;border-right:1px solid #000">
      <div>${cb(has(f,'cert_agree_1'))}I hereby certify that the foregoing answers and statements are complete, true and correct. If the application is approved, the insurance shall be deemed based upon the statements contained herein. I further agree that PCIC reserves the right to reject and/or void the insurance if found that there is fraud/concealment/misrepresentation on this statement material to the risk.</div>
      <div style="margin-top:3px">${cb(has(f,'cert_agree_2'))}By submitting this application, I hereby consent to the collection, use, processing, and disclosure of my sensitive personal data in accordance with the Data Privacy Act of 2012.</div>
      <div style="margin-top:3px"><b>Deed of Assignment for borrowing farmers/beneficiaries of government projects</b> <i>(if applicable)</i></div>
      <div>${cb(has(f,'cert_agree_3'))}I hereby assign all or part of my rights, title, and interest in this insurance coverage to the Assignee (Lender) stated above. This assignment includes any indemnities that may become payable under the terms of the policy. I acknowledge that this assignment is made in accordance with the terms and conditions of the insurance policy and is subject to the approval of the insurance provider.</div></div>
    <div style="flex:1;text-align:center;font-size:7.5pt;padding-top:22px">
      <div style="border-top:1px solid #000;width:90%;margin:0 auto;padding-top:2px"><b style="font-size:8pt">${esc(((f.get('first_name')||'')+' '+(f.get('last_name')||'')).trim())}</b><br>Signature or Thumb Mark over Printed Name<br><b>Applicant</b></div>
      <div style="margin-top:12px;text-align:left;padding-left:6px">Date <u>${'&nbsp;'.repeat(22)}</u><div class="of-cap" style="text-align:right;padding-right:12px">(mm/dd/yyyy)</div></div></div>
  </div>

  <div class="pc-bar" style="border-top:0">D.&nbsp; COVERAGE (FOR PCIC USE ONLY)</div>
  <table class="pc-t" style="border-top:0">
    <tr><td style="width:50%;border-top:0;height:.24in"><b>D.1 Sum Insured</b> per head <span style="float:right">(PhP)</span></td><td style="border-top:0"><b>D.2 Total Sum Insured</b><span style="float:right">(PhP)</span></td></tr>
    <tr><td colspan="2" style="padding:0"><table><tr><td style="border:0;border-right:1px solid #000;width:50%"><b>D.3 Extended Coverage for Epidemic Diseases</b></td><td style="border:0"><b>D.4 Transport Risk Insurance</b> (if applicable)</td></tr></table></td></tr>
    <tr><td style="padding:0;height:.36in" colspan="2"><table><tr>
      <td style="border:0;border-right:1px solid #000;width:25%;height:.36in">D.3.1 ${'_'.repeat(18)}<br>D.3.2 ${'_'.repeat(18)}</td>
      <td style="border:0;border-right:1px solid #000;width:25%">D.3.3 ${'_'.repeat(18)}<br>D.3.4 ${'_'.repeat(18)}</td>
      <td style="border:0">D.4.1 ${'_'.repeat(26)}</td></tr></table></td></tr>
    <tr><td colspan="2"><b>D.5 Source of Premium</b></td></tr>
    <tr><td colspan="2" style="padding:0"><table><tr><td style="border:0;border-right:1px solid #000;width:22%">${cb(0)}Non-Subsidized/Regular</td><td style="border:0;border-right:1px solid #000;width:46%">${cb(0)}Subsidized/RSBSA &ndash; Ref. No. ${'_'.repeat(14)}</td><td style="border:0">${cb(0)}Others: ${'_'.repeat(22)}</td></tr></table></td></tr>
  </table>
  <div style="font-size:7.2pt;line-height:1.2;margin-top:2px"><b><i>Notes:</i></b>
    <table style="margin-top:0"><tr><td style="width:50%;padding:0 12px 0 14px;font-style:italic">1 &ndash; For B.2 refer to the following legends: F1 &ndash; 1st Generation Breeder, PS &ndash; Parent Stock, GPS &ndash; Grandparent Stock, I.Grower &ndash; Independent Grower, C. Grower &ndash; Contract Grower<br>2 - Please attach additional sheets as necessary of item B.3</td>
    <td style="padding:0;font-style:italic">3 &ndash; For poultry, fill out per group of animals and use bldg. number or location with total number of birds per location, for other livestock fill out per individual animal<br>4 &ndash; Attachment: <u>Proof of Ownership</u></td></tr></table></div>
</div>`;
  }

  /* =========================== RSBSA ENROLLMENT =========================== */
  function buildRSBSA(){
    const f=FD('rsbsaEnrollmentForm');
    const ref=String(f.get('reference_no')||'').replace(/[^0-9]/g,'');
    const refTxt=esc(f.get('reference_no')||'');
    const sex=String(f.get('pet_sex')||'');
    const cs=String(f.get('pet_civil_status')||'').toLowerCase();
    const yn=(k)=>{const v=String(f.get(k)||'').toLowerCase();return v==='yes'||v==='1';};
    const hh=String(f.get('hh_head')||'').toLowerCase()==='yes';
    const assoc=String(f.get('farmers_assoc')||'').toLowerCase()==='yes';
    const gid=has(f,'gov_id_number');
    const nm=(f.get('surname')||''), fn=(f.get('first_name')||''), mn=(f.get('middle_name')||''), ex=(f.get('ext_name')||'');
    const income=(k)=>{const n=parseFloat(f.get(k)||0);return n?n.toLocaleString('en-PH',{minimumFractionDigits:2}):'';};
    const fixedRef=refTxt||'&nbsp;';
    const ownMap={ 'Registered Owner':0,'Tenant':1,'Lessee':2,'Others':3 };
    let parcels='';
    for(let i=1;i<=4;i++){
      const loc=g(f,'parcel_loc_'+i), area=g(f,'parcel_area_'+i), own=String(f.get('parcel_owner_'+i)||''), crop=g(f,'parcel_crops_'+i);
      parcels+=`<tr>
        <td class="of-c of-b" style="width:8%;vertical-align:middle">${i}</td>
        <td style="width:24%">${loc}<div class="rs-sm" style="margin-top:6px;color:#444">Barangay, Municipality / Province</div></td>
        <td class="of-c" style="width:11%;vertical-align:middle">${area}${area?' ha':''}</td>
        <td style="width:24%;font-size:7pt;line-height:1.5">
          <b>Ownership Type</b><br>${cb(own==='Registered Owner')}Registered Owner<br>${cb(own==='Tenant')}Tenant<br>${cb(own==='Lessee')}Lessee<br>${cb(own==='Others')}Others, specify: ______</td>
        <td style="width:20%">${crop}<div class="rs-sm" style="margin-top:6px;color:#444">Crop / Commodity / Livestock</div></td>
        <td style="width:13%">&nbsp;</td></tr>`;
    }
    const pg1=`
<div class="of-sheet">
  <div class="rs-sub" style="text-align:right">Revised Form No. C1-2020 &bull; Department of Agriculture</div>
  <div class="rs-top">
    <div style="display:flex;gap:8px;align-items:center">
      <div style="width:.62in;height:.62in;border-radius:50%;border:3px solid #0a7d3e;background:#eaf6ee"></div>
      <div><div class="rs-sub">REPUBLIC OF THE PHILIPPINES &bull; DEPARTMENT OF AGRICULTURE</div>
      <div class="rs-title">ANI AT KITA<small>RSBSA ENROLLMENT FORM</small></div>
      <div class="rs-sub">REGISTRY SYSTEM FOR BASIC SECTORS IN AGRICULTURE (RSBSA)</div></div></div>
    <div class="rs-pic"><b>2x2</b>PICTURE<span style="font-weight:600;font-size:6pt">(Within 6 months)</span></div>
  </div>
  <table class="rs-t" style="margin-top:6px"><tr>
    <td style="width:38%"><span class="rs-l">Enrollment Type</span>${opt(eq(f,'enrollment_type','New'),'New')}${opt(eq(f,'enrollment_type','Updating'),'Updating')}</td>
    <td>${R('Reference Number',fixedRef,'font-size:9.5pt;letter-spacing:1px')}</td></tr></table>

  <div class="rs-ban">Part I: Personal Information</div>
  <table class="rs-t">
    <tr><td style="width:30%">${R('Surname',esc(nm))}</td><td style="width:30%">${R('First Name',esc(fn))}</td><td style="width:22%">${R('Middle Name',esc(mn))}</td><td style="width:8%">${R('Ext.',esc(ex))}</td><td><span class="rs-l">Sex</span>${opt(sex==='Male','Male')}<br>${opt(sex==='Female','Female')}</td></tr>
    <tr><td colspan="5" style="padding:0"><table><tr>
      <td style="border:0;border-right:1px solid #000;width:14%">${R('House/Lot/Bldg. No.',g(f,'pet_house_no'))}</td>
      <td style="border:0;border-right:1px solid #000;width:22%">${R('Street/Sitio/Subdv.',g(f,'pet_street'))}</td>
      <td style="border:0;border-right:1px solid #000;width:22%">${R('Barangay',g(f,'pet_barangay'))}</td>
      <td style="border:0;border-right:1px solid #000;width:22%">${R('Municipality/City',g(f,'pet_municipality'))}</td>
      <td style="border:0">${R('Province',g(f,'pet_province'))}</td></tr></table></td></tr>
    <tr><td colspan="5" style="padding:0"><table><tr>
      <td style="border:0;border-right:1px solid #000;width:18%">${R('Date of Birth (mm/dd/yyyy)',dt(f.get('pet_dob')))}</td>
      <td style="border:0;border-right:1px solid #000;width:30%">${R('Place of Birth',g(f,'pet_pob'))}</td>
      <td style="border:0;border-right:1px solid #000;width:25%">${R('Mobile Number',g(f,'contact_phone'))}</td>
      <td style="border:0">${R('Landline Number','')}</td></tr></table></td></tr>
    <tr><td colspan="5" style="padding:0"><table><tr>
      <td style="border:0;border-right:1px solid #000;width:20%">${R('Religion','')}</td>
      <td style="border:0;border-right:1px solid #000;width:30%"><span class="rs-l">Civil Status</span><span class="rs-sm">${opt(cs==='single','Single')}${opt(cs==='married','Married')}<br>${opt(cs==='widowed'||cs==='widow','Widowed')}${opt(cs==='separated','Separated')}</span></td>
      <td style="border:0;border-right:1px solid #000;width:25%">${R('Name of Spouse (if married)',g(f,'pet_spouse'))}</td>
      <td style="border:0">${R("Mother's Maiden Name",g(f,'pet_mothers_name'))}</td></tr></table></td></tr>
    <tr><td colspan="5" style="padding:0"><table><tr>
      <td style="border:0;border-right:1px solid #000;width:20%"><span class="rs-l">Household Head?</span>${opt(hh,'Yes')}${opt(!hh,'No')}</td>
      <td style="border:0;border-right:1px solid #000;width:38%">${R('If no, name of household head &amp; relationship',hh?'':g(f,'hh_head_name'))}</td>
      <td style="border:0">${R('No. of living household members','')}<span class="rs-sm">Male: ____ &nbsp;&nbsp; Female: ____</span></td></tr></table></td></tr>
    <tr><td colspan="5" style="padding:0"><table><tr>
      <td style="border:0;border-right:1px solid #000;width:25%"><span class="rs-l">Person with Disability (PWD)?</span>${opt(has(f,'pwd'),'Yes')}${opt(!has(f,'pwd'),'No')}</td>
      <td style="border:0;border-right:1px solid #000;width:25%"><span class="rs-l">4Ps Beneficiary?</span>${opt(has(f,'fourps'),'Yes')}${opt(!has(f,'fourps'),'No')}</td>
      <td style="border:0"><span class="rs-l">Member of an Indigenous Group?</span>${opt(has(f,'indigenous'),'Yes')}${opt(!has(f,'indigenous'),'No')} <span class="rs-sm">If yes, specify: <u>${g(f,'indigenous_specify')||'&nbsp;'.repeat(18)}</u></span></td></tr></table></td></tr>
    <tr><td colspan="5" style="padding:0"><table><tr>
      <td style="border:0;border-right:1px solid #000;width:44%"><span class="rs-l">With Government ID?</span>${opt(gid,'Yes')}${opt(!gid,'No')} <span class="rs-sm">ID Type / No.: <u>${g(f,'gov_id_number')||'&nbsp;'.repeat(16)}</u></span></td>
      <td style="border:0"><span class="rs-l">Member of any Farmers Association / Cooperative?</span>${opt(assoc,'Yes')}${opt(!assoc,'No')} <span class="rs-sm">If yes, specify: <u>${g(f,'farmers_assoc_name')||'&nbsp;'.repeat(16)}</u></span></td></tr></table></td></tr>
    <tr><td colspan="5" style="padding:0"><table><tr>
      <td style="border:0;border-right:1px solid #000;width:55%">${R('Person to Notify in case of Emergency',g(f,'emergency_contact_name'))}</td>
      <td style="border:0">${R('Contact Number',g(f,'emergency_contact_no'))}</td></tr></table></td></tr>
  </table>

  <div class="rs-ban">Part II: Farm Profile</div>
  <div class="rs-sm of-b" style="margin:2px 0">MAIN LIVELIHOOD <span style="font-weight:600">(Please tick only one)</span></div>
  <table class="rs-live"><tr>
    <td style="width:29%"><b>${cb(has(f,'farming_farmer'))}2.1 FARMER</b>
      <span class="sub">${cb(has(f,'farming_rice'))}Rice</span><span class="sub">${cb(has(f,'farming_corn'))}Corn</span>
      <span class="sub">${cb(has(f,'farming_livestock'))}Livestock (hog, cattle, goat, carabao...)<br>&nbsp;&nbsp;&nbsp;<u>${g(f,'livestock_specify')||'&nbsp;'.repeat(18)}</u></span>
      <span class="sub">${cb(has(f,'farming_poultry'))}Poultry (chicken, duck...)<br>&nbsp;&nbsp;&nbsp;<u>${g(f,'poultry_specify')||'&nbsp;'.repeat(18)}</u></span>
      <span class="sub">${cb(0)}Other crops, please specify: ____</span></td>
    <td style="width:26%"><b>${cb(has(f,'farming_worker'))}2.2 FARMWORKER / LABORER</b>
      <span class="sub">${cb(has(f,'work_landprep'))}Land Preparation</span><span class="sub">${cb(has(f,'work_planting'))}Planting / Transplanting</span>
      <span class="sub">${cb(has(f,'work_cultivation'))}Cultivation / Weeding</span><span class="sub">${cb(has(f,'work_harvest'))}Harvesting / Threshing</span><span class="sub">${cb(0)}Others, specify: ____</span></td>
    <td style="width:23%"><b>${cb(has(f,'farming_fisher'))}2.3 FISHERFOLK</b>
      <span class="sub">${cb(has(f,'fish_capture'))}Fish Capture</span><span class="sub">${cb(has(f,'fish_culture'))}Aquaculture</span>
      <span class="sub">${cb(has(f,'fish_glean'))}Gleaning</span><span class="sub">${cb(has(f,'fish_process'))}Fish Processing / Vending</span><span class="sub">${cb(0)}Others, specify: ____</span></td>
    <td><b>${cb(has(f,'farming_youth'))}2.4 AGRI YOUTH</b>
      <span class="sub">${cb(has(f,'youth_hh'))}Part of a farming household</span><span class="sub">${cb(has(f,'youth_course'))}Enrolled in agri-related course / scholar</span>
      <span class="sub">${cb(has(f,'youth_program'))}Participating in agri youth program</span><span class="sub">${cb(0)}Others, specify: ____</span></td></tr></table>
  <table class="rs-t" style="margin-top:3px"><tr>
    <td style="width:50%">${R('Gross Annual Income Last Year &mdash; Farming (PHP)',income('income_farming'))}</td>
    <td>${R('Gross Annual Income Last Year &mdash; Non-Farming (PHP)',income('income_non_farming'))}</td></tr></table>

  <div class="rs-cut"><span>&#9986;</span></div>
  <div class="of-c of-b" style="font-size:10pt;color:#0a7d3e;line-height:1.15">Registry System for Basic Sectors in Agriculture (RSBSA)<br>ENROLLMENT CLIENT'S COPY</div>
  <table class="rs-t" style="margin-top:5px">
    <tr><td colspan="4">${R('Reference Number',fixedRef,'font-size:9.5pt;letter-spacing:1px')}</td></tr>
    <tr><td style="width:32%">${R('Surname',esc(nm))}</td><td style="width:32%">${R('First Name',esc(fn))}</td><td style="width:24%">${R('Middle Name',esc(mn))}</td><td>${R('Ext.',esc(ex))}</td></tr></table>
  <div class="rs-note" style="margin-top:6px">Keep this stub as proof of enrollment. Present it together with a valid government-issued ID when claiming agricultural assistance, subsidies and services from the Department of Agriculture.</div>
  <div class="of-c of-b" style="position:absolute;bottom:.28in;left:0;right:0;font-size:7.5pt">THIS FORM IS NOT FOR SALE</div>
</div>`;

    const pg2=`
<div class="of-sheet">
  <table class="rs-t"><tr><td style="width:50%">${R('Reference Number',fixedRef)}</td><td>${R('Name of Applicant',esc((nm+', '+fn+' '+mn+' '+ex).trim()))}</td></tr></table>
  <div class="rs-ban">Part III: Farm Parcel Information</div>
  <table class="rs-p">
    <thead><tr><th>FARM PARCEL NO.</th><th>FARM LOCATION<br>(Barangay, Municipality)</th><th>TOTAL FARM AREA (ha)</th><th>OWNERSHIP / TENURE</th><th>CROP / COMMODITY<br>LIVESTOCK / POULTRY</th><th>REMARKS</th></tr></thead>
    <tbody>${parcels}</tbody></table>

  <table class="rs-t" style="margin-top:6px;font-size:7pt;line-height:1.55"><tr>
    <td style="width:55%"><b style="font-size:7.5pt">OWNERSHIP DOCUMENT *</b><br>
      ${cb(0)}Certificate of Land Transfer<br>${cb(0)}Emancipation Patent<br>${cb(0)}Individual CLOA (Cert. of Land Ownership Award)<br>${cb(0)}Collective CLOA<br>${cb(0)}Co-ownership CLOA<br>${cb(0)}Agricultural Sales Patent<br>${cb(0)}Homestead Patent<br>${cb(0)}Free Patent<br>${cb(0)}Certificate of Title / Regular Title<br>${cb(0)}Certificate of Ancestral Domain Title<br>${cb(0)}Certificate of Ancestral Land Title<br>${cb(0)}Tax Declaration<br>${cb(0)}Others, specify: ________</td>
    <td><b style="font-size:7.5pt">FARM TYPE *</b><br>${cb(0)}Irrigated<br>${cb(0)}Rainfed Upland<br>${cb(0)}Rainfed Lowland<br><br><span class="rs-note">* Tick all that apply for each parcel.</span></td></tr></table>

  <div class="rs-note" style="border:1px solid #000;border-top:0;padding:3px 5px">I hereby declare that all information provided in this form is true, correct and complete to the best of my knowledge. I understand that any false statement or misrepresentation shall be ground for disqualification and forfeiture of government agricultural assistance.</div>
  <table class="rs-t" style="margin-top:0"><tr>
    <td style="width:46%;height:.85in;vertical-align:bottom"><div class="rs-sig" style="margin-top:30px">${esc((fn+' '+nm).trim())}<small>Signature / Thumbmark of Applicant over Printed Name</small></div></td>
    <td style="width:14%;text-align:center;vertical-align:middle;font-size:6.5pt;font-weight:700">RIGHT<br>THUMBMARK<div style="width:.55in;height:.6in;border:1px dashed #000;margin:3px auto 0"></div></td>
    <td style="vertical-align:bottom"><div class="rs-sig" style="margin-top:30px">&nbsp;<small>Date Accomplished (mm/dd/yyyy)</small></div></td></tr></table>

  <div class="rs-ban" style="margin-top:6px">Verified True and Correct By</div>
  <table class="rs-t"><tr>
    <td style="height:.8in;vertical-align:bottom;width:50%"><div class="rs-sig" style="margin-top:34px">City / Municipal Agriculture Officer<small>Signature over Printed Name &bull; Date</small></div></td>
    <td style="vertical-align:bottom"><div class="rs-sig" style="margin-top:34px">Barangay Chairman / Authorized Representative<small>Signature over Printed Name &bull; Date</small></div></td></tr></table>

  <div class="rs-note" style="margin-top:6px;border:1px solid #000;padding:4px 6px"><b>DATA PRIVACY NOTICE.</b> The personal information collected through this form will be processed by the Department of Agriculture in accordance with Republic Act No. 10173 (Data Privacy Act of 2012) for registration in the RSBSA, program targeting and delivery of agricultural services. By signing, you consent to the collection, use, storage and sharing of your data with authorized government agencies and partners.</div>
  <div class="of-c of-b" style="font-size:9pt;margin:8px 0 2px">THIS FORM IS NOT FOR SALE</div>

  <div class="rs-cut"><span>&#9986;</span></div>
  <table class="rs-t"><tr>
    <td style="width:34%">${R('Reference Number',fixedRef)}</td>
    <td style="width:33%;height:.9in;vertical-align:bottom"><div class="rs-sig" style="margin-top:36px">Verified by<small>Signature over Printed Name &bull; Date</small></div></td>
    <td style="vertical-align:bottom"><div class="rs-sig" style="margin-top:36px">Approved by (Agriculture Official)<small>Signature over Printed Name &bull; Date</small></div></td></tr></table>
</div>`;
    return pg1+pg2;
  }

  /* =========================== PUBLIC API =========================== */
  function render(kind){
    const host=document.getElementById('officialPrintHost');
    host.innerHTML = kind==='pcic'?buildPCIC():buildRSBSA();
    return host;
  }
  window.printOfficialForm=function(kind){
    render(kind);
    document.body.classList.add('print-official');
    const done=()=>{document.body.classList.remove('print-official');window.removeEventListener('afterprint',done);};
    window.addEventListener('afterprint',done);
    setTimeout(()=>window.print(),150);
  };
  window.downloadOfficialPdf=function(kind,filename){
    const host=render(kind);
    if(typeof html2pdf==='undefined'){window.printOfficialForm(kind);return;}
    if(typeof showToast==='function')showToast('Generating 8.5" x 13" PDF...','info');
    html2pdf().set({
      margin:0,
      filename:filename||(kind==='pcic'?'PCIC_LIV-UPI-1.pdf':'RSBSA_Enrollment.pdf'),
      image:{type:'jpeg',quality:0.98},
      html2canvas:{scale:2,useCORS:true,logging:false},
      jsPDF:{unit:'in',format:[8.5,13],orientation:'portrait'},
      pagebreak:{mode:['css'],avoid:'.of-sheet'}
    }).from(host).save().then(()=>{if(typeof showToast==='function')showToast('PDF downloaded!','success');})
      .catch(()=>window.printOfficialForm(kind));
  };
})();
</script>