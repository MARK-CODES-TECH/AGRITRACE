# 🔍 Camarines Sur Database Audit Guide

## **📊 Overview**

This audit suite checks the AgriTrace+ database specifically for **Camarines Sur** region to verify:
- ✅ Farm data integrity
- ✅ Correct geographic coordinates
- ✅ Livestock records
- ✅ Incident reports
- ✅ Data quality and completeness

---

## **🚀 Quick Access**

### **Visual Dashboard (Recommended)**
```
→ http://localhost/AG/camarines_audit.html
```
- 🎨 Beautiful dashboard interface
- 📊 Real-time statistics
- 📥 Export as JSON
- 📱 Mobile responsive

### **API Endpoint (JSON Output)**
```
→ http://localhost/AG/api/camarines_audit.php
```
- Raw JSON data
- Programmable access
- For automated monitoring

---

## **📋 What Gets Checked**

### **1️⃣ FARMS**
- Total count in Camarines Sur
- Owner information
- Farm type (Cattle, Swine, Poultry, etc.)
- Approval status
- GPS coordinates validity
- Address format

### **2️⃣ LIVESTOCK**
- Total animals per farm
- Livestock type breakdown
- Quantity distribution
- Health status tracking

### **3️⃣ INCIDENTS**
- Incident type (Sick, Dead, Stray, Disease)
- Priority level (Low, Medium, High, Critical)
- Status (Pending, In Progress, Resolved)
- Location accuracy
- Assignment tracking

### **4️⃣ DATA QUALITY**
- Missing coordinates (latitude/longitude)
- Incomplete records
- Unapproved farms
- Orphaned data

---

## **🗺️ Camarines Sur Geographic Bounds**

```
North: 14.48°N
South: 13.10°N
East: 123.58°E
West: 120.01°E
```

**Audit automatically detects:**
- Farms with address containing "Camarines" or "CamSur"
- Farms with coordinates within geographic bounds
- Incidents within the region

---

## **📊 Dashboard Metrics**

| Metric | What It Shows |
|--------|---------------|
| **📍 Total Farms** | Count of farms in region |
| **🐄 Total Livestock** | Sum of all animals across farms |
| **⚠️ Total Incidents** | All incidents (pending + active) |
| **🚨 Critical Issues** | Count of critical priority incidents |

---

## **🐛 Common Issues Found**

### **⚠️ Missing Latitude/Longitude**
```
Issue: GPS coordinates not set
Impact: Can't display on map
Fix: Update farms table with coordinates
```

### **⚠️ Unapproved Farms**
```
Issue: Farm status is "Pending" or "Rejected"
Impact: Won't show in public view
Fix: Admin should review and approve
```

### **⚠️ Incomplete Address**
```
Issue: Address doesn't clearly indicate region
Impact: May not filter correctly
Fix: Ensure address includes municipality + province
```

### **⚠️ Invalid Coordinates**
```
Issue: Lat/Lng outside Philippine bounds
Impact: Won't display on map
Fix: Verify coordinates are valid for Philippines
```

---

## **✅ Quality Checks**

### **Data Integrity**
- ✓ All farms have owner (ownerId)
- ✓ All farms have type specified
- ✓ All farms have coordinates
- ✓ All coordinates are valid

### **Geographic Accuracy**
- ✓ Latitude 13.10 - 14.48
- ✓ Longitude 120.01 - 123.58
- ✓ Address matches coordinates
- ✓ Region properly identified

### **Incident Tracking**
- ✓ Incidents have location data
- ✓ Incidents linked to farms (where applicable)
- ✓ Priority correctly assigned
- ✓ Status tracking accurate

---

## **🔧 How to Use**

### **Step 1: Open Dashboard**
```
http://localhost/AG/camarines_audit.html
```

### **Step 2: Review Statistics**
```
📊 Check all 4 metric cards
- Farms count reasonable?
- Livestock numbers make sense?
- Incidents within expected range?
```

### **Step 3: Check for Issues**
```
⚠️ Yellow box shows any problems
- Missing coordinates?
- Unapproved farms?
- Data quality issues?
```

### **Step 4: Review Tables**
```
📋 Scroll through three tables:
1. All farms in region
2. Livestock per farm
3. Active incidents
```

### **Step 5: Take Action**
```
🔧 If issues found:
- Fix coordinates in database
- Approve pending farms
- Update incomplete addresses
```

### **Step 6: Refresh**
```
🔄 Click "Refresh Audit" button
- Verifies your fixes
- Updates all statistics
```

---

## **📥 Export Data**

### **Export as JSON**
1. Open dashboard
2. Click "📥 Export JSON" button
3. File downloads: `camarines-audit-YYYY-MM-DD.json`
4. Use for:
   - Backup
   - Analysis
   - Reporting
   - Integration

---

## **🗄️ SQL Queries**

### **Manual Database Check**

```sql
-- See all farms in Camarines Sur
SELECT * FROM farms 
WHERE address LIKE '%Camarines%' 
OR address LIKE '%CamSur%';

-- Count by status
SELECT status, COUNT(*) 
FROM farms 
WHERE address LIKE '%Camarines%'
GROUP BY status;

-- See incidents
SELECT * FROM incidents i
LEFT JOIN farms f ON i.farmId = f.id
WHERE f.address LIKE '%Camarines%';
```

### **Via MySQL Workbench**

1. Open `camarines_sur_audit.sql` file
2. Import into MySQL
3. Execute each query one by one
4. Review results

---

## **📱 Mobile Usage**

✅ Dashboard works on mobile!

**Responsive breakpoints:**
- **Desktop (>768px)** - Full layout
- **Tablet (481-768px)** - Optimized grid
- **Mobile (<480px)** - Stacked layout

**Touch-friendly:**
- Click to sort tables
- Swipe to scroll
- Buttons properly spaced

---

## **🔐 Security**

✅ **Safe Operations:**
- Read-only queries (no modifications)
- No sensitive data exposed
- Limited to Camarines Sur data
- No authentication bypass

⚠️ **Remember:**
- Only accessible from `localhost`
- Don't share dashboards externally
- Sensitive farm data displayed
- Restrict access appropriately

---

## **🛠️ Troubleshooting**

### **Dashboard Won't Load**
```
1. Check XAMPP is running
2. Check database connection
3. Verify db_connect.php is correct
4. Check browser console (F12) for errors
```

### **No Data Showing**
```
1. Verify farms exist in database
2. Check address field contains "Camarines"
3. Run SQL query manually
4. Check API endpoint directly
```

### **Wrong Coordinates**
```
1. Verify Philippine coordinates (13-17°N, 120-127°E)
2. Check decimal places (should be 8 decimals)
3. Look for typos or missing values
4. Validate against map
```

### **Slow Loading**
```
1. Large dataset may take few seconds
2. Create database index on address field
3. Limit date range if possible
4. Check server resources
```

---

## **📈 Performance Tips**

✅ **Optimize Database:**
```sql
-- Add indexes for faster queries
CREATE INDEX idx_farms_address ON farms(address);
CREATE INDEX idx_incidents_farmId ON incidents(farmId);
```

✅ **Regular Maintenance:**
- Weekly audit
- Monthly cleanup
- Quarterly backups
- Annual review

---

## **📞 API Reference**

### **Endpoint**
```
GET /api/camarines_audit.php
```

### **Response Structure**
```json
{
  "status": "success",
  "farms": [...],
  "livestock_summary": [...],
  "incidents": [...],
  "stats": {
    "total_farms": 42,
    "unique_owners": 18,
    "total_livestock": 520,
    "total_incidents": 8,
    "critical_incidents": 2,
    "pending_incidents": 3
  },
  "issues": ["⚠️ 3 farms missing latitude", ...],
  "timestamp": "2026-05-03 14:30:00"
}
```

### **Usage Example**
```javascript
fetch('/AG/api/camarines_audit.php')
  .then(r => r.json())
  .then(data => {
    console.log(`Farms: ${data.stats.total_farms}`);
    console.log(`Issues: ${data.issues.length}`);
  });
```

---

## **🎯 Regular Maintenance Checklist**

- [ ] **Weekly**: Run audit, review new issues
- [ ] **Monthly**: Fix identified problems
- [ ] **Quarterly**: Full data validation
- [ ] **Annually**: Archive old data, optimize tables
- [ ] **Upon Update**: Re-run audit after changes

---

## **✨ Best Practices**

1. **Run regularly** - Don't wait for problems
2. **Act on issues** - Fix problems found
3. **Document changes** - Track what was fixed
4. **Backup before**... - Always backup before modifying
5. **Test after** - Verify fixes work

---

## **📚 Related Files**

- **`database.sql`** - Schema and initialization
- **`db_connect.php`** - Database connection
- **`camarines_sur_audit.sql`** - Manual query file
- **`api/camarines_audit.php`** - API endpoint
- **`camarines_audit.html`** - Visual dashboard

---

## **🆘 Need Help?**

1. **Check console** - F12 → Console tab for errors
2. **Review logs** - Check XAMPP error logs
3. **Test connection** - Run `db-setup.php`
4. **Verify data** - Query database directly
5. **Start fresh** - Run deployment scripts

---

**Happy Auditing! 🌾✨**

*Last Updated: May 3, 2026*
