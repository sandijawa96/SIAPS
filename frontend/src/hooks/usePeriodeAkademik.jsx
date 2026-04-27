import { useState, useEffect, useCallback } from 'react';
import { periodeAkademikService } from '../services/periodeAkademikService';

export const usePeriodeAkademik = (tahunAjaranId = null) => {
    const [periodeList, setPeriodeList] = useState([]);
    const [currentPeriode, setCurrentPeriode] = useState(null);
    const [loading, setLoading] = useState(false);

    // Fetch all periode akademik
    const fetchPeriodeList = useCallback(async (params = {}) => {
        try {
            setLoading(true);
            const queryParams = tahunAjaranId
                ? { ...params, tahun_ajaran_id: tahunAjaranId, no_pagination: true }
                : { ...params, no_pagination: true };
            const response = await periodeAkademikService.getAll(queryParams);
            const payload = response?.data;
            const normalizedList = Array.isArray(payload)
                ? payload
                : (Array.isArray(payload?.data) ? payload.data : []);
            setPeriodeList(normalizedList);
        } catch (error) {
            console.error('Gagal mengambil data periode akademik', error);
            setPeriodeList([]);
        } finally {
            setLoading(false);
        }
    }, [tahunAjaranId]);

    // Fetch current periode
    const fetchCurrentPeriode = useCallback(async () => {
        try {
            const response = await periodeAkademikService.getCurrentPeriode();
            setCurrentPeriode(response?.data ?? null);
        } catch (error) {
            // 404 on this endpoint means no running period, not a fatal error.
            if (error?.response?.status === 404) {
                setCurrentPeriode(null);
                return;
            }
            console.error('Gagal mengambil periode akademik saat ini', error);
        }
    }, []);

    // Create new periode
    const createPeriode = async (data) => {
        try {
            setLoading(true);
            const response = await periodeAkademikService.create(data);
            setPeriodeList(prev => [...prev, response.data]);
            await fetchPeriodeList(); // Refresh data
            return response.data;
        } catch (error) {
            console.error('Gagal membuat periode akademik', error);
            throw error;
        } finally {
            setLoading(false);
        }
    };

    // Update periode
    const updatePeriode = async (id, data) => {
        try {
            setLoading(true);
            const response = await periodeAkademikService.update(id, data);
            setPeriodeList(prev => 
                prev.map(item => item.id === id ? response.data : item)
            );
            await fetchPeriodeList(); // Refresh data
            return response.data;
        } catch (error) {
            console.error('Gagal memperbarui periode akademik', error);
            throw error;
        } finally {
            setLoading(false);
        }
    };

    // Delete periode
    const deletePeriode = async (id) => {
        try {
            setLoading(true);
            await periodeAkademikService.delete(id);
            setPeriodeList(prev => prev.filter(item => item.id !== id));
            await fetchPeriodeList(); // Refresh data
        } catch (error) {
            console.error('Gagal menghapus periode akademik', error);
            throw error;
        } finally {
            setLoading(false);
        }
    };

    // Check absensi validity
    const checkAbsensiValidity = async (params) => {
        try {
            const response = await periodeAkademikService.checkAbsensiValidity(params);
            return response.data;
        } catch (error) {
            console.error('Gagal memeriksa validitas absensi', error);
            throw error;
        }
    };

    // Filter periode by jenis
    const filterByJenis = (jenis) => {
        return periodeList.filter(periode => periode.jenis === jenis);
    };

    // Get active periode
    const getActivePeriode = () => {
        return periodeList.filter(periode => periode.is_active);
    };

    // Get periode by semester
    const getBySemester = (semester) => {
        return periodeList.filter(periode => periode.semester === semester);
    };

    // Load data on mount and when tahunAjaranId changes
    useEffect(() => {
        fetchPeriodeList();
        fetchCurrentPeriode();
    }, [fetchPeriodeList, fetchCurrentPeriode]);

    return {
        periodeList,
        currentPeriode,
        loading,
        createPeriode,
        updatePeriode,
        deletePeriode,
        checkAbsensiValidity,
        filterByJenis,
        getActivePeriode,
        getBySemester,
        refreshData: fetchPeriodeList
    };
};

export default usePeriodeAkademik;
