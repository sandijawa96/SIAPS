import { useState, useCallback, useContext, createContext } from 'react';

const SidebarContext = createContext();

export const SidebarProvider = ({ children }) => {
  const [openCategories, setOpenCategories] = useState({});
  const [isMobileOpen, setIsMobileOpen] = useState(false);

  const toggleCategory = useCallback((categoryName) => {
    setOpenCategories(prev => {
      // Close other categories when opening a new one (accordion behavior)
      const otherCategories = Object.keys(prev).reduce((acc, key) => {
        if (key !== categoryName) {
          acc[key] = false;
        }
        return acc;
      }, {});

      return {
        ...otherCategories,
        [categoryName]: !prev[categoryName]
      };
    });
  }, []);

  const setActiveCategory = useCallback((categoryName) => {
    setOpenCategories(prev => ({
      ...prev,
      [categoryName]: true
    }));
  }, []);

  const toggleMobile = useCallback(() => {
    setIsMobileOpen(prev => !prev);
  }, []);

  const closeMobile = useCallback(() => {
    setIsMobileOpen(false);
  }, []);

  const openMobile = useCallback(() => {
    setIsMobileOpen(true);
  }, []);

  const value = {
    openCategories,
    isMobileOpen,
    toggleCategory,
    setActiveCategory,
    toggleMobile,
    closeMobile,
    openMobile
  };

  return (
    <SidebarContext.Provider value={value}>
      {children}
    </SidebarContext.Provider>
  );
};

export const useSidebarController = () => {
  const context = useContext(SidebarContext);
  if (!context) {
    throw new Error('useSidebarController must be used within a SidebarProvider');
  }
  return context;
};
