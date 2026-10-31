import { Clock, AlertTriangle, CheckCircle2, RotateCcw, XCircle, ArrowUpRight, Activity } from 'lucide-react';

const StatusBadge = ({ status, size = 'sm' }) => {
  const normStatus = (status || '').toLowerCase().trim();

  const getStatusConfig = () => {
    switch (normStatus) {
      case 'pending':
        return {
          label: 'Pending',
          icon: Clock,
          classes: 'bg-amber-50 text-amber-700 border-amber-200',
          dot: 'bg-amber-500'
        };
      case 'assigned':
        return {
          label: 'Assigned',
          icon: Activity,
          classes: 'bg-blue-50 text-blue-700 border-blue-200',
          dot: 'bg-blue-500'
        };
      case 'in_progress':
        return {
          label: 'In Progress',
          icon: Activity,
          classes: 'bg-sky-50 text-sky-700 border-sky-200',
          dot: 'bg-sky-500 animate-pulse'
        };
      case 'escalated':
        return {
          label: 'Escalated',
          icon: ArrowUpRight,
          classes: 'bg-orange-50 text-orange-700 border-orange-200',
          dot: 'bg-orange-500'
        };
      case 'approved':
        return {
          label: 'Approved',
          icon: CheckCircle2,
          classes: 'bg-emerald-50 text-emerald-700 border-emerald-200',
          dot: 'bg-emerald-500'
        };
      case 'returned':
        return {
          label: 'Returned',
          icon: RotateCcw,
          classes: 'bg-amber-50 text-amber-800 border-amber-300',
          dot: 'bg-amber-600'
        };
      case 'resolved':
        return {
          label: 'Resolved',
          icon: CheckCircle2,
          classes: 'bg-emerald-50 text-emerald-700 border-emerald-200',
          dot: 'bg-emerald-500'
        };
      case 'closed':
        return {
          label: 'Closed',
          icon: CheckCircle2,
          classes: 'bg-slate-100 text-slate-700 border-slate-200',
          dot: 'bg-slate-400'
        };
      case 'withdrawn':
        return {
          label: 'Withdrawn',
          icon: XCircle,
          classes: 'bg-slate-100 text-slate-600 border-slate-200',
          dot: 'bg-slate-400'
        };
      case 'rejected':
        return {
          label: 'Rejected',
          icon: XCircle,
          classes: 'bg-red-50 text-red-700 border-red-200',
          dot: 'bg-red-500'
        };
      default:
        return {
          label: normStatus ? normStatus.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase()) : 'Unknown',
          icon: Clock,
          classes: 'bg-slate-100 text-slate-600 border-slate-200',
          dot: 'bg-slate-400'
        };
    }
  };

  const config = getStatusConfig();
  const Icon = config.icon;
  const sizeClasses = size === 'xs' ? 'px-2 py-0.5 text-[11px]' : 'px-2.5 py-0.5 text-xs';

  return (
    <span className={`inline-flex items-center gap-1.5 font-semibold rounded-full border shadow-2xs ${sizeClasses} ${config.classes}`}>
      <span className={`w-1.5 h-1.5 rounded-full ${config.dot}`} aria-hidden="true" />
      <span>{config.label}</span>
    </span>
  );
};

export default StatusBadge;
