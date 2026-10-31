import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import { Package, Loader2, CheckCircle2, ArrowRight, Layers, AlertCircle } from 'lucide-react';
import api from '../api';
import PageHeader from '../components/PageHeader';
import StatCard from '../components/StatCard';
import StatusBadge from '../components/StatusBadge';
import PriorityBadge from '../components/PriorityBadge';
import SlaStatusBadge from '../components/SlaStatusBadge';
import EmptyState from '../components/EmptyState';
import LoadingSpinner from '../components/LoadingSpinner';

const DeptStaffDashboard = () => {
  const { user } = useAuth();
  const [concerns, setConcerns] = useState([]);
  const [recurringIncidents, setRecurringIncidents] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const fetchQueue = async () => {
      try {
        const res = await api.get('/dept-staff/queue');
        setConcerns(res.data.concerns || []);
      } catch (err) {
        console.error(err);
      } finally {
        setLoading(false);
      }
    };

    const fetchRecurring = async () => {
      try {
        const res = await api.get('/dept-staff/recurring-incidents');
        setRecurringIncidents(res.data.data || []);
      } catch (err) {
        console.error('Failed to load dept staff recurring incidents', err);
      }
    };

    fetchQueue();
    fetchRecurring();
  }, []);

  const newCount = concerns.filter(c => c.status === 'pending').length;
  const inProgressCount = concerns.filter(c => c.status === 'in_progress').length;
  const resolvedCount = concerns.filter(c => c.status === 'resolved').length;

  const recent = [...concerns]
    .sort((a, b) => new Date(b.submitted_at || b.created_at) - new Date(a.submitted_at || a.created_at))
    .slice(0, 5);

  const formatDate = (d) => {
    if (!d) return '—';
    const date = new Date(d);
    if (isNaN(date.getTime())) return '—';
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
  };

  if (loading) {
    return <LoadingSpinner message="Loading department dashboard..." fullPage />;
  }

  return (
    <div className="space-y-6 sm:space-y-8">
      <PageHeader
        category="Department Portal"
        title="Department Overview"
        subtitle="Manage student concerns assigned to your operational or academic department, and review automated recurrence clusters."
      />

      {/* Summary KPI Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 sm:gap-6">
        <StatCard
          title="New Concerns"
          value={newCount}
          icon={Package}
          badge={newCount > 0 ? `${newCount} Pending` : undefined}
          subtitle="Awaiting initial assessment"
          iconBg="bg-blue-50 text-blue-600 border-blue-100"
        />
        <StatCard
          title="Under Investigation"
          value={inProgressCount}
          icon={Loader2}
          subtitle="Currently active and investigated"
          iconBg="bg-amber-50 text-amber-600 border-amber-100"
        />
        <StatCard
          title="Resolved Concerns"
          value={resolvedCount}
          icon={CheckCircle2}
          subtitle="Completed actions and closed tickets"
          iconBg="bg-emerald-50 text-emerald-600 border-emerald-100"
        />
      </div>

      {/* Recent Activity Table */}
      <div className="card">
        <div className="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
          <div>
            <h2 className="text-base font-bold text-slate-900">Recent Assigned Activity</h2>
            <p className="text-xs text-slate-500 mt-0.5">Most recent concerns dispatched to your department</p>
          </div>
          <Link to="/dept-staff/concerns" className="text-xs font-bold text-blue-600 hover:text-blue-800 transition-colors inline-flex items-center gap-1">
            <span>View All ({concerns.length})</span>
            <ArrowRight size={13} />
          </Link>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full text-sm text-left">
            <thead className="text-[11px] uppercase text-slate-500 font-bold tracking-wider border-b border-slate-200 bg-slate-50">
              <tr>
                <th className="px-5 py-3.5">Concern ID</th>
                <th className="px-5 py-3.5">Title</th>
                <th className="px-5 py-3.5">Priority</th>
                <th className="px-5 py-3.5">Status</th>
                <th className="px-5 py-3.5">Date Submitted</th>
                <th className="px-5 py-3.5 text-right">Action</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {recent.length === 0 ? (
                <tr>
                  <td colSpan="6" className="p-0">
                    <EmptyState
                      title="No recent concerns"
                      description="There are no active concerns currently assigned to your queue."
                      className="border-0 shadow-none py-8"
                    />
                  </td>
                </tr>
              ) : (
                recent.map(c => (
                  <tr key={c.concern_id} className="hover:bg-slate-50/80 transition-colors">
                    <td className="px-5 py-4 font-mono font-bold text-xs text-slate-700 whitespace-nowrap">
                      #C-{c.concern_id.toString().padStart(4, '0')}
                    </td>
                    <td className="px-5 py-4 text-slate-900 font-semibold max-w-sm truncate">{c.title}</td>
                    <td className="px-5 py-4 whitespace-nowrap">
                      <PriorityBadge priority={c.priority} isOverridden={c.is_priority_overridden} size="xs" />
                    </td>
                    <td className="px-5 py-4 whitespace-nowrap">
                      <div className="flex flex-wrap items-center gap-1">
                        <StatusBadge status={c.status} size="xs" />
                        {c.sla && !['resolved', 'closed', 'escalated'].includes(c.sla.state) && (
                          <SlaStatusBadge sla={c.sla} status={c.status} size="xs" />
                        )}
                      </div>
                    </td>
                    <td className="px-5 py-4 text-slate-500 text-xs whitespace-nowrap">{formatDate(c.submitted_at || c.created_at)}</td>
                    <td className="px-5 py-4 text-right whitespace-nowrap">
                      <Link
                        to={`/dept-staff/concerns/${c.concern_id}`}
                        className="btn-secondary py-1 px-2.5 text-xs inline-flex items-center gap-1 text-slate-700 hover:text-blue-600"
                      >
                        Manage
                      </Link>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>

      {/* Department Recurring Incidents */}
      <div className="card">
        <div className="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
          <div>
            <h2 className="text-base font-bold text-slate-900">Department Recurring Incidents</h2>
            <p className="text-xs text-slate-500 mt-0.5">Automated clusters of repeated student concerns in your department</p>
          </div>
          {recurringIncidents.length > 0 && (
            <span className="px-2.5 py-0.5 bg-blue-50 text-blue-700 text-xs font-bold rounded-full border border-blue-200">
              {recurringIncidents.length} Active Incidents
            </span>
          )}
        </div>

        <div className="overflow-x-auto">
          <table className="w-full text-sm text-left">
            <thead className="text-[11px] uppercase text-slate-500 font-bold tracking-wider border-b border-slate-200 bg-slate-50">
              <tr>
                <th className="px-5 py-3.5">Incident Code</th>
                <th className="px-5 py-3.5">Title</th>
                <th className="px-5 py-3.5">Recurrence Tier</th>
                <th className="px-5 py-3.5">Unique Students</th>
                <th className="px-5 py-3.5">Priority</th>
                <th className="px-5 py-3.5 text-right">Action</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {recurringIncidents.length === 0 ? (
                <tr>
                  <td colSpan="6" className="p-0">
                    <EmptyState
                      title="No recurring incidents detected"
                      description="No recurring clusters currently meet the escalation threshold in your department."
                      className="border-0 shadow-none py-8"
                    />
                  </td>
                </tr>
              ) : (
                recurringIncidents.map(inc => (
                  <tr key={inc.id} className="hover:bg-slate-50/80 transition-colors">
                    <td className="px-5 py-4 font-mono font-bold text-[#1e3a8a] text-xs whitespace-nowrap">
                      {inc.incident_code}
                    </td>
                    <td className="px-5 py-4 text-slate-900 font-semibold max-w-sm truncate">{inc.title}</td>
                    <td className="px-5 py-4 whitespace-nowrap">
                      <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider ${
                        inc.recurrence_tier === 'major_critical' ? 'bg-red-600 text-white animate-pulse' :
                        inc.recurrence_tier === 'critical' ? 'bg-red-100 text-red-700 border border-red-200' :
                        inc.recurrence_tier === 'high' ? 'bg-amber-100 text-amber-800 border border-amber-200' :
                        'bg-blue-50 text-blue-700 border border-blue-200'
                      }`}>
                        {inc.recurrence_tier?.replace('_', ' ')}
                      </span>
                    </td>
                    <td className="px-5 py-4 font-semibold text-slate-800 whitespace-nowrap text-xs">
                      {inc.unique_students_count || 0} distinct students
                    </td>
                    <td className="px-5 py-4 whitespace-nowrap">
                      <PriorityBadge priority={inc.priority} size="xs" />
                    </td>
                    <td className="px-5 py-4 text-right whitespace-nowrap">
                      <Link
                        to={`/dept-staff/recurring-incidents/${inc.id}`}
                        className="btn-secondary py-1 px-2.5 text-xs text-blue-600 hover:text-blue-800 inline-flex items-center gap-1"
                      >
                        Inspect
                      </Link>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
};

export default DeptStaffDashboard;
