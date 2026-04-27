import { useState, useEffect, useCallback } from 'react';
import { eventAkademikService } from '../services/eventAkademikService';
import { toServerDateInput } from '../services/serverClock';

export const useEventAkademik = (tahunAjaranId = null) => {
    const [eventList, setEventList] = useState([]);
    const [upcomingEvents, setUpcomingEvents] = useState([]);
    const [todayEvents, setTodayEvents] = useState([]);
    const [loading, setLoading] = useState(false);

    // Fetch all event akademik
    const fetchEventList = useCallback(async (params = {}) => {
        try {
            setLoading(true);
            const queryParams = tahunAjaranId
                ? { ...params, tahun_ajaran_id: tahunAjaranId, no_pagination: true }
                : { ...params, no_pagination: true };
            const response = await eventAkademikService.getAll(queryParams);
            const payload = response?.data;
            const normalizedList = Array.isArray(payload)
                ? payload
                : (Array.isArray(payload?.data) ? payload.data : []);
            setEventList(normalizedList);
        } catch (error) {
            console.error('Gagal mengambil data event akademik', error);
            setEventList([]);
        } finally {
            setLoading(false);
        }
    }, [tahunAjaranId]);

    // Fetch upcoming events
    const fetchUpcomingEvents = useCallback(async (days = 7) => {
        try {
            const response = await eventAkademikService.getUpcomingEvents(days);
            const payload = response?.data;
            const normalizedList = Array.isArray(payload)
                ? payload
                : (Array.isArray(payload?.data) ? payload.data : []);
            setUpcomingEvents(normalizedList);
        } catch (error) {
            console.error('Gagal mengambil event mendatang', error);
            setUpcomingEvents([]);
        }
    }, []);

    // Fetch today's events
    const fetchTodayEvents = useCallback(async () => {
        try {
            const response = await eventAkademikService.getTodayEvents();
            const payload = response?.data;
            const normalizedList = Array.isArray(payload)
                ? payload
                : (Array.isArray(payload?.data) ? payload.data : []);
            setTodayEvents(normalizedList);
        } catch (error) {
            console.error('Gagal mengambil event hari ini', error);
            setTodayEvents([]);
        }
    }, []);

    // Create new event
    const createEvent = async (data) => {
        try {
            setLoading(true);
            const response = await eventAkademikService.create(data);
            setEventList(prev => [...prev, response.data]);
            await fetchEventList(); // Refresh data
            await fetchUpcomingEvents(); // Refresh upcoming events
            await fetchTodayEvents(); // Refresh today events
            return response.data;
        } catch (error) {
            console.error('Gagal membuat event akademik', error);
            throw error;
        } finally {
            setLoading(false);
        }
    };

    // Update event
    const updateEvent = async (id, data) => {
        try {
            setLoading(true);
            const response = await eventAkademikService.update(id, data);
            setEventList(prev => 
                prev.map(item => item.id === id ? response.data : item)
            );
            await fetchEventList(); // Refresh data
            await fetchUpcomingEvents(); // Refresh upcoming events
            await fetchTodayEvents(); // Refresh today events
            return response.data;
        } catch (error) {
            console.error('Gagal memperbarui event akademik', error);
            throw error;
        } finally {
            setLoading(false);
        }
    };

    // Delete event
    const deleteEvent = async (id) => {
        try {
            setLoading(true);
            await eventAkademikService.delete(id);
            setEventList(prev => prev.filter(item => item.id !== id));
            await fetchEventList(); // Refresh data
            await fetchUpcomingEvents(); // Refresh upcoming events
            await fetchTodayEvents(); // Refresh today events
        } catch (error) {
            console.error('Gagal menghapus event akademik', error);
            throw error;
        } finally {
            setLoading(false);
        }
    };

    // Filter events by jenis
    const filterByJenis = (jenis) => {
        return eventList.filter(event => event.jenis === jenis);
    };

    // Get active events
    const getActiveEvents = () => {
        return eventList.filter(event => event.is_active);
    };

    // Get mandatory events
    const getMandatoryEvents = () => {
        return eventList.filter(event => event.is_wajib);
    };

    // Get events by date range
    const getEventsByDateRange = (startDate, endDate) => {
        const rangeStart = toServerDateInput(startDate);
        const rangeEnd = toServerDateInput(endDate);
        if (!rangeStart || !rangeEnd) {
            return [];
        }

        return eventList.filter(event => {
            const eventStart = toServerDateInput(event.tanggal_mulai);
            const eventEnd = toServerDateInput(event.tanggal_selesai || event.tanggal_mulai);
            if (!eventStart || !eventEnd) {
                return false;
            }

            return (eventStart >= rangeStart && eventStart <= rangeEnd) ||
                   (eventEnd >= rangeStart && eventEnd <= rangeEnd) ||
                   (eventStart <= rangeStart && eventEnd >= rangeEnd);
        });
    };

    // Get events by tingkat
    const getEventsByTingkat = (tingkatId) => {
        return eventList.filter(event => 
            !event.tingkat_id || event.tingkat_id === tingkatId
        );
    };

    // Get events by kelas
    const getEventsByKelas = (kelasId) => {
        return eventList.filter(event => 
            !event.kelas_id || event.kelas_id === kelasId
        );
    };

    // Load data on mount
    useEffect(() => {
        fetchEventList();
        fetchUpcomingEvents();
        fetchTodayEvents();
    }, [fetchEventList, fetchUpcomingEvents, fetchTodayEvents]);

    return {
        eventList,
        upcomingEvents,
        todayEvents,
        loading,
        createEvent,
        updateEvent,
        deleteEvent,
        filterByJenis,
        getActiveEvents,
        getMandatoryEvents,
        getEventsByDateRange,
        getEventsByTingkat,
        getEventsByKelas,
        refreshData: fetchEventList,
        refreshUpcoming: fetchUpcomingEvents,
        refreshToday: fetchTodayEvents
    };
};

export default useEventAkademik;
