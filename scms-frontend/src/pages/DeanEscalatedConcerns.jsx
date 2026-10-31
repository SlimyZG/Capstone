import { useState, useEffect, useMemo } from 'react';
import { Link } from 'react-router-dom';
import { Search, CheckCircle2, Clock, AlertCircle, RotateCcw, Eye, FolderOpen } from 'lucide-react';
import api from '../api';
import PageHeader from '../components/PageHeader';
import StatCard from '../components/StatCard';
import StatusBadge from '../components/StatusBadge';
import PriorityBadge from '../components/PriorityBadge';
import SlaStatusBadge from '../components/SlaStatusBadge';
import EmptyState from '../components/EmptyState';
import PaginationControls from '../components/PaginationControls';
import LoadingSpinner from '../components/LoadingSpinner';

const DeanEscalatedConcerns = () => {
  const [directConcerns, setDirectConcerns] = useState([]);
  const [escalatedConcerns, setEscalatedConcerns] = useState([]);
  const [loading, setLoading] = useState(true);
  const [searchTerm, setSearchTerm] = useState('');
  const [actionLoading, setActionLoading] = useState('');
  const [currentPage, setCurrentPage] = useState(1);
  const pageSize = 10;

  useEffect(() => { fetchInbox(); }, []);

  const fetchInbox = async () => {
    try {
      const res = await api.get('/dean/inbox');
      setDirectConcerns(res.data.direct || []);
      setEscalatedConcerns(res.data.escalated || []);
    } catch (err) {
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  const handleAction = async (action, id) => {
    let confirmPrompt = 'Approve this concern?';
    if (action === 'resolve') confirmPrompt = 'Mark this concern as resolved?';
    if (action === 'return') confirmPrompt = 'Return this concern to the department for further action?';
    
    if (!window.confirm(confirmPrompt)) return;
    setActionLoading(id.toString());
    try {
      await api.post(`/dean/concerns/${id}/${action}`);
      fetchInbox();
    } catch (err) {
      alert(err.response?.data?.message || 'Action failed.');
    } finally {
      setActionLoading('');
    }
  };

  const allConcerns = [...escalatedConcerns, ...directConcerns];
  const totalEscalations = allConcerns.length;
  const pendingReview = escalatedConcerns.length;

  const filtered = useMemo(() => {
    const term = searchTerm.toLowerCase();
    return allConcerns.filter(c => {
      return (
        c.concern_id.toString().includes(term) ||
        (c.student?.name || '').toLowerCase().includes(term) ||
        (c.title || '').toLowerCase().includes(term)
      );
    });
  }, [allConcerns, searchTerm]);

  const totalPages = Math.ceil(filtered.length / pageSize);
  const paginated = useMemo(() => {
    const start = (currentPage - 1) * pageSize;
    return filtered.slice(start, start + pageSize);
  }, [filtered, currentPage, pageSize]);

  const getInitials = (name) => (name || '?').split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2);

  if (loading) {
    return <LoadingSpinner message="Loading escalated concerns queue..." fullPage />;
  }

  return (
    <div className="space-y-6">
      <PageHeader
        category="Academic Affairs"
        title="Escalated Concerns Queue"
        subtitle="Review, approve, return, or resolve high-priority academic concerns requiring dean intervention."
        breadcrumbs={[
          { label: 'Dashboard', path: '/dean' },
          { label: 'Escalated Concerns' }
        ]}
      />

      {/* Stats */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-5">
        <StatCard
          title="Total In Queue"
          value={totalEscalations}
          icon={AlertCircle}
          subtitle="All active and direct dean cases"
          iconBg="bg-blue-50 text-blue-600 border-blue-100"
        />
        <StatCard
          title="Pending Dean Review"
          value={pendingReview}
          icon={Clock}
          badge={pendingReview > 0 ? `${pendingReview} Urgent` : undefined}
          subtitle="Cases requiring direct administrative decision"
          iconBg="bg-red-50 text-red-600 border-red-100"
        />
        <StatCard
          title="Direct Academic Requests"
          value={directConcerns.length}
          icon={CheckCircle2}
          subtitle="Assigned directly to Dean's Office"
          iconBg="bg-emerald-50 text-emerald-600 border-emerald-100"
        />
      </div>

      {/* Active Concerns Table */}
      <div className="card overflow-hidden">
        <div className="p-4 sm:p-5 border-b border-slate-200 flex flex-col sm:flex-row items-center justify-between gap-3 bg-slate-50/50">
          <div>
            <h2 className="text-base font-bold text-slate-900">Escalated Cases</h2>
            <p className="text-xs text-slate-500 mt-0.5">Filter by case ID, student name, or concern topic</p>
          </div>
          <div className="relative w-full sm:w-72">
            <Search size={15} className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
            <input
              type="text"
              placeholder="Search ID, Student, or Title..."
              className="input-field pl-9"
              value={searchTerm}
              onChange={e => {
                setSearchTerm(e.target.value);
                setCurrentPage(1);
              }}
            />
          </div>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full text-sm text-left">
            <thead className="text-[11px] uppercase text-slate-500 font-bold tracking-wider bg-slate-50 border-b border-slate-200">
              <tr>
                <th className="px-5 py-3.5">Concern ID</th>
                <th className="px-5 py-3.5">Student</th>
                <th className="px-5 py-3.5">Title & Topic</th>
                <th className="px-5 py-3.5">Priority</th>
                <th className="px-5 py-3.5">Status</th>
                <th className="px-5 py-3.5 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {filtered.length === 0 ? (
                <tr>
                  <td colSpan="6" className="p-0">
                    <EmptyState
                      icon={FolderOpen}
                      title="No matching escalated concerns"
                      description={searchTerm ? "No cases match your search query." : "There are no escalated concerns pending review at this time."}
                      className="border-0 shadow-none py-12"
                    />
                  </td>
                </tr>
              ) : (
                paginated.map((c) => {
                  const isEscalated = c.status === 'escalated';
                  return (
                    <tr key={c.concern_id} className="hover:bg-slate-50/80 transition-colors">
                      <td className="px-5 py-4 font-mono font-bold text-xs text-slate-700 whitespace-nowrap">
                        <Link 
                          to={`/dean/concerns/${c.concern_id}`}
                          className="text-[#1e3a8a] hover:underline"
                        >
                          #ESC-{c.concern_id.toString().padStart(4, '0')}
                        </Link>
                      </td>
                      <td className="px-5 py-4 whitespace-nowrap text-xs">
                        <div className="flex items-center gap-2.5">
                          <div className="h-7 w-7 rounded-full bg-blue-100 text-[#1e3a8a] flex items-center justify-center text-xs font-bold shrink-0">
                            {getInitials(c.student?.name)}
                          </div>
                          <div>
                            <p className="font-semibold text-slate-900">{c.student?.name || 'Unknown'}</p>
                            <p className="text-[10px] text-slate-400">ID: {c.student?.student_id_number || '—'}</p>
                          </div>
                        </div>
                      </td>
                      <td className="px-5 py-4 max-w-sm">
                        <Link 
                          to={`/dean/concerns/${c.concern_id}`}
                          className="font-semibold text-slate-900 text-xs hover:text-blue-600 transition-colors block truncate"
                        >
                          {c.title || 'No Title'}
                        </Link>
                        <p className="text-[10px] text-slate-500 uppercase tracking-wider mt-0.5">
                          {c.category?.replace('_', ' ') || 'Academic'}
                        </p>
                      </td>
                      <td className="px-5 py-4 whitespace-nowrap">
                        <PriorityBadge priority={c.priority} isOverridden={c.is_priority_overridden} size="xs" />
                      </td>
                      <td className="px-5 py-4 whitespace-nowrap">
                        <div className="flex flex-col items-start gap-1">
                          <StatusBadge status={c.status} size="xs" />
                          {c.sla && (c.sla.escalated_by_sla || !['resolved', 'closed', 'escalated'].includes(c.sla.state)) && (
                            <SlaStatusBadge sla={c.sla} status={c.status} size="xs" />
                          )}
                        </div>
                      </td>
                      <td className="px-5 py-4 text-right whitespace-nowrap">
                        <div className="flex items-center justify-end gap-1.5">
                          <Link
                            to={`/dean/concerns/${c.concern_id}`}
                            className="btn-secondary py-1 px-2.5 text-xs text-slate-700 hover:text-blue-600 inline-flex items-center gap-1"
                          >
                            <Eye size={13} />
                            <span>Review</span>
                          </Link>
                          {isEscalated && (
                            <>
                              <button
                                onClick={() => handleAction('approve', c.concern_id)}
                                disabled={actionLoading === c.concern_id.toString()}
                                className="btn-success py-1 px-2.5 text-xs inline-flex items-center gap-1"
                                title="Approve concern"
                              >
                                <CheckCircle2 size={13} />
                                <span>{actionLoading === c.concern_id.toString() ? '...' : 'Approve'}</span>
                              </button>
                              <button
                                onClick={() => handleAction('return', c.concern_id)}
                                disabled={actionLoading === c.concern_id.toString()}
                                className="btn-warning py-1 px-2.5 text-xs inline-flex items-center gap-1"
                                title="Return for further action"
                              >
                                <RotateCcw size={13} />
                                <span>Return</span>
                              </button>
                            </>
                          )}
                          <button
                            onClick={() => handleAction('resolve', c.concern_id)}
                            disabled={actionLoading === c.concern_id.toString()}
                            className="btn-dark py-1 px-2.5 text-xs inline-flex items-center gap-1"
                          >
                            <CheckCircle2 size={13} />
                            <span>{actionLoading === c.concern_id.toString() ? '...' : 'Resolve'}</span>
                          </button>
                        </div>
                      </td>
                    </tr>
                  );
                })
              )}
            </tbody>
          </table>
        </div>

        {/* Pagination */}
        <PaginationControls
          currentPage={currentPage}
          totalPages={totalPages}
          totalItems={filtered.length}
          pageSize={pageSize}
          onPageChange={setCurrentPage}
        />
      </div>
    </div>
  );
};

export default DeanEscalatedConcerns;
