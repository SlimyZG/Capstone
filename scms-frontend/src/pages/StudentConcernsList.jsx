import { useState, useEffect } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { Plus, Search, Eye } from 'lucide-react';
import api from '../api';

const StudentConcernsList = () => {
  const [concerns, setConcerns] = useState([]);
  const [loading, setLoading] = useState(true);
  const [searchTerm, setSearchTerm] = useState('');
  const [departmentFilter, setDepartmentFilter] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const navigate = useNavigate();

  useEffect(() => {
    fetchConcerns();
  }, []);

  const fetchConcerns = async () => {
    try {
      const res = await api.get('/concerns');
      setConcerns(res.data.concerns);
    } catch (err) {
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  const getStatusBadge = (status) => {
    switch (status) {
      case 'submitted': return <span className="px-2 py-1 bg-slate-100 text-slate-600 rounded-full text-[10px] font-bold uppercase tracking-wider flex items-center w-max"><span className="w-1.5 h-1.5 rounded-full bg-slate-400 mr-1.5"></span> Submitted</span>;
      case 'in_progress': return <span className="px-2 py-1 bg-amber-50 text-amber-700 rounded-full text-[10px] font-bold uppercase tracking-wider flex items-center w-max"><span className="w-1.5 h-1.5 rounded-full bg-amber-500 mr-1.5"></span> In Progress</span>;
      case 'resolved': return <span className="px-2 py-1 bg-emerald-50 text-emerald-700 rounded-full text-[10px] font-bold uppercase tracking-wider flex items-center w-max"><span className="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-1.5"></span> Resolved</span>;
      default: return <span className="px-2 py-1 bg-slate-100 text-slate-600 rounded-full text-[10px] font-bold uppercase tracking-wider flex items-center w-max"><span className="w-1.5 h-1.5 rounded-full bg-slate-400 mr-1.5"></span> {status}</span>;
    }
  };

  const formatDate = (dateString) => {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
  };

  const filteredConcerns = concerns.filter(c => {
    const matchSearch = c.concern_id.toString().toLowerCase().includes(searchTerm.toLowerCase()) || 
                        c.title.toLowerCase().includes(searchTerm.toLowerCase());
    const matchDept = departmentFilter ? (c.department?.name === departmentFilter || c.academic_unit?.name === departmentFilter) : true;
    const matchStatus = statusFilter ? c.status === statusFilter : true;
    return matchSearch && matchDept && matchStatus;
  });

  const departments = [...new Set(concerns.map(c => c.department?.name || c.academic_unit?.name))].filter(Boolean);

  return (
    <div>
      <div className="flex justify-between items-end mb-6">
        <div>
          <h1 className="text-3xl font-bold text-slate-900 tracking-tight">My Concerns</h1>
          <p className="text-slate-600 mt-1 text-sm">Track and manage your submitted requests and issues.</p>
        </div>
        <Link to="/student/submit" className="bg-[#1e3a8a] hover:bg-blue-800 text-white font-semibold text-sm px-4 py-2 rounded-md transition-colors flex items-center shadow-sm">
          <Plus size={16} className="mr-2" /> New Concern
        </Link>
      </div>

      <div className="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        {/* Filters */}
        <div className="p-4 border-b border-slate-200 flex flex-col sm:flex-row gap-4 bg-slate-50/50">
          <div className="relative flex-1">
            <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
            <input 
              type="text" 
              placeholder="Search by ID or Title..." 
              className="w-full pl-9 pr-4 py-2 bg-white border border-slate-200 rounded-md text-sm focus:ring-1 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all"
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
            />
          </div>
          <div className="flex gap-2">
            <select 
              className="px-3 py-2 bg-white border border-slate-200 rounded-md text-sm text-slate-700 outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
              value={departmentFilter}
              onChange={(e) => setDepartmentFilter(e.target.value)}
            >
              <option value="">All Departments</option>
              {departments.map(d => <option key={d} value={d}>{d}</option>)}
            </select>
            <select 
              className="px-3 py-2 bg-white border border-slate-200 rounded-md text-sm text-slate-700 outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
              value={statusFilter}
              onChange={(e) => setStatusFilter(e.target.value)}
            >
              <option value="">All Statuses</option>
              <option value="submitted">Submitted</option>
              <option value="in_progress">In Progress</option>
              <option value="resolved">Resolved</option>
            </select>
          </div>
        </div>

        {/* Table */}
        <div className="overflow-x-auto">
          <table className="w-full text-left text-sm text-slate-600">
            <thead className="text-xs uppercase bg-slate-50 text-slate-500 font-semibold border-b border-slate-200">
              <tr>
                <th className="px-6 py-4">Concern ID</th>
                <th className="px-6 py-4">Title</th>
                <th className="px-6 py-4">Department</th>
                <th className="px-6 py-4">Status</th>
                <th className="px-6 py-4">Date Submitted</th>
                <th className="px-6 py-4 text-center">Actions</th>
              </tr>
            </thead>
            <tbody>
              {loading ? (
                <tr><td colSpan="6" className="px-6 py-8 text-center text-slate-500">Loading concerns...</td></tr>
              ) : filteredConcerns.length === 0 ? (
                <tr><td colSpan="6" className="px-6 py-8 text-center text-slate-500">No concerns found.</td></tr>
              ) : (
                filteredConcerns.map((concern) => (
                  <tr key={concern.concern_id} className="border-b border-slate-100 hover:bg-slate-50 transition-colors">
                    <td className="px-6 py-4 font-bold text-slate-700 whitespace-nowrap">#{concern.concern_id}</td>
                    <td className="px-6 py-4">
                      <div className="font-bold text-slate-900">{concern.title}</div>
                      <div className="text-xs text-slate-500 mt-0.5 truncate max-w-[250px]">{concern.description}</div>
                    </td>
                    <td className="px-6 py-4">{concern.department ? concern.department.name : concern.academic_unit?.name}</td>
                    <td className="px-6 py-4">{getStatusBadge(concern.status)}</td>
                    <td className="px-6 py-4">{formatDate(concern.created_at)}</td>
                    <td className="px-6 py-4 text-center">
                      <button 
                        onClick={() => navigate(`/student/concerns/${concern.concern_id}`)}
                        className="text-blue-600 hover:text-blue-800 p-1.5 hover:bg-blue-50 rounded-md transition-colors inline-flex"
                        title="View Details"
                      >
                        <Eye size={18} />
                      </button>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>

        {/* Pagination (Visual only for now based on mockup) */}
        {!loading && filteredConcerns.length > 0 && (
          <div className="px-6 py-4 border-t border-slate-200 flex items-center justify-between bg-white text-sm">
            <span className="text-slate-500">Showing 1 to {filteredConcerns.length} of {filteredConcerns.length} entries</span>
            <div className="flex space-x-1">
              <button className="px-3 py-1.5 border border-slate-200 rounded-md text-slate-400 cursor-not-allowed">Previous</button>
              <button className="px-3 py-1.5 border border-[#1e3a8a] bg-[#1e3a8a] text-white font-medium rounded-md">1</button>
              <button className="px-3 py-1.5 border border-slate-200 rounded-md text-slate-600 hover:bg-slate-50">Next</button>
            </div>
          </div>
        )}
      </div>
    </div>
  );
};

export default StudentConcernsList;
