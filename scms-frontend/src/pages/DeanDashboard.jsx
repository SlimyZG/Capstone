import { useState, useEffect } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import { Users, AlertTriangle, FileText, CheckCircle2, ArrowRight } from 'lucide-react';
import api from '../api';
import PageHeader from '../components/PageHeader';
import StatCard from '../components/StatCard';
import StatusBadge from '../components/StatusBadge';
import PriorityBadge from '../components/PriorityBadge';
import SlaStatusBadge from '../components/SlaStatusBadge';
import EmptyState from '../components/EmptyState';
import LoadingSpinner from '../components/LoadingSpinner';

const DeanDashboard = () => {
  const { user } = useAuth();
  const navigate = useNavigate();
  const [directConcerns, setDirectConcerns] = useState([]);
  const [escalatedConcerns, setEscalatedConcerns] = useState([]);
  const [recurringIncidents, setRecurringIncidents] = useState([]);
  const [stats, setStats] = useState({ total_students: 0, reports_generated: 0 });
  const [loading, setLoading] = useState(true);
  const [actionLoading, setActionLoading] = useState('');

  useEffect(() => { 
    fetchInbox(); 
    fetchRecurring();
  }, []);

  const fetchRecurring = async () => {
    try {
      const res = await api.get('/dean/recurring-incidents');
      setRecurringIncidents(res.data.data || []);
    } catch (err) {
      console.error('Failed to load dean recurring incidents', err);
    }
  };

  const fetchInbox = async () => {
    try {
      const res = await api.get('/dean/inbox');
      setDirectConcerns(res.data.direct || []);
      setEscalatedConcerns(res.data.escalated || []);
      if (res.data.stats) setStats(res.data.stats);
    } catch (err) {
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  const handleResolve = async (concernId) => {
    if (!window.confirm('Resolve this concern?')) return;
    setActionLoading(concernId.toString());
    try {
      await api.post(`/dean/concerns/${concernId}/resolve`);
      fetchInbox();
    } catch (err) {
      alert(err.response?.data?.message || 'Action failed.');
    } finally {
      setActionLoading('');
    }
  };

  const allConcerns = [...escalatedConcerns, ...directConcerns];
  const priority = allConcerns.slice(0, 6);
  const escalatedCount = escalatedConcerns.length;
  const totalStudents = stats.total_students;
  const reportsGenerated = stats.reports_generated;

  const getInitials = (name) => (name || '?').split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2);

  const firstName = user?.name?.split(' ')[0] || 'Dean';

  if (loading) {
    return <LoadingSpinner message="Loading Dean overview..." fullPage />;
  }

  return (
    <div className="space-y-6 sm:space-y-8">
      <PageHeader
        category="Academic Affairs"
        title={`Welcome back, ${firstName}.`}
        subtitle="Current status of academic concerns, escalated investigations, and student body metrics for your college."
      />

      {/* Metric cards */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-5">
        <StatCard
          title="Total Students"
          value={totalStudents.toLocaleString()}
          icon={Users}
          subtitle="Enrolled students in academic unit"
          iconBg="bg-blue-50 text-blue-600 border-blue-100"
        />
        <StatCard
          title="Escalated Concerns"
          value={escalatedCount}
          icon={AlertTriangle}
          badge={escalatedCount > 0 ? `${escalatedCount} Pending Review` : undefined}
          subtitle="Cases requiring dean intervention"
          iconBg="bg-red-50 text-red-600 border-red-100"
        />
        <StatCard
          title="Reports Generated"
          value={reportsGenerated}
          icon={FileText}
          subtitle="Archived summaries & resolutions"
          iconBg="bg-slate-50 text-slate-600 border-slate-200"
        />
      </div>

      {/* Priority Escalations table */}
      <div className="card">
        <div className="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
          <div>
            <h2 className="text-base font-bold text-slate-900">Priority Escalations</h2>
            <p className="text-xs text-slate-500 mt-0.5">Cases forwarded from departments requiring immediate dean intervention</p>
          </div>
          <Link
            to="/dean/escalated"
            className="text-xs font-bold text-blue-600 hover:text-blue-800 transition-colors inline-flex items-center gap-1"
          >
            <span>View All ({allConcerns.length})</span>
            <ArrowRight size={13} />
          </Link>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full text-sm text-left">
            <thead className="text-[11px] uppercase text-slate-500 font-bold tracking-wider bg-slate-50 border-b border-slate-200">
              <tr>
                <th className="px-5 py-3.5">Case ID</th>
                <th className="px-5 py-3.5">Student</th>
                <th className="px-5 py-3.5">Concern Title</th>
                <th className="px-5 py-3.5">Priority</th>
                <th className="px-5 py-3.5">Status</th>
                <th className="px-5 py-3.5 text-right">Action</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {priority.length === 0 ? (
                <tr>
                  <td colSpan="6" className="p-0">
                    <EmptyState
                      title="No escalated concerns"
                      description="There are currently no active concerns escalated to the Dean's Office."
                      className="border-0 shadow-none py-8"
                    />
                  </td>
                </tr>
              ) : (
                priority.map((c) => (
                  <tr key={c.concern_id} className="hover:bg-slate-50/80 transition-colors">
                    <td className="px-5 py-4 font-mono font-bold text-slate-700 whitespace-nowrap text-xs">
                      <Link 
                        to={`/dean/concerns/${c.concern_id}`}
                        className="text-[#1e3a8a] hover:underline"
                      >
                        #ESC-{c.concern_id.toString().padStart(4, '0')}
                      </Link>
                    </td>
                    <td className="px-5 py-4 whitespace-nowrap text-xs">
                      <div className="flex items-center gap-2.5">
                        <div className="h-7 w-7 rounded-full bg-blue-100 text-[#1e3a8a] flex items-center justify-center font-bold text-xs shrink-0">
                          {getInitials(c.student?.name)}
                        </div>
                        <div>
                          <p className="font-semibold text-slate-900">{c.student?.name || 'Unknown'}</p>
                          <p className="text-[10px] text-slate-400">ID: {c.student?.student_id_number || '—'}</p>
                        </div>
                      </div>
                    </td>
                    <td className="px-5 py-4 max-w-sm truncate text-xs text-slate-800 font-medium">
                      {c.title}
                    </td>
                    <td className="px-5 py-4 whitespace-nowrap">
                      <PriorityBadge priority={c.priority} isOverridden={c.is_priority_overridden} size="xs" />
                    </td>
                    <td className="px-5 py-4 whitespace-nowrap">
                      <div className="flex flex-wrap items-center gap-1">
                        <StatusBadge status={c.status} size="xs" />
                        {c.sla && (c.sla.escalated_by_sla || !['resolved', 'closed', 'escalated'].includes(c.sla.state)) && (
                          <SlaStatusBadge sla={c.sla} status={c.status} size="xs" />
                        )}
                      </div>
                    </td>
                    <td className="px-5 py-4 text-right whitespace-nowrap">
                      <div className="inline-flex items-center gap-2">
                        <Link
                          to={`/dean/concerns/${c.concern_id}`}
                          className="btn-secondary py-1 px-2.5 text-xs text-slate-700 hover:text-blue-600"
                        >
                          Review
                        </Link>
                        <button
                          onClick={() => handleResolve(c.concern_id)}
                          disabled={actionLoading === c.concern_id.toString()}
                          className="btn-success py-1 px-2.5 text-xs inline-flex items-center gap-1"
                        >
                          <CheckCircle2 size={13} />
                          <span>{actionLoading === c.concern_id.toString() ? 'Resolving...' : 'Resolve'}</span>
                        </button>
                      </div>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>

      {/* College Recurring Incidents */}
      <div className="card">
        <div className="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
          <div>
            <div className="flex items-center gap-2">
              <h2 className="text-base font-bold text-slate-900">College Recurring Incidents</h2>
              {recurringIncidents.length > 0 && (
                <span className="px-2.5 py-0.5 bg-blue-50 text-blue-700 text-xs font-bold rounded-full border border-blue-200">
                  {recurringIncidents.length} Active
                </span>
              )}
            </div>
            <p className="text-xs text-slate-500 mt-0.5">Automated detection of clustered student reports within your academic unit</p>
          </div>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full text-sm text-left">
            <thead className="text-[11px] uppercase text-slate-500 font-bold tracking-wider bg-slate-50 border-b border-slate-200">
              <tr>
                <th className="px-5 py-3.5">Incident Code</th>
                <th className="px-5 py-3.5">Pattern Title</th>
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
                      title="No active recurring incidents"
                      description="No recurrent clusters currently detected in your college."
                      className="border-0 shadow-none py-8"
                    />
                  </td>
                </tr>
              ) : (
                recurringIncidents.map((inc) => (
                  <tr key={inc.id} className="hover:bg-slate-50/80 transition-colors">
                    <td className="px-5 py-4 font-mono font-bold text-[#1e3a8a] text-xs whitespace-nowrap">
                      {inc.incident_code}
                    </td>
                    <td className="px-5 py-4 font-semibold text-slate-900 max-w-sm truncate text-xs">
                      {inc.title}
                    </td>
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
                    <td className="px-5 py-4 font-semibold text-slate-800 text-xs whitespace-nowrap">
                      {inc.unique_students_count || 0} students
                    </td>
                    <td className="px-5 py-4 whitespace-nowrap">
                      <PriorityBadge priority={inc.priority} size="xs" />
                    </td>
                    <td className="px-5 py-4 text-right whitespace-nowrap">
                      <Link
                        to={`/dean/recurring-incidents/${inc.id}`}
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

export default DeanDashboard;
