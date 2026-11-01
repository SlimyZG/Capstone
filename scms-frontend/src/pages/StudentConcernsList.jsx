import { useState, useEffect, useMemo } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { Plus, Search, Eye, Filter, FolderOpen } from 'lucide-react';
import api from '../api';
import PageHeader from '../components/PageHeader';
import StatusBadge from '../components/StatusBadge';
import AiCategoryBadge from '../components/AiCategoryBadge';
import EmptyState from '../components/EmptyState';
import PaginationControls from '../components/PaginationControls';

const StudentConcernsList = () => {
  const [concerns, setConcerns] = useState([]);
  const [loading, setLoading] = useState(true);
  const [searchTerm, setSearchTerm] = useState('');
  const [departmentFilter, setDepartmentFilter] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [currentPage, setCurrentPage] = useState(1);
  const pageSize = 10;
  const navigate = useNavigate();

  useEffect(() => {
    fetchConcerns();
  }, []);

  const fetchConcerns = async () => {
    try {
      const res = await api.get('/concerns');
      setConcerns(res.data.concerns || []);
    } catch (err) {
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  const formatDate = (dateString) => {
    if (!dateString) return '—';
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return '—';
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
  };

  const filteredConcerns = useMemo(() => {
    return concerns.filter(c => {
      const matchSearch = c.concern_id.toString().toLowerCase().includes(searchTerm.toLowerCase()) || 
                          c.title.toLowerCase().includes(searchTerm.toLowerCase()) ||
                          (c.description || '').toLowerCase().includes(searchTerm.toLowerCase());
      const matchDept = departmentFilter ? (c.department?.name === departmentFilter || c.academic_unit?.name === departmentFilter) : true;
      const matchStatus = statusFilter ? c.status === statusFilter : true;
      return matchSearch && matchDept && matchStatus;
    });
  }, [concerns, searchTerm, departmentFilter, statusFilter]);

  const totalPages = Math.ceil(filteredConcerns.length / pageSize);
  const paginatedConcerns = useMemo(() => {
    const start = (currentPage - 1) * pageSize;
    return filteredConcerns.slice(start, start + pageSize);
  }, [filteredConcerns, currentPage, pageSize]);

  const departments = [...new Set(concerns.map(c => c.department?.name || c.academic_unit?.name))].filter(Boolean);

  const headerActions = (
    <Link to="/student/submit" className="btn-dark text-xs py-2 px-3.5">
      <Plus size={15} className="mr-1.5" />
      <span>New Concern</span>
    </Link>
  );

  return (
    <div className="space-y-6">
      <PageHeader
        category="Student Portal"
        title="My Concerns"
        subtitle="Review, monitor, and follow up on all your submitted academic and administrative concerns."
        breadcrumbs={[
          { label: 'Dashboard', path: '/student' },
          { label: 'My Concerns' }
        ]}
        actions={headerActions}
      />

      <div className="card overflow-hidden">
        {/* Search & Filters */}
        <div className="p-4 sm:p-5 border-b border-slate-200 flex flex-col sm:flex-row gap-3 bg-slate-50/50">
          <div className="relative flex-1">
            <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
            <input 
              type="text" 
              placeholder="Search by Concern ID, Title, or Keywords..." 
              className="input-field pl-9"
              value={searchTerm}
              onChange={(e) => {
                setSearchTerm(e.target.value);
                setCurrentPage(1);
              }}
            />
          </div>
          <div className="flex flex-wrap sm:flex-nowrap gap-2.5">
            <select 
              className="input-field text-xs sm:w-auto"
              value={departmentFilter}
              onChange={(e) => {
                setDepartmentFilter(e.target.value);
                setCurrentPage(1);
              }}
            >
              <option value="">All Departments</option>
              {departments.map(d => <option key={d} value={d}>{d}</option>)}
            </select>
            <select 
              className="input-field text-xs sm:w-auto"
              value={statusFilter}
              onChange={(e) => {
                setStatusFilter(e.target.value);
                setCurrentPage(1);
              }}
            >
              <option value="">All Statuses</option>
              <option value="pending">Pending</option>
              <option value="in_progress">In Progress</option>
              <option value="escalated">Escalated</option>
              <option value="resolved">Resolved</option>
              <option value="withdrawn">Withdrawn</option>
            </select>
          </div>
        </div>

        {/* Concerns Table */}
        <div className="overflow-x-auto">
          <table className="w-full text-left text-sm text-slate-600">
            <thead className="text-[11px] uppercase bg-slate-50 text-slate-500 font-bold tracking-wider border-b border-slate-200">
              <tr>
                <th className="px-5 py-3.5">Concern ID</th>
                <th className="px-5 py-3.5">Title & Category</th>
                <th className="px-5 py-3.5">Assigned Target</th>
                <th className="px-5 py-3.5">Status</th>
                <th className="px-5 py-3.5">Date Submitted</th>
                <th className="px-5 py-3.5 text-center">Action</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {loading ? (
                <tr>
                  <td colSpan="6" className="px-6 py-12 text-center text-slate-400 text-sm">
                    Loading your concerns...
                  </td>
                </tr>
              ) : filteredConcerns.length === 0 ? (
                <tr>
                  <td colSpan="6" className="p-0">
                    <EmptyState
                      icon={FolderOpen}
                      title="No matching concerns found"
                      description={searchTerm || departmentFilter || statusFilter ? "Try adjusting your search query or filters to find your submitted tickets." : "You have not submitted any concerns yet."}
                      action={
                        <Link to="/student/submit" className="btn-primary text-xs">
                          <Plus size={14} className="mr-1.5" />
                          Submit New Concern
                        </Link>
                      }
                      className="border-0 shadow-none py-12"
                    />
                  </td>
                </tr>
              ) : (
                paginatedConcerns.map((concern) => (
                  <tr 
                    key={concern.concern_id} 
                    onClick={() => navigate(`/student/concerns/${concern.concern_id}`)}
                    className="hover:bg-slate-50/80 transition-colors cursor-pointer group"
                  >
                    <td className="px-5 py-4 font-mono font-bold text-slate-600 whitespace-nowrap text-xs">
                      #REQ-{concern.concern_id.toString().padStart(4, '0')}
                    </td>
                    <td className="px-5 py-4 max-w-sm">
                      <div className="font-bold text-slate-900 group-hover:text-blue-600 transition-colors truncate">
                        {concern.title}
                      </div>
                      <div className="mt-1 flex items-center gap-2 flex-wrap">
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
                    <td className="px-5 py-4 text-xs font-medium text-slate-700 whitespace-nowrap">
                      {concern.department ? concern.department.name : (concern.academic_unit?.name || 'Academic Unit')}
                    </td>
                    <td className="px-5 py-4 whitespace-nowrap">
                      <StatusBadge status={concern.status} />
                    </td>
                    <td className="px-5 py-4 whitespace-nowrap text-xs text-slate-500">
                      {formatDate(concern.submitted_at || concern.created_at)}
                    </td>
                    <td className="px-5 py-4 text-center whitespace-nowrap" onClick={(e) => e.stopPropagation()}>
                      <button 
                        onClick={() => navigate(`/student/concerns/${concern.concern_id}`)}
                        className="btn-secondary py-1.5 px-2.5 text-xs inline-flex items-center gap-1 text-slate-700 hover:text-blue-600"
                        title="View Concern Details"
                      >
                        <Eye size={14} />
                        <span>View</span>
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

export default StudentConcernsList;
