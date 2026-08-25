import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { Search } from 'lucide-react';
import api from '../api';

const DeptStaffConcernsList = () => {
  const [concerns, setConcerns] = useState([]);
  const [loading, setLoading] = useState(true);
  const [searchTerm, setSearchTerm] = useState('');
  const [priorityFilter, setPriorityFilter] = useState('');
  const [statusFilter, setStatusFilter] = useState('Active (New & In Progress)');
  const navigate = useNavigate();

  useEffect(() => {
    const fetchQueue = async () => {
      try {
        const res = await api.get('/dept-staff/queue');
        setConcerns(res.data.concerns);
      } catch (err) {
        console.error(err);
      } finally {
        setLoading(false);
      }
    };
    fetchQueue();
  }, []);

  const getPriorityBadge = (priority) => {
    switch ((priority || '').toLowerCase()) {
      case 'high': return <span className="inline-flex items-center gap-1 px-2.5 py-1 bg-red-50 text-red-600 rounded-full text-xs font-bold border border-red-100"><span className="w-1.5 h-1.5 rounded-full bg-red-500"></span>High</span>;
      case 'medium': return <span className="inline-flex items-center gap-1 px-2.5 py-1 bg-amber-50 text-amber-600 rounded-full text-xs font-bold border border-amber-100"><span className="w-1.5 h-1.5 rounded-full bg-amber-500"></span>Medium</span>;
      case 'low': return <span className="inline-flex items-center gap-1 px-2.5 py-1 bg-slate-100 text-slate-600 rounded-full text-xs font-bold"><span className="w-1.5 h-1.5 rounded-full bg-slate-400"></span>Low</span>;
      default: return <span className="inline-flex items-center gap-1 px-2.5 py-1 bg-slate-100 text-slate-500 rounded-full text-xs font-bold">—</span>;
    }
  };

  const getStatusBadge = (status) => {
    switch (status) {
      case 'pending': return <span className="px-2.5 py-1 bg-blue-50 text-blue-600 rounded-full text-xs font-bold border border-blue-100">New</span>;
      case 'in_progress': return <span className="px-2.5 py-1 bg-amber-50 text-amber-600 rounded-full text-xs font-bold border border-amber-100">In Progress</span>;
      case 'resolved': return <span className="px-2.5 py-1 bg-emerald-50 text-emerald-700 rounded-full text-xs font-bold border border-emerald-100">Resolved</span>;
      case 'escalated': return <span className="px-2.5 py-1 bg-purple-50 text-purple-600 rounded-full text-xs font-bold border border-purple-100">Escalated</span>;
      default: return <span className="px-2.5 py-1 bg-slate-100 text-slate-500 rounded-full text-xs font-bold">{status}</span>;
    }
  };

  const getInitials = (name) => {
    if (!name) return '?';
    return name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2);
  };

  const filteredConcerns = concerns.filter(c => {
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

  return (
    <div>
      <div className="mb-6">
        <h1 className="text-3xl font-bold text-slate-900 tracking-tight">Assigned Concerns</h1>
        <p className="text-slate-500 mt-1 text-sm">Manage and review student behavioral and academic reports.</p>
      </div>

      <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        {/* Filters */}
        <div className="p-4 flex flex-col sm:flex-row gap-3 border-b border-slate-200">
          <div className="relative flex-1">
            <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
            <input
              type="text"
              placeholder="Search by ID or Student Name..."
              className="w-full pl-9 pr-4 py-2.5 border border-slate-200 rounded-lg text-sm bg-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all"
              value={searchTerm}
              onChange={e => setSearchTerm(e.target.value)}
            />
          </div>
          <div className="flex gap-2 items-center">
            <span className="text-xs font-semibold text-slate-500 uppercase tracking-wider whitespace-nowrap">Priority:</span>
            <select
              className="px-3 py-2.5 border border-slate-200 rounded-lg text-sm bg-white text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500"
              value={priorityFilter}
              onChange={e => setPriorityFilter(e.target.value)}
            >
              <option value="">All</option>
              <option value="High">High</option>
              <option value="Medium">Medium</option>
              <option value="Low">Low</option>
            </select>
          </div>
          <div className="flex gap-2 items-center">
            <span className="text-xs font-semibold text-slate-500 uppercase tracking-wider whitespace-nowrap">Status:</span>
            <select
              className="px-3 py-2.5 border border-slate-200 rounded-lg text-sm bg-white text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500"
              value={statusFilter}
              onChange={e => setStatusFilter(e.target.value)}
            >
              <option>Active (New & In Progress)</option>
              <option>Resolved</option>
              <option>Escalated</option>
            </select>
          </div>
        </div>

        {/* Table */}
        <div className="overflow-x-auto">
          <table className="w-full text-sm text-left">
            <thead className="text-[10px] uppercase text-slate-500 font-semibold border-b border-slate-100 bg-slate-50/60">
              <tr>
                <th className="px-6 py-4">Concern ID</th>
                <th className="px-6 py-4">Student Name</th>
                <th className="px-6 py-4">Priority</th>
                <th className="px-6 py-4">Status</th>
                <th className="px-6 py-4 text-right">Action</th>
              </tr>
            </thead>
            <tbody>
              {loading ? (
                <tr><td colSpan="5" className="px-6 py-10 text-center text-slate-400">Loading concerns...</td></tr>
              ) : filteredConcerns.length === 0 ? (
                <tr><td colSpan="5" className="px-6 py-10 text-center text-slate-400">No concerns match the current filters.</td></tr>
              ) : (
                filteredConcerns.map(c => (
                  <tr key={c.concern_id} className="border-b border-slate-100 hover:bg-slate-50 transition-colors">
                    <td className="px-6 py-4">
                      <span className="font-bold text-[#1e3a8a]">#CON-{c.concern_id.toString().padStart(4, '0')}</span>
                    </td>
                    <td className="px-6 py-4">
                      <div className="flex items-center gap-3">
                        <div className="h-8 w-8 rounded-full bg-slate-200 flex-shrink-0 flex items-center justify-center text-slate-600 text-xs font-bold">
                          {getInitials(c.student?.name)}
                        </div>
                        <span className="font-medium text-slate-800">{c.student?.name || 'Unknown'}</span>
                      </div>
                    </td>
                    <td className="px-6 py-4">{getPriorityBadge(c.priority)}</td>
                    <td className="px-6 py-4">{getStatusBadge(c.status)}</td>
                    <td className="px-6 py-4 text-right">
                      <button
                        onClick={() => navigate(`/dept-staff/concerns/${c.concern_id}`)}
                        className="font-bold text-[#1e3a8a] hover:text-blue-900 text-sm"
                      >
                        View
                      </button>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>

        {/* Pagination */}
        {!loading && filteredConcerns.length > 0 && (
          <div className="px-6 py-4 border-t border-slate-100 flex items-center justify-between text-sm bg-white">
            <span className="text-slate-500">Showing 1 to {filteredConcerns.length} of {filteredConcerns.length} concerns</span>
            <div className="flex space-x-1">
              <button className="px-3 py-1.5 border border-slate-200 rounded-md text-slate-400 cursor-not-allowed text-xs font-medium">Previous</button>
              <button className="px-3 py-1.5 border border-slate-200 rounded-md text-slate-600 hover:bg-slate-50 text-xs font-medium">Next</button>
            </div>
          </div>
        )}
      </div>
    </div>
  );
};

export default DeptStaffConcernsList;
