import { useState, useEffect } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { 
  ShieldCheck, 
  Search, 
  Inbox,
  Eye,
  Sliders,
  FolderOpen
} from 'lucide-react';
import api from '../api';
import PageHeader from '../components/PageHeader';
import StatusBadge from '../components/StatusBadge';
import PriorityBadge from '../components/PriorityBadge';
import SlaStatusBadge from '../components/SlaStatusBadge';
import AiCategoryBadge from '../components/AiCategoryBadge';
import EmptyState from '../components/EmptyState';
import PaginationControls from '../components/PaginationControls';
import LoadingSpinner from '../components/LoadingSpinner';

const SaoAssignedConcerns = () => {
  const [concerns, setConcerns] = useState([]);
  const [pagination, setPagination] = useState(null);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [status, setStatus] = useState('');
  const [priority, setPriority] = useState('');
  const [saoDept, setSaoDept] = useState(null);
  const [currentPage, setCurrentPage] = useState(1);
  const navigate = useNavigate();

  useEffect(() => {
    fetchAssignedConcerns(currentPage);
  }, [status, priority, currentPage]);

  const fetchAssignedConcerns = async (page = 1) => {
    setLoading(true);
    try {
      const params = new URLSearchParams();
      params.append('page', page);
      if (status) params.append('status', status);
      if (priority) params.append('priority', priority);
      if (search) params.append('search', search);

      const res = await api.get(`/sao/assigned-concerns?${params.toString()}`);
      setConcerns(res.data.concerns?.data || []);
      setPagination(res.data.concerns);
      if (res.data.sao_department) {
        setSaoDept(res.data.sao_department);
      }
    } catch (err) {
      console.error('Failed to fetch SAO assigned concerns:', err);
    } finally {
      setLoading(false);
    }
  };

  const handleSearchSubmit = (e) => {
    e.preventDefault();
    setCurrentPage(1);
    fetchAssignedConcerns(1);
  };

  const formatDate = (dateStr) => {
    if (!dateStr) return '—';
    return new Date(dateStr).toLocaleDateString('en-US', {
      month: 'short',
      day: 'numeric',
      year: 'numeric'
    });
  };

  return (
    <div className="space-y-6">
      <PageHeader
        category="Student Affairs Office"
        title="Assigned Concerns Queue"
        subtitle={`Concerns officially assigned to the Student Affairs Office (${saoDept?.name || 'SAO'}). Authorized SAO officers can review cases and execute manual priority overrides.`}
        breadcrumbs={[
          { label: 'Dashboard', path: '/sao' },
          { label: 'Assigned Concerns' }
        ]}
      />

      {/* Filter and Search Bar */}
      <div className="card p-4 sm:p-5 bg-slate-50/50">
        <form onSubmit={handleSearchSubmit} className="flex flex-col md:flex-row gap-3">
          <div className="flex-1 relative">
            <Search className="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" size={16} />
            <input
              type="text"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="Search assigned concerns by ID, title, or keywords..."
              className="input-field pl-10"
            />
          </div>

          <div className="flex flex-wrap sm:flex-nowrap items-center gap-2.5">
            <select
              value={status}
              onChange={(e) => {
                setStatus(e.target.value);
                setCurrentPage(1);
              }}
              className="input-field text-xs sm:w-auto"
            >
              <option value="">All Statuses</option>
              <option value="pending">Pending</option>
              <option value="in_progress">In Progress</option>
              <option value="escalated">Escalated</option>
              <option value="resolved">Resolved</option>
              <option value="rejected">Rejected</option>
            </select>

            <select
              value={priority}
              onChange={(e) => {
                setPriority(e.target.value);
                setCurrentPage(1);
              }}
              className="input-field text-xs sm:w-auto"
            >
              <option value="">All Priorities</option>
              <option value="critical">Critical</option>
              <option value="high">High</option>
              <option value="medium">Medium</option>
              <option value="low">Low</option>
            </select>

            <button
              type="submit"
              className="btn-dark text-xs py-2 px-4"
            >
              Search
            </button>
          </div>
        </form>
      </div>

      {/* Concerns Table Card */}
      <div className="card overflow-hidden">
        <div className="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
          <div className="flex items-center gap-2">
            <Inbox size={16} className="text-[#1e3a8a]" />
            <h2 className="text-sm font-bold text-slate-900 uppercase tracking-wider">
              Assigned Cases ({pagination?.total || concerns.length})
            </h2>
          </div>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full text-sm text-left">
            <thead className="text-[11px] uppercase text-slate-500 font-bold tracking-wider bg-slate-50 border-b border-slate-200">
              <tr>
                <th className="px-5 py-3.5">Reference</th>
                <th className="px-5 py-3.5">Title & Classification</th>
                <th className="px-5 py-3.5">Student</th>
                <th className="px-5 py-3.5">Priority</th>
                <th className="px-5 py-3.5">Status</th>
                <th className="px-5 py-3.5">Submitted</th>
                <th className="px-5 py-3.5 text-right">Action</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {loading ? (
                <tr>
                  <td colSpan="7" className="px-6 py-12 text-center text-slate-400 text-sm">
                    Loading assigned concerns...
                  </td>
                </tr>
              ) : concerns.length === 0 ? (
                <tr>
                  <td colSpan="7" className="p-0">
                    <EmptyState
                      icon={FolderOpen}
                      title="No assigned concerns found"
                      description="No concerns are currently assigned to the Student Affairs Office matching these criteria."
                      className="border-0 shadow-none py-12"
                    />
                  </td>
                </tr>
              ) : (
                concerns.map((c) => (
                  <tr
                    key={c.concern_id}
                    onClick={() => navigate(`/sao/concerns/${c.concern_id}`)}
                    className="hover:bg-slate-50/80 transition-colors cursor-pointer group"
                  >
                    <td className="px-5 py-4 font-mono font-bold text-xs text-slate-700 whitespace-nowrap">
                      #CON-{c.concern_id.toString().padStart(4, '0')}
                    </td>
                    <td className="px-5 py-4 max-w-sm">
                      <div className="font-bold text-slate-900 group-hover:text-blue-600 transition-colors truncate">
                        {c.title}
                      </div>
                      <div className="mt-1 flex items-center gap-1.5 flex-wrap">
                        {c.issue_category && (
                          <AiCategoryBadge
                            category={c.issue_category}
                            subcategory={c.issue_subcategory}
                            size="xs"
                          />
                        )}
                        <span className="text-[11px] text-slate-400 truncate max-w-[200px]">
                          {c.description}
                        </span>
                      </div>
                    </td>
                    <td className="px-5 py-4 whitespace-nowrap text-xs">
                      <span className="font-semibold text-slate-800">{c.student?.name || 'Anonymous Student'}</span>
                      {c.student?.student_id_number && (
                        <span className="block text-slate-400 text-[10px]">ID: {c.student.student_id_number}</span>
                      )}
                    </td>
                    <td className="px-5 py-4 whitespace-nowrap">
                      <PriorityBadge priority={c.priority} isOverridden={c.is_priority_overridden} size="xs" />
                    </td>
                    <td className="px-5 py-4 whitespace-nowrap">
                      <div className="flex flex-col items-start gap-1">
                        <StatusBadge status={c.status} size="xs" />
                        {c.sla && !['resolved', 'closed', 'escalated'].includes(c.sla.state) && (
                          <SlaStatusBadge sla={c.sla} status={c.status} size="xs" showRemaining={true} />
                        )}
                      </div>
                    </td>
                    <td className="px-5 py-4 whitespace-nowrap text-xs text-slate-500">
                      {formatDate(c.submitted_at || c.created_at)}
                    </td>
                    <td className="px-5 py-4 text-right whitespace-nowrap" onClick={(e) => e.stopPropagation()}>
                      <Link
                        to={`/sao/concerns/${c.concern_id}`}
                        className="btn-secondary py-1 px-2.5 text-xs text-slate-700 hover:text-blue-600 inline-flex items-center gap-1"
                      >
                        <Eye size={13} />
                        <span>Manage</span>
                      </Link>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>

        {/* Pagination */}
        {pagination && (
          <PaginationControls
            currentPage={pagination.current_page || 1}
            totalPages={pagination.last_page || 1}
            totalItems={pagination.total || concerns.length}
            pageSize={pagination.per_page || 15}
            onPageChange={(page) => setCurrentPage(page)}
          />
        )}
      </div>
    </div>
  );
};

export default SaoAssignedConcerns;
