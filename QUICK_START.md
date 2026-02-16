# 🚗 Vehicle Registry - Quick Start Guide

## 5-Minute Setup

### Step 1️⃣: Run Database Setup (Automatic - 30 seconds)
```
1. Open browser: http://localhost/FuelCapstone/setup_vehicle_registry.php
2. Check for ✓ "Setup Complete" message
3. This adds 13 columns to your vehicles table automatically
```

### Step 2️⃣: Access Vehicle Registry (Immediate)
```
Click: Vehicles → Vehicle Registry (from sidebar)
URL: http://localhost/FuelCapstone/vehicle_registry.php
```

### Step 3️⃣: Register Your First Vehicle (2 minutes)
```
1. Click "Register New Vehicle" button
2. Fill in:
   - Vehicle Plate: BFP-001
   - Type: Fire Truck
   - Make: Toyota
   - Model: Hiace
   - Year: 2020
   - Fuel Type: Gasoline
   - Tank Capacity: 100
   - Current Fuel: 50

3. Optionally:
   - Color: Red
   - Upload photo
   - Enable GPS + Device ID: GPS-001
   - Enable Sensor + Device ID: SENSOR-001

4. Click "Register Vehicle"
```

### Step 4️⃣: Manage Vehicles (Ongoing)
```
In the vehicles table:
👁️  = View full details
⛽ = Update fuel level
🔧 = Configure GPS/Sensors
```

---

## 🎯 What You Can Do Now

### Register Vehicles
- ✅ Complete vehicle details (make, model, year, etc.)
- ✅ Upload photos
- ✅ Set fuel capacity and current levels
- ✅ Configure GPS tracking
- ✅ Configure sensor monitoring

### View Information
- ✅ See all vehicle details in a beautiful modal
- ✅ View vehicle photos
- ✅ Check GPS and sensor status
- ✅ Monitor fuel levels

### Update Data
- ✅ Change fuel levels quickly
- ✅ Update GPS/Sensor configuration
- ✅ Modify vehicle status
- ✅ Edit any vehicle information

---

## 🔑 Important Fields

| Field | Required | Notes |
|-------|----------|-------|
| Vehicle Plate No | ✅ | Must be unique |
| Vehicle Type | ✅ | Select from list |
| Make | ✅ | Manufacturer |
| Model | ✅ | Model name |
| Year | ✅ | Manufacturing year |
| Fuel Type | ✅ | Gasoline, Diesel, etc |
| Tank Capacity | ✅ | In liters |
| Current Fuel | ❌ | Can update later |
| GPS Device ID | ❌ | Only if GPS enabled |
| Sensor Device ID | ❌ | Only if Sensor enabled |
| Vehicle Photo | ❌ | JPG, PNG, or GIF |

---

## 📞 Quick Help

### Photo not uploading?
- ✅ Check file format (JPG, PNG, GIF only)
- ✅ Check file size (should be under 10MB)
- ✅ Ensure uploads/vehicle_photos/ folder exists

### Can't find Vehicle Registry?
- ✅ Click sidebar "Vehicles" dropdown
- ✅ Click "Vehicle Registry"
- ✅ Or use URL: vehicle_registry.php

### GPS/Sensor fields not showing?
- ✅ Check the enable checkbox first
- ✅ Then Device ID field will appear

---

## 🌟 Pro Tips

1. **Use descriptive vehicle numbers**: BFP-001, BFP-002 (easy to remember)

2. **Keep fuel levels updated**: Helps track consumption

3. **Enable GPS/Sensors**: Makes fleet tracking possible later

4. **Upload clear photos**: Makes vehicle identification easier

5. **Set status correctly**: Helps with fleet management

---

## 📊 Sample Data

Try registering with this sample data:

**Vehicle 1: Fire Truck**
```
Plate: BFP-001
Type: Fire Truck
Make: Toyota
Model: Hino
Year: 2019
Fuel Capacity: 120L
Current Fuel: 85L
```

**Vehicle 2: Rescue Truck**
```
Plate: BFP-002
Type: Rescue Truck
Make: Isuzu
Model: NPR
Year: 2020
Fuel Capacity: 100L
Current Fuel: 60L
```

---

## ✨ What Happens Next

Once you register vehicles, you can:
- Track fuel consumption over time
- Monitor GPS locations (when integrated)
- Get sensor data (when integrated)
- View vehicle history
- Generate reports

---

## 🔐 Security

- All data is protected
- Photos are stored securely
- User authentication required
- No sensitive data exposed

---

**Ready to start? Go to:** `http://localhost/FuelCapstone/vehicle_registry.php` 🚀
