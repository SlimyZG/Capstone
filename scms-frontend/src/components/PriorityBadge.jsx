import { AlertCircle, AlertTriangle, Shield, CheckCircle } from 'lucide-react';

const PriorityBadge = ({ priority, isOverridden = false, size = 'sm' }) => {
  const normPriority = (priority || 'medium').toLowerCase().trim();

  const getPriorityConfig = () => {
    switch (normPriority) {
      case 'critical':
        return {
          label: 'Critical',
          classes: 'bg-red-50 text-red-700 border-red-200',
          dot: 'bg-red-500 animate-pulse',
        };
      case 'high':
        return {
          label: 'High',
          classes: 'bg-amber-50 text-amber-700 border-amber-200',
          dot: 'bg-amber-500',
        };
      case 'medium':
        return {
          label: 'Medium',
          classes: 'bg-blue-50 text-blue-700 border-blue-200',
          dot: 'bg-blue-500',
        };
      case 'low':
        return {
          label: 'Low',
          classes: 'bg-slate-50 text-slate-700 border-slate-200',
          dot: 'bg-slate-400',
        };
      default:
        return {
          label: normPriority.toUpperCase(),
          classes: 'bg-slate-50 text-slate-700 border-slate-200',
          dot: 'bg-slate-400',
        };
    }
  };

  const config = getPriorityConfig();
  const sizeClasses = size === 'xs' ? 'px-2 py-0.5 text-[11px]' : 'px-2.5 py-0.5 text-xs';

  return (
    <span className="inline-flex items-center gap-1.5 flex-wrap">
      <span className={`inline-flex items-center gap-1.5 font-bold rounded-full border shadow-2xs ${sizeClasses} ${config.classes}`}>
        <span className={`w-1.5 h-1.5 rounded-full ${config.dot}`} aria-hidden="true" />
        <span>{config.label} Priority</span>
      </span>
      {isOverridden && (
        <span className="inline-flex items-center px-1.5 py-0.2 rounded text-[10px] font-semibold bg-amber-100 text-amber-800 border border-amber-200">
          Overridden
        </span>
      )}
    </span>
  );
};

export default PriorityBadge;
