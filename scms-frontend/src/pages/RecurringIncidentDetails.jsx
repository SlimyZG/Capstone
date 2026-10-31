import { useState, useEffect } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import { 
  AlertTriangle, ArrowLeft, Shield, Users, Clock, 
  Layers, CheckCircle2, ChevronRight, X, AlertCircle, Unlink
} from 'lucide-react';
import api from '../api';

const RecurringIncidentDetails = () => {
  const { id } = useParams();
  const navigate = useNavigate();
  const { user } = useAuth();

  const [incident, setIncident] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  // Priority override modal state
  const [showOverrideModal, setShowOverrideModal] = useState(false);
  const [newPriority, setNewPriority] = useState('critical');
  const [overrideReason, setOverrideReason] = useState('');
  const [submittingOverride, setSubmittingOverride] = useState(false);
  const [overrideError, setOverrideError] = useState(null);

  // Unlink concern state
  const [unlinkingConcernId, setUnlinkingConcernId] = useState(null);

  const getRolePrefix = () => {
    if (user?.role === 'sao') return 'sao';
    if (user?.role === 'dean') return 'dean';
    return 'dept-staff';
  };

  const rolePrefix = getRolePrefix();

  const fetchIncident = async () => {
    try {
      setLoading(true);
      setError(null);
      const res = await api.get(`/${rolePrefix}/recurring-incidents/${id}`);
      setIncident(res.data.data);
      if (res.data.data?.priority) {
        setNewPriority(res.data.data.priority);
      }
    } catch (err) {
      console.error(err);
      setError(err.response?.data?.message || 'Failed to load recurring incident details.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (id) {
      fetchIncident();
    }
  }, [id]);

  const handlePriorityOverride = async (e) => {
    e.preventDefault();
    if (!overrideReason.trim() || overrideReason.trim().length < 5) {
      setOverrideError('Please provide a reason with at least 5 characters.');
      return;
    }

    try {
      setSubmittingOverride(true);
      setOverrideError(null);
      await api.post(`/${rolePrefix}/recurring-incidents/${id}/priority-override`, {
        priority: newPriority,
        reason: overrideReason.trim(),
      });
      setShowOverrideModal(false);
      setOverrideReason('');
      fetchIncident();
    } catch (err) {
      setOverrideError(err.response?.data?.message || 'Failed to override priority.');
    } finally {
      setSubmittingOverride(false);
    }
  };

  const handleUnlinkConcern = async (concernId) => {
    if (!window.confirm(`Are you sure you want to unlink concern #${concernId} from this incident?`)) {
      return;
    }

    try {
      setUnlinkingConcernId(concernId);
      await api.post(`/${rolePrefix}/recurring-incidents/${id}/unlink/${concernId}`);
      fetchIncident();
    } catch (err) {
      alert(err.response?.data?.message || 'Failed to unlink concern.');
    } finally {
      setUnlinkingConcernId(null);
    }
  };

  const getTierBadge = (tier) => {
    switch (tier) {
      case 'major_critical':
        return (
          <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black uppercase tracking-wider bg-red-600 text-white shadow-sm animate-pulse border border-red-700">
            <AlertTriangle size={13} />
            Major Critical
          </span>
        );
      case 'critical':
        return (
          <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-red-100 text-red-700 border border-red-200">
            <AlertTriangle size={13} />
            Critical Tier
          </span>
        );
      case 'high':
        return (
          <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-amber-100 text-amber-800 border border-amber-200">
            <AlertCircle size={13} />
            High Tier
          </span>
        );
      default:
        return (
          <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-blue-50 text-blue-700 border border-blue-200">
            Normal Tier
          </span>
        );
    }
  };

  const getPriorityBadge = (priority, isOverridden) => {
    let colorClasses = 'bg-slate-100 text-slate-700 border-slate-200';
    if (priority === 'critical') colorClasses = 'bg-red-100 text-red-700 border-red-300 font-bold';
    else if (priority === 'high') colorClasses = 'bg-amber-100 text-amber-800 border-amber-300 font-semibold';
    else if (priority === 'medium') colorClasses = 'bg-blue-100 text-blue-700 border-blue-200';
    else if (priority === 'low') colorClasses = 'bg-emerald-100 text-emerald-700 border-emerald-200';

    return (
      <span className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs border ${colorClasses}`}>
        <span className="uppercase">{priority || 'low'}</span>
        {isOverridden && (
          <span className="inline-flex items-center gap-0.5 text-[10px] font-bold px-1.5 py-0.2 bg-amber-100 text-amber-800 rounded border border-amber-200" title="Manual Priority Override">
            <Shield size={10} />
            OVERRIDDEN
          </span>
        )}
      </span>
    );
  };

  const formatDate = (iso) => {
    if (!iso) return '—';
    const d = new Date(iso);
    return isNaN(d.getTime()) ? '—' : d.toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' });
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-[#F8FAFC] p-8 flex items-center justify-center">
        <div className="flex items-center gap-3 text-slate-500 font-medium">
          <div className="w-5 h-5 border-2 border-blue-600 border-t-transparent rounded-full animate-spin" />
          Loading recurring incident...
        </div>
      </div>
    );
  }

  if (error || !incident) {
    return (
      <div className="min-h-screen bg-[#F8FAFC] p-8">
        <div className="max-w-4xl mx-auto bg-white rounded-xl p-8 border border-red-200 shadow-sm text-center">
          <AlertTriangle size={40} className="mx-auto text-red-500 mb-3" />
          <h2 className="text-xl font-bold text-slate-900 mb-2">Error Loading Incident</h2>
          <p className="text-slate-600 mb-6">{error || 'Incident not found or unauthorized.'}</p>
          <button
            onClick={() => navigate(-1)}
            className="px-4 py-2 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700 transition-colors"
          >
            Go Back
          </button>
        </div>
      </div>
    );
  }

  const uniqueCount = incident.unique_students_count || 0;
  const progressPercent = Math.min(100, Math.round((uniqueCount / 30) * 100));
  const isSaoAssigned = Boolean(incident.is_sao_assigned);
  const canOverride = (user?.role === 'dean') || (user?.role === 'sao' && isSaoAssigned);
  const canUnlink = (user?.role === 'dean') || (user?.role === 'dept_staff') || (user?.role === 'sao' && isSaoAssigned);

  return (
    <div className="min-h-screen bg-[#F8FAFC] pb-12">
      {/* Top Header */}
      <div className="bg-white border-b border-[#E2E8F0] shadow-sm">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <button
            onClick={() => navigate(-1)}
            className="inline-flex items-center gap-2 text-sm text-[#64748B] hover:text-[#0F172A] font-medium mb-4 transition-colors"
          >
            <ArrowLeft size={16} />
            Back
          </button>

          <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <div>
              <div className="flex items-center gap-3 flex-wrap mb-2">
                <span className="font-mono text-xs font-semibold px-2.5 py-1 bg-slate-100 text-slate-700 rounded border border-slate-200">
                  {incident.incident_code}
                </span>
                {incident.issue_category && (
                  <span className="text-xs px-2.5 py-1 rounded-full font-semibold bg-blue-50 text-blue-700 border border-blue-200">
                    {incident.issue_category} {incident.issue_subcategory ? `› ${incident.issue_subcategory}` : ''}
                  </span>
                )}
                {getTierBadge(incident.recurrence_tier)}
                {getPriorityBadge(incident.priority, incident.is_priority_overridden)}
                <span className="text-xs px-2.5 py-1 rounded-full uppercase font-medium bg-slate-100 text-slate-700">
                  Status: {incident.status}
                </span>
              </div>
              <h1 className="text-2xl font-bold text-[#0F172A] tracking-tight">{incident.title}</h1>
              <p className="text-sm text-[#64748B] mt-1 max-w-3xl leading-relaxed break-words whitespace-normal">
                {incident.summary || 'Recurring pattern detected across student submissions.'}
              </p>
            </div>

            {canOverride && (
              <button
                onClick={() => setShowOverrideModal(true)}
                className="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-[#2563EB] hover:bg-[#1E3A8A] text-white text-sm font-semibold rounded-lg shadow-sm transition-colors"
              >
                <Shield size={16} />
                Override Priority
              </button>
            )}
          </div>
        </div>
      </div>

      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
        {/* Metric Cards */}
        <div className="grid grid-cols-1 md:grid-cols-4 gap-5">
          <div className="bg-white p-5 rounded-xl border border-[#E2E8F0] shadow-sm">
            <div className="flex items-center justify-between text-slate-500 mb-2">
              <span className="text-xs font-semibold uppercase tracking-wider">Unique Students</span>
              <Users size={18} className="text-blue-600" />
            </div>
            <div className="text-2xl font-extrabold text-[#0F172A]">{uniqueCount}</div>
            <div className="text-xs text-slate-500 mt-1">Rolling 7-day window</div>
            {/* Progress bar to next tier */}
            <div className="w-full bg-slate-100 h-1.5 rounded-full mt-3 overflow-hidden">
              <div
                className={`h-full ${
                  uniqueCount >= 30 ? 'bg-red-600' : uniqueCount >= 20 ? 'bg-rose-500' : uniqueCount >= 10 ? 'bg-amber-500' : 'bg-blue-600'
                }`}
                style={{ width: `${progressPercent}%` }}
              />
            </div>
            <div className="text-[10px] text-slate-400 mt-1 flex justify-between font-mono">
              <span>0</span>
              <span>10 (High)</span>
              <span>20 (Crit)</span>
              <span>30 (Maj)</span>
            </div>
          </div>

          <div className="bg-white p-5 rounded-xl border border-[#E2E8F0] shadow-sm">
            <div className="flex items-center justify-between text-slate-500 mb-2">
              <span className="text-xs font-semibold uppercase tracking-wider">Total Reports</span>
              <Layers size={18} className="text-purple-600" />
            </div>
            <div className="text-2xl font-extrabold text-[#0F172A]">{incident.total_reports_count || 0}</div>
            <div className="text-xs text-slate-500 mt-1">Submissions linked</div>
          </div>

          <div className="bg-white p-5 rounded-xl border border-[#E2E8F0] shadow-sm">
            <div className="flex items-center justify-between text-slate-500 mb-2">
              <span className="text-xs font-semibold uppercase tracking-wider">Scope / Unit</span>
              <Shield size={18} className="text-indigo-600" />
            </div>
            <div className="text-base font-bold text-[#0F172A] truncate">
              {incident.academic_unit?.name || incident.department?.name || 'All Institution'}
            </div>
            <div className="text-xs text-slate-500 mt-1 capitalize">{incident.workflow_type} Workflow</div>
          </div>

          <div className="bg-white p-5 rounded-xl border border-[#E2E8F0] shadow-sm">
            <div className="flex items-center justify-between text-slate-500 mb-2">
              <span className="text-xs font-semibold uppercase tracking-wider">Latest Activity</span>
              <Clock size={18} className="text-emerald-600" />
            </div>
            <div className="text-sm font-semibold text-[#0F172A]">{formatDate(incident.latest_reported_at)}</div>
            <div className="text-xs text-slate-500 mt-1">First reported: {formatDate(incident.first_reported_at)}</div>
          </div>
        </div>

        {/* Priority Override Meta if overridden */}
        {incident.is_priority_overridden && (
          <div className="bg-purple-50 border border-purple-200 rounded-xl p-5">
            <div className="flex items-start gap-3">
              <Shield size={20} className="text-purple-700 flex-shrink-0 mt-0.5" />
              <div>
                <h3 className="text-sm font-bold text-purple-900">Manual Priority Override Active</h3>
                <p className="text-xs text-purple-800 mt-1">
                  Overridden by: <span className="font-semibold">{incident.priority_overridden_by?.name || 'Administrator'}</span> on{' '}
                  {formatDate(incident.priority_overridden_at)}
                </p>
                <p className="text-xs text-purple-900 mt-2 italic bg-white/70 p-2.5 rounded border border-purple-200">
                  "{incident.priority_override_reason}"
                </p>
              </div>
            </div>
          </div>
        )}

        {/* Linked Concerns Section */}
        <div className="bg-white rounded-xl border border-[#E2E8F0] shadow-sm overflow-hidden">
          <div className="px-6 py-4 border-b border-[#E2E8F0] flex items-center justify-between">
            <div>
              <h2 className="text-base font-bold text-[#0F172A]">Linked Student Concerns</h2>
              <p className="text-xs text-[#64748B] mt-0.5">
                Individual student reports associated with this recurring pattern.
              </p>
            </div>
            <span className="text-xs font-semibold px-2.5 py-1 bg-slate-100 text-slate-700 rounded-full">
              {incident.concerns?.length || 0} concerns
            </span>
          </div>

          {user?.role === 'sao' && !isSaoAssigned ? (
            <div className="p-8 text-center bg-slate-50 border-t border-[#E2E8F0]">
              <Shield size={32} className="mx-auto text-blue-600 mb-2" />
              <h3 className="text-sm font-bold text-slate-800">Option A Institutional Oversight</h3>
              <p className="text-xs text-slate-500 mt-1 max-w-lg mx-auto leading-relaxed">
                In accordance with established SCMS privacy boundaries (Option A), individual concern records for this incident are managed directly by the assigned operational unit or college dean. SAO maintains institution-wide aggregate monitoring.
              </p>
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-left text-sm text-[#0F172A]">
                <thead className="bg-[#F8FAFC] text-xs uppercase font-semibold text-[#64748B] border-b border-[#E2E8F0]">
                  <tr>
                    <th className="px-6 py-3">ID / Reference</th>
                    <th className="px-6 py-3">Title</th>
                    {user?.role !== 'sao' && <th className="px-6 py-3">Student</th>}
                    <th className="px-6 py-3">Status</th>
                    <th className="px-6 py-3">Priority</th>
                    <th className="px-6 py-3">Submitted</th>
                    {canUnlink && <th className="px-6 py-3 text-right">Action</th>}
                  </tr>
                </thead>
                <tbody className="divide-y divide-[#E2E8F0]">
                  {incident.concerns && incident.concerns.length > 0 ? (
                    incident.concerns.map((c) => (
                      <tr key={c.concern_id} className="hover:bg-slate-50 transition-colors">
                        <td className="px-6 py-4 font-mono text-xs text-slate-600 font-semibold">
                          #{c.concern_id}
                        </td>
                        <td className="px-6 py-4 font-medium text-slate-900 max-w-xs truncate">
                          {c.title}
                        </td>
                        {user?.role !== 'sao' && (
                          <td className="px-6 py-4 text-xs text-slate-600">
                            {c.is_anonymous ? 'Anonymous' : c.student?.name || '—'}
                          </td>
                        )}
                        <td className="px-6 py-4">
                          <span className="px-2 py-0.5 rounded text-xs font-semibold uppercase bg-slate-100 text-slate-700">
                            {c.status}
                          </span>
                        </td>
                        <td className="px-6 py-4">{getPriorityBadge(c.priority, c.is_priority_overridden)}</td>
                        <td className="px-6 py-4 text-xs text-slate-500">{formatDate(c.submitted_at)}</td>
                        {canUnlink && (
                          <td className="px-6 py-4 text-right">
                            <button
                              onClick={() => handleUnlinkConcern(c.concern_id)}
                              disabled={unlinkingConcernId === c.concern_id}
                              className="inline-flex items-center gap-1 px-2.5 py-1 text-xs text-red-600 hover:text-red-800 hover:bg-red-50 rounded font-medium transition-colors"
                              title="Unlink misclassified concern"
                            >
                              <Unlink size={13} />
                              {unlinkingConcernId === c.concern_id ? 'Unlinking...' : 'Unlink'}
                            </button>
                          </td>
                        )}
                      </tr>
                    ))
                  ) : (
                    <tr>
                      <td
                        colSpan={user?.role !== 'sao' ? (canUnlink ? 7 : 6) : (canUnlink ? 6 : 5)}
                        className="px-6 py-8 text-center text-slate-400 text-sm"
                      >
                        No linked concerns currently found.
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          )}
        </div>

        {/* Audit History Timeline */}
        <div className="bg-white rounded-xl border border-[#E2E8F0] shadow-sm p-6">
          <h2 className="text-base font-bold text-[#0F172A] mb-4">Recurring Incident Audit History</h2>
          <div className="space-y-4">
            {incident.history && incident.history.length > 0 ? (
              incident.history.map((h) => (
                <div key={h.id} className="flex items-start gap-4 p-4 rounded-lg bg-slate-50 border border-slate-200">
                  <div className="w-8 h-8 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center font-bold text-xs flex-shrink-0 mt-0.5">
                    {h.action === 'priority_overridden' ? <Shield size={14} /> : <Clock size={14} />}
                  </div>
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center justify-between flex-wrap gap-2">
                      <span className="text-xs font-bold text-slate-900 uppercase tracking-wide">
                        {h.action.replace(/_/g, ' ')}
                      </span>
                      <span className="text-xs text-slate-400">{formatDate(h.created_at)}</span>
                    </div>
                    <p className="text-sm text-slate-700 mt-1">{h.notes}</p>
                    <div className="text-xs text-slate-500 mt-2 flex items-center gap-4">
                      <span>Performer: <strong className="text-slate-700">{h.actor_name || h.performed_by_name || 'System'}</strong></span>
                      {h.unique_students_snapshot !== null && (
                        <span>Unique Students: <strong className="text-slate-700">{h.unique_students_snapshot}</strong></span>
                      )}
                    </div>
                  </div>
                </div>
              ))
            ) : (
              <p className="text-sm text-slate-400 text-center py-4">No audit history recorded yet.</p>
            )}
          </div>
        </div>
      </div>

      {/* Priority Override Modal */}
      {showOverrideModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <div className="bg-white rounded-xl border border-[#E2E8F0] shadow-2xl max-w-md w-full p-6 animate-in fade-in zoom-in-95">
            <div className="flex items-center justify-between pb-3 border-b border-[#E2E8F0]">
              <div className="flex items-center gap-2">
                <Shield size={18} className="text-blue-600" />
                <h3 className="text-base font-bold text-[#0F172A]">Override Incident Priority</h3>
              </div>
              <button
                onClick={() => setShowOverrideModal(false)}
                className="text-slate-400 hover:text-slate-600 p-1 rounded-lg"
              >
                <X size={18} />
              </button>
            </div>

            <form onSubmit={handlePriorityOverride} className="mt-4 space-y-4">
              {overrideError && (
                <div className="p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-xs font-medium">
                  {overrideError}
                </div>
              )}

              <div>
                <label className="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">
                  New Priority Level
                </label>
                <select
                  value={newPriority}
                  onChange={(e) => setNewPriority(e.target.value)}
                  className="w-full px-3 py-2 bg-white border border-[#E2E8F0] rounded-lg text-sm text-[#0F172A] focus:outline-none focus:ring-2 focus:ring-blue-600"
                >
                  <option value="low">Low</option>
                  <option value="medium">Medium</option>
                  <option value="high">High</option>
                  <option value="critical">Critical</option>
                </select>
              </div>

              <div>
                <label className="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">
                  Reason for Override <span className="text-red-500">*</span>
                </label>
                <textarea
                  value={overrideReason}
                  onChange={(e) => setOverrideReason(e.target.value)}
                  rows={4}
                  required
                  placeholder="Explain why this recurring incident priority is being adjusted..."
                  className="w-full px-3 py-2 bg-white border border-[#E2E8F0] rounded-lg text-sm text-[#0F172A] focus:outline-none focus:ring-2 focus:ring-blue-600"
                />
                <span className="text-[11px] text-slate-400">Minimum 5 characters.</span>
              </div>

              <div className="flex items-center justify-end gap-3 pt-3 border-t border-[#E2E8F0]">
                <button
                  type="button"
                  onClick={() => setShowOverrideModal(false)}
                  className="px-4 py-2 border border-slate-300 text-slate-700 text-sm font-medium rounded-lg hover:bg-slate-50 transition-colors"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={submittingOverride}
                  className="px-4 py-2 bg-[#2563EB] hover:bg-[#1E3A8A] text-white text-sm font-semibold rounded-lg shadow-sm transition-colors disabled:opacity-50"
                >
                  {submittingOverride ? 'Saving...' : 'Save Override'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};

export default RecurringIncidentDetails;
