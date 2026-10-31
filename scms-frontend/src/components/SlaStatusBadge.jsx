import { Clock, AlertTriangle, BellRing, CheckCircle2, ArrowUpRight, ShieldAlert } from 'lucide-react';

/**
 * Displays a compact SLA / resolution-timeframe status badge for authorized
 * staff, Dean, and SAO views.
 *
 * Supported states (from backend ConcernSlaService::describe):
 *  - normal         -> Normal
 *  - approaching    -> Approaching deadline
 *  - followup_sent  -> 75% follow-up triggered
 *  - overdue        -> Overdue
 *  - escalated      -> Escalated
 *  - resolved       -> Resolved
 */
const SlaStatusBadge = ({ sla, status, size = 'xs', showRemaining = false }) => {
  if (!sla || sla.enabled === false || sla.state === 'not_applicable') {
    return null;
  }

  const state = (sla.state || status || 'normal').toLowerCase();

  const formatDuration = (seconds) => {
    if (seconds === null || seconds === undefined) return '';
    const abs = Math.max(0, Math.abs(Math.round(seconds)));
    const days = Math.floor(abs / 86400);
    const hours = Math.floor((abs % 86400) / 3600);
    const mins = Math.floor((abs % 3600) / 60);
    if (days > 0) return `${days}d ${hours}h`;
    if (hours > 0) return `${hours}h ${mins}m`;
    return `${mins}m`;
  };

  const getConfig = () => {
    switch (state) {
      case 'overdue':
        return {
          label: 'Overdue',
          icon: ShieldAlert,
          classes: 'bg-rose-50 text-rose-700 border-rose-200',
          dot: 'bg-rose-500 animate-pulse',
        };
      case 'followup_sent':
        return {
          label: '75% Follow-up Triggered',
          icon: BellRing,
          classes: 'bg-orange-50 text-orange-800 border-orange-200',
          dot: 'bg-orange-500 animate-pulse',
        };
      case 'approaching':
        return {
          label: 'Approaching Deadline',
          icon: AlertTriangle,
          classes: 'bg-amber-50 text-amber-800 border-amber-200',
          dot: 'bg-amber-500',
        };
      case 'escalated':
        return {
          label: sla.escalated_by_sla ? 'Auto-Escalated' : 'Escalated',
          icon: ArrowUpRight,
          classes: 'bg-purple-50 text-purple-700 border-purple-200',
          dot: 'bg-purple-500',
        };
      case 'resolved':
        return {
          label: sla.resolved_within_target === false ? 'Resolved (Past Target)' : 'Resolved',
          icon: CheckCircle2,
          classes: 'bg-emerald-50 text-emerald-700 border-emerald-200',
          dot: 'bg-emerald-500',
        };
      case 'normal':
      default:
        return {
          label: 'Normal',
          icon: Clock,
          classes: 'bg-blue-50 text-blue-700 border-blue-200',
          dot: 'bg-blue-500',
        };
    }
  };

  const config = getConfig();
  const sizeClasses = size === 'xs' ? 'px-2 py-0.5 text-[11px]' : 'px-2.5 py-0.5 text-xs';

  const remainingText =
    showRemaining &&
    typeof sla.remaining_seconds === 'number' &&
    !['resolved', 'closed'].includes(state)
      ? sla.remaining_seconds >= 0
        ? ` • ${formatDuration(sla.remaining_seconds)} left`
        : ` • ${formatDuration(sla.remaining_seconds)} overdue`
      : '';

  return (
    <span
      className={`inline-flex items-center gap-1.5 font-semibold rounded-full border shadow-2xs ${sizeClasses} ${config.classes}`}
      title={
        sla.deadline_at
          ? `Target deadline: ${new Date(sla.deadline_at).toLocaleString()}`
          : config.label
      }
    >
      <span className={`w-1.5 h-1.5 rounded-full ${config.dot}`} aria-hidden="true" />
      <span>
        {config.label}
        {remainingText}
      </span>
    </span>
  );
};

export default SlaStatusBadge;
