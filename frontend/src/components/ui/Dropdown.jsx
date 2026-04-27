import React, { useEffect, useRef } from 'react';

const Dropdown = ({ 
  show, 
  onClose, 
  children, 
  className = '',
  align = 'right'
}) => {
  const dropdownRef = useRef(null);

  useEffect(() => {
    const handleEscape = (e) => {
      if (e.key === 'Escape') {
        onClose();
      }
    };

    const handleClickOutside = (e) => {
      if (dropdownRef.current && !dropdownRef.current.contains(e.target)) {
        onClose();
      }
    };

    if (show) {
      document.addEventListener('keydown', handleEscape);
      document.addEventListener('mousedown', handleClickOutside);
      return () => {
        document.removeEventListener('keydown', handleEscape);
        document.removeEventListener('mousedown', handleClickOutside);
      };
    }
  }, [show, onClose]);

  const alignmentClasses = {
    left: 'left-0',
    right: 'right-0',
    center: 'left-1/2 transform -translate-x-1/2'
  };

  if (!show) return null;

  return (
    <div
      ref={dropdownRef}
      className={`
        absolute z-50 mt-2 origin-top-right rounded-md bg-white py-1 shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none
        ${alignmentClasses[align]}
        ${className}
      `}
      style={{ 
        opacity: show ? 1 : 0,
        transition: 'opacity 0.15s ease-out'
      }}
    >
      {children}
    </div>
  );
};

export default Dropdown;
