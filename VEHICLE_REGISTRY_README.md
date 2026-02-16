# 🚗 Vehicle Registry System - Documentation

## Overview

The Vehicle Registry system is a comprehensive vehicle management platform integrated into the BFP Fuel System. It allows administrators to register, manage, and track all fleet vehicles with detailed information including fuel management, GPS tracking, sensor configuration, and vehicle photography.

## Features

### 1. **Vehicle Registration**
Register new vehicles with comprehensive details:
- **Basic Information**: Vehicle number, type, make, model, year, color
- **Technical Details**: Engine number, chassis number
- **Fuel Management**: Fuel type, tank capacity, current fuel level
- **GPS Tracking**: Enable/disable GPS and configure GPS device ID
- **Sensor Monitoring**: Enable/disable sensors and configure sensor device ID
- **Vehicle Photography**: Upload vehicle photos for identification

### 2. **Vehicle Management**
View, edit, and manage all registered vehicles:
- View complete vehicle details in a detailed modal
- Update fuel levels in real-time
- Configure GPS and sensor devices
- View vehicle status (Available, Deployed, In Repair, Inactive)
- See vehicle photos in the list view

### 3. **Fuel Tracking**
- Track fuel capacity and current fuel level
- Update fuel levels when vehicles are refueled
- Monitor fuel type (Gasoline, Diesel, Hybrid, LPG)
- Display fuel percentage and capacity information

### 4. **GPS & Sensor Configuration**
- Enable/disable GPS tracking for each vehicle
- Assign GPS device IDs for tracking devices
- Enable/disable sensor monitoring
- Assign sensor device IDs for IoT sensors
- Easily toggle and update device configurations

### 5. **Vehicle Photography**
- Upload vehicle photos during registration
- Support for JPG, PNG, and GIF formats
- Drag-and-drop file upload support
- Display photos in vehicle list and detail views

## How to Use

### Initial Setup

1. **Run the Setup Script** (if database columns need to be added):
   ```
   Open: http://localhost/FuelCapstone/setup_vehicle_registry.php
   ```
   This will automatically add all necessary columns to the `vehicles` table.

2. **Access Vehicle Registry**:
   - Navigate to: **Vehicles → Vehicle Registry** from the sidebar
   - Or visit: `http://localhost/FuelCapstone/vehicle_registry.php`

### Registering a New Vehicle

1. Click the **"Register New Vehicle"** button
2. Fill in the form with the following information:
   
   **Basic Information** (Required):
   - Vehicle Plate Number (e.g., BFP-001)
   - Vehicle Type (Fire Truck, Rescue Truck, Ambulance, etc.)
   - Make (e.g., Toyota)
   - Model (e.g., Hiace)
   - Year (e.g., 2020)

   **Additional Details**:
   - Color
   - Engine Number
   - Chassis Number

   **Fuel Information** (Required):
   - Fuel Type (Gasoline, Diesel, Hybrid, LPG)
   - Fuel Tank Capacity (in liters)
   - Current Fuel Level (in liters)

   **GPS Configuration**:
   - Check "Enable GPS Tracking" if applicable
   - Enter GPS Device ID (e.g., GPS-001)

   **Sensor Configuration**:
   - Check "Enable Sensor Monitoring" if applicable
   - Enter Sensor Device ID (e.g., SENSOR-001)

   **Status**:
   - Select vehicle status (Available, Deployed, In Repair, Inactive)

   **Vehicle Photo**:
   - Upload a photo of the vehicle (JPG, PNG, or GIF)
   - Click or drag-and-drop to upload

3. Click **"Register Vehicle"** to save

### Managing Vehicles

In the vehicles table, you can:

**View Details** (👁️ icon):
- See complete vehicle information including all technical details
- View vehicle photo if uploaded
- Check GPS and sensor configuration status

**Update Fuel Level** (⛽ icon):
- Quick update of current fuel level
- Enter new fuel amount in liters

**Configure Sensors** (🔧 icon):
- Enable/disable GPS tracking
- Update GPS device ID
- Enable/disable sensor monitoring
- Update sensor device ID

## Database Schema

### New Vehicles Table Columns

| Column | Type | Description |
|--------|------|-------------|
| `make` | VARCHAR(100) | Vehicle manufacturer |
| `model` | VARCHAR(100) | Vehicle model |
| `year` | INT | Manufacturing year |
| `color` | VARCHAR(50) | Vehicle color |
| `engine_no` | VARCHAR(100) | Engine serial number |
| `chassis_no` | VARCHAR(100) | Chassis serial number |
| `fuel_type` | VARCHAR(50) | Type of fuel (gasoline, diesel, etc.) |
| `fuel_capacity` | DECIMAL(10,2) | Maximum fuel tank capacity in liters |
| `gps_enabled` | TINYINT | GPS tracking status (1=enabled, 0=disabled) |
| `gps_device_id` | VARCHAR(100) | ID of GPS tracking device |
| `sensor_enabled` | TINYINT | Sensor monitoring status (1=enabled, 0=disabled) |
| `sensor_device_id` | VARCHAR(100) | ID of IoT sensor device |
| `vehicle_photo` | VARCHAR(255) | Path to vehicle photo file |

## File Uploads

Vehicle photos are stored in: `uploads/vehicle_photos/`

File naming convention: `vehicle_[timestamp]_[vehicle-plate-number].[extension]`

Example: `vehicle_20260131101530_BFP001.jpg`

## API Integration Points

The system is ready for integration with:
- **GPS Tracking Systems**: Use `gps_device_id` to link with GPS devices
- **IoT Sensors**: Use `sensor_device_id` to link with sensor networks
- **Fuel Management Systems**: Track fuel consumption using `fuel_capacity` and `current_fuel`
- **Vehicle Maintenance Systems**: Use vehicle technical details for maintenance scheduling

## File Structure

```
FuelCapstone/
├── vehicle_registry.php          # Main vehicle registry page
├── setup_vehicle_registry.php    # Database setup script
├── Components/
│   └── Sidebar.php              # Updated sidebar with registry link
└── uploads/
    └── vehicle_photos/          # Vehicle photo storage
```

## Security Features

- **SQL Injection Prevention**: Using parameterized queries throughout
- **File Upload Validation**: 
  - Only images accepted (JPG, PNG, GIF)
  - File size limitations enforced
- **User Authentication**: Required login for access
- **Input Sanitization**: All user inputs are sanitized and escaped
- **Session Management**: Secure session handling

## Troubleshooting

### Issue: "Table columns not found" error
**Solution**: Run the setup script at `setup_vehicle_registry.php`

### Issue: Cannot upload vehicle photo
**Solution**: Check that `uploads/vehicle_photos/` directory has write permissions (755 or 777)

### Issue: Vehicle registry page not accessible from sidebar
**Solution**: Clear browser cache and reload the page

## Future Enhancements

- Real-time GPS tracking map integration
- Sensor data visualization dashboard
- Fuel consumption analytics
- Maintenance schedule tracking
- Vehicle history and activity logs
- Export vehicle records to PDF
- Barcode/QR code generation for vehicles
- Integration with fuel pump stations

## Support

For issues or questions regarding the vehicle registry system, please contact the administrator or check the system logs in `debug_logs.php`.

---

**Version**: 1.0
**Last Updated**: January 31, 2026
**Developed for**: BFP Fuel Capstone System
