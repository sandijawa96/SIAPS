import { useState, useEffect } from 'react';
import pegawaiService from '../services/pegawaiService.jsx';
import toast from 'react-hot-toast';
import { useAuth } from './useAuth';

export const usePegawaiManagement = () => {
  const { hasPermission } = useAuth();
  const canReadPegawai = hasPermission('view_pegawai') || hasPermission('manage_pegawai');
  const [pegawaiList, setPegawaiList] = useState([]);

  const normalizePegawaiPage = (response) => {
    // Shape from pegawaiService.getAll({ page }) => { data: { success, data: paginator } }
    const paginated = response?.data?.data;
    if (paginated && Array.isArray(paginated.data)) {
      return {
        data: paginated.data,
        currentPage: Number(paginated.current_page || 1),
        lastPage: Number(paginated.last_page || 1)
      };
    }

    // Fallbacks for legacy shapes
    if (Array.isArray(response?.data)) {
      return { data: response.data, currentPage: 1, lastPage: 1 };
    }

    if (Array.isArray(response)) {
      return { data: response, currentPage: 1, lastPage: 1 };
    }

    return { data: [], currentPage: 1, lastPage: 1 };
  };

  // Fetch pegawai data
  useEffect(() => {
    if (!canReadPegawai) {
      setPegawaiList([]);
      return;
    }

    const fetchPegawai = async () => {
      try {
        // Ambil semua halaman agar daftar wali kelas tidak terpotong page 1 saja.
        let page = 1;
        let lastPage = 1;
        const aggregated = [];

        do {
          const response = await pegawaiService.getAll({
            page,
            per_page: 100,
            sort_by: 'nama_lengkap',
            sort_direction: 'asc'
          });

          const { data, lastPage: resolvedLastPage } = normalizePegawaiPage(response);
          aggregated.push(...data);
          lastPage = resolvedLastPage;
          page += 1;
        } while (page <= lastPage);

        // Deduplicate by id to keep list stable if backend returns overlaps.
        const uniquePegawai = Array.from(
          new Map(aggregated.map((pegawai) => [pegawai.id, pegawai])).values()
        );

        // Map nama_lengkap to nama for consistency
        const mappedPegawai = uniquePegawai.map((pegawai) => ({
          ...pegawai,
          nama: pegawai.nama_lengkap || pegawai.nama
        }));

        // Keep deterministic order for UI dropdowns
        mappedPegawai.sort((a, b) => (a.nama || '').localeCompare(b.nama || '', 'id'));

        setPegawaiList(mappedPegawai);
      } catch (error) {
        if (error?.response?.status === 403) {
          // Not all roles can access pegawai listing endpoint.
          setPegawaiList([]);
          return;
        }

        console.error('Error fetching pegawai:', error);
        toast.error('Gagal memuat data pegawai');
        setPegawaiList([]);
      }
    };
    fetchPegawai();
  }, [canReadPegawai]);

  // Function to get available pegawai for wali kelas assignment
  const getAvailablePegawai = (excludeCurrentWali = null, kelasList = []) => {
    if (!Array.isArray(pegawaiList) || !Array.isArray(kelasList)) {
      return [];
    }

    const excludeId = excludeCurrentWali !== null ? Number(excludeCurrentWali) : null;

    // Get all assigned wali kelas IDs (except current one while editing)
    const assignedWaliIds = kelasList
      .filter((kelas) => kelas.wali_kelas_id && Number(kelas.wali_kelas_id) !== excludeId)
      .map((kelas) => Number(kelas.wali_kelas_id));

    // Filter pegawai: must have wali kelas role (or be current wali on edit) AND not assigned elsewhere
    return pegawaiList.filter((pegawai) => {
      const pegawaiId = Number(pegawai.id);
      const isCurrentWali = excludeId !== null && pegawaiId === excludeId;
      const hasWaliKelasRole = Array.isArray(pegawai.roles) && pegawai.roles.some((role) =>
        role.name === 'Wali_Kelas' ||
        role.name === 'Wali Kelas' ||
        role.name === 'wali_kelas' ||
        role.name === 'wali kelas' ||
        role.display_name === 'Wali Kelas' ||
        role.display_name === 'wali kelas'
      );
      const isAssignedElsewhere = assignedWaliIds.includes(pegawaiId);

      return (hasWaliKelasRole || isCurrentWali) && (!isAssignedElsewhere || isCurrentWali);
    });
  };

  return {
    pegawaiList,
    getAvailablePegawai
  };
};
