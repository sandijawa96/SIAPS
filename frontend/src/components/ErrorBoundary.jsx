import React from 'react';
import { AlertTriangle } from 'lucide-react';
import Button from './ui/Button';

class ErrorBoundary extends React.Component {
  constructor(props) {
    super(props);
    this.state = { hasError: false, error: null };
  }

  static getDerivedStateFromError(error) {
    return { hasError: true, error };
  }

  componentDidCatch(error, errorInfo) {
    console.error('Error caught by boundary:', error, errorInfo);
  }

  render() {
    if (this.state.hasError) {
      return (
        <div className="min-h-screen bg-gray-50 flex flex-col justify-center py-12 sm:px-6 lg:px-8">
          <div className="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
            <div className="bg-white py-8 px-4 shadow sm:rounded-lg sm:px-10">
              <div className="text-center">
                <AlertTriangle className="mx-auto h-12 w-12 text-yellow-500" />
                <h3 className="mt-2 text-lg font-medium text-gray-900">
                  Terjadi Kesalahan
                </h3>
                <div className="mt-4">
                  <div className="text-sm text-gray-500">
                    {this.state.error?.message || 'Terjadi kesalahan yang tidak diketahui'}
                  </div>
                  {this.state.error?.stack && (
                    <pre className="mt-2 text-xs text-left text-gray-500 bg-gray-50 p-4 rounded-md overflow-auto">
                      {this.state.error.stack}
                    </pre>
                  )}
                </div>
                <div className="mt-6 flex justify-center gap-3">
                  <Button
                    variant="outline"
                    onClick={() => window.location.reload()}
                  >
                    Muat Ulang
                  </Button>
                  <Button
                    variant="primary"
                    onClick={() => window.history.back()}
                  >
                    Kembali
                  </Button>
                </div>
              </div>
            </div>
          </div>
        </div>
      );
    }

    return this.props.children;
  }
}

export default ErrorBoundary;
