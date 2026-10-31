import { Clock, BellRing, ArrowUpRight, CheckCircle2, CalendarClock, Timer, TrendingUp, Info } from 'lucide-react';
import SlaStatusBadge from './SlaStatusBadge';

/**
 * Displays:
 * 1. Official Resolution Target (SLA), 75% follow-up status, and escalation tracking
 * 2. Predictive Resolution-Time Estimate (Historical Cohort Median & IQR)
 * 3. Actual Resolution Time (when resolved)
 * on authorized Department Staff, Dean, and SAO concern detail pages.
 */
const SlaTrackingCard = ({ concern }) => {
  const sla = concern?.sla;
  const prediction = concern?.prediction;
  if ((!sla || sla.enabled === false) && !prediction) {
    return null;
  }

  const formatDateTime = (dateStr) => {
    if (!dateStr) return '—';
    return new Date(dateStr).toLocaleDateString('en-US', {
      month: 'short',
      day: 'numeric',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  };

  const formatDuration = (seconds) => {
    if (seconds === null || seconds === undefined) return '—';
    const abs = Math.max(0, Math.abs(Math.round(seconds)));
    const days = Math.floor(abs / 86400);
    const hours = Math.floor((abs % 86400) / 3600);
    const mins = Math.floor((abs % 3600) / 60);
    if (days > 0) return `${days}d ${hours}h ${mins}m`;
    if (hours > 0) return `${hours}h ${mins}m`;
    return `${mins}m`;
  };

  const formatHoursLabel = (hours) => {
    if (hours === null || hours === undefined) return '—';
    return formatDuration(Math.round(Number(hours) * 3600));
  };

  const confidenceBadgeStyle = (level) => {
    switch (level) {
      case 'high':
        return 'bg-emerald-50 text-emerald-700 border-emerald-200';
      case 'moderate':
        return 'bg-blue-50 text-blue-700 border-blue-200';
      case 'low':
        return 'bg-amber-50 text-amber-800 border-amber-200';
      default:
        return 'bg-slate-100 text-slate-600 border-slate-200';
    }
  };

  const ratioPct = typeof sla?.elapsed_ratio === 'number'
    ? Math.min(100, Math.max(0, Math.round(sla.elapsed_ratio * 100)))
    : 0;
  const rawPct = typeof sla?.elapsed_ratio === 'number'
    ? Math.max(0, Math.round(sla.elapsed_ratio * 100))
    : 0;
  const thresholdPct = Math.round((sla?.followup_threshold ?? 0.75) * 100);

  const isResolved = concern?.status === 'resolved';
  const isEscalated = concern?.status === 'escalated';
  const isClosed = ['resolved', 'closed', 'rejected', 'withdrawn'].includes(concern?.status);

  const barColor =
    sla?.state === 'overdue'
      ? 'bg-rose-600'
      : sla?.state === 'followup_sent'
      ? 'bg-orange-500'
      : sla?.state === 'approaching'
      ? 'bg-amber-500'
      : sla?.state === 'resolved'
      ? 'bg-emerald-600'
      : 'bg-[#1e3a8a]';

  const getRemainingLabel = () => {
    if (isResolved) {
      return sla?.resolved_within_target === false
        ? `Resolved ${formatDuration(Math.abs(sla?.remaining_seconds || 0))} past target`
        : 'Resolved within target timeframe';
    }
    if (isClosed) {
      return 'Closed';
    }
    if (typeof sla?.remaining_seconds !== 'number') {
      return '—';
    }
    if (sla.remaining_seconds >= 0) {
      return `${formatDuration(sla.remaining_seconds)} remaining`;
    }
    return `Overdue by ${formatDuration(Math.abs(sla.remaining_seconds))}`;
  };

  const getFollowupText = () => {
    if (sla?.followup_sent_at) {
      return `Triggered on ${formatDateTime(sla.followup_sent_at)}`;
    }
    if (isClosed) {
      return 'Closed before 75% follow-up threshold';
    }
    if (sla?.followup_due_at) {
      return `Scheduled at ${thresholdPct}% (${formatDateTime(sla.followup_due_at)})`;
    }
    return 'Not triggered';
  };

  const getEscalationText = () => {
    if (sla?.escalated_by_sla) {
      return sla.deadline_reached_at
        ? `Auto-escalated to Dean at deadline (${formatDateTime(sla.deadline_reached_at)})`
        : 'Auto-escalated to Dean at resolution deadline';
    }
    if (isEscalated) {
      return concern?.forwarded_to_cad_at
        ? 'Forwarded to Campus Administration (CAD)'
        : 'Escalated to Academic Unit Dean';
    }
    if (sla?.deadline_reached_at) {
      return `Deadline reached (${formatDateTime(sla.deadline_reached_at)}) — Overdue notice sent`;
    }
    if (isResolved) {
      return 'Resolved without escalation';
    }
    return 'In assigned department workflow';
  };

  return (
    <div className="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-5">
      {sla && sla.enabled !== false && (
        <div>
          <div className="flex flex-wrap items-center justify-between gap-2 mb-4">
            <div className="flex items-center gap-2">
              <div className="w-7 h-7 rounded-lg bg-blue-50 text-[#1e3a8a] flex items-center justify-center">
                <Timer size={15} />
              </div>
              <div>
                <h2 className="text-xs font-bold text-slate-900 uppercase tracking-wider">
                  Official SLA Deadline &amp; Follow-up
                </h2>
                <p className="text-[11px] text-slate-500">
                  Official Target: {sla.target_hours ?? '—'}h ({sla.priority_basis || 'medium'} priority)
                </p>
              </div>
            </div>
            <SlaStatusBadge sla={sla} status={concern?.status} size="sm" />
          </div>

          {/* Elapsed Progress Bar with 75% Threshold Marker */}
          <div className="mb-4">
            <div className="flex items-center justify-between text-[11px] text-slate-600 mb-1.5">
              <span className="font-semibold">
                Elapsed: <span className="text-slate-900">{rawPct}%</span> of official SLA target
              </span>
              <span className="text-slate-500">
                {thresholdPct}% follow-up threshold
              </span>
            </div>
            <div className="relative h-2.5 w-full bg-slate-100 rounded-full overflow-hidden border border-slate-200">
              <div
                className={`h-full transition-all duration-300 ${barColor}`}
                style={{ width: `${ratioPct}%` }}
              />
              <div
                className="absolute top-0 bottom-0 w-0.5 bg-amber-600/80"
                style={{ left: `${thresholdPct}%` }}
                title={`${thresholdPct}% automatic follow-up threshold`}
              />
            </div>
          </div>

          {/* Key Timestamps & Metrics */}
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
            <div className="p-3 rounded-xl bg-slate-50 border border-slate-100">
              <div className="flex items-center gap-1.5 text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">
                <CalendarClock size={12} />
                <span>Submitted</span>
              </div>
              <p className="font-semibold text-slate-800">
                {formatDateTime(concern?.submitted_at || sla.started_at)}
              </p>
            </div>

            <div className="p-3 rounded-xl bg-slate-50 border border-slate-100">
              <div className="flex items-center gap-1.5 text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">
                <Clock size={12} />
                <span>Official SLA Deadline</span>
              </div>
              <p className="font-semibold text-slate-800">
                {formatDateTime(sla.deadline_at)}
              </p>
            </div>

            <div className="p-3 rounded-xl bg-slate-50 border border-slate-100">
              <div className="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">
                {isResolved ? 'Actual Resolution Duration' : 'Time Elapsed'}
              </div>
              <p className="font-semibold text-slate-800">
                {formatDuration(sla.elapsed_seconds)}
              </p>
            </div>

            <div className="p-3 rounded-xl bg-slate-50 border border-slate-100">
              <div className="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">
                {isResolved ? 'SLA Outcome' : 'Remaining SLA Time'}
              </div>
              <p
                className={`font-semibold ${
                  !isClosed && typeof sla.remaining_seconds === 'number' && sla.remaining_seconds < 0
                    ? 'text-rose-700'
                    : 'text-slate-800'
                }`}
              >
                {getRemainingLabel()}
              </p>
            </div>
          </div>

          {/* Follow-up & Escalation Status */}
          <div className="mt-3 pt-3 border-t border-slate-100 space-y-2 text-xs">
            <div className="flex items-start justify-between gap-2">
              <span className="inline-flex items-center gap-1.5 text-slate-500 font-medium shrink-0">
                <BellRing size={13} className={sla.followup_sent_at ? 'text-orange-600' : 'text-slate-400'} />
                <span>75% Follow-up Status:</span>
              </span>
              <span className={`font-semibold text-right ${sla.followup_sent_at ? 'text-orange-700' : 'text-slate-700'}`}>
                {getFollowupText()}
              </span>
            </div>

            <div className="flex items-start justify-between gap-2">
              <span className="inline-flex items-center gap-1.5 text-slate-500 font-medium shrink-0">
                {isResolved ? (
                  <CheckCircle2 size={13} className="text-emerald-600" />
                ) : (
                  <ArrowUpRight size={13} className={isEscalated ? 'text-purple-600' : 'text-slate-400'} />
                )}
                <span>Escalation Status:</span>
              </span>
              <span className={`font-semibold text-right ${isEscalated ? 'text-purple-700' : 'text-slate-700'}`}>
                {getEscalationText()}
              </span>
            </div>
          </div>
        </div>
      )}

      {/* Predictive Resolution-Time Estimation (Historical Cohort Analysis) */}
      {prediction && prediction.enabled !== false && (
        <div className={sla && sla.enabled !== false ? 'pt-4 border-t border-slate-200' : ''}>
          <div className="flex flex-wrap items-center justify-between gap-2 mb-3">
            <div className="flex items-center gap-2">
              <div className="w-7 h-7 rounded-lg bg-indigo-50 text-indigo-700 flex items-center justify-center">
                <TrendingUp size={15} />
              </div>
              <div>
                <h3 className="text-xs font-bold text-slate-900 uppercase tracking-wider">
                  Predictive Resolution Estimate
                </h3>
                <p className="text-[11px] text-slate-500">
                  {prediction.tier_label || 'Historical Cohort Statistical Estimator'}
                </p>
              </div>
            </div>
            <span
              className={`px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider border ${confidenceBadgeStyle(
                prediction.confidence_level
              )}`}
            >
              {prediction.confidence_level === 'insufficient_data'
                ? 'Insufficient Data'
                : `${prediction.confidence_level} Confidence (n=${prediction.sample_size ?? 0})`}
            </span>
          </div>

          {prediction.available ? (
            <>
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs mb-3">
                <div className="p-3 rounded-xl bg-indigo-50/50 border border-indigo-100">
                  <div className="text-[10px] font-bold text-indigo-600 uppercase tracking-wider mb-0.5">
                    Predicted Resolution Time (Median)
                  </div>
                  <p className="font-bold text-slate-900 text-sm">
                    {prediction.estimated_label || formatHoursLabel(prediction.estimated_hours)}
                  </p>
                  {prediction.range_hours && (
                    <p className="text-[11px] text-slate-500 mt-0.5">
                      Typical Range (IQR): {formatHoursLabel(prediction.range_hours.p25)} – {formatHoursLabel(prediction.range_hours.p75)}
                    </p>
                  )}
                </div>

                <div className="p-3 rounded-xl bg-indigo-50/50 border border-indigo-100">
                  <div className="text-[10px] font-bold text-indigo-600 uppercase tracking-wider mb-0.5">
                    {isResolved ? 'Actual vs. Predicted' : 'Predicted Completion Date'}
                  </div>
                  {isResolved && prediction.actual_resolution ? (
                    <>
                      <p className="font-bold text-slate-900 text-sm">
                        Actual: {formatHoursLabel(prediction.actual_resolution.actual_hours)}
                      </p>
                      <p className="text-[11px] text-slate-500 mt-0.5">
                        {prediction.actual_resolution.within_predicted_iqr
                          ? 'Resolved within typical historical range'
                          : prediction.actual_resolution.difference_hours <= 0
                          ? `${formatHoursLabel(Math.abs(prediction.actual_resolution.difference_hours))} faster than median`
                          : `${formatHoursLabel(prediction.actual_resolution.difference_hours)} longer than median`}
                      </p>
                    </>
                  ) : (
                    <>
                      <p className="font-bold text-slate-900 text-sm">
                        {formatDateTime(prediction.estimated_resolved_at)}
                      </p>
                      <p className="text-[11px] text-slate-500 mt-0.5">
                        Based on {prediction.sample_size} historical resolved cases
                      </p>
                    </>
                  )}
                </div>
              </div>

              {Array.isArray(prediction.factors_used) && prediction.factors_used.length > 0 && (
                <div className="flex flex-wrap items-center gap-1.5 mb-2.5">
                  <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider mr-1">
                    Matched Factors:
                  </span>
                  {prediction.factors_used.map((factor, idx) => (
                    <span
                      key={idx}
                      className="px-2 py-0.5 rounded-md bg-slate-100 text-slate-700 border border-slate-200 text-[10px] font-medium"
                    >
                      {factor}
                    </span>
                  ))}
                </div>
              )}

              <p className="text-[11px] text-slate-500 leading-relaxed flex items-start gap-1.5">
                <Info size={13} className="text-slate-400 shrink-0 mt-0.5" />
                <span>{prediction.disclaimer}</span>
              </p>
            </>
          ) : (
            <div className="p-3.5 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-600 flex items-start gap-2">
              <Info size={15} className="text-slate-400 shrink-0 mt-0.5" />
              <div>
                <p className="font-semibold text-slate-800">Insufficient Historical Data for Prediction</p>
                <p className="text-[11px] text-slate-500 mt-0.5 leading-relaxed">
                  {prediction.explanation || 'Not enough historical resolved concerns match this profile yet to compute a reliable statistical estimate.'}
                </p>
              </div>
            </div>
          )}
        </div>
      )}
    </div>
  );
};

export default SlaTrackingCard;
