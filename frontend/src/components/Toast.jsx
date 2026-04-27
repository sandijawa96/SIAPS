import { Toaster } from 'react-hot-toast';

const Toast = () => {
  return (
    <Toaster
      position="top-right"
      reverseOrder={false}
      gutter={8}
      containerClassName=""
      containerStyle={{}}
      toastOptions={{
        // Default options for all toasts
        className: '',
        duration: 5000,
        style: {
          background: '#fff',
          color: '#363636',
        },
        // Default options for specific types
        success: {
          duration: 3000,
          iconTheme: {
            primary: '#22c55e',
            secondary: '#fff',
          },
          style: {
            border: '1px solid #22c55e',
            padding: '16px',
          },
        },
        error: {
          duration: 4000,
          iconTheme: {
            primary: '#ef4444',
            secondary: '#fff',
          },
          style: {
            border: '1px solid #ef4444',
            padding: '16px',
          },
        },
        loading: {
          duration: Infinity,
          style: {
            border: '1px solid #3b82f6',
            padding: '16px',
          },
        },
      }}
    />
  );
};

export default Toast;
