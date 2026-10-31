import { FolderOpen } from 'lucide-react';

const EmptyState = ({
  icon: Icon = FolderOpen,
  title = 'No records found',
  description = 'There are no items matching your criteria at this moment.',
  action,
  className = '',
}) => {
  return (
    <div className={`text-center py-12 px-4 bg-white rounded-xl border border-slate-200 shadow-2xs ${className}`}>
      <div className="mx-auto h-12 w-12 rounded-full bg-slate-50 border border-slate-200 flex items-center justify-center text-slate-400 mb-3">
        <Icon size={22} />
      </div>
      <h3 className="text-base font-bold text-slate-900 mb-1">{title}</h3>
      <p className="text-sm text-slate-500 max-w-md mx-auto mb-5 leading-relaxed">
        {description}
      </p>
      {action && <div className="flex justify-center">{action}</div>}
    </div>
  );
};

export default EmptyState;
