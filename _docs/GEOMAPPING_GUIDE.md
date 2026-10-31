# 🗺️ **AgriTrace+ Enhanced Geomapping System**

## **✅ Implemented Features**

### **1. Smart Clustering** 📍
- **Auto-groups nearby farms** when zoomed out
- **Color-coded by density** (green, yellow, red)
- **Smart breakpoints** - individual markers at zoom 18
- **Performance optimized** for 1000+ farms
- URL: `http://localhost/AG/maps/farm-map.php`

### **2. Advanced Filtering** 🔍
**Region Filter:**
- All Regions
- Camarines Sur
- Iloilo
- Davao
- Laguna

**Livestock Count Filter:**
- Min/Max range selection
- Real-time updates
- Auto-focus on results

### **3. Incident Heatmap** 🔥
- Shows incident hotspots
- Color gradient: Blue → Green → Yellow → Orange → Red
- Togglable on/off
- Highlights disease/incident areas

### **4. Distance Calculation** 📏
- **Find Nearest Farms** button
- Calculates distance from map center
- Displays top 10 nearest farms
- Sorted by distance (1-10)
- Uses Turf.js library

### **5. Live Statistics** 📊
**Bottom-left panel shows:**
- Total farms visible
- Total livestock count
- Active incidents

**Updates in real-time** with filters

### **6. Interactive Sidebar** 📋
- Click any farm marker to view details
- Shows farm info:
  - Name & Status
  - Type & Livestock count
  - Address & Coordinates
  - Created/Updated dates
- Auto-centers map on selection

### **7. Search & Navigation** 🔎
- Leaflet Geocoder integration
- Search Philippines by location
- Address lookup
- Auto-navigate

---

## **🚀 How to Use**

### **Access the Map:**
```
http://localhost/AG/maps/farm-map.php
```

### **Main Controls (Top-Left)**

**1. Region Filter**
- Dropdown to select region
- Filters all displayed farms
- Auto-zooms to region

**2. Livestock Filter**
- Enter Min/Max range
- Shows only farms in range

**3. Toggle Heatmap**
- Checkbox to show incidents
- See accident/disease hotspots

**4. Smart Clustering**
- Toggle grouping on/off
- Default: ON

**5. Find Nearest Farms**
- Click button
- Shows 10 closest farms
- Numbered 1-10
- Sorted by distance

**6. Reset**
- Clears all filters
- Resets view to Philippines

---

## **🎨 Color Coding**

### **Marker Colors (Livestock Count)**
```
Gray   (#94a3b8) = 0-4 livestock
Green  (#10b981) = 5-10 livestock
Yellow (#f59e0b) = 11-20 livestock
Red    (#ef4444) = 20+ livestock
```

### **Cluster Colors**
```
Green  = Small cluster (< 50)
Yellow = Medium cluster (50-100)
Red    = Large cluster (100+)
```

### **Heatmap Gradient**
```
Blue   = Low incident density
Green
Yellow
Orange
Red    = High incident density
```

---

## **📊 Statistics Panel (Bottom-Left)**

Real-time displays:
- **📍 Farms:** Count of visible farms
- **🐄 Livestock:** Total livestock in view
- **⚠️ Incidents:** Active incidents in database

Updates when filtering or panning.

---

## **🔍 Farm Details Sidebar**

Click any farm marker to see:
- Farm name & status
- Type (Cattle, Swine, Poultry, etc.)
- Livestock count
- Full address
- GPS coordinates
- Created date
- Last updated

---

## **💡 Advanced Features**

### **Distance-Based Search**
1. Pan/zoom to desired location
2. Click "Find Nearest Farms"
3. Shows 10 closest within 50km radius
4. Numbered in order of distance

### **Heatmap Visualization**
1. Check "Show Incidents Heatmap"
2. Red areas = incident hotspots
3. Helps identify problem areas
4. Great for disease tracking

### **Regional Analysis**
1. Select region from dropdown
2. Filters to that area only
3. Shows statistics for region
4. Can combine with livestock filter

### **Smart Zoom**
- Zoom in → Individual markers
- Zoom out → Clusters
- Very smooth performance

---

## **⚙️ Technical Stack**

**Libraries Used:**
- **Leaflet.js** - Mapping engine
- **Leaflet MarkerCluster** - Clustering
- **Turf.js** - Distance calculations
- **OpenStreetMap** - Base tiles
- **Leaflet Geocoder** - Search

**Database:**
- Reads from `farms` table
- Reads from `incidents` table
- Real-time data pull

---

## **🐛 Troubleshooting**

### **Map not showing?**
- Check if `db.php` is working
- Verify database connection
- Check console (F12) for errors

### **Farms not appearing?**
- Ensure farms have latitude/longitude
- Check database for missing coordinates
- Verify zoom level (should zoom globally)

### **Heatmap not working?**
- Need incidents in database
- Incidents must have lat/lng
- Check incident data exists

### **Distance calculation off?**
- Uses Turf.js library
- Calculates in kilometers
- From current map center

---

## **🎯 Performance Notes**

✅ **Optimized for:**
- 1000+ farms
- 10000+ incidents
- Low bandwidth

⚡ **Speed Tips:**
- Clustering enabled (default)
- Vector tiles (OSM)
- Lazy loading data
- Client-side filtering

---

## **📱 Mobile Support**

✅ **Works on:**
- Mobile phones
- Tablets
- Desktop

📱 **Mobile Features:**
- Touch zoom
- Responsive layout
- All filters work
- Sidebar accessible

---

## **🔐 Security & Privacy**

✅ **Features:**
- No authentication required (public map)
- Read-only access
- No data modifications from map
- XSS protection

---

## **🎓 Next Steps**

Want to add more?
1. ✅ Street View on markers
2. ✅ Export map as PDF
3. ✅ Real-time WebSocket updates
4. ✅ 3D terrain visualization
5. ✅ Historical tracking overlay

---

## **📞 API Endpoints Used**

```
GET /api/db.php?action=getAll&table=farms
GET /api/db.php?action=getAll&table=incidents
```

Both return JSON array of records.

---

## **Quick Reference**

| Feature | How to Use |
|---------|-----------|
| Filter by Region | Select from dropdown |
| Filter by Livestock | Enter Min/Max |
| See Incidents | Check "Heatmap" |
| Find Nearby | Click "Find Nearest" |
| View Details | Click any marker |
| Reset | Click "Reset" |
| Search Location | Use geocoder search |
| Zoom | Scroll mouse wheel |
| Pan | Click & drag map |

---

**Happy Mapping! 🗺️✨**
