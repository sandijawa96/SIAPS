import React, { Suspense, useEffect, useState } from 'react';
import { Outlet, useLocation } from 'react-router-dom';
import { Box, Drawer, useTheme, useMediaQuery } from '@mui/material';
import Sidebar from './Sidebar';
import Header from './layout/Header';
import BroadcastAnnouncementPopup from './notifications/BroadcastAnnouncementPopup';
import { useSidebarController } from '../hooks/useSidebarController';

// Enhanced loading fallback with dynamic animation
const LoadingFallback = () => (
  <Box
    sx={{
      display: 'flex',
      flexDirection: 'column',
      justifyContent: 'center',
      alignItems: 'center',
      height: '300px',
      gap: 3
    }}
  >
    <Box
      sx={{
        position: 'relative',
        width: 60,
        height: 60
      }}
    >
      {/* Outer ring */}
      <Box
        sx={{
          position: 'absolute',
          width: '100%',
          height: '100%',
          border: '3px solid',
          borderColor: 'primary.light',
          borderTopColor: 'primary.main',
          borderRadius: '50%',
          animation: 'spin 1.5s linear infinite',
          '@keyframes spin': {
            '0%': { transform: 'rotate(0deg)' },
            '100%': { transform: 'rotate(360deg)' }
          }
        }}
      />
      {/* Inner ring */}
      <Box
        sx={{
          position: 'absolute',
          top: '50%',
          left: '50%',
          transform: 'translate(-50%, -50%)',
          width: '70%',
          height: '70%',
          border: '2px solid',
          borderColor: 'primary.dark',
          borderBottomColor: 'transparent',
          borderRadius: '50%',
          animation: 'spinReverse 1s linear infinite',
          '@keyframes spinReverse': {
            '0%': { transform: 'translate(-50%, -50%) rotate(0deg)' },
            '100%': { transform: 'translate(-50%, -50%) rotate(-360deg)' }
          }
        }}
      />
    </Box>
    
    {/* Loading text with pulse animation */}
    <Box
      sx={{
        display: 'flex',
        gap: 0.5,
        alignItems: 'center'
      }}
    >
      {['M', 'e', 'm', 'u', 'a', 't'].map((letter, index) => (
        <Box
          key={index}
          sx={{
            fontSize: '1rem',
            fontWeight: 600,
            color: 'primary.main',
            animation: `pulse 1.5s ease-in-out infinite`,
            animationDelay: `${index * 0.1}s`,
            '@keyframes pulse': {
              '0%, 100%': { opacity: 0.4, transform: 'scale(1)' },
              '50%': { opacity: 1, transform: 'scale(1.1)' }
            }
          }}
        >
          {letter}
        </Box>
      ))}
      <Box sx={{ ml: 1, fontSize: '1rem', color: 'text.secondary' }}>...</Box>
    </Box>
  </Box>
);

const Layout = () => {
  const theme = useTheme();
  const isMobile = useMediaQuery(theme.breakpoints.down('lg'));
  const { isMobileOpen, toggleMobile, closeMobile } = useSidebarController();
  const location = useLocation();
  const [isTransitioning, setIsTransitioning] = useState(false);

  // Handle page transitions
  useEffect(() => {
    setIsTransitioning(true);
    const timer = setTimeout(() => {
      setIsTransitioning(false);
    }, 200);
    return () => clearTimeout(timer);
  }, [location.pathname]);

  const drawerWidth = 280;

  return (
    <Box sx={{ 
      display: 'flex', 
      minHeight: '100vh',
      backgroundColor: theme.palette.background.default 
    }}>
      {/* Mobile Drawer */}
      <Drawer
        variant="temporary"
        open={isMobileOpen}
        onClose={closeMobile}
        ModalProps={{
          keepMounted: true
        }}
        sx={{
          display: { xs: 'block', lg: 'none' },
          '& .MuiDrawer-paper': {
            boxSizing: 'border-box',
            width: drawerWidth,
            border: 'none',
            borderRadius: 0
          },
          '& .MuiBackdrop-root': {
            backgroundColor: 'rgba(0,0,0,0.6)',
            backdropFilter: 'blur(4px)'
          }
        }}
      >
        <Sidebar onClose={closeMobile} />
      </Drawer>

      {/* Desktop Sidebar */}
      <Box
        component="nav"
        sx={{
          width: { lg: drawerWidth },
          flexShrink: { lg: 0 },
          display: { xs: 'none', lg: 'block' },
          height: '100vh',
          position: 'sticky',
          top: 0,
          left: 0,
          zIndex: theme.zIndex.drawer
        }}
      >
        <Drawer
          variant="permanent"
          sx={{
            height: '100%',
            '& .MuiDrawer-paper': {
              boxSizing: 'border-box',
              width: drawerWidth,
              border: 'none',
              borderRadius: 0,
              position: 'static',
              height: '100%',
              overflowY: 'visible',
              '&::before': {
                content: '""',
                position: 'absolute',
                top: 0,
                right: 0,
                width: '1px',
                height: '64px',
                background: `linear-gradient(135deg, ${theme.palette.primary.main} 0%, ${theme.palette.primary.dark} 100%)`,
                zIndex: 1
              }
            }
          }}
          open
        >
          <Sidebar />
        </Drawer>
      </Box>

      {/* Main Content */}
      <Box
        component="main"
        sx={{
          flexGrow: 1,
          width: { xs: '100%', lg: `calc(100% - ${drawerWidth}px)` },
          minHeight: '100vh',
          display: 'flex',
          flexDirection: 'column'
        }}
      >
        {/* Header */}
        <Header onMenuClick={toggleMobile} />

        {/* Page Content */}
        <Box
          sx={{
            p: { xs: 2, sm: 3 },
            flex: 1,
            opacity: isTransitioning ? 0.8 : 1,
            transform: isTransitioning ? 'translateY(12px)' : 'translateY(0)',
            transition: 'all 0.3s cubic-bezier(0.4, 0, 0.2, 1)'
          }}
        >
          <Suspense fallback={<LoadingFallback />}>
            <Outlet />
          </Suspense>
        </Box>

        <BroadcastAnnouncementPopup />
      </Box>
    </Box>
  );
};

export default React.memo(Layout);
