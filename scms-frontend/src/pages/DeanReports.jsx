import { useState, useCallback, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  FileText,
  Download,
  BarChart2,
  AlertTriangle,
  CheckCircle,
  Clock,
  TrendingUp,
  TrendingDown,
  RefreshCw,
  Activity,
  ChevronRight,
  Filter,
  RotateCcw,
  Lightbulb,
  AlertCircle,
  Calendar,
  CheckCircle2,
} from 'lucide-react';
import api from '../api';
import StatusBadge from '../components/StatusBadge';
import PriorityBadge from '../components/PriorityBadge';
import ResolutionAnalyticsSection from '../components/ResolutionAnalyticsSection';

// ─── Formatting Helpers ──────────────────────────────────────────────────────

const formatHoursLabel = (hours) => {
  if (hours === null || hours === undefined) return 'No data yet';
  const num = Number(hours);
  if (Number.isNaN(num)) return 'No data yet';
  if (num >= 24) {
    return `${num.toFixed(1)}h (${(num / 24).toFixed(1)} days)`;
  }
  return `${num.toFixed(1)} hours`;
};

const toIsoDate = (dateObj) => dateObj.toISOString().slice(0, 10);

function SummaryKpiCard({
  icon: Icon,
  label,
  value,
  explanation,
  badgeText,
  badgeTone = 'neutral',
  accentBorder = 'border-slate-200',
  iconStyle = 'bg-blue-50 text-[#2563EB]',
}) {
  const badgeColors = {
    positive: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    warning:  'bg-amber-50 text-amber-800 border-amber-200',
    danger:   'bg-red-50 text-red-700 border-red-200',
    info:     'bg-blue-50 text-[#1E3A8A] border-blue-200',
    neutral:  'bg-slate-100 text-slate-600 border-slate-200',
  };

  return (
    <div className={`bg-white rounded-xl border ${accentBorder} p-5 shadow-sm flex flex-col justify-between`}>
      <div>
        <div className="flex items-center justify-between gap-2 mb-3">
          <span className="text-xs font-bold text-[#64748B] uppercase tracking-wider">
            {label}
          </span>
          <div className={`w-9 h-9 rounded-lg flex items-center justify-center shrink-0 ${iconStyle}`}>
            <Icon size={18} />
          </div>
        </div>
        <div className="text-3xl font-extrabold text-[#0F172A] tracking-tight">
          {value}
        </div>
      </div>

      <div className="mt-3 pt-3 border-t border-slate-100 flex items-center justify-between gap-2 flex-wrap">
        <span className="text-xs text-[#64748B] leading-snug">{explanation}</span>
        {badgeText && (
          <span className={`px-2 py-0.5 rounded-full text-[11px] font-semibold border ${badgeColors[badgeTone] || badgeColors.neutral}`}>
            {badgeText}
          </span>
        )}
      </div>
    </div>
  );
}

function HorizontalBreakdownBar({ label, count, total, color = 'bg-[#2563EB]', badge = null }) {
  const pct = total > 0 ? Math.round((count / total) * 100) : 0;
  return (
    <div className="space-y-1.5">
      <div className="flex items-center justify-between text-xs gap-2">
        <div className="flex items-center gap-2 min-w-0">
          <span className="font-semibold text-[#0F172A] capitalize truncate">
            {String(label).replace(/_/g, ' ')}
          </span>
          {badge}
        </div>
        <span className="font-bold text-[#0F172A] shrink-0">
          {count} <span className="text-[#64748B] font-normal">({pct}%)</span>
        </span>
      </div>
      <div className="w-full bg-slate-100 rounded-full h-2.5 overflow-hidden">
        <div
          className={`${color} h-2.5 rounded-full transition-all duration-300`}
          style={{ width: `${Math.max(count > 0 ? 4 : 0, Math.min(pct, 100))}%` }}
        />
      </div>
    </div>
  );
}

// ─── Main Component ──────────────────────────────────────────────────────────

const DeanReports = () => {
  const navigate = useNavigate();

  const [periodPreset, setPeriodPreset] = useState('all');
  const [filters, setFilters] = useState({
    date_from:     '',
    date_to:       '',
    status:        '',
    priority:      '',
    workflow_type: '',
    category:      '',
  });

  const [report, setReport]       = useState(null);
  const [loading, setLoading]     = useState(false);
  const [exporting, setExporting] = useState(false);
  const [error, setError]         = useState(null);
  const [generated, setGenerated] = useState(false);

  const buildParams = useCallback((activeFilters = filters) => {
    const p = {};
    if (activeFilters.date_from)     p.date_from     = activeFilters.date_from;
    if (activeFilters.date_to)       p.date_to       = activeFilters.date_to;
    if (activeFilters.status)        p.status        = activeFilters.status;
    if (activeFilters.priority)      p.priority      = activeFilters.priority;
    if (activeFilters.workflow_type) p.workflow_type = activeFilters.workflow_type;
    if (activeFilters.category)      p.category      = activeFilters.category;
    return p;
  }, [filters]);

  const fetchReport = useCallback(async (overrideFilters = null) => {
    const active = overrideFilters ?? filters;
    setLoading(true);
    setError(null);
    try {
      const res = await api.get('/dean/reports', { params: buildParams(active) });
      setReport(res.data);
      setGenerated(true);
    } catch (err) {
      setError(err.response?.data?.message ?? 'Failed to load report. Please try again.');
    } finally {
      setLoading(false);
    }
  }, [filters, buildParams]);

  // Auto-generate report on first load so the Dean immediately sees actionable analytics
  useEffect(() => {
    fetchReport();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const handleFilterChange = (e) => {
    const { name, value } = e.target;
    if (name === 'date_from' || name === 'date_to') {
      setPeriodPreset('custom');
    }
    const next = { ...filters, [name]: value };
    setFilters(next);
  };

  const handlePresetSelect = (preset) => {
    setPeriodPreset(preset);
    const today = new Date();
    let from = '';
    let to = '';
    if (preset === '7d') {
      const start = new Date();
      start.setDate(today.getDate() - 6);
      from = toIsoDate(start);
      to = toIsoDate(today);
    } else if (preset === '30d') {
      const start = new Date();
      start.setDate(today.getDate() - 29);
      from = toIsoDate(start);
      to = toIsoDate(today);
    } else if (preset === '90d') {
      const start = new Date();
      start.setDate(today.getDate() - 89);
      from = toIsoDate(start);
      to = toIsoDate(today);
    }
    const next = { ...filters, date_from: from, date_to: to };
    setFilters(next);
    fetchReport(next);
  };

  const handleResetFilters = () => {
    setPeriodPreset('all');
    const reset = {
      date_from:     '',
      date_to:       '',
      status:        '',
      priority:      '',
      workflow_type: '',
      category:      '',
    };
    setFilters(reset);
    fetchReport(reset);
  };

  const exportCsv = async () => {
    setExporting(true);
    try {
      const token = localStorage.getItem('token');
      const params = new URLSearchParams(buildParams()).toString();
      const url = `http://localhost:8000/api/dean/reports/export-csv${params ? '?' + params : ''}`;
      const res = await fetch(url, { headers: { Authorization: `Bearer ${token}` } });
      if (!res.ok) throw new Error('Export failed');
      const blob = await res.blob();
      const link = document.createElement('a');
      link.href = URL.createObjectURL(blob);
      link.download = `dean_report_${new Date().toISOString().slice(0, 10)}.csv`;
      link.click();
      URL.revokeObjectURL(link.href);
    } catch {
      setError('CSV export failed. Please try again.');
    } finally {
      setExporting(false);
    }
  };

  const printReport = () => window.print();

  const {
    summary = {},
    by_status = {},
    by_priority = {},
    by_workflow = {},
    by_category = [],
    available_categories = [],
    trend = [],
    activity_trend = [],
    activity_summary = {},
    attention_required = {},
    insights = [],
    sentiment = null,
    concerns = [],
    generated_at,
  } = report ?? {};

  const totalConcerns = summary.total ?? 0;
  const unresolvedCount = summary.unresolved ?? summary.pending ?? 0;
  const overdueCount = summary.overdue ?? 0;
  const prevPeriod = summary.previous_period || null;
  const activeFilterCount = Object.values(filters).filter(Boolean).length;

  const STATUS_BAR_COLORS = {
    pending:     'bg-amber-500',
    in_progress: 'bg-[#2563EB]',
    escalated:   'bg-orange-500',
    approved:    'bg-teal-500',
    rejected:    'bg-red-500',
    resolved:    'bg-[#10B981]',
    closed:      'bg-slate-400',
    withdrawn:   'bg-slate-300',
  };

  const PRIORITY_BAR_COLORS = {
    critical: 'bg-[#EF4444]',
    high:     'bg-[#F59E0B]',
    medium:   'bg-[#2563EB]',
    low:      'bg-slate-400',
  };

  const periodDescription = filters.date_from || filters.date_to
    ? `${filters.date_from || 'Start'} to ${filters.date_to || 'Present'}`
    : 'All Recorded Dates (Activity Chart: Last 30 Days)';

  return (
    <div className="space-y-6 print:bg-white">
      {/* ── Page Header ── */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 print:hidden">
        <div>
          <h1 className="text-2xl font-extrabold text-[#0F172A] tracking-tight">
            Dean&apos;s Reports &amp; Academic Unit Analytics
          </h1>
          <p className="text-sm text-[#64748B] mt-0.5">
            Plain-language overview of escalated concerns, academic unit workload, items needing Dean review, and historical resolution turnaround.
          </p>
        </div>
        <div className="flex items-center gap-2 flex-wrap">
          {generated && (
            <>
              <button
                type="button"
                onClick={printReport}
                className="flex items-center gap-2 px-3.5 py-2 border border-[#E2E8F0] rounded-lg bg-white text-xs sm:text-sm font-semibold text-[#0F172A] hover:bg-[#F8FAFC] shadow-xs"
              >
                <FileText size={15} className="text-[#64748B]" /> Print / PDF
              </button>
              <button
                type="button"
                onClick={exportCsv}
                disabled={exporting}
                className="flex items-center gap-2 px-3.5 py-2 border border-[#E2E8F0] rounded-lg bg-white text-xs sm:text-sm font-semibold text-[#0F172A] hover:bg-[#F8FAFC] shadow-xs disabled:opacity-60"
              >
                <Download size={15} className="text-[#64748B]" /> {exporting ? 'Exporting…' : 'Export CSV'}
              </button>
            </>
          )}
          <button
            type="button"
            onClick={() => fetchReport()}
            disabled={loading}
            className="flex items-center gap-2 px-4 py-2 bg-[#1E3A8A] rounded-lg text-xs sm:text-sm font-semibold text-white hover:bg-[#152960] shadow-sm disabled:opacity-60"
          >
            {loading ? <RefreshCw size={15} className="animate-spin" /> : <BarChart2 size={15} />}
            {loading ? 'Updating…' : 'Generate Report'}
          </button>
        </div>
      </div>

      {/* ── Print Header ── */}
      <div className="hidden print:block mb-4 pb-4 border-b border-slate-200">
        <h1 className="text-2xl font-bold text-[#0F172A]">Dean&apos;s Academic Unit Concern Report</h1>
        <p className="text-xs text-[#64748B] mt-1">
          Reporting Period: {periodDescription} &bull; Generated: {generated_at ? new Date(generated_at).toLocaleString() : '—'}
        </p>
      </div>

      {/* ── REPORT FILTERS (Section 7) ── */}
      <div className="bg-white rounded-xl border border-[#E2E8F0] p-5 shadow-sm space-y-4 print:hidden">
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-3 border-b border-slate-100">
          <div className="flex items-center gap-2">
            <Filter size={16} className="text-[#2563EB]" />
            <span className="text-xs font-bold text-[#0F172A] uppercase tracking-wider">
              Report Filters &amp; Period Selection
            </span>
            {activeFilterCount > 0 && (
              <span className="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-blue-50 text-[#2563EB] border border-blue-200">
                {activeFilterCount} active
              </span>
            )}
          </div>

          <div className="flex items-center gap-1.5 flex-wrap">
            <span className="text-xs text-[#64748B] mr-1 flex items-center gap-1">
              <Calendar size={13} /> Period:
            </span>
            {[
              { id: 'all', label: 'All Time' },
              { id: '7d',  label: 'Last 7 Days' },
              { id: '30d', label: 'Last 30 Days' },
              { id: '90d', label: 'Last 90 Days' },
            ].map((preset) => (
              <button
                key={preset.id}
                type="button"
                onClick={() => handlePresetSelect(preset.id)}
                className={`px-2.5 py-1 rounded-md text-xs font-semibold transition-colors ${
                  periodPreset === preset.id
                    ? 'bg-[#2563EB] text-white'
                    : 'bg-[#F8FAFC] text-[#64748B] hover:text-[#0F172A] border border-[#E2E8F0]'
                }`}
              >
                {preset.label}
              </button>
            ))}
            {activeFilterCount > 0 && (
              <button
                type="button"
                onClick={handleResetFilters}
                className="ml-2 inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-semibold text-red-600 hover:bg-red-50 transition-colors"
              >
                <RotateCcw size={12} /> Reset Filters
              </button>
            )}
          </div>
        </div>

        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3">
          <div>
            <label className="block text-xs font-semibold text-[#64748B] mb-1">Start Date</label>
            <input
              type="date"
              name="date_from"
              value={filters.date_from}
              onChange={handleFilterChange}
              className="w-full px-3 py-2 border border-[#E2E8F0] rounded-lg text-xs sm:text-sm bg-white text-[#0F172A] focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-[#2563EB]"
            />
          </div>
          <div>
            <label className="block text-xs font-semibold text-[#64748B] mb-1">End Date</label>
            <input
              type="date"
              name="date_to"
              value={filters.date_to}
              onChange={handleFilterChange}
              className="w-full px-3 py-2 border border-[#E2E8F0] rounded-lg text-xs sm:text-sm bg-white text-[#0F172A] focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-[#2563EB]"
            />
          </div>
          <div>
            <label className="block text-xs font-semibold text-[#64748B] mb-1">Category</label>
            <select
              name="category"
              value={filters.category}
              onChange={handleFilterChange}
              className="w-full px-3 py-2 border border-[#E2E8F0] rounded-lg text-xs sm:text-sm bg-white text-[#0F172A] focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-[#2563EB]"
            >
              <option value="">All Categories</option>
              {available_categories.map((cat) => (
                <option key={cat} value={cat}>
                  {cat === 'academic_units' ? 'Academic Concerns' : cat === 'operational_departments' ? 'Operational Departments' : cat}
                </option>
              ))}
            </select>
          </div>
          <div>
            <label className="block text-xs font-semibold text-[#64748B] mb-1">Priority</label>
            <select
              name="priority"
              value={filters.priority}
              onChange={handleFilterChange}
              className="w-full px-3 py-2 border border-[#E2E8F0] rounded-lg text-xs sm:text-sm bg-white text-[#0F172A] focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-[#2563EB]"
            >
              <option value="">All Priorities</option>
              <option value="critical">Critical</option>
              <option value="high">High</option>
              <option value="medium">Medium</option>
              <option value="low">Low</option>
            </select>
          </div>
          <div>
            <label className="block text-xs font-semibold text-[#64748B] mb-1">Status</label>
            <select
              name="status"
              value={filters.status}
              onChange={handleFilterChange}
              className="w-full px-3 py-2 border border-[#E2E8F0] rounded-lg text-xs sm:text-sm bg-white text-[#0F172A] focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-[#2563EB]"
            >
              <option value="">All Statuses</option>
              <option value="pending">Pending</option>
              <option value="in_progress">In Progress</option>
              <option value="escalated">Escalated</option>
              <option value="approved">Approved</option>
              <option value="rejected">Rejected</option>
              <option value="resolved">Resolved</option>
              <option value="closed">Closed</option>
            </select>
          </div>
          <div>
            <label className="block text-xs font-semibold text-[#64748B] mb-1">Workflow Type</label>
            <select
              name="workflow_type"
              value={filters.workflow_type}
              onChange={handleFilterChange}
              className="w-full px-3 py-2 border border-[#E2E8F0] rounded-lg text-xs sm:text-sm bg-white text-[#0F172A] focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-[#2563EB]"
            >
              <option value="">All Types</option>
              <option value="academic">Academic</option>
              <option value="non_academic">Non-Academic</option>
            </select>
          </div>
        </div>
      </div>

      {/* ── Error Banner ── */}
      {error && (
        <div className="bg-red-50 border border-red-200 rounded-xl p-4 text-sm text-red-700 flex items-center gap-2">
          <AlertTriangle size={16} className="shrink-0" />
          <span>{error}</span>
        </div>
      )}

      {/* ── Loading State ── */}
      {loading && (
        <div className="bg-white rounded-xl border border-[#E2E8F0] shadow-sm flex items-center justify-center py-20 text-[#64748B]">
          <RefreshCw size={22} className="animate-spin mr-3 text-[#2563EB]" />
          <span className="text-sm font-medium">Calculating report metrics for your academic unit…</span>
        </div>
      )}

      {/* ── Main Report Sections ── */}
      {generated && report && !loading && (
        <div className="space-y-6">
          {/* ── SECTION A: REPORT SUMMARY ── */}
          <div className="space-y-4">
            <div className="bg-white rounded-xl border border-[#E2E8F0] px-5 py-3.5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
              <div className="flex items-center gap-2 text-xs sm:text-sm text-[#0F172A]">
                <span className="w-2.5 h-2.5 rounded-full bg-[#2563EB]"></span>
                <span className="font-bold">Reporting Period:</span>
                <span className="text-[#64748B]">{periodDescription}</span>
              </div>
              <div className="text-xs text-[#64748B]">
                Scope: <strong className="text-[#0F172A]">Dean&apos;s Academic Unit &amp; Escalated Governance</strong>
                {generated_at && (
                  <span className="ml-2">&bull; Updated {new Date(generated_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</span>
                )}
              </div>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
              <SummaryKpiCard
                icon={Activity}
                label="Total Concerns Received"
                value={totalConcerns}
                explanation="All concerns routed or escalated to your academic unit scope."
                badgeText={
                  prevPeriod
                    ? `${prevPeriod.total_delta >= 0 ? '+' : ''}${prevPeriod.total_delta} ${prevPeriod.label}`
                    : null
                }
                badgeTone="info"
                iconStyle="bg-blue-50 text-[#2563EB]"
              />

              <SummaryKpiCard
                icon={CheckCircle}
                label="Total Resolved Concerns"
                value={summary.resolved ?? 0}
                explanation="Concerns completed and marked resolved."
                badgeText={
                  prevPeriod
                    ? `${prevPeriod.resolved_delta >= 0 ? '+' : ''}${prevPeriod.resolved_delta} ${prevPeriod.label}`
                    : `${summary.resolution_rate ?? 0}% resolved`
                }
                badgeTone="positive"
                iconStyle="bg-emerald-50 text-[#10B981]"
              />

              <SummaryKpiCard
                icon={Clock}
                label="Total Unresolved Concerns"
                value={unresolvedCount}
                explanation={`Open backlog (${summary.pending ?? 0} pending/in-progress, ${summary.escalated ?? 0} escalated).`}
                badgeText={unresolvedCount > 0 ? 'Active Backlog' : 'All Clear'}
                badgeTone={unresolvedCount > 0 ? 'warning' : 'positive'}
                iconStyle="bg-amber-50 text-[#F59E0B]"
              />

              <SummaryKpiCard
                icon={AlertTriangle}
                label="Overdue Concerns"
                value={overdueCount}
                explanation="Open concerns that have passed their official SLA resolution target."
                badgeText={
                  (summary.approaching_deadline ?? 0) > 0
                    ? `${summary.approaching_deadline} approaching target (≥75%)`
                    : overdueCount === 0
                    ? '0 Overdue'
                    : 'Needs Immediate Action'
                }
                badgeTone={overdueCount > 0 ? 'danger' : 'positive'}
                accentBorder={overdueCount > 0 ? 'border-red-200' : 'border-[#E2E8F0]'}
                iconStyle={overdueCount > 0 ? 'bg-red-50 text-[#EF4444]' : 'bg-slate-100 text-[#64748B]'}
              />

              <SummaryKpiCard
                icon={TrendingUp}
                label="Typical Resolution Time"
                value={formatHoursLabel(summary.median_resolution_hours)}
                explanation="Middle resolution duration across completed cases in your academic unit."
                badgeText={
                  summary.mean_resolution_hours !== null && summary.mean_resolution_hours !== undefined
                    ? `Avg: ${formatHoursLabel(summary.mean_resolution_hours)}`
                    : null
                }
                badgeTone="info"
                iconStyle="bg-indigo-50 text-[#1E3A8A]"
              />

              <SummaryKpiCard
                icon={CheckCircle2}
                label="Resolution Rate"
                value={`${summary.resolution_rate ?? 0}%`}
                explanation={`Percentage of concerns in scope resolved (${summary.resolved ?? 0} of ${totalConcerns}).`}
                badgeText={
                  (summary.resolution_rate ?? 0) >= 70
                    ? 'Healthy Turnaround'
                    : totalConcerns === 0
                    ? 'No Cases'
                    : 'In Progress'
                }
                badgeTone={(summary.resolution_rate ?? 0) >= 70 ? 'positive' : 'neutral'}
                iconStyle="bg-emerald-50 text-[#10B981]"
              />
            </div>
          </div>

          {/* ── SECTION G: AUTOMATIC INSIGHTS ── */}
          {insights.length > 0 && (
            <div className="bg-white rounded-xl border border-[#E2E8F0] p-5 shadow-sm">
              <div className="flex items-center gap-2.5 mb-3">
                <div className="w-8 h-8 rounded-lg bg-blue-50 text-[#2563EB] flex items-center justify-center shrink-0">
                  <Lightbulb size={17} />
                </div>
                <div>
                  <h2 className="text-base font-bold text-[#0F172A]">Key Report Takeaways</h2>
                  <p className="text-xs text-[#64748B]">
                    Automatic plain-language summary generated directly from your academic unit&apos;s current numbers.
                  </p>
                </div>
              </div>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                {insights.map((item, idx) => {
                  const toneStyles = {
                    critical: 'bg-red-50/70 border-red-200 text-red-900',
                    warning:  'bg-amber-50/70 border-amber-200 text-amber-900',
                    positive: 'bg-emerald-50/70 border-emerald-200 text-emerald-900',
                    info:     'bg-[#F8FAFC] border-[#E2E8F0] text-[#0F172A]',
                    neutral:  'bg-[#F8FAFC] border-[#E2E8F0] text-[#64748B]',
                  };
                  return (
                    <div
                      key={idx}
                      className={`p-3.5 rounded-lg border text-xs sm:text-sm leading-relaxed flex items-start gap-2.5 ${toneStyles[item.type] || toneStyles.info}`}
                    >
                      <span className="w-2 h-2 rounded-full bg-current mt-1.5 shrink-0" />
                      <span>{item.text}</span>
                    </div>
                  );
                })}
              </div>
            </div>
          )}

          {/* ── SECTION C: CONCERNS REQUIRING ATTENTION ── */}
          <div className="bg-white rounded-xl border border-[#E2E8F0] p-6 shadow-sm space-y-5">
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 pb-4 border-b border-slate-100">
              <div className="flex items-center gap-3">
                <div className="w-10 h-10 rounded-xl bg-red-50 text-[#EF4444] flex items-center justify-center shrink-0 border border-red-100">
                  <AlertCircle size={20} />
                </div>
                <div>
                  <h2 className="text-lg font-bold text-[#0F172A]">
                    Concerns Requiring Dean Attention
                  </h2>
                  <p className="text-sm text-[#64748B]">
                    Unresolved critical, high-priority, overdue, or escalated cases within your academic unit scope.
                  </p>
                </div>
              </div>
            </div>

            <div className="grid grid-cols-2 lg:grid-cols-4 gap-3">
              <div className="p-4 rounded-xl bg-red-50/60 border border-red-200">
                <div className="text-xs font-bold text-red-800 uppercase tracking-wider">Critical Unresolved</div>
                <div className="text-2xl font-extrabold text-[#EF4444] mt-1">
                  {attention_required.critical_unresolved_count ?? summary.critical_unresolved ?? 0}
                </div>
                <div className="text-xs text-red-700/80 mt-0.5">Highest urgency open cases</div>
              </div>

              <div className="p-4 rounded-xl bg-amber-50/60 border border-amber-200">
                <div className="text-xs font-bold text-amber-900 uppercase tracking-wider">High-Priority Unresolved</div>
                <div className="text-2xl font-extrabold text-[#F59E0B] mt-1">
                  {attention_required.high_unresolved_count ?? summary.high_unresolved ?? 0}
                </div>
                <div className="text-xs text-amber-800/80 mt-0.5">Open high-priority cases</div>
              </div>

              <div className="p-4 rounded-xl bg-rose-50/60 border border-rose-200">
                <div className="text-xs font-bold text-rose-900 uppercase tracking-wider">Overdue Target (SLA)</div>
                <div className="text-2xl font-extrabold text-rose-700 mt-1">
                  {attention_required.overdue_count ?? overdueCount}
                </div>
                <div className="text-xs text-rose-800/80 mt-0.5">Past official resolution target</div>
              </div>

              <div className="p-4 rounded-xl bg-orange-50/60 border border-orange-200">
                <div className="text-xs font-bold text-orange-900 uppercase tracking-wider">Escalated Cases</div>
                <div className="text-2xl font-extrabold text-orange-600 mt-1">
                  {attention_required.escalated_count ?? summary.escalated ?? 0}
                </div>
                <div className="text-xs text-orange-800/80 mt-0.5">Escalated to Dean / CAD</div>
              </div>
            </div>

            {Array.isArray(attention_required.items) && attention_required.items.length > 0 ? (
              <div className="overflow-x-auto rounded-xl border border-[#E2E8F0]">
                <table className="w-full text-xs text-left">
                  <thead className="bg-[#F8FAFC] text-[11px] uppercase font-bold text-[#64748B] border-b border-[#E2E8F0]">
                    <tr>
                      <th className="px-4 py-3">Concern</th>
                      <th className="px-4 py-3">Why It Needs Attention</th>
                      <th className="px-4 py-3">Priority &amp; Status</th>
                      <th className="px-4 py-3">Elapsed vs. Target</th>
                      <th className="px-4 py-3 text-right print:hidden">Action</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100">
                    {attention_required.items.map((item) => (
                      <tr key={item.concern_id} className="hover:bg-slate-50/80">
                        <td className="px-4 py-3">
                          <div className="font-bold text-[#0F172A]">#{item.concern_id} — {item.title}</div>
                          <div className="text-[11px] text-[#64748B] mt-0.5">
                            {item.category} &bull; Student: {item.student_name}
                          </div>
                        </td>
                        <td className="px-4 py-3">
                          <div className="flex flex-wrap gap-1">
                            {(item.reasons || []).map((r, i) => (
                              <span
                                key={i}
                                className={`px-2 py-0.5 rounded-full text-[10px] font-bold border ${
                                  r.includes('Critical') || r.includes('Overdue')
                                    ? 'bg-red-50 text-red-700 border-red-200'
                                    : 'bg-amber-50 text-amber-800 border-amber-200'
                                }`}
                              >
                                {r}
                              </span>
                            ))}
                          </div>
                        </td>
                        <td className="px-4 py-3">
                          <div className="flex items-center gap-1.5">
                            <PriorityBadge priority={item.priority} size="xs" />
                            <StatusBadge status={item.status} size="xs" />
                          </div>
                        </td>
                        <td className="px-4 py-3 text-[#64748B]">
                          {item.elapsed_hours !== null && item.target_hours
                            ? `${item.elapsed_hours}h elapsed / ${item.target_hours}h target`
                            : '—'}
                        </td>
                        <td className="px-4 py-3 text-right print:hidden">
                          <button
                            type="button"
                            onClick={() => navigate(`/dean/concerns/${item.concern_id}`)}
                            className="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-blue-50 hover:bg-blue-100 text-[#1E3A8A] font-semibold text-xs transition-colors"
                          >
                            Review <ChevronRight size={13} />
                          </button>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            ) : (
              <div className="p-5 rounded-xl bg-emerald-50/60 border border-emerald-200 flex items-center gap-3 text-emerald-900 text-xs sm:text-sm">
                <CheckCircle2 size={18} className="text-[#10B981] shrink-0" />
                <span>
                  <strong>All clear:</strong> There are currently no critical, high-priority, overdue, or escalated unresolved concerns requiring urgent Dean action in your scope.
                </span>
              </div>
            )}
          </div>

          {/* ── SECTION B: CONCERN ACTIVITY & SENTIMENT SUMMARY ── */}
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {/* Activity Chart (2 Cols) */}
            <div className="lg:col-span-2 bg-white rounded-xl border border-[#E2E8F0] p-6 shadow-sm space-y-5">
              <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-4 border-b border-slate-100">
                <div>
                  <h2 className="text-lg font-bold text-[#0F172A]">
                    Concern Activity &amp; Backlog Trend
                  </h2>
                  <p className="text-sm text-[#64748B]">
                    Submitted vs. resolved concerns over time and net backlog changes.
                  </p>
                </div>
                <div className="flex items-center gap-4 text-xs font-semibold">
                  <span className="flex items-center gap-1.5 text-[#0F172A]">
                    <span className="w-3 h-3 rounded-xs bg-[#2563EB] inline-block" /> Submitted
                  </span>
                  <span className="flex items-center gap-1.5 text-[#0F172A]">
                    <span className="w-3 h-3 rounded-xs bg-[#10B981] inline-block" /> Resolved
                  </span>
                </div>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div className="p-3.5 rounded-xl bg-[#F8FAFC] border border-[#E2E8F0] flex items-center justify-between">
                  <span className="text-xs font-semibold text-[#64748B]">Submitted</span>
                  <span className="text-lg font-extrabold text-[#2563EB]">
                    {activity_summary.period_submitted ?? trend.reduce((acc, d) => acc + (d.count || 0), 0)}
                  </span>
                </div>
                <div className="p-3.5 rounded-xl bg-[#F8FAFC] border border-[#E2E8F0] flex items-center justify-between">
                  <span className="text-xs font-semibold text-[#64748B]">Resolved</span>
                  <span className="text-lg font-extrabold text-[#10B981]">
                    {activity_summary.period_resolved ?? 0}
                  </span>
                </div>
                <div className="p-3.5 rounded-xl bg-[#F8FAFC] border border-[#E2E8F0] flex items-center justify-between">
                  <span className="text-xs font-semibold text-[#64748B]">Net Backlog</span>
                  <span
                    className={`text-lg font-extrabold flex items-center gap-1 ${
                      (activity_summary.net_backlog_change ?? 0) > 0
                        ? 'text-[#F59E0B]'
                        : (activity_summary.net_backlog_change ?? 0) < 0
                        ? 'text-[#10B981]'
                        : 'text-[#0F172A]'
                    }`}
                  >
                    {(activity_summary.net_backlog_change ?? 0) > 0 ? (
                      <><TrendingUp size={16} /> +{activity_summary.net_backlog_change}</>
                    ) : (activity_summary.net_backlog_change ?? 0) < 0 ? (
                      <><TrendingDown size={16} /> {activity_summary.net_backlog_change}</>
                    ) : (
                      '0 (Balanced)'
                    )}
                  </span>
                </div>
              </div>

              {activity_trend.length === 0 && trend.length === 0 ? (
                <div className="py-10 text-center text-xs text-[#64748B] bg-[#F8FAFC] rounded-xl border border-[#E2E8F0]">
                  No submission or resolution activity recorded for the selected date range.
                </div>
              ) : (
                <div className="space-y-2.5 max-h-64 overflow-y-auto pr-2">
                  {(() => {
                    const rows = activity_trend.length > 0
                      ? activity_trend
                      : trend.map((d) => ({ date: d.date, submitted: d.count, resolved: 0, cumulative_backlog_change: d.count }));
                    const maxVal = Math.max(...rows.map((r) => Math.max(r.submitted || 0, r.resolved || 0)), 1);

                    return rows.map((day) => {
                      const subPct = Math.round(((day.submitted || 0) / maxVal) * 100);
                      const resPct = Math.round(((day.resolved || 0) / maxVal) * 100);
                      return (
                        <div key={day.date} className="p-2.5 rounded-lg bg-[#F8FAFC] border border-slate-100 grid grid-cols-12 items-center gap-3 text-xs">
                          <div className="col-span-3 font-semibold text-[#0F172A]">
                            {day.date}
                          </div>
                          <div className="col-span-6 space-y-1.5">
                            <div className="flex items-center gap-2">
                              <span className="w-16 text-[11px] text-[#64748B] shrink-0">Submitted</span>
                              <div className="flex-1 bg-slate-200/70 rounded-full h-2 overflow-hidden">
                                <div className="bg-[#2563EB] h-2 rounded-full" style={{ width: `${subPct}%` }} />
                              </div>
                              <span className="w-6 text-right font-bold text-[#2563EB]">{day.submitted}</span>
                            </div>
                            <div className="flex items-center gap-2">
                              <span className="w-16 text-[11px] text-[#64748B] shrink-0">Resolved</span>
                              <div className="flex-1 bg-slate-200/70 rounded-full h-2 overflow-hidden">
                                <div className="bg-[#10B981] h-2 rounded-full" style={{ width: `${resPct}%` }} />
                              </div>
                              <span className="w-6 text-right font-bold text-[#10B981]">{day.resolved}</span>
                            </div>
                          </div>
                          <div className="col-span-3 text-right">
                            <span
                              className={`inline-flex px-2 py-0.5 rounded text-[11px] font-semibold ${
                                day.cumulative_backlog_change > 0
                                  ? 'bg-amber-50 text-amber-800 border border-amber-200'
                                  : day.cumulative_backlog_change < 0
                                  ? 'bg-emerald-50 text-emerald-700 border border-emerald-200'
                                  : 'bg-slate-100 text-[#64748B]'
                              }`}
                            >
                              Backlog: {day.cumulative_backlog_change > 0 ? `+${day.cumulative_backlog_change}` : day.cumulative_backlog_change}
                            </span>
                          </div>
                        </div>
                      );
                    });
                  })()}
                </div>
              )}

              {activity_summary.explanation && (
                <div className="p-3.5 rounded-xl bg-blue-50/60 border border-blue-200 text-xs sm:text-sm text-[#1E3A8A] flex items-center gap-2.5">
                  <Activity size={16} className="text-[#2563EB] shrink-0" />
                  <span><strong>What this chart means:</strong> {activity_summary.explanation}</span>
                </div>
              )}
            </div>

            {/* Sentiment & Workflow Type Summary (1 Col) */}
            <div className="bg-white rounded-xl border border-[#E2E8F0] p-6 shadow-sm flex flex-col justify-between space-y-5">
              <div>
                <h2 className="text-lg font-bold text-[#0F172A] mb-1">Student Sentiment &amp; Type</h2>
                <p className="text-xs text-[#64748B] mb-4">
                  Emotional tone of submitted concerns and workflow distribution.
                </p>

                {!sentiment ? (
                  <p className="text-xs text-[#64748B] py-4 bg-[#F8FAFC] p-3 rounded-lg border border-[#E2E8F0]">
                    No sentiment data available for the selected filters.
                  </p>
                ) : (
                  <div className="space-y-3.5">
                    {[
                      { label: 'Positive Tone', key: 'positive', color: 'bg-[#10B981]' },
                      { label: 'Neutral Tone',  key: 'neutral',  color: 'bg-slate-400' },
                      { label: 'Negative Tone', key: 'negative', color: 'bg-[#EF4444]' },
                    ].map(({ label, key, color }) => {
                      const total = (sentiment.positive ?? 0) + (sentiment.neutral ?? 0) + (sentiment.negative ?? 0);
                      const val   = sentiment[key] ?? 0;
                      const pct   = total > 0 ? Math.round((val / total) * 100) : 0;
                      return (
                        <div key={key}>
                          <div className="flex justify-between text-xs text-[#0F172A] mb-1">
                            <span className="font-semibold">{label}</span>
                            <span className="font-bold">{val} <span className="text-[#64748B] font-normal">({pct}%)</span></span>
                          </div>
                          <div className="bg-slate-100 rounded-full h-2.5 overflow-hidden">
                            <div className={`${color} h-2.5 rounded-full`} style={{ width: `${pct}%` }} />
                          </div>
                        </div>
                      );
                    })}
                  </div>
                )}

                {by_workflow && Object.keys(by_workflow).length > 0 && (
                  <div className="mt-6 pt-5 border-t border-slate-100">
                    <h3 className="text-xs font-bold text-[#64748B] uppercase tracking-wider mb-3">
                      Concerns by Workflow Type
                    </h3>
                    <div className="grid grid-cols-2 gap-3">
                      {Object.entries(by_workflow).map(([type, count]) => (
                        <div key={type} className="p-3 rounded-xl bg-[#F8FAFC] border border-[#E2E8F0]">
                          <div className="text-xl font-extrabold text-[#0F172A]">{count}</div>
                          <div className="text-xs text-[#64748B] capitalize mt-0.5">{type.replace('_', '-')}</div>
                        </div>
                      ))}
                    </div>
                  </div>
                )}
              </div>

              <button
                type="button"
                onClick={() => navigate('/analytics')}
                className="w-full py-2.5 px-4 rounded-lg bg-blue-50 hover:bg-blue-100 text-[#1E3A8A] text-xs font-bold flex items-center justify-center gap-2 transition-colors print:hidden"
              >
                <Activity size={14} /> Open Detailed Sentiment Analytics
              </button>
            </div>
          </div>

          {/* ── SECTION E: PRIORITY, CATEGORY & STATUS BREAKDOWNS ── */}
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-5">
            {/* Priority Breakdown */}
            <div className="bg-white rounded-xl border border-[#E2E8F0] shadow-sm p-5">
              <div className="flex items-center justify-between mb-1">
                <h2 className="text-base font-bold text-[#0F172A]">Concerns by Priority</h2>
                <span className="text-xs font-semibold text-[#64748B]">n = {totalConcerns}</span>
              </div>
              <p className="text-xs text-[#64748B] mb-4">
                Share of concerns at each urgency level.
              </p>
              {totalConcerns === 0 ? (
                <p className="text-xs text-[#64748B] py-6 text-center">No priority data for current filters.</p>
              ) : (
                <div className="space-y-3.5">
                  {['critical', 'high', 'medium', 'low'].map((p) => (
                    <HorizontalBreakdownBar
                      key={p}
                      label={p}
                      count={by_priority[p] ?? 0}
                      total={totalConcerns}
                      color={PRIORITY_BAR_COLORS[p]}
                    />
                  ))}
                </div>
              )}
            </div>

            {/* Category Breakdown */}
            <div className="bg-white rounded-xl border border-[#E2E8F0] shadow-sm p-5">
              <div className="flex items-center justify-between mb-1">
                <h2 className="text-base font-bold text-[#0F172A]">Concerns by Category</h2>
                <span className="text-xs font-semibold text-[#64748B]">n = {totalConcerns}</span>
              </div>
              <p className="text-xs text-[#64748B] mb-4">
                Where concerns are occurring by problem category.
              </p>
              {by_category.length === 0 ? (
                <p className="text-xs text-[#64748B] py-6 text-center">No category data for current filters.</p>
              ) : (
                <div className="space-y-3.5 max-h-60 overflow-y-auto pr-1">
                  {by_category.map((row) => (
                    <HorizontalBreakdownBar
                      key={row.category}
                      label={row.category}
                      count={row.count}
                      total={totalConcerns}
                      color="bg-[#2563EB]"
                      badge={
                        row.count < 3 ? (
                          <span className="px-1.5 py-0.5 rounded bg-amber-50 text-amber-800 border border-amber-200 text-[10px] font-semibold">
                            Low n
                          </span>
                        ) : null
                      }
                    />
                  ))}
                </div>
              )}
            </div>

            {/* Status Breakdown */}
            <div className="bg-white rounded-xl border border-[#E2E8F0] shadow-sm p-5">
              <div className="flex items-center justify-between mb-1">
                <h2 className="text-base font-bold text-[#0F172A]">Concerns by Status</h2>
                <span className="text-xs font-semibold text-[#64748B]">n = {totalConcerns}</span>
              </div>
              <p className="text-xs text-[#64748B] mb-4">
                Current workflow stage of all concerns in this report.
              </p>
              {Object.keys(by_status).length === 0 ? (
                <p className="text-xs text-[#64748B] py-6 text-center">No status data for current filters.</p>
              ) : (
                <div className="space-y-3.5">
                  {Object.entries(by_status).map(([status, count]) => (
                    <HorizontalBreakdownBar
                      key={status}
                      label={status}
                      count={count}
                      total={totalConcerns}
                      color={STATUS_BAR_COLORS[status] ?? 'bg-slate-400'}
                    />
                  ))}
                </div>
              )}
            </div>
          </div>

          {/* ── SECTIONS D & F + COLLAPSIBLE ADVANCED ANALYTICS ── */}
          {report?.resolution_analytics && (
            <ResolutionAnalyticsSection
              analytics={report.resolution_analytics}
              scopeLabel="Academic Unit Scope"
            />
          )}

          {/* ── CONCERN RECORDS TABLE ── */}
          <div className="bg-white rounded-xl border border-[#E2E8F0] shadow-sm overflow-hidden">
            <div className="px-6 py-4 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
              <div>
                <h2 className="text-base font-bold text-[#0F172A]">
                  Matching Concern Records
                  <span className="ml-2 text-xs font-normal text-[#64748B]">
                    (showing up to 50 most recent)
                  </span>
                </h2>
                <p className="text-xs text-[#64748B]">
                  Individual concerns strictly within your academic unit and escalated governance scope.
                </p>
              </div>
              {generated_at && (
                <span className="text-xs text-[#64748B]">
                  Generated {new Date(generated_at).toLocaleString()}
                </span>
              )}
            </div>
            {concerns.length === 0 ? (
              <div className="px-6 py-12 text-center text-sm text-[#64748B]">
                No concerns match the selected filters.
              </div>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full text-xs text-left">
                  <thead className="text-[11px] uppercase text-[#64748B] font-bold bg-[#F8FAFC] border-b border-[#E2E8F0]">
                    <tr>
                      <th className="px-4 py-3">ID</th>
                      <th className="px-4 py-3">Title &amp; Category</th>
                      <th className="px-4 py-3">Status</th>
                      <th className="px-4 py-3">Priority</th>
                      <th className="px-4 py-3">Type</th>
                      <th className="px-4 py-3">Sentiment</th>
                      <th className="px-4 py-3">Student</th>
                      <th className="px-4 py-3">Submitted</th>
                      <th className="px-4 py-3 text-right print:hidden">Action</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100">
                    {concerns.map((c) => (
                      <tr key={c.concern_id} className="hover:bg-slate-50/80">
                        <td className="px-4 py-3 font-semibold text-[#64748B]">#{c.concern_id}</td>
                        <td className="px-4 py-3">
                          <div className="font-semibold text-[#0F172A] max-w-xs truncate">{c.title}</div>
                          <div className="text-[11px] text-[#64748B]">
                            {c.issue_category || c.category || 'General'}
                          </div>
                        </td>
                        <td className="px-4 py-3"><StatusBadge status={c.status} size="xs" /></td>
                        <td className="px-4 py-3"><PriorityBadge priority={c.priority} size="xs" /></td>
                        <td className="px-4 py-3 capitalize text-[#64748B]">{c.workflow_type?.replace('_', '-') ?? '—'}</td>
                        <td className="px-4 py-3 capitalize text-[#64748B]">{c.sentiment_label ?? '—'}</td>
                        <td className="px-4 py-3 text-[#0F172A]">{c.student?.name ?? '—'}</td>
                        <td className="px-4 py-3 text-[#64748B] whitespace-nowrap">
                          {c.submitted_at ? new Date(c.submitted_at).toLocaleDateString() : '—'}
                        </td>
                        <td className="px-4 py-3 text-right print:hidden">
                          <button
                            type="button"
                            onClick={() => navigate(`/dean/concerns/${c.concern_id}`)}
                            className="text-[#1E3A8A] hover:text-[#152960] font-semibold inline-flex items-center gap-0.5"
                          >
                            View <ChevronRight size={13} />
                          </button>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  );
};

export default DeanReports;
