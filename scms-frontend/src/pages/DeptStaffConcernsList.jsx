import { useState, useEffect, useMemo } from 'react';
import { useNavigate } from 'react-router-dom';
import { Search, Eye, Filter, FolderOpen } from 'lucide-react';
import api from '../api';
import PageHeader from '../components/PageHeader';
import StatusBadge from '../components/StatusBadge';
import PriorityBadge from '../components/PriorityBadge';
import SlaStatusBadge from '../components/SlaStatusBadge';
import AiCategoryBadge from '../components/AiCategoryBadge';
import EmptyState from '../components/EmptyState';
import PaginationControls from '../components/PaginationControls';

const DeptStaffConcernsList = () => {
  const [concerns, setConcerns] = useState([]);
  const [loading, setLoading] = useState(true);
  const [searchTerm, setSearchTerm] = useState('');
  const [priorityFilter, setPriorityFilter] = useState('');
  const [statusFilter, setStatusFilter] = useState('Active (New & In Progress)');
  const [currentPage, setCurrentPage] = useState(1);
  const pageSize = 10;
  const navigate = useNavigate();

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
      if (statusFilter === 'Active (New & In Progress)') matchStatus = ['pending', 'in_progress'].includes(c.status);
      else if (statusFilter === 'Resolved') matchStatus = c.status === 'resolved';
      else if (statusFilter === 'Escalated') matchStatus = c.status === 'escalated';

      return matchSearch && matchPriority && matchStatus;
    });
  }, [concerns, searchTerm, priorityFilter, statusFilter]);

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
        subtitle="Review, investigate, escalate, and resolve student concerns assigned directly to your department."
      />

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
