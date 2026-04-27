import React, { useState } from 'react';
import { Container, Box, Button } from '@mui/material';
import { Settings, Download } from 'lucide-react';
import TrackingSettings from '../components/live-tracking/TrackingSettings';
import ExportDialog from '../components/live-tracking/ExportDialog';
import { useServerClock } from '../hooks/useServerClock';
import { formatServerDateTime } from '../services/serverClock';

const TestLiveTracking = () => {
  const { serverNowMs } = useServerClock();
  const serverNowLabel = formatServerDateTime(serverNowMs, 'id-ID') || '-';
  // States
  const [showSettings, setShowSettings] = useState(false);
  const [showExport, setShowExport] = useState(false);
  const [settings, setSettings] = useState({
    refresh: {
      interval: 30,
      autoRefresh: true,
      refreshOnFocus: true
    },
    map: {
      defaultZoom: 15,
      theme: 'default',
      showTrafficLayer: false,
      autoCenter: true
    },
    display: {
      showInactiveStudents: true,
      showLastLocation: true,
      showAccuracyCircle: true,
      maxStudentsInList: 100
    },
    notifications: {
      enabled: true,
      studentOutOfArea: true,
      connectionLost: true
    }
  });

  // Mock data for testing
  const mockStudents = [
    {
      id: 1,
      name: 'John Doe',
      class: '10A',
      status: 'active',
      isInSchoolArea: true,
      lastUpdate: serverNowLabel
    },
    {
      id: 2,
      name: 'Jane Smith',
      class: '10A',
      status: 'inactive',
      isInSchoolArea: false,
      lastUpdate: serverNowLabel
    }
  ];

  const mockFilters = {
    status: 'all',
    area: 'all',
    search: '',
    class: ''
  };

  // Handlers
  const handleExport = () => {
    setShowExport(true);
  };

  const handleSettings = () => {
    setShowSettings(true);
  };

  const handleExportSubmit = (exportSettings) => {
    console.log('Export settings:', exportSettings);
    setShowExport(false);
  };

  const handleSettingsSave = (newSettings) => {
    console.log('New settings:', newSettings);
    setSettings(newSettings);
    setShowSettings(false);
  };

  return (
    <Container maxWidth="xl" className="py-8 space-y-6">
      <Box className="flex justify-between items-center">
        <h1 className="text-2xl font-bold">Test Live Tracking</h1>
        <Box className="space-x-2">
          <Button
            variant="outlined"
            startIcon={<Download />}
            onClick={handleExport}
          >
            Export
          </Button>
          <Button
            variant="outlined"
            startIcon={<Settings />}
            onClick={handleSettings}
          >
            Settings
          </Button>
        </Box>
      </Box>

      {/* Settings Dialog */}
      <TrackingSettings
        open={showSettings}
        onClose={() => setShowSettings(false)}
        settings={settings}
        onSave={handleSettingsSave}
        onReset={() => {
          setSettings({
            refresh: {
              interval: 30,
              autoRefresh: true,
              refreshOnFocus: true
            },
            map: {
              defaultZoom: 15,
              theme: 'default',
              showTrafficLayer: false,
              autoCenter: true
            },
            display: {
              showInactiveStudents: true,
              showLastLocation: true,
              showAccuracyCircle: true,
              maxStudentsInList: 100
            },
            notifications: {
              enabled: true,
              studentOutOfArea: true,
              connectionLost: true
            }
          });
        }}
      />

      {/* Export Dialog */}
      <ExportDialog
        open={showExport}
        onClose={() => setShowExport(false)}
        onExport={handleExportSubmit}
        students={mockStudents}
        filters={mockFilters}
        loading={false}
        error={null}
      />

      {/* Current Settings Display */}
      <Box className="bg-white rounded-lg shadow p-4 mt-4">
        <h2 className="text-lg font-semibold mb-2">Current Settings</h2>
        <pre className="bg-gray-50 p-4 rounded overflow-auto">
          {JSON.stringify(settings, null, 2)}
        </pre>
      </Box>
    </Container>
  );
};

export default TestLiveTracking;
