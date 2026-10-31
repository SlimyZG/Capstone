import React from 'react';
import { AlertTriangle, RefreshCw, Home } from 'lucide-react';

class ErrorBoundary extends React.Component {
  constructor(props) {
    super(props);
    this.state = { hasError: false, error: null };
  }

  static getDerivedStateFromError(error) {
    return { hasError: true, error };
  }

  componentDidCatch(error, errorInfo) {
    console.error('SCMS Error Boundary Caught Exception:', error, errorInfo);
  }

  render() {
    if (this.state.hasError) {
      return (
        <div className="min-h-screen bg-[#f8fafc] flex items-center justify-center p-6">
          <div className="bg-white border border-slate-200 rounded-2xl shadow-sm p-8 max-w-lg w-full text-center">
            <div className="w-12 h-12 rounded-full bg-rose-50 text-rose-600 flex items-center justify-center mx-auto mb-4 border border-rose-200">
              <AlertTriangle size={24} />
            </div>
            <h2 className="text-xl font-bold text-slate-900 mb-1">Something went wrong</h2>
            <p className="text-xs text-slate-500 mb-4">
              An unexpected interface error occurred while loading this view.
            </p>
            {this.state.error?.message && (
              <div className="bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs text-left text-slate-700 font-mono mb-6 break-words max-h-32 overflow-y-auto">
                {this.state.error.message}
              </div>
            )}
            <div className="flex items-center justify-center gap-3">
              <button
                type="button"
                onClick={() => {
                  this.setState({ hasError: false, error: null });
                  window.location.reload();
                }}
                className="inline-flex items-center gap-1.5 px-4 py-2.5 bg-[#1e3a8a] text-white rounded-xl text-xs font-bold hover:bg-blue-900 transition-colors shadow-xs"
              >
                <RefreshCw size={14} />
                <span>Reload Page</span>
              </button>
              <button
                type="button"
                onClick={() => {
                  this.setState({ hasError: false, error: null });
                  window.location.href = '/';
                }}
                className="inline-flex items-center gap-1.5 px-4 py-2.5 bg-slate-100 text-slate-700 rounded-xl text-xs font-semibold hover:bg-slate-200 transition-colors"
              >
                <Home size={14} />
                <span>Return Home</span>
              </button>
            </div>
          </div>
        </div>
      );
    }
    return this.props.children;
  }
}

export default ErrorBoundary;
