import { useState, useCallback } from 'react';
import { message } from 'antd';
import { liburNasionalService } from '../services/liburNasionalService';
import { eventAkademikService } from '../services/eventAkademikService';
import { toServerDateInput } from '../services/serverClock';

export const useLiburNasional = (tahunAjaranId) => {
  const [loading, setLoading] = useState(false);
  const [events, setEvents] = useState([]);

  const fetchEvents = useCallback(async () => {
    if (!tahunAjaranId) return;
    
    try {
      const response = await eventAkademikService.getAll({
        tahun_ajaran_id: tahunAjaranId,
        jenis: 'libur'
      });
      setEvents(response.data);
    } catch (error) {
      console.error('Error fetching libur:', error);
    }
  }, [tahunAjaranId]);

  const syncLiburNasional = useCallback(async () => {
    if (!tahunAjaranId) {
      message.warning('Pilih tahun ajaran terlebih dahulu');
      return;
    }

    try {
      setLoading(true);
      const result = await liburNasionalService.syncLiburNasional(tahunAjaranId);
      message.success(`Berhasil sync ${result.data.synced} libur nasional`);
      await fetchEvents(); // Refresh events after sync
    } catch (error) {
      console.error('Error syncing libur:', error);
      message.error('Gagal sync libur nasional: ' + error.message);
    } finally {
      setLoading(false);
    }
  }, [tahunAjaranId, fetchEvents]);

  const getLiburNasionalEvents = useCallback((date) => {
    const targetDate = toServerDateInput(date);
    if (!targetDate) {
      return [];
    }

    return events.filter(event => {
      const eventDate = toServerDateInput(event.tanggal_mulai);
      return eventDate === targetDate;
    });
  }, [events]);

  return {
    loading,
    events,
    syncLiburNasional,
    getLiburNasionalEvents,
    fetchEvents
  };
};
