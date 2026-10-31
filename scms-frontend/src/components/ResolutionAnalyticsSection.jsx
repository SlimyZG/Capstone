import { useState } from 'react';
import {
  Clock,
  TrendingUp,
  BarChart2,
  Info,
  CheckCircle2,
  AlertTriangle,
  ChevronDown,
  ChevronUp,
  ShieldAlert,
  HelpCircle,
  Sliders,
  Building2,
} from 'lucide-react';

/**
 * Displays user-friendly Historical Resolution Performance, Horizontal Bar Breakdowns,
 * Predictive vs. Official SLA explanation, and collapsible Advanced Analytics
 * for Department Staff Reports, Dean Reports, and SAO Oversight.
 */
const ResolutionAnalyticsSection = ({ analytics, scopeLabel = 'Institution-Wide' }) => {
  const [showAdvanced, setShowAdvanced] = useState(false);

  if (!analytics || analytics.enabled === false) {
    return null;
  }

  const summary = analytics.summary || {};
  const sampleSize = summary.sample_size ?? analytics.sample_size ?? 0;
  const lowSampleOverall = Boolean(summary.low_sample_warning ?? analytics.low_sample_warning ?? sampleSize < 3);
  const byCategory = Array.isArray(analytics.by_category) ? analytics.by_category : [];
  const byPriority = Array.isArray(analytics.by_priority) ? analytics.by_priority : [];
  const byDepartment = Array.isArray(analytics.by_department) ? analytics.by_department : [];
  const pva = analytics.predicted_vs_actual || {};
  const pvaByPriority = Array.isArray(pva.by_priority) ? pva.by_priority : [];

  const formatHours = (hours) => {
    if (hours === null || hours === undefined) return '—';
    const num = Number(hours);
    if (Number.isNaN(num)) return '—';
    if (num >= 24) {
      const days = (num / 24).toFixed(1);
      return `${num.toFixed(1)}h (${days}d)`;
    }
    return `${num.toFixed(1)}h`;
  };

  const maxPriorityHours = Math.max(
    ...byPriority.map((r) => Math.max(Number(r.median_hours || 0), Number(r.sla_target_hours || 0))),
    1
  );
  const maxCategoryHours = Math.max(...byCategory.map((r) => Number(r.median_hours || 0)), 1);
  const maxDeptHours = Math.max(...byDepartment.map((r) => Number(r.median_hours || 0)), 1);

  const PRIORITY_BAR_STYLES = {
    critical: { bar: 'bg-red-500', badge: 'bg-red-50 text-red-700 border-red-200' },
    high:     { bar: 'bg-amber-500', badge: 'bg-amber-50 text-amber-800 border-amber-200' },
    medium:   { bar: 'bg-blue-600', badge: 'bg-blue-50 text-blue-700 border-blue-200' },
    low:      { bar: 'bg-slate-500', badge: 'bg-slate-100 text-slate-700 border-slate-200' },
  };

  return (
    <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-6">
      {/* ── Section Header ── */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-4 border-b border-slate-200">
        <div className="flex items-start gap-3">
          <div className="w-10 h-10 rounded-xl bg-blue-50 text-[#1E3A8A] flex items-center justify-center shrink-0 mt-0.5 border border-blue-100">
            <TrendingUp size={20} />
          </div>
          <div>
            <div className="flex items-center gap-2 flex-wrap">
              <h2 className="text-lg font-bold text-[#0F172A]">
                Resolution Performance &amp; Predictive Estimates
              </h2>
              <span className="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-50 text-[#1E3A8A] border border-blue-200">
                {scopeLabel}
              </span>
            </div>
            <p className="text-sm text-[#64748B] mt-0.5">
              How long resolved concerns typically take to complete and how historical patterns guide resolution estimates.
            </p>
          </div>
        </div>

        <div className="flex items-center gap-2 self-start sm:self-center">
          <span
            className="px-3 py-1.5 rounded-lg text-xs font-semibold bg-[#F8FAFC] text-[#0F172A] border border-[#E2E8F0]"
            title="The number of valid completed concerns included in the calculation."
          >
            Historical Cases Used: <strong>{sampleSize}</strong>
          </span>
          {lowSampleOverall && sampleSize > 0 && (
            <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-semibold bg-amber-50 text-amber-800 border border-amber-200">
              <AlertTriangle size={13} className="text-amber-600" />
              Low Sample Size
            </span>
          )}
        </div>
      </div>

      {/* ── Empty / Insufficient Historical Data State ── */}
      {sampleSize === 0 ? (
        <div className="p-6 rounded-xl bg-[#F8FAFC] border border-[#E2E8F0] flex flex-col sm:flex-row items-center gap-4 text-left">
          <div className="w-11 h-11 rounded-full bg-amber-50 text-amber-600 flex items-center justify-center shrink-0 border border-amber-200">
            <AlertTriangle size={22} />
          </div>
          <div>
            <h4 className="text-sm font-bold text-[#0F172A]">
              Insufficient Historical Data in Current Scope
            </h4>
            <p className="text-xs text-[#64748B] mt-1 leading-relaxed">
              There are no validated resolved concerns matching the current filters yet. Once concerns in this scope are resolved, typical resolution times and prediction accuracy checks will appear here automatically.
            </p>
          </div>
        </div>
      ) : (
        <>
          {/* Low-Confidence / Small Sample Banner */}
          {lowSampleOverall && (
            <div className="p-4 rounded-xl bg-amber-50/80 border border-amber-200 flex items-start gap-3">
              <AlertTriangle size={18} className="text-amber-600 shrink-0 mt-0.5" />
              <div className="text-xs text-amber-900 leading-relaxed">
                <span className="font-bold">Limited Historical Data ({sampleSize} completed case{sampleSize === 1 ? '' : 's'}): </span>
                At least 3 completed cases are recommended for stable statistical estimates. Treat the resolution times below as preliminary indicators.
              </div>
            </div>
          )}

          {/* ── SECTION D: RESOLUTION PERFORMANCE SUMMARY CARDS ── */}
          <div>
            <div className="flex items-center justify-between mb-3">
              <h3 className="text-sm font-bold text-[#0F172A] uppercase tracking-wider">
                Resolution Time Overview
              </h3>
              <span className="text-xs text-[#64748B]">
                Based on {sampleSize} completed historical case{sampleSize === 1 ? '' : 's'}
              </span>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
              {/* Card 1: Typical Resolution Time (Median) */}
              <div className="p-4 rounded-xl bg-[#F8FAFC] border border-[#E2E8F0] flex flex-col justify-between">
                <div>
                  <div className="flex items-center justify-between gap-1">
                    <span className="text-xs font-bold text-[#64748B] uppercase tracking-wider">
                      Typical Resolution Time
                    </span>
                    <span
                      className="text-[#64748B] hover:text-[#0F172A] cursor-help"
                      title="Median Resolution Time: The middle value of historical resolution durations. It is less affected by unusually long cases."
                    >
                      <HelpCircle size={14} />
                    </span>
                  </div>
                  <div className="text-2xl font-extrabold text-[#1E3A8A] mt-2">
                    {formatHours(summary.median_resolution_hours)}
                  </div>
                </div>
                <p className="text-xs text-[#64748B] mt-2 leading-relaxed">
                  The middle value of historical resolution durations. Less affected by unusually long cases.
                </p>
              </div>

              {/* Card 2: Average Resolution Time (Mean & Typical Range) */}
              <div className="p-4 rounded-xl bg-[#F8FAFC] border border-[#E2E8F0] flex flex-col justify-between">
                <div>
                  <div className="flex items-center justify-between gap-1">
                    <span className="text-xs font-bold text-[#64748B] uppercase tracking-wider">
                      Average Resolution Time
                    </span>
                    <span
                      className="text-[#64748B] hover:text-[#0F172A] cursor-help"
                      title="Average (Mean) Resolution Time across all completed concerns in this scope."
                    >
                      <HelpCircle size={14} />
                    </span>
                  </div>
                  <div className="text-2xl font-extrabold text-[#0F172A] mt-2">
                    {formatHours(summary.mean_resolution_hours)}
                  </div>
                </div>
                <p className="text-xs text-[#64748B] mt-2 leading-relaxed">
                  {summary.iqr_hours
                    ? `Typical Resolution Range: ${formatHours(summary.iqr_hours.p25)} – ${formatHours(summary.iqr_hours.p75)} (middle 50% of cases).`
                    : 'Simple average across all completed concerns in this scope.'}
                </p>
              </div>

              {/* Card 3: Average Prediction Error (MAE) */}
              <div className="p-4 rounded-xl bg-[#F8FAFC] border border-[#E2E8F0] flex flex-col justify-between">
                <div>
                  <div className="flex items-center justify-between gap-1">
                    <span className="text-xs font-bold text-[#64748B] uppercase tracking-wider">
                      Average Prediction Error
                    </span>
                    <span
                      className="text-[#64748B] hover:text-[#0F172A] cursor-help"
                      title="How far the estimated resolution time was from the actual resolution time during historical testing (Mean Absolute Error)."
                    >
                      <HelpCircle size={14} />
                    </span>
                  </div>
                  <div className="text-2xl font-extrabold text-[#2563EB] mt-2">
                    {pva.evaluated_count > 0 ? `±${formatHours(pva.mean_absolute_error_hours)}` : 'Not enough data'}
                  </div>
                </div>
                <p className="text-xs text-[#64748B] mt-2 leading-relaxed">
                  {pva.evaluated_count > 0
                    ? `How far the estimated time was from actual completion across ${pva.evaluated_count} tested case(s).`
                    : 'Requires at least 2 similar completed cases to test prediction accuracy.'}
                </p>
              </div>

              {/* Card 4: Predictions Within Expected Range & Historical Cases Used */}
              <div className="p-4 rounded-xl bg-[#F8FAFC] border border-[#E2E8F0] flex flex-col justify-between">
                <div>
                  <div className="flex items-center justify-between gap-1">
                    <span className="text-xs font-bold text-[#64748B] uppercase tracking-wider">
                      Predictions Within Expected Range
                    </span>
                    <span
                      className="text-[#64748B] hover:text-[#0F172A] cursor-help"
                      title="Percentage of tested concerns whose actual resolution time fell inside the predicted Typical Resolution Range."
                    >
                      <HelpCircle size={14} />
                    </span>
                  </div>
                  <div className="text-2xl font-extrabold text-[#10B981] mt-2">
                    {pva.within_iqr_percentage !== null && pva.within_iqr_percentage !== undefined
                      ? `${pva.within_iqr_percentage}%`
                      : '—'}
                  </div>
                </div>
                <p className="text-xs text-[#64748B] mt-2 leading-relaxed">
                  Completed within the predicted Typical Resolution Range ({sampleSize} historical case{sampleSize === 1 ? '' : 's'} used).
                </p>
              </div>
            </div>
          </div>

          {/* ── SECTION F: PREDICTIVE ANALYTICS VS. OFFICIAL SLA GUIDE ── */}
          <div className="p-4 rounded-xl bg-blue-50/60 border border-blue-200">
            <div className="flex items-center gap-2 mb-2.5">
              <Info size={16} className="text-[#2563EB] shrink-0" />
              <h4 className="text-xs font-bold text-[#1E3A8A] uppercase tracking-wider">
                How to Read Resolution Timeframes (Official SLA vs. Historical Estimate)
              </h4>
            </div>
            <div className="grid grid-cols-1 md:grid-cols-3 gap-3 text-xs">
              <div className="bg-white p-3 rounded-lg border border-blue-100">
                <div className="font-bold text-[#0F172A]">1. Official SLA Target</div>
                <p className="text-[#64748B] mt-1 leading-relaxed">
                  The institution&apos;s configured deadline target by priority (Critical: 4h, High: 24h, Medium: 72h, Low: 168h). Used for the 75% follow-up alert and 100% deadline escalation.
                </p>
              </div>
              <div className="bg-white p-3 rounded-lg border border-blue-100">
                <div className="font-bold text-[#0F172A]">2. Predicted Resolution Time</div>
                <p className="text-[#64748B] mt-1 leading-relaxed">
                  An advisory estimate calculated from historical completed concerns of the same department, category, and priority. It never replaces the official SLA deadline.
                </p>
              </div>
              <div className="bg-white p-3 rounded-lg border border-blue-100">
                <div className="font-bold text-[#0F172A]">3. Actual Resolution Time</div>
                <p className="text-[#64748B] mt-1 leading-relaxed">
                  The real elapsed time measured from when a student submits a concern until it is marked resolved.
                </p>
              </div>
            </div>
          </div>

          {/* ── SECTION E: VISUAL HORIZONTAL BAR BREAKDOWNS ── */}
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {/* Priority Breakdown Horizontal Bar Chart */}
            <div className="rounded-xl border border-[#E2E8F0] p-5 bg-white">
              <div className="flex items-center justify-between mb-1">
                <h4 className="text-sm font-bold text-[#0F172A] flex items-center gap-2">
                  <Clock size={16} className="text-[#2563EB]" />
                  Typical Resolution Time by Priority
                </h4>
                <span className="text-xs text-[#64748B]">vs. Official SLA Target</span>
              </div>
              <p className="text-xs text-[#64748B] mb-4">
                Compares typical (median) completion time against the official SLA target for each priority level.
              </p>

              {byPriority.length === 0 ? (
                <p className="text-xs text-[#64748B] py-6 text-center">No priority resolution data available.</p>
              ) : (
                <div className="space-y-4">
                  {byPriority.map((row) => {
                    const pctOfCohort = sampleSize > 0 ? Math.round((row.count / sampleSize) * 100) : 0;
                    const barWidth = Math.max(6, Math.round((Number(row.median_hours || 0) / maxPriorityHours) * 100));
                    const style = PRIORITY_BAR_STYLES[row.priority] || PRIORITY_BAR_STYLES.low;
                    const exceedsSla = row.sla_target_hours && Number(row.median_hours) > Number(row.sla_target_hours);

                    return (
                      <div key={row.priority} className="space-y-1.5">
                        <div className="flex items-center justify-between text-xs flex-wrap gap-1">
                          <div className="flex items-center gap-2">
                            <span className={`px-2 py-0.5 rounded font-bold uppercase text-[11px] border ${style.badge}`}>
                              {row.priority}
                            </span>
                            <span className="text-[#64748B] font-medium">
                              {row.count} case{row.count === 1 ? '' : 's'} ({pctOfCohort}%)
                            </span>
                            {row.low_sample && (
                              <span
                                className="px-1.5 py-0.5 rounded bg-amber-50 text-amber-800 border border-amber-200 text-[10px] font-semibold"
                                title="Fewer than 3 historical cases in this priority level"
                              >
                                Low sample (n={row.count})
                              </span>
                            )}
                          </div>
                          <div className="text-right">
                            <span className="font-bold text-[#0F172A]">
                              Typical: {formatHours(row.median_hours)}
                            </span>
                            {row.sla_target_hours && (
                              <span className={`ml-2 text-[11px] font-medium ${exceedsSla ? 'text-red-600 font-semibold' : 'text-[#64748B]'}`}>
                                (Official Target: {row.sla_target_hours}h)
                              </span>
                            )}
                          </div>
                        </div>
                        <div className="w-full bg-slate-100 rounded-full h-3 overflow-hidden">
                          <div
                            className={`${style.bar} h-3 rounded-full transition-all duration-300`}
                            style={{ width: `${Math.min(barWidth, 100)}%` }}
                          />
                        </div>
                      </div>
                    );
                  })}
                </div>
              )}
            </div>

            {/* Category Breakdown Horizontal Bar Chart */}
            <div className="rounded-xl border border-[#E2E8F0] p-5 bg-white">
              <div className="flex items-center justify-between mb-1">
                <h4 className="text-sm font-bold text-[#0F172A] flex items-center gap-2">
                  <BarChart2 size={16} className="text-[#2563EB]" />
                  Typical Resolution Time by Category
                </h4>
                <span className="text-xs text-[#64748B]">Historical Turnaround</span>
              </div>
              <p className="text-xs text-[#64748B] mb-4">
                Shows which concern categories take the longest to resolve and how many completed cases were analyzed.
              </p>

              {byCategory.length === 0 ? (
                <p className="text-xs text-[#64748B] py-6 text-center">No category resolution data available.</p>
              ) : (
                <div className="space-y-4 max-h-72 overflow-y-auto pr-1">
                  {byCategory.map((row, idx) => {
                    const pctOfCohort = sampleSize > 0 ? Math.round((row.count / sampleSize) * 100) : 0;
                    const barWidth = Math.max(6, Math.round((Number(row.median_hours || 0) / maxCategoryHours) * 100));
                    return (
                      <div key={`${row.category}-${idx}`} className="space-y-1.5">
                        <div className="flex items-center justify-between text-xs flex-wrap gap-1">
                          <div className="flex items-center gap-2">
                            <span className="font-bold text-[#0F172A]">{row.category}</span>
                            <span className="text-[#64748B]">
                              {row.count} case{row.count === 1 ? '' : 's'} ({pctOfCohort}%)
                            </span>
                            {row.low_sample && (
                              <span className="px-1.5 py-0.5 rounded bg-amber-50 text-amber-800 border border-amber-200 text-[10px] font-semibold">
                                Low sample (n={row.count})
                              </span>
                            )}
                          </div>
                          <div className="text-right">
                            <span className="font-bold text-[#1E3A8A]">
                              Typical: {formatHours(row.median_hours)}
                            </span>
                            <span className="ml-2 text-[11px] text-[#64748B]">
                              (Avg: {formatHours(row.mean_hours)})
                            </span>
                          </div>
                        </div>
                        <div className="w-full bg-slate-100 rounded-full h-3 overflow-hidden">
                          <div
                            className="bg-[#2563EB] h-3 rounded-full transition-all duration-300"
                            style={{ width: `${Math.min(barWidth, 100)}%` }}
                          />
                        </div>
                      </div>
                    );
                  })}
                </div>
              )}
            </div>
          </div>

          {/* Department / Academic Unit Turnaround Comparison (shown when 1+ units exist) */}
          {byDepartment.length > 0 && (
            <div className="rounded-xl border border-[#E2E8F0] p-5 bg-white">
              <div className="flex items-center justify-between mb-1">
                <h4 className="text-sm font-bold text-[#0F172A] flex items-center gap-2">
                  <Building2 size={16} className="text-[#2563EB]" />
                  Resolution Turnaround by Department / Academic Unit
                </h4>
                <span className="text-xs text-[#64748B]">Completed Cases Breakdown</span>
              </div>
              <p className="text-xs text-[#64748B] mb-4">
                Historical resolution turnaround across operational departments and academic units within this report&apos;s scope.
              </p>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                {byDepartment.map((row, idx) => {
                  const pctOfCohort = sampleSize > 0 ? Math.round((row.count / sampleSize) * 100) : 0;
                  const barWidth = Math.max(6, Math.round((Number(row.median_hours || 0) / maxDeptHours) * 100));
                  return (
                    <div key={`${row.name}-${idx}`} className="p-3.5 rounded-xl bg-[#F8FAFC] border border-[#E2E8F0] space-y-2">
                      <div className="flex items-center justify-between text-xs gap-2">
                        <div className="font-bold text-[#0F172A] truncate">{row.name}</div>
                        <div className="flex items-center gap-1.5 shrink-0">
                          <span className="font-semibold text-[#64748B]">
                            {row.count} resolved ({pctOfCohort}%)
                          </span>
                          {row.low_sample && (
                            <span className="px-1.5 py-0.5 rounded bg-amber-50 text-amber-800 border border-amber-200 text-[10px] font-semibold">
                              Low sample
                            </span>
                          )}
                        </div>
                      </div>
                      <div className="w-full bg-slate-200/70 rounded-full h-2.5 overflow-hidden">
                        <div
                          className="bg-[#1E3A8A] h-2.5 rounded-full transition-all duration-300"
                          style={{ width: `${Math.min(barWidth, 100)}%` }}
                        />
                      </div>
                      <div className="flex items-center justify-between text-[11px] text-[#64748B]">
                        <span>Typical Resolution Time: <strong className="text-[#0F172A]">{formatHours(row.median_hours)}</strong></span>
                        <span>Average Time: <strong className="text-[#0F172A]">{formatHours(row.mean_hours)}</strong></span>
                      </div>
                    </div>
                  );
                })}
              </div>
            </div>
          )}

          {/* ── SECTION 6: COLLAPSIBLE ADVANCED ANALYTICS ── */}
          <div className="rounded-xl border border-[#E2E8F0] overflow-hidden bg-[#F8FAFC]">
            <button
              type="button"
              onClick={() => setShowAdvanced((prev) => !prev)}
              className="w-full px-5 py-3.5 flex items-center justify-between text-left hover:bg-slate-100/80 transition-colors print:hidden"
            >
              <div className="flex items-center gap-2.5">
                <Sliders size={16} className="text-[#1E3A8A]" />
                <div>
                  <span className="text-sm font-bold text-[#0F172A]">
                    Advanced Resolution Analytics &amp; Prediction Accuracy Details
                  </span>
                  <span className="block text-xs text-[#64748B]">
                    Expand to inspect detailed statistical tables, Typical Resolution Ranges (IQR), and Prediction Accuracy Check (Leave-One-Out Backtest) metrics.
                  </span>
                </div>
              </div>
              <span className="inline-flex items-center gap-1 px-3 py-1 rounded-lg bg-white border border-[#E2E8F0] text-xs font-semibold text-[#1E3A8A] shrink-0">
                {showAdvanced ? (
                  <>Hide Details <ChevronUp size={14} /></>
                ) : (
                  <>Show Advanced Tables <ChevronDown size={14} /></>
                )}
              </span>
            </button>

            {showAdvanced && (
              <div className="p-5 bg-white border-t border-[#E2E8F0] space-y-5">
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-5">
                  {/* Detailed Priority Breakdown Table */}
                  <div className="rounded-xl border border-[#E2E8F0] overflow-hidden">
                    <div className="px-4 py-3 bg-[#F8FAFC] border-b border-[#E2E8F0] flex items-center justify-between">
                      <span className="text-xs font-bold text-[#0F172A] uppercase tracking-wider">
                        Detailed Priority Breakdown
                      </span>
                      <span className="text-[11px] text-[#64748B]">Historical vs. Official SLA</span>
                    </div>
                    <div className="overflow-x-auto">
                      <table className="w-full text-xs text-left">
                        <thead className="bg-slate-50/70 text-[10px] font-bold text-[#64748B] uppercase border-b border-slate-100">
                          <tr>
                            <th className="py-2.5 px-3">Priority</th>
                            <th className="py-2.5 px-3">Cases Used (n)</th>
                            <th className="py-2.5 px-3">Typical (Median)</th>
                            <th className="py-2.5 px-3">Average (Mean)</th>
                            <th className="py-2.5 px-3">Official SLA</th>
                          </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                          {byPriority.map((row) => (
                            <tr key={row.priority} className="hover:bg-slate-50/60">
                              <td className="py-2.5 px-3 font-bold capitalize text-[#0F172A]">
                                {row.priority}
                                {row.low_sample && row.count > 0 && (
                                  <span className="ml-1.5 px-1.5 py-0.5 rounded bg-amber-50 text-amber-700 border border-amber-200 text-[9px] font-semibold">
                                    Low n
                                  </span>
                                )}
                              </td>
                              <td className="py-2.5 px-3 text-[#64748B] font-semibold">{row.count}</td>
                              <td className="py-2.5 px-3 font-semibold text-[#1E3A8A]">
                                {formatHours(row.median_hours)}
                              </td>
                              <td className="py-2.5 px-3 text-[#0F172A]">{formatHours(row.mean_hours)}</td>
                              <td className="py-2.5 px-3 text-[#64748B] font-medium">
                                {row.sla_target_hours ? `${row.sla_target_hours}h` : '—'}
                              </td>
                            </tr>
                          ))}
                        </tbody>
                      </table>
                    </div>
                  </div>

                  {/* Prediction Accuracy Details (Leave-One-Out Backtest) */}
                  <div className="rounded-xl border border-[#E2E8F0] overflow-hidden">
                    <div className="px-4 py-3 bg-[#F8FAFC] border-b border-[#E2E8F0] flex items-center justify-between">
                      <span className="text-xs font-bold text-[#0F172A] uppercase tracking-wider">
                        Prediction Accuracy Details
                      </span>
                      <span className="text-[11px] text-[#64748B]">Prediction Accuracy Check (Leave-One-Out Backtest)</span>
                    </div>
                    <div className="overflow-x-auto">
                      <table className="w-full text-xs text-left">
                        <thead className="bg-slate-50/70 text-[10px] font-bold text-[#64748B] uppercase border-b border-slate-100">
                          <tr>
                            <th className="py-2.5 px-3">Priority</th>
                            <th className="py-2.5 px-3">Tested Cases</th>
                            <th className="py-2.5 px-3">Avg Predicted</th>
                            <th className="py-2.5 px-3">Avg Actual</th>
                            <th className="py-2.5 px-3">Avg Error (MAE)</th>
                          </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                          {pvaByPriority.length === 0 ? (
                            <tr>
                              <td colSpan={5} className="py-4 text-center text-[#64748B]">
                                Not enough historical records for prediction accuracy comparison.
                              </td>
                            </tr>
                          ) : (
                            pvaByPriority.map((row) => (
                              <tr key={row.priority} className="hover:bg-slate-50/60">
                                <td className="py-2.5 px-3 font-bold capitalize text-[#0F172A]">
                                  {row.priority}
                                </td>
                                <td className="py-2.5 px-3 text-[#64748B] font-semibold">{row.count}</td>
                                <td className="py-2.5 px-3 font-semibold text-[#1E3A8A]">
                                  {formatHours(row.avg_predicted_hours)}
                                </td>
                                <td className="py-2.5 px-3 font-semibold text-[#0F172A]">
                                  {formatHours(row.avg_actual_hours)}
                                </td>
                                <td className="py-2.5 px-3 text-[#64748B]">
                                  ±{formatHours(row.mae_hours)}
                                </td>
                              </tr>
                            ))
                          )}
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>

                {/* Historical Data Breakdown by Category & Department */}
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-5">
                  <div className="rounded-xl border border-[#E2E8F0] overflow-hidden">
                    <div className="px-4 py-3 bg-[#F8FAFC] border-b border-[#E2E8F0] flex items-center justify-between">
                      <span className="text-xs font-bold text-[#0F172A] uppercase tracking-wider">
                        Historical Data Breakdown by Category
                      </span>
                      <span className="text-[11px] text-[#64748B]">Typical &amp; Range</span>
                    </div>
                    <div className="overflow-x-auto max-h-60">
                      <table className="w-full text-xs text-left">
                        <thead className="bg-slate-50/70 text-[10px] font-bold text-[#64748B] uppercase border-b border-slate-100">
                          <tr>
                            <th className="py-2.5 px-3">Category</th>
                            <th className="py-2.5 px-3">Cases (n)</th>
                            <th className="py-2.5 px-3">Typical (Median)</th>
                            <th className="py-2.5 px-3">Average (Mean)</th>
                            <th className="py-2.5 px-3">Typical Range (IQR)</th>
                          </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                          {byCategory.map((row, idx) => (
                            <tr key={`${row.category}-${idx}`} className="hover:bg-slate-50/60">
                              <td className="py-2.5 px-3 font-semibold text-[#0F172A]">{row.category}</td>
                              <td className="py-2.5 px-3 text-[#64748B] font-semibold">{row.count}</td>
                              <td className="py-2.5 px-3 font-semibold text-[#1E3A8A]">{formatHours(row.median_hours)}</td>
                              <td className="py-2.5 px-3 text-[#0F172A]">{formatHours(row.mean_hours)}</td>
                              <td className="py-2.5 px-3 text-[#64748B]">
                                {row.p25_hours !== undefined && row.p75_hours !== undefined
                                  ? `${formatHours(row.p25_hours)} – ${formatHours(row.p75_hours)}`
                                  : '—'}
                              </td>
                            </tr>
                          ))}
                        </tbody>
                      </table>
                    </div>
                  </div>

                  <div className="rounded-xl border border-[#E2E8F0] overflow-hidden">
                    <div className="px-4 py-3 bg-[#F8FAFC] border-b border-[#E2E8F0] flex items-center justify-between">
                      <span className="text-xs font-bold text-[#0F172A] uppercase tracking-wider">
                        Historical Data Breakdown by Department / Unit
                      </span>
                      <span className="text-[11px] text-[#64748B]">Turnaround Table</span>
                    </div>
                    <div className="overflow-x-auto max-h-60">
                      <table className="w-full text-xs text-left">
                        <thead className="bg-slate-50/70 text-[10px] font-bold text-[#64748B] uppercase border-b border-slate-100">
                          <tr>
                            <th className="py-2.5 px-3">Department / Unit</th>
                            <th className="py-2.5 px-3">Cases (n)</th>
                            <th className="py-2.5 px-3">Typical (Median)</th>
                            <th className="py-2.5 px-3">Average (Mean)</th>
                          </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                          {byDepartment.map((row, idx) => (
                            <tr key={`${row.name}-${idx}`} className="hover:bg-slate-50/60">
                              <td className="py-2.5 px-3 font-semibold text-[#0F172A]">{row.name}</td>
                              <td className="py-2.5 px-3 text-[#64748B] font-semibold">{row.count}</td>
                              <td className="py-2.5 px-3 font-semibold text-[#1E3A8A]">{formatHours(row.median_hours)}</td>
                              <td className="py-2.5 px-3 text-[#0F172A]">{formatHours(row.mean_hours)}</td>
                            </tr>
                          ))}
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>
              </div>
            )}
          </div>
        </>
      )}

      {/* ── Plain-Language Methodology Note ── */}
      <div className="pt-2 border-t border-slate-100 text-xs text-[#64748B] flex items-start gap-2">
        <Info size={14} className="text-[#64748B] shrink-0 mt-0.5" />
        <span>
          {analytics.notice ||
            'Estimates are based on historical completed concerns using a 5-step similarity match (department, category, and priority). Groups marked "Low sample" have fewer than 3 completed cases.'}
        </span>
      </div>
    </div>
  );
};

export default ResolutionAnalyticsSection;
