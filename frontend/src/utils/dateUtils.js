import {
  formatServerDate,
  formatServerDateTime,
  toServerCalendarDate,
  toServerDateInput,
} from '../services/serverClock';

/**
 * Format tanggal ke format Indonesia (DD/MM/YYYY)
 * @param {string} date - Tanggal dalam format ISO atau string yang valid
 * @returns {string} Tanggal terformat
 */
export const formatDate = (date) => {
  if (!date) return '-';
  
  try {
    const formatted = formatServerDate(date, 'id-ID', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric'
    });
    return formatted || '-';
  } catch (error) {
    console.error('Error formatting date:', error);
    return '-';
  }
};

/**
 * Format rentang tanggal (DD/MM/YYYY - DD/MM/YYYY)
 * @param {string} startDate - Tanggal mulai
 * @param {string} endDate - Tanggal selesai
 * @returns {string} Rentang tanggal terformat
 */
export const formatDateRange = (startDate, endDate) => {
  if (!startDate || !endDate) return '-';
  
  try {
    return `${formatDate(startDate)} - ${formatDate(endDate)}`;
  } catch (error) {
    console.error('Error formatting date range:', error);
    return '-';
  }
};

/**
 * Hitung jumlah hari antara dua tanggal
 * @param {string} startDate - Tanggal mulai
 * @param {string} endDate - Tanggal selesai
 * @returns {number} Jumlah hari
 */
export const getDaysBetween = (startDate, endDate) => {
  if (!startDate || !endDate) return 0;
  
  try {
    const start = toServerCalendarDate(startDate);
    const end = toServerCalendarDate(endDate);
    if (!start || !end) {
      return 0;
    }

    const diffTime = Math.abs(end - start);
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1; // +1 karena inklusif
    return diffDays;
  } catch (error) {
    console.error('Error calculating days between:', error);
    return 0;
  }
};

/**
 * Format tanggal ke format Indonesia lengkap (Senin, 1 Januari 2024)
 * @param {string} date - Tanggal dalam format ISO atau string yang valid
 * @returns {string} Tanggal terformat lengkap
 */
export const formatDateLong = (date) => {
  if (!date) return '-';
  
  try {
    const formatted = formatServerDate(date, 'id-ID', {
      weekday: 'long',
      day: 'numeric',
      month: 'long',
      year: 'numeric'
    });
    return formatted || '-';
  } catch (error) {
    console.error('Error formatting long date:', error);
    return '-';
  }
};

/**
 * Format tanggal dan waktu (DD/MM/YYYY HH:mm)
 * @param {string} datetime - Tanggal dan waktu dalam format ISO atau string yang valid
 * @returns {string} Tanggal dan waktu terformat
 */
export const formatDateTime = (datetime) => {
  if (!datetime) return '-';
  
  try {
    const formatted = formatServerDateTime(datetime, 'id-ID', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
    return formatted || '-';
  } catch (error) {
    console.error('Error formatting datetime:', error);
    return '-';
  }
};

/**
 * Cek apakah tanggal valid
 * @param {string} date - Tanggal dalam format ISO atau string yang valid
 * @returns {boolean} True jika tanggal valid
 */
export const isValidDate = (date) => {
  if (!date) return false;
  
  try {
    return Boolean(toServerDateInput(date));
  } catch (error) {
    return false;
  }
};

/**
 * Format durasi dalam menit ke format jam:menit
 * @param {number} minutes - Durasi dalam menit
 * @returns {string} Durasi terformat
 */
export const formatDuration = (minutes) => {
  if (!minutes || isNaN(minutes)) return '00:00';
  
  try {
    const hours = Math.floor(minutes / 60);
    const mins = minutes % 60;
    return `${hours.toString().padStart(2, '0')}:${mins.toString().padStart(2, '0')}`;
  } catch (error) {
    console.error('Error formatting duration:', error);
    return '00:00';
  }
};
