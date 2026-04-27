import { useMemo } from 'react';

export const useAnimationConfig = () => {
  const animations = useMemo(() => ({
    // Fade animations
    fade: {
      initial: { opacity: 0 },
      animate: { opacity: 1 },
      exit: { opacity: 0 },
      transition: { duration: 0.2 }
    },

    // Slide animations
    slideRight: {
      initial: { x: -20, opacity: 0 },
      animate: { x: 0, opacity: 1 },
      exit: { x: -20, opacity: 0 },
      transition: { duration: 0.3, ease: 'easeInOut' }
    },
    
    slideLeft: {
      initial: { x: 20, opacity: 0 },
      animate: { x: 0, opacity: 1 },
      exit: { x: 20, opacity: 0 },
      transition: { duration: 0.3, ease: 'easeInOut' }
    },
    
    slideUp: {
      initial: { y: 20, opacity: 0 },
      animate: { y: 0, opacity: 1 },
      exit: { y: 20, opacity: 0 },
      transition: { duration: 0.3, ease: 'easeInOut' }
    },
    
    slideDown: {
      initial: { y: -20, opacity: 0 },
      animate: { y: 0, opacity: 1 },
      exit: { y: -20, opacity: 0 },
      transition: { duration: 0.3, ease: 'easeInOut' }
    },

    // Scale animations
    scale: {
      initial: { scale: 0.95, opacity: 0 },
      animate: { scale: 1, opacity: 1 },
      exit: { scale: 0.95, opacity: 0 },
      transition: { duration: 0.2, ease: 'easeOut' }
    },

    // Dropdown specific
    dropdown: {
      initial: { opacity: 0, y: -4, scale: 0.95 },
      animate: { opacity: 1, y: 0, scale: 1 },
      exit: { opacity: 0, y: -4, scale: 0.95 },
      transition: { duration: 0.2, ease: 'easeOut' }
    },

    // Sidebar specific
    sidebar: {
      initial: { x: '-100%' },
      animate: { x: 0 },
      exit: { x: '-100%' },
      transition: { duration: 0.3, ease: 'easeInOut' }
    },

    // Menu item specific
    menuItem: {
      initial: { x: -4, opacity: 0 },
      animate: { x: 0, opacity: 1 },
      exit: { x: -4, opacity: 0 },
      transition: { duration: 0.2 }
    },

    // Shared transition configs
    spring: {
      type: 'spring',
      stiffness: 400,
      damping: 30
    },
    
    easeInOut: {
      duration: 0.2,
      ease: 'easeInOut'
    }
  }), []);

  return animations;
};
