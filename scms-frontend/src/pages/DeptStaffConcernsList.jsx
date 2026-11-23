import { useState, useEffect, useMemo } from 'react';
import { useNavigate } from 'react-router-dom';
import { Search, Eye, Filter, FolderOpen, ArrowRightLeft, Users, ShieldCheck, Building2 } from 'lucide-react';
import api from '../api';
import PageHeader from '../components/PageHeader';
import StatusBadge from '../components/StatusBadge';
import PriorityBadge from '../components/PriorityBadge';
import SlaStatusBadge from '../components/SlaStatusBadge';
import AiCategoryBadge from '../components/AiCategoryBadge';
import EmptyState from '../components/EmptyState';
import PaginationControls from '../components/PaginationControls';

const COORDINATION_BADGE_MAP = {
  primary: { label: 'Primary Owner', className: 'bg-blue-50 text-blue-700 border-blue-200' },
  active_collaborator: { label: 'Active Collaborator', className: 'bg-indigo-50 text-indigo-700 border-indigo-200' },
  collaborator: { label: 'Active Collaborator', className: 'bg-indigo-50 text-indigo-700 border-indigo-200' },
  pending_collaborator: { label: 'Collaboration Invite', className: 'bg-amber-50 text-amber-800 border-amber-300' },
  completed_collaborator: { label: 'Completed Collaborator', className: 'bg-slate-100 text-slate-600 border-slate-200' },
  pending_transfer_target: { label: 'Incoming Transfer Preview', className: 'bg-amber-100 text-amber-900 border-amber-300' },
  pco_approver: { label: 'PCO Approval Review', className: 'bg-purple-50 text-purple-700 border-purple-200' },
  pco_approver_readonly: { label: 'PCO Approver (Read-Only)', className: 'bg-slate-100 text-slate-600 border-slate-200' },
};

const DeptStaffConcernsList = () => {
  const [concerns, setConcerns] = useState([]);
  const [incomingTransfers, setIncomingTransfers] = useState([]);
  const [incomingCollaborations, setIncomingCollaborations] = useState([]);
  const [pendingPcoApprovals, setPendingPcoApprovals] = useState([]);
  const [loading, setLoading] = useState(true);
  const [searchTerm, setSearchTerm] = useState('');
  const [priorityFilter, setPriorityFilter] = useState('');
  const [statusFilter, setStatusFilter] = useState('Active (New & In Progress)');
  const [roleFilter, setRoleFilter] = useState('all');
  const [currentPage, setCurrentPage] = useState(1);
  const pageSize = 10;
  const navigate = useNavigate();

  useEffect(() => {
    const fetchQueue = async () => {
      try {
        const res = await api.get('/dept-staff/queue');
        setConcerns(res.data.concerns || []);
        setIncomingTransfers(res.data.incoming_transfers || []);
        setIncomingCollaborations(res.data.incoming_collaborations || []);
        setPendingPcoApprovals(res.data.pending_pco_approvals || []);
      } catch (err) {
        console.error(err);
      } finally {
        setLoading(false);
      }
    };
    fetchQueue();
  }, []);

  const formatDate = (dateString) => {
    if (!dateString) return '—';
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return '—';
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
  };

  const filteredConcerns = useMemo(() => {
    return concerns.filter(c => {
      const matchSearch = c.concern_id.toString().includes(searchTerm) ||
        (c.student?.name || '').toLowerCase().includes(searchTerm.toLowerCase()) ||
        c.title.toLowerCase().includes(searchTerm.toLowerCase());

      const matchPriority = priorityFilter === '' || priorityFilter === 'All'
        ? true
        : (c.priority || '').toLowerCase() === priorityFilter.toLowerCase();

      let matchStatus = true;
      if (statusFilter === 'Active (New & In Progress)') matchStatus = ['pending', 'in_progress', 'approved', 'returned'].includes(c.status);
      else if (statusFilter === 'Resolved') matchStatus = c.status === 'resolved';
      else if (statusFilter === 'Escalated') matchStatus = c.status === 'escalated';

      let matchRole = true;
      const mode = c.access_mode || 'primary';
      if (roleFilter === 'primary') matchRole = mode === 'primary';
      else if (roleFilter === 'transfers') matchRole = mode === 'pending_transfer_target';
      else if (roleFilter === 'collaborations') matchRole = ['active_collaborator', 'collaborator', 'pending_collaborator', 'completed_collaborator'].includes(mode);
      else if (roleFilter === 'pco_approvals') matchRole = ['pco_approver', 'pco_approver_readonly'].includes(mode);

      return matchSearch && matchPriority && matchStatus && matchRole;
    });
  }, [concerns, searchTerm, priorityFilter, statusFilter, roleFilter]);

  const totalPages = Math.ceil(filteredConcerns.length / pageSize);
  const paginatedConcerns = useMemo(() => {
    const start = (currentPage - 1) * pageSize;
    return filteredConcerns.slice(start, start + pageSize);
  }, [filteredConcerns, currentPage, pageSize]);

  return (
    <div className="space-y-6">
      <PageHeader
        category="Department Portal"
        title="Department Concern Queue"
        subtitle="Review, coordinate, transfer, collaborate, and resolve student concerns assigned or shared with your department."
      />

      {/* Coordination Quick Filter Pills */}
      <div className="flex flex-wrap items-center gap-2">
        {[
          { key: 'all', label: 'All Accessible Concerns', count: concerns.length, icon: Building2 },
          { key: 'primary', label: 'Primary Responsibility', count: concerns.filter(c => (c.access_mode || 'primary') === 'primary').length, icon: Building2 },
          { key: 'transfers', label: 'Incoming Transfer Requests', count: incomingTransfers.length, icon: ArrowRightLeft },
          { key: 'collaborations', label: 'Department Collaborations', count: incomingCollaborations.length, icon: Users },
          { key: 'pco_approvals', label: 'Pending PCO Approvals', count: pendingPcoApprovals.length, icon: ShieldCheck },
        ].map((tab) => {
          const Icon = tab.icon;
          const active = roleFilter === tab.key;
          return (
            <button
              key={tab.key}
              type="button"
              onClick={() => {
                setRoleFilter(tab.key);
                setCurrentPage(1);
              }}
              className={`px-3.5 py-2 rounded-xl text-xs font-bold flex items-center gap-1.5 border transition-colors ${
                active
                  ? 'bg-[#1e3a8a] text-white border-[#1e3a8a] shadow-2xs'
                  : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50'
              }`}
            >
              <Icon size={13} />
              <span>{tab.label}</span>
              <span className={`px-1.5 py-0.5 rounded-full text-[10px] ${
                active ? 'bg-blue-800 text-white' : 'bg-slate-100 text-slate-600'
              }`}>
                {tab.count}
              </span>
            </button>
          );
        })}
      </div>

      <div className="card overflow-hidden">
        {/* Filters Bar */}
        <div className="p-4 sm:p-5 border-b border-slate-200 flex flex-col sm:flex-row gap-3 bg-slate-50/50">
          <div className="relative flex-1">
            <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
            <input
              type="text"
              placeholder="Search by ID, Title, or Student Name..."
              className="input-field pl-9"
              value={searchTerm}
              onChange={e => {
                setSearchTerm(e.target.value);
                setCurrentPage(1);
              }}
            />
          </div>
          <div className="flex flex-wrap sm:flex-nowrap gap-2.5">
            <select
              className="input-field text-xs sm:w-auto"
              value={priorityFilter}
              onChange={e => {
                setPriorityFilter(e.target.value);
                setCurrentPage(1);
              }}
            >
              <option value="">All Priorities</option>
              <option value="Critical">Critical</option>
              <option value="High">High</option>
              <option value="Medium">Medium</option>
              <option value="Low">Low</option>
            </select>
            <select
              className="input-field text-xs sm:w-auto"
              value={statusFilter}
              onChange={e => {
                setStatusFilter(e.target.value);
                setCurrentPage(1);
              }}
            >
              <option value="Active (New & In Progress)">Active (New & In Progress)</option>
              <option value="All">All Statuses</option>
              <option value="Resolved">Resolved</option>
              <option value="Escalated">Escalated</option>
            </select>
          </div>
        </div>

        {/* Queue Table */}
        <div className="overflow-x-auto">
          <table className="w-full text-left text-sm text-slate-600">
            <thead className="text-[11px] uppercase bg-slate-50 text-slate-500 font-bold tracking-wider border-b border-slate-200">
              <tr>
                <th className="px-5 py-3.5">ID</th>
                <th className="px-5 py-3.5">Title & Category</th>
                <th className="px-5 py-3.5">Student</th>
                <th className="px-5 py-3.5">Priority</th>
                <th className="px-5 py-3.5">Status</th>
                <th className="px-5 py-3.5">Submitted</th>
                <th className="px-5 py-3.5 text-center">Action</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {loading ? (
                <tr>
                  <td colSpan="7" className="px-6 py-12 text-center text-slate-400 text-sm">
                    Loading department queue...
                  </td>
                </tr>
              ) : filteredConcerns.length === 0 ? (
                <tr>
                  <td colSpan="7" className="p-0">
                    <EmptyState
                      icon={FolderOpen}
                      title="No concerns found"
                      description="No concerns currently match your filter criteria in this queue."
                      className="border-0 shadow-none py-12"
                    />
                  </td>
                </tr>
              ) : (
                paginatedConcerns.map((concern) => (
                  <tr
                    key={concern.concern_id}
                    onClick={() => navigate(`/dept-staff/concerns/${concern.concern_id}`)}
                    className="hover:bg-slate-50/80 transition-colors cursor-pointer group"
                  >
                    <td className="px-5 py-4 font-mono font-bold text-xs text-slate-600 whitespace-nowrap">
                      #C-{concern.concern_id.toString().padStart(4, '0')}
                    </td>
                    <td className="px-5 py-4 max-w-sm">
                      <div className="font-bold text-slate-900 group-hover:text-blue-600 transition-colors truncate">
                        {concern.title}
                      </div>
                      <div className="mt-1 flex items-center gap-1.5 flex-wrap">
                        {concern.access_mode && COORDINATION_BADGE_MAP[concern.access_mode] && (
                          <span className={`px-2 py-0.5 rounded-full text-[10px] font-bold border ${COORDINATION_BADGE_MAP[concern.access_mode].className}`}>
                            {COORDINATION_BADGE_MAP[concern.access_mode].label}
                          </span>
                        )}
                        {concern.issue_category && (
                          <AiCategoryBadge
                            category={concern.issue_category}
                            subcategory={concern.issue_subcategory}
                            size="xs"
                          />
                        )}
                        <span className="text-[11px] text-slate-400 truncate max-w-[200px]">
                          {concern.description}
                        </span>
                      </div>
                    </td>
                    <td className="px-5 py-4 whitespace-nowrap text-xs">
                      <span className="font-semibold text-slate-800">{concern.student?.name || 'Anonymous Student'}</span>
                      {concern.student?.student_id_number && (
                        <span className="block text-slate-400 text-[10px]">{concern.student.student_id_number}</span>
                      )}
                    </td>
                    <td className="px-5 py-4 whitespace-nowrap">
                      <PriorityBadge priority={concern.priority} isOverridden={concern.is_priority_overridden} size="xs" />
                    </td>
                    <td className="px-5 py-4 whitespace-nowrap">
                      <div className="flex flex-col items-start gap-1">
                        <StatusBadge status={concern.status} size="xs" />
                        {concern.sla && !['resolved', 'closed', 'escalated'].includes(concern.sla.state) && (
                          <SlaStatusBadge sla={concern.sla} status={concern.status} size="xs" showRemaining={true} />
                        )}
                      </div>
                    </td>
                    <td className="px-5 py-4 whitespace-nowrap text-xs text-slate-500">
                      {formatDate(concern.submitted_at || concern.created_at)}
                    </td>
                    <td className="px-5 py-4 text-center whitespace-nowrap" onClick={e => e.stopPropagation()}>
                      <button
                        onClick={() => navigate(`/dept-staff/concerns/${concern.concern_id}`)}
                        className="btn-secondary py-1.5 px-2.5 text-xs inline-flex items-center gap-1 text-slate-700 hover:text-blue-600"
                        title="Open Concern Details"
                      >
                        <Eye size={14} />
                        <span>Inspect</span>
                      </button>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>

        {/* Pagination */}
        <PaginationControls
          currentPage={currentPage}
          totalPages={totalPages}
          totalItems={filteredConcerns.length}
          pageSize={pageSize}
          onPageChange={setCurrentPage}
        />
      </div>
    </div>
  );
};

export default DeptStaffConcernsList;
