import { useState, useEffect } from 'react';
import api from '../services/api';

const useMyAttendanceStatus = () => {
  const [myAttendance, setMyAttendance] = useState({
    has_attendance: false,
    check_in: null,
    check_out: null,
    status: 'Belum Absen',
    duration: null,
    location_in: null,
    location_out: null
  });
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  const fetchMyAttendanceStatus = async () => {
    try {
      setLoading(true);
      const response = await api.get('/dashboard/my-attendance-status');
      setMyAttendance(response.data);
    } catch (err) {
      setError(err.message);
      console.error('Error fetching attendance status:', err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchMyAttendanceStatus();
  }, []);

  const refreshAttendanceStatus = () => {
    fetchMyAttendanceStatus();
  };

  return {
    myAttendance,
    loading,
    error,
    refreshAttendanceStatus
  };
};

export default useMyAttendanceStatus;
