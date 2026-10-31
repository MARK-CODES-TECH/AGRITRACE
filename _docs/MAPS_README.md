# AgriTrace+ Map Quick Start

## Access

Open:

```text
http://localhost/AG/maps/farm-map.php
```

The map is public and does not require login.

## Current Scope

The map now shows only the **1st District of Camarines Sur**:

- Cabusao
- Del Gallego
- Lupi
- Ragay
- Sipocot

The map is locked to this district area and draws a visible operating boundary.

## Demo Data

Sample data has been added for live testing:

- 5 approved farms, one in each municipality
- 1 pending demo farm in Ragay
- Registered farmer accounts for each sample farm
- Livestock records with real marker totals
- Farm incident reports and one public incident report

You can re-add the demo data from:

```text
http://localhost/AG/db-setup.php
```

Click **Add Sample Data**.

## Map Controls

| Control | Purpose |
| --- | --- |
| Streets / Satellite / Terrain | Switch the map base view |
| Municipality | Filter farms by the five 1st District municipalities |
| Farm Type | Filter Cattle, Swine, Poultry, Goat, or Mixed farms |
| Farm Status | Show Approved, Pending, or all farms |
| Livestock Population | Filter by minimum and maximum livestock count |
| Cluster nearby farms | Toggle clustered markers on/off |
| Show incident reports | Toggle incident/public report markers |
| Reset | Clear filters and restore the district view |
| Fit View | Re-center the map on the 1st District boundary |

## Legend

- Green marker: 1-20 livestock
- Yellow marker: 21-60 livestock
- Red marker: 61+ livestock
- Gray marker: pending farm
- Triangle marker: incident or public report
- Dashed boundary: 1st District map scope

## Live Data API

The map reads from:

```text
/AG/api/db.php?action=getFirstDistrictMapData
/AG/api/db.php?action=getFirstDistrictIncidents
```

These endpoints return only farms and incidents inside the 1st District scope.

## Quick Test

1. Start XAMPP Apache and MySQL.
2. Visit `http://localhost/AG/maps/farm-map.php`.
3. Confirm markers appear in Cabusao, Del Gallego, Lupi, Ragay, and Sipocot.
4. Try filtering by municipality and farm type.
5. Toggle incident reports to show or hide public/farm incidents.
