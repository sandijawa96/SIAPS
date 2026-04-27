import React, { useEffect, Suspense } from 'react';
import { RouterProvider } from 'react-router-dom';
import { ThemeProvider, createTheme } from '@mui/material/styles';
import { CssBaseline, StyledEngineProvider } from '@mui/material';
import { Toaster } from 'react-hot-toast';
import { AuthProvider } from './hooks/useAuth';
import { SidebarProvider } from './hooks/useSidebarController';
import { ServerClockProvider } from './contexts/ServerClockContext';
import Toast from './components/Toast';
import LoadingScreen from './components/LoadingScreen';
import ErrorBoundary from './components/ErrorBoundary';
import useLiveTrackingSender from './hooks/useLiveTrackingSender';
import router from './router';
import theme from './theme';
import { registerServiceWorker } from './utils/performanceOptimizations';

// Enhanced content component with performance monitoring
const AppContent = React.memo(() => {
  return (
    <Suspense fallback={<LoadingScreen message="Memuat halaman..." />}>
      <RouterProvider router={router} />
    </Suspense>
  );
});

AppContent.displayName = 'AppContent';

const LiveTrackingBootstrap = React.memo(() => {
  useLiveTrackingSender();
  return null;
});

LiveTrackingBootstrap.displayName = 'LiveTrackingBootstrap';

const App = () => {
  // Register service worker for performance
  useEffect(() => {
    registerServiceWorker();
  }, []);

  return (
    <StyledEngineProvider injectFirst>
      <ThemeProvider theme={theme}>
        <CssBaseline />
        <ErrorBoundary>
          <ServerClockProvider>
            <AuthProvider>
              <SidebarProvider>
                <LiveTrackingBootstrap />
                <AppContent />
                <Toast />
                <Toaster
                  position="top-right"
                  toastOptions={{
                    duration: 4000,
                    style: {
                      background: '#363636',
                      color: '#fff',
                    },
                    success: {
                      duration: 3000,
                      theme: {
                        primary: 'green',
                        secondary: 'black',
                      },
                    },
                  }}
                />
              </SidebarProvider>
            </AuthProvider>
          </ServerClockProvider>
        </ErrorBoundary>
      </ThemeProvider>
    </StyledEngineProvider>
  );
};

export default React.memo(App);
