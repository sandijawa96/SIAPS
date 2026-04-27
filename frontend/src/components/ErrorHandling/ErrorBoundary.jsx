import React from 'react';
import { Alert, AlertTitle, Button, Card, CardContent, Typography, Box } from '@mui/material';
import { RefreshCw, AlertTriangle, Bug, Home } from 'lucide-react';
import { getServerIsoString, getServerNowEpochMs } from '../../services/serverClock';

class ErrorBoundary extends React.Component {
  constructor(props) {
    super(props);
    this.state = {
      hasError: false,
      error: null,
      errorInfo: null,
      errorId: null
    };
  }

  static getDerivedStateFromError(error) {
    // Update state so the next render will show the fallback UI
    return {
      hasError: true,
      errorId: `${getServerNowEpochMs().toString(36)}${Math.random().toString(36).slice(2)}`
    };
  }

  componentDidCatch(error, errorInfo) {
    // Log error details
    console.error('ErrorBoundary caught an error:', error, errorInfo);
    
    this.setState({
      error: error,
      errorInfo: errorInfo
    });

    // Send error to monitoring service (if available)
    this.logErrorToService(error, errorInfo);
  }

  logErrorToService = (error, errorInfo) => {
    try {
      // Log to console with structured format
      const errorReport = {
        timestamp: getServerIsoString(),
        errorId: this.state.errorId,
        message: error.message,
        stack: error.stack,
        componentStack: errorInfo.componentStack,
        userAgent: navigator.userAgent,
        url: window.location.href
      };

      console.error('🚨 Error Report:', errorReport);

      // Here you could send to external service like Sentry, LogRocket, etc.
      // Example: Sentry.captureException(error, { extra: errorInfo });
    } catch (loggingError) {
      console.error('Failed to log error:', loggingError);
    }
  };

  handleRetry = () => {
    this.setState({
      hasError: false,
      error: null,
      errorInfo: null,
      errorId: null
    });
  };

  handleGoHome = () => {
    window.location.href = '/';
  };

  render() {
    if (this.state.hasError) {
      const { error, errorInfo, errorId } = this.state;
      const isDevelopment = process.env.NODE_ENV === 'development';

      return (
        <Box className="min-h-screen flex items-center justify-center bg-gray-50 p-4">
          <Card className="max-w-2xl w-full">
            <CardContent className="p-6">
              <div className="text-center mb-6">
                <AlertTriangle className="w-16 h-16 text-red-500 mx-auto mb-4" />
                <Typography variant="h4" className="font-bold text-gray-900 mb-2">
                  Oops! Terjadi Kesalahan
                </Typography>
                <Typography variant="body1" className="text-gray-600">
                  Aplikasi mengalami error yang tidak terduga. Tim kami telah diberitahu tentang masalah ini.
                </Typography>
              </div>

              <Alert severity="error" className="mb-4">
                <AlertTitle>Error ID: {errorId}</AlertTitle>
                {error?.message || 'Unknown error occurred'}
              </Alert>

              {isDevelopment && (
                <Card variant="outlined" className="mb-4 bg-gray-50">
                  <CardContent>
                    <Typography variant="h6" className="flex items-center mb-2">
                      <Bug className="w-5 h-5 mr-2" />
                      Debug Information
                    </Typography>
                    <Typography variant="body2" className="font-mono text-sm mb-2">
                      <strong>Error:</strong> {error?.message}
                    </Typography>
                    {error?.stack && (
                      <details className="mb-2">
                        <summary className="cursor-pointer text-sm font-medium">
                          Stack Trace
                        </summary>
                        <pre className="text-xs bg-white p-2 rounded border mt-1 overflow-auto">
                          {error.stack}
                        </pre>
                      </details>
                    )}
                    {errorInfo?.componentStack && (
                      <details>
                        <summary className="cursor-pointer text-sm font-medium">
                          Component Stack
                        </summary>
                        <pre className="text-xs bg-white p-2 rounded border mt-1 overflow-auto">
                          {errorInfo.componentStack}
                        </pre>
                      </details>
                    )}
                  </CardContent>
                </Card>
              )}

              <div className="flex flex-col sm:flex-row gap-3 justify-center">
                <Button
                  variant="contained"
                  color="primary"
                  onClick={this.handleRetry}
                  startIcon={<RefreshCw className="w-4 h-4" />}
                  className="flex-1 sm:flex-none"
                >
                  Coba Lagi
                </Button>
                <Button
                  variant="outlined"
                  onClick={this.handleGoHome}
                  startIcon={<Home className="w-4 h-4" />}
                  className="flex-1 sm:flex-none"
                >
                  Kembali ke Beranda
                </Button>
              </div>

              <div className="mt-6 text-center">
                <Typography variant="body2" className="text-gray-500">
                  Jika masalah terus berlanjut, silakan hubungi administrator sistem.
                </Typography>
              </div>
            </CardContent>
          </Card>
        </Box>
      );
    }

    return this.props.children;
  }
}

export default ErrorBoundary;
