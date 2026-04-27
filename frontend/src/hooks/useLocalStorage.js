import { useState, useCallback, useEffect, useRef } from 'react';

export const useLocalStorage = (key, initialValue) => {
  // Use ref to track if we're initializing
  const isInitializing = useRef(true);
  
  // Optimized initial value getter
  const getInitialValue = useCallback(() => {
    if (typeof window === 'undefined') return initialValue;
    
    try {
      const item = window.localStorage.getItem(key);
      return item ? JSON.parse(item) : initialValue;
    } catch (error) {
      console.warn(`Error reading localStorage key "${key}":`, error);
      return initialValue;
    }
  }, [key, initialValue]);

  // State dengan nilai awal dari localStorage
  const [storedValue, setStoredValue] = useState(getInitialValue);

  // Mark initialization as complete after first render
  useEffect(() => {
    isInitializing.current = false;
  }, []);

  // Optimized setValue dengan debouncing untuk localStorage writes
  const setValue = useCallback((value) => {
    try {
      const valueToStore = value instanceof Function ? value(storedValue) : value;
      
      // Update state immediately
      setStoredValue(valueToStore);
      
      // Debounce localStorage writes to avoid excessive I/O
      if (!isInitializing.current) {
        const timeoutId = setTimeout(() => {
          try {
            window.localStorage.setItem(key, JSON.stringify(valueToStore));
          } catch (error) {
            console.warn(`Error setting localStorage key "${key}":`, error);
          }
        }, 100);
        
        return () => clearTimeout(timeoutId);
      }
    } catch (error) {
      console.warn(`Error setting localStorage key "${key}":`, error);
    }
  }, [key, storedValue]);

  // Optimized storage event listener
  useEffect(() => {
    if (typeof window === 'undefined') return;

    const handleStorageChange = (e) => {
      if (e.key === key && e.newValue !== null) {
        try {
          const newValue = JSON.parse(e.newValue);
          setStoredValue(newValue);
        } catch (error) {
          console.warn(`Error parsing localStorage value for key "${key}":`, error);
        }
      }
    };

    window.addEventListener('storage', handleStorageChange);
    return () => window.removeEventListener('storage', handleStorageChange);
  }, [key]);

  return [storedValue, setValue];
};
