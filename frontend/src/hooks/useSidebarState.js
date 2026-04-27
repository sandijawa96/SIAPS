import { useState, useCallback, useMemo } from 'react';
import { useLocalStorage } from './useLocalStorage';

export const useSidebarState = () => {
  // State untuk expanded sections - mulai dengan semua section collapsed
  const [expandedSections, setExpandedSections] = useLocalStorage('sidebar-expanded-sections', []);

  // State untuk mobile sidebar
  const [isMobileOpen, setIsMobileOpen] = useState(false);

  // Toggle section
  const toggleSection = useCallback((sectionId) => {
    setExpandedSections(current => {
      const isExpanded = current.includes(sectionId);
      if (isExpanded) {
        return current.filter(id => id !== sectionId);
      } else {
        return [...current, sectionId];
      }
    });
  }, [setExpandedSections]);

  // Check if section is expanded
  const isExpanded = useCallback((sectionId) => {
    return expandedSections.includes(sectionId);
  }, [expandedSections]);

  // Mobile handlers
  const toggleMobile = useCallback(() => {
    setIsMobileOpen(prev => !prev);
  }, []);

  const closeMobile = useCallback(() => {
    setIsMobileOpen(false);
  }, []);

  return useMemo(() => ({
    expandedSections,
    isMobileOpen,
    isExpanded,
    toggleSection,
    toggleMobile,
    closeMobile
  }), [
    expandedSections,
    isMobileOpen,
    isExpanded,
    toggleSection,
    toggleMobile,
    closeMobile
  ]);
};
