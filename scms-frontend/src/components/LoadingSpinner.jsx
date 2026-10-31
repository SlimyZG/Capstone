import { Loader2 } from 'lucide-react';

const LoadingSpinner = ({ message = 'Loading...', fullPage = false }) => {
  const content = (
    <div className="flex flex-col items-center justify-center p-8 text-center">
      <Loader2 className="h-8 w-8 text-blue-600 animate-spin mb-3" />
      <p className="text-sm font-medium text-slate-500">{message}</p>
    </div>
  );

  if (fullPage) {
    return (
      <div className="min-h-[50vh] flex items-center justify-center">
        {content}
      </div>
    );
  }

  return content;
};

export default LoadingSpinner;
