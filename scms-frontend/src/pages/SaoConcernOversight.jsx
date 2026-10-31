import { useState, useEffect, useMemo } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import {
  ShieldCheck,
  FileText,
  AlertTriangle,
  Clock,
  CheckCircle2,
  Filter,
  RefreshCw,
  TrendingUp,
  Layers,
  Users,
  BarChart3,
  Building,
  GraduationCap,
  ArrowLeft,
  Info,
  Calendar,
  XCircle,
  AlertCircle
} from 'lucide-react';
import api from '../api';
import ResolutionAnalyticsSection from '../components/ResolutionAnalyticsSection';

const SaoConcernOversight = () => {
  const { user } = useAuth();

  // Data State
  const [oversightData, setOversightData] = useState(null);
  const [academicUnits, setAcademicUnits] = useState([]);
  const [departments, setDepartments] = useState([]);
  const [recurringIncidents, setRecurringIncidents] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  // Filter State
  const [filters, setFilters] = useState({
    date_from: '',
    date_to: '',
    academic_unit_id: '',
    dept_id: '',
    status: '',
    priority: '',
  });

  // Active sub-tab for Unit vs Department breakdown
  const [breakdownView, setBreakdownView] = useState('units'); // 'units' | 'departments'

  useEffect(() => {
    fetchInitialOptions();
    fetchOversightData();
    fetchRecurringIncidents();
  }, []);

  const fetchRecurringIncidents = async () => {
    try {
      const res = await api.get('/sao/recurring-incidents');
      setRecurringIncidents(res.data.data || []);
    } catch (err) {
      console.error('Failed to load recurring incidents', err);
    }
  };

  const fetchInitialOptions = async () => {
    try {
      const [unitsRes, deptsRes] = await Promise.all([
        api.get('/academic-units'),
        api.get('/departments')
      ]);
      setAcademicUnits(unitsRes.data.academic_units || []);
      setDepartments(deptsRes.data.departments || []);
    } catch (err) {
      console.error('Failed to load filter options', err);
    }
  };

  const fetchOversightData = async (activeFilters = filters) => {
    setLoading(true);
    setError(null);
    try {
      const params = {};
      if (activeFilters.date_from) params.date_from = activeFilters.date_from;
      if (activeFilters.date_to) params.date_to = activeFilters.date_to;
      if (activeFilters.academic_unit_id) params.academic_unit_id = activeFilters.academic_unit_id;
      if (activeFilters.dept_id) params.dept_id = activeFilters.dept_id;
      if (activeFilters.status) params.status = activeFilters.status;
      if (activeFilters.priority) params.priority = activeFilters.priority;

      const res = await api.get('/sao/oversight', { params });
      setOversightData(res.data);
    } catch (err) {
      console.error('Failed to load oversight data', err);
      setError(err.response?.data?.message || 'Failed to retrieve oversight analytics.');
    } finally {
      setLoading(false);
    }
  };

  const handleFilterChange = (field, value) => {
    const updated = { ...filters, [field]: value };
    setFilters(updated);
    fetchOversightData(updated);
  };

  const handleResetFilters = () => {
    const reset = {
      date_from: '',
      date_to: '',
      academic_unit_id: '',
      dept_id: '',
      status: '',
      priority: '',
    };
    setFilters(reset);
    fetchOversightData(reset);
  };

  const summary = oversightData?.summary || {};
  const sla = oversightData?.sla_monitoring || {};
  const criticalBreakdown = oversightData?.critical_breakdown || [];
  const unitsList = oversightData?.academic_units || [];
  const deptsList = oversightData?.departments || [];
  const categoriesList = oversightData?.categories || [];
  const issueCategories = oversightData?.issue_categories || [];
  const issueSubcategories = oversightData?.issue_subcategories || [];
  const recurring = oversightData?.recurring_issues || {};
  const monthlyTrend = oversightData?.monthly_trend || [];

  return (
    <div className="space-y-6">
      {/* ── 0. Breadcrumb & Header ── */}
      <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
          <div className="flex items-center gap-2 text-sm text-slate-500 mb-1">
            <Link to="/sao" className="hover:text-blue-600 flex items-center gap-1 transition-colors">
              <ArrowLeft size={14} /> Back to SAO Dashboard
            </Link>
            <span>/</span>
            <span className="text-slate-700 font-medium">Concern Oversight</span>
          </div>
          <div className="flex items-center gap-3">
            <h1 className="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
              Concern Oversight Console
            </h1>
            <span className="badge bg-blue-50 text-blue-700 border border-blue-200">
              Option A Strict Oversight
            </span>
          </div>
          <p className="text-sm text-slate-600 mt-1">
            Institution-wide aggregate concern monitoring, critical issue tracking, and departmental performance auditing.
          </p>
        </div>

        <div className="flex items-center gap-2">
          <button
            onClick={() => fetchOversightData(filters)}
            disabled={loading}
            className="btn-secondary py-2 px-3 text-xs inline-flex items-center gap-1.5"
          >
            <RefreshCw size={14} className={loading ? 'animate-spin' : ''} />
            Refresh Metrics
          </button>
        </div>
      </div>

      {/* ── 1. Top Banner (Consistent SCMS Blue & White Design) ── */}
      <div className="card p-6 bg-white border border-slate-200">
        <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
          <div className="space-y-1.5">
            <div className="flex items-center gap-2.5 flex-wrap">
              <span className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-50 text-[#1e3a8a] border border-blue-200">
                <ShieldCheck size={14} className="text-blue-600" />
                Student Affairs Office (SAO)
              </span>
              <span className="text-xs text-slate-500 font-medium">
                University-Wide Concern Governance &amp; Oversight
              </span>
            </div>
            <h2 className="text-xl sm:text-2xl font-bold tracking-tight text-slate-900">
              Institutional Concern Status &amp; Performance
            </h2>
            <p className="text-slate-500 text-xs sm:text-sm max-w-3xl leading-relaxed">
              All metrics are computed live from active database records. Under Option A strict governance, SAO monitors university-wide aggregate health while respecting individual student privacy.
            </p>
          </div>

          <div className="flex items-center gap-2 self-start md:self-center bg-slate-50 px-4 py-2 rounded-lg border border-slate-200 text-xs">
            <span className="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
            <span className="text-slate-500">Governance Scope:</span>
            <span className="font-bold text-slate-900">Institution-Wide</span>
          </div>
        </div>
      </div>

      {/* ── 2. Error Banner ── */}
      {error && (
        <div className="bg-red-50 border border-red-200 rounded-xl p-4 text-sm text-red-700 flex items-start gap-2 shadow-xs">
          <AlertTriangle size={18} className="shrink-0 mt-0.5 text-red-600" />
          <span className="font-medium">{error}</span>
        </div>
      )}

      {/* ── 3. Filters Toolbar ── */}
      <div className="card p-4 bg-white border border-slate-200 shadow-sm space-y-3">
        <div className="flex items-center justify-between flex-wrap gap-2">
          <div className="flex items-center gap-2 text-xs font-bold text-slate-700 uppercase tracking-wider">
            <Filter size={14} className="text-blue-600" /> Filter Aggregate Metrics
          </div>
          {(filters.date_from || filters.date_to || filters.academic_unit_id || filters.dept_id || filters.status || filters.priority) && (
            <button
              onClick={handleResetFilters}
              className="text-xs text-blue-600 hover:text-blue-800 font-semibold"
            >
              Clear All Filters
            </button>
          )}
        </div>

        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3 text-xs">
          {/* Date From */}
          <div>
            <label className="block text-[11px] font-semibold text-slate-600 mb-1">Date From</label>
            <input
              type="date"
              value={filters.date_from}
              onChange={(e) => handleFilterChange('date_from', e.target.value)}
              className="input-field py-1.5 text-xs w-full bg-white"
            />
          </div>

          {/* Date To */}
          <div>
            <label className="block text-[11px] font-semibold text-slate-600 mb-1">Date To</label>
            <input
              type="date"
              value={filters.date_to}
              onChange={(e) => handleFilterChange('date_to', e.target.value)}
              className="input-field py-1.5 text-xs w-full bg-white"
            />
          </div>

          {/* Academic Unit */}
          <div>
            <label className="block text-[11px] font-semibold text-slate-600 mb-1">Academic Unit</label>
            <select
              value={filters.academic_unit_id}
              onChange={(e) => handleFilterChange('academic_unit_id', e.target.value)}
              className="input-field py-1.5 text-xs w-full bg-white"
            >
              <option value="">All Academic Units</option>
              {academicUnits.map((u) => (
                <option key={u.id} value={u.id}>{u.name}</option>
              ))}
            </select>
          </div>

          {/* Department */}
          <div>
            <label className="block text-[11px] font-semibold text-slate-600 mb-1">Department</label>
            <select
              value={filters.dept_id}
              onChange={(e) => handleFilterChange('dept_id', e.target.value)}
              className="input-field py-1.5 text-xs w-full bg-white"
            >
              <option value="">All Departments</option>
              {departments.map((d) => (
                <option key={d.id} value={d.id}>{d.name}</option>
              ))}
            </select>
          </div>

          {/* Status */}
          <div>
            <label className="block text-[11px] font-semibold text-slate-600 mb-1">Status</label>
            <select
              value={filters.status}
              onChange={(e) => handleFilterChange('status', e.target.value)}
              className="input-field py-1.5 text-xs w-full bg-white"
            >
              <option value="">All Statuses</option>
              <option value="pending">Pending</option>
              <option value="in_progress">In Progress</option>
              <option value="escalated">Escalated</option>
              <option value="resolved">Resolved</option>
              <option value="rejected">Rejected / Returned</option>
            </select>
          </div>

          {/* Priority */}
          <div>
            <label className="block text-[11px] font-semibold text-slate-600 mb-1">Priority</label>
            <select
              value={filters.priority}
              onChange={(e) => handleFilterChange('priority', e.target.value)}
              className="input-field py-1.5 text-xs w-full bg-white"
            >
              <option value="">All Priorities</option>
              <option value="critical">Critical</option>
              <option value="high">High</option>
              <option value="medium">Medium</option>
              <option value="low">Low</option>
            </select>
          </div>
        </div>

        <div className="pt-2 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-400">
          <span>* Filters scope aggregate calculations only. Strict Option A access controls prevent unauthorized access to individual records.</span>
          {loading && <span className="text-blue-600 font-semibold flex items-center gap-1"><RefreshCw size={12} className="animate-spin" /> Calculating...</span>}
        </div>
      </div>

      {/* ── 4. Main KPI Cards Grid (4 per row on desktop for high readability) ── */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {/* Total Submitted */}
        <div className="card p-5 bg-white border border-slate-200 shadow-xs flex flex-col justify-between">
          <div className="text-xs font-bold text-slate-500 uppercase tracking-wider">Total Concerns Received</div>
          <div className="text-3xl font-extrabold text-slate-900 mt-2">{summary.total_submitted ?? 0}</div>
          <div className="text-xs text-slate-500 mt-2">All student concerns submitted in scope</div>
        </div>

        {/* Resolved */}
        <div className="card p-5 bg-white border border-slate-200 shadow-xs flex flex-col justify-between">
          <div className="text-xs font-bold text-emerald-700 uppercase tracking-wider">Total Resolved Concerns</div>
          <div className="text-3xl font-extrabold text-emerald-600 mt-2">{summary.resolved ?? 0}</div>
          <div className="text-xs text-slate-500 mt-2">
            {summary.total_submitted > 0
              ? `${Math.round(((summary.resolved ?? 0) / summary.total_submitted) * 100)}% overall resolution rate`
              : 'Completed and closed'}
          </div>
        </div>

        {/* Unresolved Backlog (Pending + In Progress + Escalated) */}
        <div className="card p-5 bg-white border border-slate-200 shadow-xs flex flex-col justify-between">
          <div className="text-xs font-bold text-amber-700 uppercase tracking-wider">Total Unresolved Backlog</div>
          <div className="text-3xl font-extrabold text-amber-600 mt-2">
            {(summary.pending ?? 0) + (summary.in_progress ?? 0) + (summary.escalated ?? 0)}
          </div>
          <div className="text-xs text-slate-500 mt-2">
            {summary.pending ?? 0} Pending &bull; {summary.in_progress ?? 0} In Progress &bull; {summary.escalated ?? 0} Escalated
          </div>
        </div>

        {/* Overdue SLA */}
        <div className="card p-5 bg-white border border-slate-200 shadow-xs flex flex-col justify-between">
          <div className="text-xs font-bold text-rose-700 uppercase tracking-wider">Overdue Concerns (SLA)</div>
          <div className="text-3xl font-extrabold text-rose-600 mt-2">{sla.overdue_count ?? 0}</div>
          <div className="text-xs text-slate-500 mt-2">
            {sla.approaching_count ?? 0} approaching target (≥75%)
          </div>
        </div>

        {/* Critical Priority */}
        <div className="card p-5 bg-red-50/70 border border-red-200 shadow-xs flex flex-col justify-between">
          <div className="text-xs font-bold text-red-700 uppercase tracking-wider flex items-center gap-1.5">
            <AlertTriangle size={14} className="text-red-600" /> Critical Concerns
          </div>
          <div className="text-3xl font-extrabold text-red-700 mt-2">{summary.critical ?? 0}</div>
          <div className="text-xs text-red-700/90 mt-2 font-semibold">
            {summary.active_critical ?? 0} Active Critical Unresolved
          </div>
        </div>

        {/* High Priority */}
        <div className="card p-5 bg-amber-50/70 border border-amber-200 shadow-xs flex flex-col justify-between">
          <div className="text-xs font-bold text-amber-800 uppercase tracking-wider">High-Priority Concerns</div>
          <div className="text-3xl font-extrabold text-amber-800 mt-2">{summary.high_priority ?? 0}</div>
          <div className="text-xs text-amber-800/90 mt-2">High-urgency submissions in scope</div>
        </div>

        {/* Escalated */}
        <div className="card p-5 bg-white border border-slate-200 shadow-xs flex flex-col justify-between">
          <div className="text-xs font-bold text-purple-700 uppercase tracking-wider">Escalated Concerns</div>
          <div className="text-3xl font-extrabold text-purple-700 mt-2">{summary.escalated ?? 0}</div>
          <div className="text-xs text-slate-500 mt-2">
            {summary.awaiting_dean_review ?? 0} Dean &bull; {summary.awaiting_cad_approval ?? 0} CAD
          </div>
        </div>

        {/* Typical Resolution Time */}
        <div className="card p-5 bg-white border border-slate-200 shadow-xs flex flex-col justify-between">
          <div className="text-xs font-bold text-blue-800 uppercase tracking-wider">Typical Resolution Time</div>
          <div className="text-3xl font-extrabold text-[#1E3A8A] mt-2">
            {summary.median_resolution_hours !== null && summary.median_resolution_hours !== undefined
              ? `${summary.median_resolution_hours}h`
              : '—'}
          </div>
          <div className="text-xs text-slate-500 mt-2">
            Middle duration across completed cases ({summary.rejected ?? 0} rejected/returned)
          </div>
        </div>
      </div>

      {/* ── 5. Critical Concerns Monitoring & SLA Overview (2-Col) ── */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Left: Critical Concerns Monitoring */}
        <div className="card p-6 bg-white border border-slate-200 shadow-sm flex flex-col justify-between">
          <div>
            <div className="flex items-center justify-between mb-4">
              <div className="flex items-center gap-2">
                <div className="w-9 h-9 rounded-xl bg-red-50 text-red-600 flex items-center justify-center font-bold">
                  <AlertTriangle size={20} />
                </div>
                <div>
                  <h3 className="text-base font-bold text-slate-900">Critical Concerns Monitoring</h3>
                  <p className="text-xs text-slate-500">Active institutional emergencies &amp; high-risk alerts</p>
                </div>
              </div>
              <span className="badge bg-red-100 text-red-800 border border-red-200 font-bold text-xs">
                {summary.active_critical ?? 0} Active Critical
              </span>
            </div>

            <div className="grid grid-cols-2 gap-3 mb-4">
              <div className="bg-slate-50 p-3 rounded-xl border border-slate-200">
                <div className="text-[11px] font-semibold text-slate-500 uppercase">Recent Submissions (7 Days)</div>
                <div className="text-xl font-bold text-slate-900 mt-0.5">{summary.recent_critical ?? 0}</div>
              </div>
              <div className="bg-slate-50 p-3 rounded-xl border border-slate-200">
                <div className="text-[11px] font-semibold text-slate-500 uppercase">Awaiting Urgent Action</div>
                <div className="text-xl font-bold text-red-600 mt-0.5">{summary.active_critical ?? 0}</div>
              </div>
            </div>

            {criticalBreakdown.length === 0 ? (
              <div className="py-8 text-center bg-slate-50/60 rounded-xl border border-slate-200">
                <CheckCircle2 size={28} className="mx-auto text-emerald-500 mb-2" />
                <p className="text-xs font-semibold text-slate-700">No active critical concerns</p>
                <p className="text-[11px] text-slate-400 mt-0.5">All evaluated concerns are within standard priority boundaries.</p>
              </div>
            ) : (
              <div className="space-y-2 max-h-56 overflow-y-auto pr-1">
                <div className="text-[11px] font-bold text-slate-400 uppercase tracking-wider">
                  Critical Distribution By Academic Unit / Department
                </div>
                {criticalBreakdown.map((item, idx) => (
                  <div
                    key={`${item.type}-${item.name}-${idx}`}
                    className="flex items-center justify-between p-3 rounded-lg bg-red-50/50 border border-red-100 text-xs"
                  >
                    <div>
                      <div className="font-bold text-slate-900">{item.name}</div>
                      <div className="text-[10px] text-slate-500 flex items-center gap-1 mt-0.5">
                        <span className="uppercase font-semibold">{item.type.replace('_', ' ')}</span>
                        {item.latest_submission && (
                          <span>&bull; Latest: {new Date(item.latest_submission).toLocaleDateString()}</span>
                        )}
                      </div>
                    </div>
                    <span className="badge bg-red-100 text-red-800 font-bold text-xs">
                      {item.count} Critical
                    </span>
                  </div>
                ))}
              </div>
            )}
          </div>

          <div className="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-500">
            <span className="flex items-center gap-1 text-slate-600">
              <ShieldCheck size={14} className="text-blue-600" /> Option A Privacy Protection
            </span>
            <span className="text-slate-400">Aggregated for institutional safety</span>
          </div>
        </div>

        {/* Right: Escalation & SLA Status Monitoring */}
        <div className="card p-6 bg-white border border-slate-200 shadow-sm flex flex-col justify-between">
          <div>
            <div className="flex items-center justify-between mb-4">
              <div className="flex items-center gap-2">
                <div className="w-9 h-9 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center font-bold">
                  <Clock size={20} />
                </div>
                <div>
                  <h3 className="text-base font-bold text-slate-900">Escalation &amp; Resolution Performance</h3>
                  <p className="text-xs text-slate-500">Workflow review bottlenecks &amp; real turnaround tracking</p>
                </div>
              </div>
              <span className="badge bg-blue-50 text-blue-700 border border-blue-200">
                Workflow Health
              </span>
            </div>

            <div className="grid grid-cols-2 gap-3 mb-4">
              <div className="bg-slate-50 p-3 rounded-xl border border-slate-200">
                <div className="text-[11px] font-semibold text-slate-500 uppercase">Awaiting Dean Review</div>
                <div className="text-xl font-bold text-purple-700 mt-0.5">{summary.awaiting_dean_review ?? 0}</div>
                <div className="text-[10px] text-slate-400">Escalated academic items</div>
              </div>
              <div className="bg-slate-50 p-3 rounded-xl border border-slate-200">
                <div className="text-[11px] font-semibold text-slate-500 uppercase">Awaiting CAD Approval</div>
                <div className="text-xl font-bold text-blue-700 mt-0.5">{summary.awaiting_cad_approval ?? 0}</div>
                <div className="text-[10px] text-slate-400">Forwarded for governance</div>
              </div>
              <div className="bg-slate-50 p-3 rounded-xl border border-slate-200">
                <div className="text-[11px] font-semibold text-slate-500 uppercase">Avg. Resolution Time</div>
                <div className="text-xl font-bold text-emerald-700 mt-0.5">
                  {summary.avg_resolution_days !== null ? `${summary.avg_resolution_days} days` : 'N/A'}
                </div>
                <div className="text-[10px] text-slate-400">
                  {summary.avg_resolution_hours !== null ? `(${summary.avg_resolution_hours} hours total)` : 'No closed concerns'}
                </div>
              </div>
              <div className="bg-slate-50 p-3 rounded-xl border border-slate-200">
                <div className="text-[11px] font-semibold text-slate-500 uppercase">Total In Escalation</div>
                <div className="text-xl font-bold text-slate-900 mt-0.5">{summary.escalated ?? 0}</div>
                <div className="text-[10px] text-slate-400">Across entire institution</div>
              </div>
            </div>

            {/* SLA Status Card */}
            {sla.supported ? (
              <div className="p-4 rounded-xl bg-blue-50/50 border border-blue-200 space-y-3">
                <div className="flex items-center justify-between">
                  <div className="text-xs font-bold text-slate-900">
                    Resolution Timeframe &amp; 75% Follow-up Monitoring
                  </div>
                  <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
                    Active
                  </span>
                </div>
                <div className="grid grid-cols-2 sm:grid-cols-4 gap-2 text-center">
                  <div className="bg-white p-2 rounded-lg border border-slate-200">
                    <div className="text-[10px] font-semibold text-slate-500 uppercase">Approaching</div>
                    <div className="text-base font-bold text-amber-700">{sla.approaching_count ?? 0}</div>
                  </div>
                  <div className="bg-white p-2 rounded-lg border border-slate-200">
                    <div className="text-[10px] font-semibold text-slate-500 uppercase">75% Follow-up</div>
                    <div className="text-base font-bold text-orange-700">{sla.followup_sent_count ?? 0}</div>
                  </div>
                  <div className="bg-white p-2 rounded-lg border border-slate-200">
                    <div className="text-[10px] font-semibold text-slate-500 uppercase">Overdue</div>
                    <div className="text-base font-bold text-rose-700">{sla.overdue_count ?? 0}</div>
                  </div>
                  <div className="bg-white p-2 rounded-lg border border-slate-200">
                    <div className="text-[10px] font-semibold text-slate-500 uppercase">Auto-Escalated</div>
                    <div className="text-base font-bold text-purple-700">{sla.auto_escalated_count ?? 0}</div>
                  </div>
                </div>
                <p className="text-slate-600 leading-relaxed text-[11px]">
                  {sla.notice}
                </p>
              </div>
            ) : (
              <div className="p-4 rounded-xl bg-amber-50/60 border border-amber-200">
                <div className="flex items-start gap-2.5">
                  <Info size={18} className="text-amber-600 shrink-0 mt-0.5" />
                  <div className="text-xs text-amber-900">
                    <div className="font-bold mb-0.5">SLA Tracking Status: Disabled</div>
                    <p className="text-amber-800 leading-relaxed text-[11px]">
                      {sla.notice || 'SLA deadline tracking is disabled.'}
                    </p>
                  </div>
                </div>
              </div>
            )}
          </div>

          <div className="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-500">
            <span>Real metrics computed from actual database timestamps</span>
            <span className="font-semibold text-blue-600">Live DB Metrics</span>
          </div>
        </div>
      </div>

      {/* ── Historical Resolution-Time & Predictive Analytics (Phase 9) ── */}
      {oversightData?.resolution_analytics && (
        <ResolutionAnalyticsSection
          analytics={oversightData.resolution_analytics}
          scopeLabel="Institution-Wide Aggregate"
        />
      )}

      {/* ── Active Recurring Incidents (Automated Clustering & Escalation) ── */}
      <div className="card bg-white border border-slate-200 shadow-sm overflow-hidden">
        <div className="px-6 py-4 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
          <div>
            <div className="flex items-center gap-2">
              <Layers size={18} className="text-blue-600" />
              <h3 className="text-base font-bold text-slate-900">Active Recurring Incidents</h3>
              <span className="badge bg-purple-50 text-purple-700 border border-purple-200 text-xs">
                {recurringIncidents.length} Active Patterns
              </span>
            </div>
            <p className="text-xs text-slate-500 mt-0.5">
              Automated multi-stage incident clustering and threshold-based escalation monitoring.
            </p>
          </div>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs text-slate-700">
            <thead className="bg-[#F8FAFC] text-[11px] uppercase font-semibold text-slate-500 border-b border-slate-200">
              <tr>
                <th className="px-6 py-3">Code / Incident</th>
                <th className="px-6 py-3">Scope / Unit</th>
                <th className="px-6 py-3">Recurrence Tier</th>
                <th className="px-6 py-3">Unique Students</th>
                <th className="px-6 py-3">Priority</th>
                <th className="px-6 py-3">Latest Activity</th>
                <th className="px-6 py-3 text-right">Action</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-200">
              {recurringIncidents.length === 0 ? (
                <tr>
                  <td colSpan={7} className="px-6 py-8 text-center text-slate-400">
                    No active recurring incidents detected across institutional workflows.
                  </td>
                </tr>
              ) : (
                recurringIncidents.map((inc) => {
                  const uCount = inc.unique_students_count || 0;
                  const isMajor = inc.recurrence_tier === 'major_critical';
                  const isCrit = inc.recurrence_tier === 'critical';
                  const isHigh = inc.recurrence_tier === 'high';

                  return (
                    <tr key={inc.id} className="hover:bg-slate-50/80 transition-colors">
                      <td className="px-6 py-3.5">
                        <div className="font-mono text-xs font-bold text-blue-700">{inc.incident_code}</div>
                        <div className="font-medium text-slate-900 max-w-xs truncate mt-0.5">{inc.title}</div>
                      </td>
                      <td className="px-6 py-3.5 text-slate-600">
                        {inc.academic_unit?.name || inc.department?.name || 'Institutional'}
                      </td>
                      <td className="px-6 py-3.5">
                        {isMajor ? (
                          <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase bg-red-600 text-white animate-pulse">
                            <AlertTriangle size={10} /> Major Critical
                          </span>
                        ) : isCrit ? (
                          <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase bg-red-100 text-red-700 border border-red-200">
                            <AlertTriangle size={10} /> Critical
                          </span>
                        ) : isHigh ? (
                          <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase bg-amber-100 text-amber-800 border border-amber-200">
                            <AlertCircle size={10} /> High
                          </span>
                        ) : (
                          <span className="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-blue-50 text-blue-700 border border-blue-200">
                            Normal
                          </span>
                        )}
                      </td>
                      <td className="px-6 py-3.5">
                        <div className="flex items-center gap-2">
                          <span className="font-bold text-slate-900">{uCount} students</span>
                          <span className="text-[10px] text-slate-400 font-mono">({inc.total_reports_count || 0} reports)</span>
                        </div>
                      </td>
                      <td className="px-6 py-3.5">
                        <span className={`px-2 py-0.5 rounded text-[11px] font-bold uppercase ${
                          inc.priority === 'critical' ? 'bg-red-100 text-red-700' :
                          inc.priority === 'high' ? 'bg-amber-100 text-amber-800' :
                          'bg-slate-100 text-slate-700'
                        }`}>
                          {inc.priority}
                        </span>
                      </td>
                      <td className="px-6 py-3.5 text-slate-500 text-[11px]">
                        {inc.latest_reported_at ? new Date(inc.latest_reported_at).toLocaleDateString() : '—'}
                      </td>
                      <td className="px-6 py-3.5 text-right">
                        <Link
                          to={`/sao/recurring-incidents/${inc.id}`}
                          className="inline-flex items-center gap-1 px-3 py-1 bg-blue-50 hover:bg-blue-100 text-blue-700 rounded-lg text-xs font-semibold transition-colors"
                        >
                          View Details
                        </Link>
                      </td>
                    </tr>
                  );
                })
              )}
            </tbody>
          </table>
        </div>
      </div>

      {/* ── 6. Department and Academic Unit Monitoring ── */}
      <div className="card bg-white border border-slate-200 shadow-sm overflow-hidden">
        <div className="px-6 py-4 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
          <div>
            <h3 className="text-base font-bold text-slate-900">Department &amp; Academic Unit Oversight</h3>
            <p className="text-xs text-slate-500">
              Summarized workload, pending backlogs, and resolution performance across operational units
            </p>
          </div>

          {/* Toggle between Academic Units, Operational Departments & AI Problem Categories */}
          <div className="inline-flex rounded-lg border border-slate-200 bg-slate-50 p-0.5 text-xs">
            <button
              onClick={() => setBreakdownView('units')}
              className={`px-3 py-1.5 rounded-md font-semibold transition-all flex items-center gap-1.5 ${
                breakdownView === 'units'
                  ? 'bg-blue-600 text-white shadow-xs'
                  : 'text-slate-600 hover:text-slate-900'
              }`}
            >
              <GraduationCap size={14} />
              Academic Units ({unitsList.length})
            </button>
            <button
              onClick={() => setBreakdownView('departments')}
              className={`px-3 py-1.5 rounded-md font-semibold transition-all flex items-center gap-1.5 ${
                breakdownView === 'departments'
                  ? 'bg-blue-600 text-white shadow-xs'
                  : 'text-slate-600 hover:text-slate-900'
              }`}
            >
              <Building size={14} />
              Operational Depts ({deptsList.length})
            </button>
            <button
              onClick={() => setBreakdownView('issues')}
              className={`px-3 py-1.5 rounded-md font-semibold transition-all flex items-center gap-1.5 ${
                breakdownView === 'issues'
                  ? 'bg-blue-600 text-white shadow-xs'
                  : 'text-slate-600 hover:text-slate-900'
              }`}
            >
              <Layers size={14} />
              AI Problem Categories ({issueCategories.length})
            </button>
          </div>
        </div>

        <div className="overflow-x-auto">
          {breakdownView === 'issues' ? (
            <table className="min-w-full divide-y divide-slate-200 text-left text-xs">
              <thead className="bg-slate-50 text-slate-600 font-semibold uppercase tracking-wider">
                <tr>
                  <th scope="col" className="px-6 py-3.5">AI Issue Category</th>
                  <th scope="col" className="px-6 py-3.5 text-center">Total Reports</th>
                  <th scope="col" className="px-6 py-3.5 text-center">Share of Total</th>
                  <th scope="col" className="px-6 py-3.5">Top Identified Subcategories</th>
                </tr>
              </thead>
              <tbody className="bg-white divide-y divide-slate-200">
                {issueCategories.length === 0 ? (
                  <tr>
                    <td colSpan={4} className="px-6 py-8 text-center text-slate-400">
                      No classified issue data available in the current scope.
                    </td>
                  </tr>
                ) : (
                  issueCategories.map((cat) => {
                    const relatedSubs = issueSubcategories.filter((s) => s.category === cat.category);
                    return (
                      <tr key={`issue-${cat.category}`} className="hover:bg-slate-50/80 transition-colors">
                        <td className="px-6 py-4 font-bold text-slate-900">
                          {cat.category}
                        </td>
                        <td className="px-6 py-4 text-center font-extrabold text-blue-700">
                          {cat.count}
                        </td>
                        <td className="px-6 py-4 text-center">
                          <span className="badge bg-blue-50 text-blue-700 font-semibold px-2 py-0.5 rounded-full">
                            {cat.percentage}%
                          </span>
                        </td>
                        <td className="px-6 py-4">
                          <div className="flex flex-wrap gap-1.5">
                            {relatedSubs.length === 0 ? (
                              <span className="text-slate-400 italic text-[11px]">General issues</span>
                            ) : (
                              relatedSubs.map((sub, i) => (
                                <span key={i} className="px-2 py-0.5 bg-slate-100 text-slate-700 rounded text-[11px] font-medium">
                                  {sub.subcategory} ({sub.count})
                                </span>
                              ))
                            )}
                          </div>
                        </td>
                      </tr>
                    );
                  })
                )}
              </tbody>
            </table>
          ) : (
            <table className="min-w-full divide-y divide-slate-200 text-left text-xs">
              <thead className="bg-slate-50 text-slate-600 font-semibold uppercase tracking-wider">
                <tr>
                  <th scope="col" className="px-6 py-3.5">
                    {breakdownView === 'units' ? 'Academic Unit' : 'Operational Department'}
                  </th>
                  <th scope="col" className="px-6 py-3.5 text-center">Total</th>
                  <th scope="col" className="px-6 py-3.5 text-center">Pending</th>
                  <th scope="col" className="px-6 py-3.5 text-center">In Progress</th>
                  <th scope="col" className="px-6 py-3.5 text-center">Escalated</th>
                  <th scope="col" className="px-6 py-3.5 text-center">Resolved</th>
                  <th scope="col" className="px-6 py-3.5 text-center">Critical</th>
                </tr>
              </thead>
              <tbody className="bg-white divide-y divide-slate-200">
                {(breakdownView === 'units' ? unitsList : deptsList).map((item) => (
                  <tr key={`${breakdownView}-${item.id}`} className="hover:bg-slate-50/80 transition-colors">
                    <td className="px-6 py-4 font-semibold text-slate-900">
                      {item.name}
                    </td>
                    <td className="px-6 py-4 text-center font-bold text-slate-900">
                      {item.total}
                    </td>
                    <td className="px-6 py-4 text-center">
                      <span className={item.pending > 0 ? 'text-amber-700 font-bold' : 'text-slate-400'}>
                        {item.pending}
                      </span>
                    </td>
                    <td className="px-6 py-4 text-center">
                      <span className={item.in_progress > 0 ? 'text-blue-700 font-bold' : 'text-slate-400'}>
                        {item.in_progress}
                      </span>
                    </td>
                    <td className="px-6 py-4 text-center">
                      <span className={item.escalated > 0 ? 'text-purple-700 font-bold' : 'text-slate-400'}>
                        {item.escalated}
                      </span>
                    </td>
                    <td className="px-6 py-4 text-center">
                      <span className={item.resolved > 0 ? 'text-emerald-700 font-bold' : 'text-slate-400'}>
                        {item.resolved}
                      </span>
                    </td>
                    <td className="px-6 py-4 text-center">
                      {item.critical > 0 ? (
                        <span className="badge bg-red-100 text-red-800 font-bold px-2 py-0.5 rounded-full">
                          {item.critical}
                        </span>
                      ) : (
                        <span className="text-slate-400">0</span>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>
      </div>

      {/* ── 7. Recurring Issues & Trend Overview (2-Col) ── */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Left: Category Distribution & Recurring Patterns */}
        <div className="card p-6 bg-white border border-slate-200 shadow-sm flex flex-col justify-between">
          <div>
            <div className="flex items-center justify-between mb-4">
              <div className="flex items-center gap-2">
                <div className="w-9 h-9 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center font-bold">
                  <BarChart3 size={20} />
                </div>
                <div>
                  <h3 className="text-base font-bold text-slate-900">Recurring Concern Categories</h3>
                  <p className="text-xs text-slate-500">Most frequent concern types across institution</p>
                </div>
              </div>
              <span className="badge bg-slate-100 text-slate-700 border border-slate-200">
                {categoriesList.length} Categories
              </span>
            </div>

            {categoriesList.length === 0 ? (
              <div className="py-8 text-center text-xs text-slate-400 bg-slate-50/60 rounded-xl border border-slate-200">
                No category data available for the current filter scope.
              </div>
            ) : (
              <div className="space-y-3">
                {categoriesList.map((cat) => (
                  <div key={cat.category} className="space-y-1">
                    <div className="flex items-center justify-between text-xs">
                      <span className="font-semibold text-slate-800">{cat.category}</span>
                      <span className="font-bold text-slate-900">{cat.count} ({cat.percentage}%)</span>
                    </div>
                    <div className="w-full bg-slate-100 rounded-full h-2 overflow-hidden">
                      <div
                        className="bg-blue-600 h-2 rounded-full transition-all duration-300"
                        style={{ width: `${Math.min(cat.percentage, 100)}%` }}
                      />
                    </div>
                  </div>
                ))}
              </div>
            )}
          </div>

          {/* Department recurring patterns */}
          <div className="mt-6 pt-4 border-t border-slate-100">
            <div className="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-2">
              Repeated Issues Within Operational Units
            </div>
            {((recurring?.by_department?.length || 0) + (recurring?.by_academic_unit?.length || 0)) === 0 ? (
              <p className="text-xs text-slate-400">No repeated issue patterns (2+ items in single unit) detected.</p>
            ) : (
              <div className="space-y-1.5 max-h-36 overflow-y-auto">
                {recurring?.by_department?.map((r, i) => (
                  <div key={`dept-rec-${i}`} className="flex items-center justify-between text-xs p-2 rounded-md bg-slate-50 border border-slate-100">
                    <span className="text-slate-800 font-medium">{r.department} &bull; {r.category}</span>
                    <span className="font-bold text-blue-700">{r.occurrences} instances</span>
                  </div>
                ))}
                {recurring?.by_academic_unit?.map((r, i) => (
                  <div key={`unit-rec-${i}`} className="flex items-center justify-between text-xs p-2 rounded-md bg-slate-50 border border-slate-100">
                    <span className="text-slate-800 font-medium">{r.academic_unit} &bull; {r.category}</span>
                    <span className="font-bold text-blue-700">{r.occurrences} instances</span>
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>

        {/* Right: Monthly Volume Trend */}
        <div className="card p-6 bg-white border border-slate-200 shadow-sm flex flex-col justify-between">
          <div>
            <div className="flex items-center justify-between mb-4">
              <div className="flex items-center gap-2">
                <div className="w-9 h-9 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center font-bold">
                  <TrendingUp size={20} />
                </div>
                <div>
                  <h3 className="text-base font-bold text-slate-900">Submission Volume Trends</h3>
                  <p className="text-xs text-slate-500">Monthly concern intake over the last 6 months</p>
                </div>
              </div>
              <span className="badge bg-blue-50 text-blue-700 border border-blue-200 font-semibold">
                Historical Trend
              </span>
            </div>

            {monthlyTrend.length === 0 ? (
              <div className="py-8 text-center text-xs text-slate-400 bg-slate-50/60 rounded-xl border border-slate-200">
                No historical monthly trends available.
              </div>
            ) : (
              <div className="space-y-3">
                {monthlyTrend.map((m) => (
                  <div key={m.month} className="p-3 rounded-xl bg-slate-50 border border-slate-200 flex items-center justify-between">
                    <div>
                      <div className="font-bold text-slate-900 text-xs">{m.month}</div>
                      <div className="text-[11px] text-slate-500 mt-0.5 flex items-center gap-3">
                        <span className="text-emerald-600 font-semibold">{m.resolved} Resolved</span>
                        {m.critical > 0 && (
                          <span className="text-red-600 font-bold">{m.critical} Critical</span>
                        )}
                      </div>
                    </div>
                    <div className="text-right">
                      <div className="text-lg font-black text-slate-900">{m.total}</div>
                      <div className="text-[10px] text-slate-400">total submissions</div>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </div>

          {/* Option A Strict Governance Box */}
          <div className="mt-6 pt-4 border-t border-slate-100 p-3 rounded-xl bg-blue-50/60 border border-blue-100 flex items-start gap-2.5">
            <ShieldCheck size={18} className="text-blue-700 shrink-0 mt-0.5" />
            <div className="text-xs text-blue-900">
              <span className="font-bold">Option A Governance:</span>
              <span className="text-blue-800 ml-1">
                The Student Affairs Office provides aggregate institutional oversight. Individual student records and internal messages remain protected unless explicitly routed to the SAO department.
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default SaoConcernOversight;
