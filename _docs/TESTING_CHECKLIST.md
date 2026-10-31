# ✅ **Enhanced Geomapping - Testing Checklist**

## **Pre-Testing Setup**

- [ ] XAMPP running
- [ ] Database connected
- [ ] Farms table populated with lat/lng
- [ ] Incidents table populated (optional for heatmap)
- [ ] Browser cache cleared (Ctrl+Shift+Del)

---

## **Phase 1: Basic Loading**

### **Page Access**
- [ ] Visit `http://localhost/AG/maps/farm-map.php`
- [ ] Page loads without authentication error
- [ ] No 404 or connection errors
- [ ] Map container visible

### **UI Elements Visible**
- [ ] Map displays (centered on Philippines)
- [ ] Control panel on LEFT side
- [ ] Stats panel on BOTTOM-LEFT
- [ ] Legend on BOTTOM-RIGHT
- [ ] Sidebar hidden (until marker clicked)

---

## **Phase 2: Data Loading**

### **Console Check (F12 → Console Tab)**
- [ ] No red error messages
- [ ] "Farms loaded" should appear
- [ ] No CORS errors
- [ ] No 404 errors for JS files

### **Farm Markers**
- [ ] Markers appear on map
- [ ] Markers are color-coded
- [ ] Marker count > 0
- [ ] Markers cluster when zoomed out

---

## **Phase 3: Control Panel - Region Filter**

### **Region Filter Dropdown**
- [ ] Click dropdown
- [ ] Shows options:
  - All Regions
  - Camarines Sur
  - Iloilo
  - Davao
  - Laguna
- [ ] Select "Camarines Sur"
  - [ ] Map zooms to region
  - [ ] Markers filter (fewer visible)
  - [ ] Stats update
- [ ] Select "All Regions"
  - [ ] Markers return to full view
  - [ ] Map resets zoom

---

## **Phase 4: Control Panel - Livestock Filter**

### **Min & Max Count Fields**
- [ ] Enter Min: 10
  - [ ] Map updates
  - [ ] Only farms with 10+ livestock shown
  - [ ] Marker colors adjust
  - [ ] Stats update
- [ ] Enter Max: 15
  - [ ] Only 10-15 livestock shown
  - [ ] Fewer markers visible
- [ ] Clear fields
  - [ ] All farms return

---

## **Phase 5: Control Panel - Heatmap**

### **Heatmap Checkbox**
- [ ] Check "Show Incidents Heatmap"
  - [ ] New layer appears on map
  - [ ] Red heat gradient visible (if incidents exist)
  - [ ] Heatmap overlays on farms
- [ ] Uncheck
  - [ ] Heatmap disappears
  - [ ] Farms still visible
- [ ] Toggle multiple times
  - [ ] No console errors
  - [ ] Smooth transitions

---

## **Phase 6: Control Panel - Clustering**

### **Clustering Checkbox**
- [ ] Zoom out fully (zoom level 6)
- [ ] Check "Toggle Clusters"
  - [ ] Farms group into clusters
  - [ ] Cluster circles show count
  - [ ] Colors: green/yellow/red by count
- [ ] Uncheck
  - [ ] All individual markers show
  - [ ] No clustering
- [ ] Zoom in
  - [ ] Clusters auto-break into individual markers
  - [ ] Smooth animation

---

## **Phase 7: Control Panel - Find Nearest**

### **Find Nearest Farms Button**
- [ ] Click "Find Nearest Farms"
  - [ ] 10 farms highlighted/numbered (1-10)
  - [ ] Sorted by distance from map center
  - [ ] Markers stand out visually
  - [ ] Console shows distances
- [ ] Pan map to different location
- [ ] Click again
  - [ ] Different 10 farms highlighted
  - [ ] New distances calculated
  - [ ] Numbered 1-10 again

---

## **Phase 8: Farm Details Sidebar**

### **Click Farm Markers**
- [ ] Click any farm marker
  - [ ] Sidebar slides in from RIGHT
  - [ ] Shows farm details:
    - [ ] Farm name
    - [ ] Status
    - [ ] Type
    - [ ] Livestock count
    - [ ] Address
    - [ ] Coordinates
    - [ ] Created date
- [ ] Click different markers
  - [ ] Sidebar updates with new farm
  - [ ] Map center on marker
- [ ] Click map background
  - [ ] Sidebar closes/hides
- [ ] Mobile test (resize to 500px width)
  - [ ] Sidebar fits screen
  - [ ] Text readable
  - [ ] Close button works

---

## **Phase 9: Statistics Panel**

### **Display Accuracy**
- [ ] Farm count visible
- [ ] Livestock count visible
- [ ] Incident count visible
- [ ] Numbers > 0

### **Updates on Filter**
- [ ] Apply region filter
  - [ ] All 3 stats update
  - [ ] Numbers decrease (fewer in region)
- [ ] Apply livestock filter
  - [ ] Farm count changes
  - [ ] Livestock count changes
- [ ] Apply heatmap
  - [ ] Incident count visible
- [ ] Apply nearest farms
  - [ ] Only top 10 stats shown

---

## **Phase 10: Legend Display**

### **Legend Panel (Bottom-Right)**
- [ ] Shows 4 color blocks
- [ ] Labels:
  - [ ] "0-4 livestock" (Gray)
  - [ ] "5-10 livestock" (Green)
  - [ ] "11-20 livestock" (Yellow)
  - [ ] "20+ livestock" (Red)
- [ ] Colors match actual markers
- [ ] Text readable

---

## **Phase 11: Responsive Design (Mobile)**

### **Desktop View (Full Width)**
- [ ] All elements visible
- [ ] Map controls on left
- [ ] Stats on bottom-left
- [ ] Legend on bottom-right
- [ ] No overlapping

### **Tablet View (768px)**
- [ ] Control panel still accessible
- [ ] Stats visible
- [ ] Legend still shows

### **Mobile View (400px)**
- [ ] Control panels stack vertically
- [ ] Sidebar takes full height
- [ ] All buttons clickable
- [ ] No horizontal scroll
- [ ] Touch zoom works
- [ ] Touch pan works

---

## **Phase 12: Error Handling**

### **Network Issues**
- [ ] Close database temporarily
  - [ ] Error message in console
  - [ ] Map still shows (no crash)
- [ ] Reopen database
  - [ ] Reload page
  - [ ] Farms load again

### **Invalid Data**
- [ ] Filter with extreme values
  - [ ] Min: 99999
  - [ ] Max: 99999
  - [ ] Shows 0 farms (no crash)
- [ ] Manual URL entry
  - [ ] `/api/db.php?action=getAll&table=farms`
  - [ ] Returns JSON (verify in browser)

---

## **Phase 13: Performance**

### **Large Dataset**
- [ ] With 500+ farms
  - [ ] Page loads < 3 seconds
  - [ ] No lag when clicking markers
  - [ ] Clustering smooth
- [ ] With 1000+ farms
  - [ ] Still responsive
  - [ ] Heatmap renders (few seconds OK)
- [ ] Filters apply quickly (< 500ms)

### **Browser Console**
- [ ] No memory leaks (DevTools → Performance)
- [ ] No repeated errors
- [ ] Network tab shows API calls
  - [ ] `/api/db.php?action=getAll&table=farms`
  - [ ] `/api/db.php?action=getAll&table=incidents`

---

## **Phase 14: Advanced Features**

### **Search/Geocoder**
- [ ] Click search box (top-left)
- [ ] Type "Manila"
  - [ ] Suggestions appear
  - [ ] Select Manila
  - [ ] Map zooms to Manila
  - [ ] Farms in area shown

### **Zoom Interactions**
- [ ] Scroll wheel zoom works
- [ ] Double-click zoom centers
- [ ] + / - buttons work
- [ ] Zoom buttons responsive

### **Drag/Pan**
- [ ] Click & drag map
- [ ] Smooth panning
- [ ] Markers update on pan
- [ ] Works in all browsers

---

## **Phase 15: Data Verification**

### **Check Farm Data**
- [ ] Sample farm has:
  - [ ] Latitude (valid Philippine coords)
  - [ ] Longitude (valid Philippine coords)
  - [ ] Name/Type present
  - [ ] Livestock count > 0
- [ ] Database query works:
  ```sql
  SELECT * FROM farms LIMIT 5;
  ```
  - [ ] All fields populated

### **Check Incident Data**
- [ ] At least 1 incident exists
- [ ] Sample incident has:
  - [ ] Latitude (valid)
  - [ ] Longitude (valid)
  - [ ] Type/Description present
- [ ] Heatmap reflects incidents

---

## **✅ Final Sign-Off**

### **All Systems Go?**
- [ ] Page loads without auth
- [ ] All controls functional
- [ ] Markers display correctly
- [ ] Filters work accurately
- [ ] Stats update live
- [ ] Mobile responsive
- [ ] No console errors
- [ ] Performance acceptable

---

## **🐛 Issues Found**

| Issue | Severity | Status |
|-------|----------|--------|
| _______________________|     |        |
| _______________________|     |        |
| _______________________|     |        |

---

## **📋 Sign-Off**

**Tested By:** ___________________  
**Date:** ___________________  
**Notes:** _______________________________________________________________________________

✅ **READY FOR PRODUCTION** → Share with team  
⚠️ **NEEDS FIXES** → Report issues above  
❌ **NOT READY** → Major blocking issues  

---
