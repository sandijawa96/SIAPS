import { useState, useCallback } from 'react';

export const useSidebarMenu = () => {
  const [openCategories, setOpenCategories] = useState({});

  // Toggle category dengan auto-close untuk kategori lain
  const toggleCategory = useCallback((categoryName) => {
    setOpenCategories(prev => {
      // Jika kategori yang diklik sudah terbuka, tutup saja
      if (prev[categoryName]) {
        return {
          ...prev,
          [categoryName]: false
        };
      }
      
      // Jika kategori yang diklik belum terbuka, buka dan tutup yang lain
      const newState = {};
      Object.keys(prev).forEach(key => {
        newState[key] = false; // Tutup semua kategori
      });
      newState[categoryName] = true; // Buka kategori yang diklik
      
      return newState;
    });
  }, []);

  // Set kategori aktif (untuk auto-expand saat route change)
  const setActiveCategory = useCallback((categoryName) => {
    setOpenCategories(prev => ({
      ...prev,
      [categoryName]: true
    }));
  }, []);

  // Close all categories
  const closeAllCategories = useCallback(() => {
    setOpenCategories({});
  }, []);

  return {
    openCategories,
    toggleCategory,
    setActiveCategory,
    closeAllCategories
  };
};
