import { useState, useEffect, useCallback, useMemo } from 'react';
import { debounce } from 'lodash';

/**
 * Custom hook untuk optimasi performance dengan error handling
 */
export const usePerformanceOptimization = () => {
  const [performanceMetrics, setPerformanceMetrics] = useState({
    apiResponseTimes: {},
    memoryUsage: null,
    renderTimes: {},
    errorCount: 0
  });

  // Track API response times
  const trackAPIPerformance = useCallback((endpoint, startTime) => {
    const duration = Date.now() - startTime;
    
    setPerformanceMetrics(prev => ({
      ...prev,
      apiResponseTimes: {
        ...prev.apiResponseTimes,
        [endpoint]: duration
      }
    }));

    // Log slow APIs
    if (duration > 1000) {
      console.warn(`🐌 Slow API: ${endpoint} took ${duration}ms`);
    }

    return duration;
  }, []);

  // Track memory usage
  const trackMemoryUsage = useCallback(() => {
    if (performance.memory) {
      const memoryInfo = {
        used: Math.round(performance.memory.usedJSHeapSize / 1024 / 1024),
        total: Math.round(performance.memory.totalJSHeapSize / 1024 / 1024),
        limit: Math.round(performance.memory.jsHeapSizeLimit / 1024 / 1024)
      };

      setPerformanceMetrics(prev => ({
        ...prev,
        memoryUsage: memoryInfo
      }));

      // Warn if memory usage is high
      if (memoryInfo.used > memoryInfo.limit * 0.8) {
        console.warn(`🚨 High memory usage: ${memoryInfo.used}MB / ${memoryInfo.limit}MB`);
      }

      return memoryInfo;
    }
    return null;
  }, []);

  // Track render times
  const trackRenderTime = useCallback((componentName, startTime) => {
    const duration = Date.now() - startTime;
    
    setPerformanceMetrics(prev => ({
      ...prev,
      renderTimes: {
        ...prev.renderTimes,
        [componentName]: duration
      }
    }));

    if (duration > 100) {
      console.warn(`🐌 Slow render: ${componentName} took ${duration}ms`);
    }

    return duration;
  }, []);

  // Track errors
  const trackError = useCallback((error, context = '') => {
    setPerformanceMetrics(prev => ({
      ...prev,
      errorCount: prev.errorCount + 1
    }));

    console.error(`❌ Error in ${context}:`, error);
  }, []);

  // Debounced search function
  const createDebouncedSearch = useCallback((searchFunction, delay = 300) => {
    return debounce(searchFunction, delay);
  }, []);

  // Memoized filter function
  const createMemoizedFilter = useCallback((filterFunction, dependencies) => {
    return useMemo(filterFunction, dependencies);
  }, []);

  // Monitor performance periodically
  useEffect(() => {
    const interval = setInterval(() => {
      trackMemoryUsage();
    }, 30000); // Every 30 seconds

    return () => clearInterval(interval);
  }, [trackMemoryUsage]);

  return {
    performanceMetrics,
    trackAPIPerformance,
    trackMemoryUsage,
    trackRenderTime,
    trackError,
    createDebouncedSearch,
    createMemoizedFilter
  };
};

/**
 * HOC untuk tracking performance komponen
 */
export const withPerformanceTracking = (WrappedComponent, componentName) => {
  return function PerformanceTrackedComponent(props) {
    const { trackRenderTime } = usePerformanceOptimization();
    const startTime = Date.now();

    useEffect(() => {
      trackRenderTime(componentName, startTime);
    }, []);

    return <WrappedComponent {...props} />;
  };
};

/**
 * Custom hook untuk API calls dengan performance tracking dan error handling
 */
export const useOptimizedAPI = () => {
  const { trackAPIPerformance, trackError } = usePerformanceOptimization();
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  const callAPI = useCallback(async (apiFunction, endpoint, ...args) => {
    const startTime = Date.now();
    setLoading(true);
    setError(null);

    try {
      const result = await apiFunction(...args);
      trackAPIPerformance(endpoint, startTime);
      return result;
    } catch (err) {
      trackError(err, endpoint);
      setError(err);
      throw err;
    } finally {
      setLoading(false);
    }
  }, [trackAPIPerformance, trackError]);

  return { callAPI, loading, error };
};
