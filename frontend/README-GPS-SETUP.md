# Setup Pengaturan Lokasi GPS

## Prerequisites
1. Node.js v24 atau lebih tinggi
2. Google Maps API Key
3. React development environment

## Installation Steps

### 1. Install Dependencies
```bash
cd frontend
npm install @googlemaps/js-api-loader --legacy-peer-deps
```

### 2. Setup Environment Variables
Buat file `.env` di root folder frontend:
```
REACT_APP_GOOGLE_MAPS_API_KEY=your_google_maps_api_key_here
REACT_APP_API_URL=http://localhost:8000/api
```

### 3. Get Google Maps API Key
1. Buka [Google Cloud Console](https://console.cloud.google.com/)
2. Buat project baru atau pilih existing project
3. Enable APIs:
   - Maps JavaScript API
   - Places API (optional)
   - Geocoding API (optional)
4. Buat API Key di Credentials
5. Restrict API Key:
   - Application restrictions: HTTP referrers
   - Add your domain (e.g., localhost:3000, your-domain.com)
   - API restrictions: Select specific APIs

### 4. Start Development Server
```bash
npm start
```

## File Structure
```
frontend/src/
├── components/
│   └── lokasi-gps/
│       ├── MapComponent.jsx          # Interactive Google Maps
│       ├── LocationForm.jsx          # Add/Edit location form
│       ├── MonitoringDashboard.jsx   # Real-time monitoring
│       └── ImportExportTools.jsx     # Import/Export utilities
├── pages/
│   └── ManajemenLokasiGPS.jsx        # Main GPS management page
└── .env                              # Environment variables
```

## Features Implemented

### 1. Interactive Map (MapComponent)
- Google Maps integration
- Draggable markers
- Editable radius circles
- Multiple map types (roadmap, satellite, hybrid)
- Real-time coordinate updates

### 2. Location Management Form (LocationForm)
- Comprehensive form validation
- Role-based access control
- Time and day scheduling
- Color picker for markers
- Real-time map preview

### 3. Monitoring Dashboard (MonitoringDashboard)
- Statistics cards
- Live activity feed
- Error logging
- Real-time map view

### 4. Import/Export Tools (ImportExportTools)
- Multiple format support (CSV, JSON, KML, GPX)
- Template downloads
- Batch operations
- Progress tracking

## Usage

### Accessing GPS Management
1. Start the application: `npm start`
2. Navigate to: `http://localhost:3000/manajemen-lokasi-gps`
3. Use the interface to:
   - Add new GPS locations
   - Edit existing locations
   - Monitor real-time activity
   - Import/Export location data

### Adding a New Location
1. Click "Tambah Lokasi" button
2. Fill in the form:
   - Nama Area (required)
   - Deskripsi (optional)
   - Set coordinates by clicking on map or dragging marker
   - Adjust radius by dragging circle edge
   - Select roles that can access this location
   - Set active time and days
3. Click "Simpan" to save

### Monitoring Locations
1. Click "Monitoring" button
2. View real-time statistics
3. Check recent activities
4. Monitor GPS errors

### Import/Export Data
1. Click "Import/Export" button
2. For Import:
   - Download template
   - Fill with your data
   - Upload file
3. For Export:
   - Choose format (CSV, JSON, KML, GPX)
   - Click export button

## API Integration

The components are designed to work with these API endpoints:

```
GET    /api/lokasi-gps           # List all locations
POST   /api/lokasi-gps           # Create new location
PUT    /api/lokasi-gps/{id}      # Update location
DELETE /api/lokasi-gps/{id}      # Delete location
POST   /api/lokasi-gps/import    # Import locations
GET    /api/lokasi-gps/export    # Export locations
GET    /api/lokasi-gps/stats     # Get statistics
```

## Data Format

Location data structure:
```json
{
  "id": 1,
  "nama": "Gedung Utama",
  "deskripsi": "Lokasi absensi gedung utama",
  "latitude": -6.2088,
  "longitude": 106.8456,
  "radius": 100,
  "status": "Aktif",
  "warna_marker": "#3B82F6",
  "roles": ["guru", "siswa", "staff"],
  "waktu_mulai": "06:00",
  "waktu_selesai": "18:00",
  "hari_aktif": ["senin", "selasa", "rabu", "kamis", "jumat"]
}
```

## Troubleshooting

### Map Not Loading
- Check if Google Maps API key is correct
- Verify API key restrictions
- Check browser console for errors
- Ensure Maps JavaScript API is enabled

### Import/Export Issues
- Verify file format matches template
- Check file size (max 10MB)
- Ensure data structure is correct

### Form Validation Errors
- Check required fields are filled
- Verify coordinate ranges (-90 to 90 for lat, -180 to 180 for lng)
- Ensure radius is between 10-1000 meters

## Development Notes

### Mock Data
Currently using mock data for development. Replace with actual API calls in production.

### Environment Variables
Make sure to set proper environment variables for production deployment.

### Security
- Restrict Google Maps API key properly
- Validate all inputs on both client and server side
- Implement proper authentication and authorization

## Next Steps

1. Connect to actual backend API
2. Implement real-time updates with WebSocket
3. Add more advanced features like geofencing alerts
4. Optimize for mobile devices
5. Add comprehensive testing
