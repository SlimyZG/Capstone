import { useState, useEffect } from 'react';
import { Search, CheckCircle2, Download, SlidersHorizontal, Clock, AlertCircle } from 'lucide-react';
import api from '../api';

const DeanEscalatedConcerns = () => {
  const [directConcerns, setDirectConcerns] = useState([]);
  const [escalatedConcerns, setEscalatedConcerns] = useState([]);
  const [loading, setLoading] = useState(true);
  const [searchTerm, setSearchTerm] = useState('');
  const [actionLoading, setActionLoading] = useState('');

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
    if (!window.confirm(`${action === 'resolve' ? 'Resolve' : 'Approve'} this concern?`)) return;
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

  const filtered = allConcerns.filter(c => {
    const term = searchTerm.toLowerCase();
    return (
      c.concern_id.toString().includes(term) ||
      (c.student?.name || '').toLowerCase().includes(term)
    );
  });

  const getInitials = (name) => (name || '?').split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2);

  const avatarColors = [
    'bg-amber-100 text-amber-700',
    'bg-blue-100 text-blue-700',
    'bg-emerald-100 text-emerald-700',
    'bg-purple-100 text-purple-700',
    'bg-red-100 text-red-700',
    'bg-teal-100 text-teal-700',
  ];

  return (
    <div>
      <div className="flex justify-between items-start mb-6">
        <div>
          <h1 className="text-2xl font-bold text-slate-900">Escalated Concerns</h1>
          <p className="text-slate-500 text-sm mt-1">Manage and resolve high-priority student issues.</p>
        </div>
        <div className="flex gap-2">
          <button className="flex items-center gap-2 px-3 py-2 border border-slate-200 rounded-lg bg-white text-sm font-medium text-slate-600 hover:bg-slate-50 shadow-sm">
            <SlidersHorizontal size={14} /> Filter
          </button>
          <button className="flex items-center gap-2 px-3 py-2 border border-slate-200 rounded-lg bg-white text-sm font-medium text-slate-600 hover:bg-slate-50 shadow-sm">
            <Download size={14} /> Export
          </button>
        </div>
      </div>

      {/* Stats */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-5 mb-8">
        <div className="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
          <div className="flex justify-between items-start mb-4">
            <p className="text-xs font-semibold text-slate-500">Total Escalations</p>
            <AlertCircle size={16} className="text-slate-400" />
          </div>
          <p className="text-3xl font-bold text-slate-900 mb-1">{loading ? '—' : totalEscalations}</p>
          <p className="text-xs text-red-500 font-semibold flex items-center gap-1">
            ↑ 12% from last month
          </p>
        </div>
        <div className="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
          <div className="flex justify-between items-start mb-4">
            <p className="text-xs font-semibold text-slate-500">Pending Review</p>
            <Clock size={16} className="text-slate-400" />
          </div>
          <p className="text-3xl font-bold text-slate-900 mb-1">{loading ? '—' : pendingReview}</p>
          <p className="text-xs text-slate-400 font-medium">Requires immediate attention</p>
        </div>
        <div className="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
          <div className="flex justify-between items-start mb-4">
            <p className="text-xs font-semibold text-slate-500">Average Resolution Time</p>
            <Clock size={16} className="text-slate-400" />
          </div>
          <p className="text-3xl font-bold text-slate-900 mb-1">3.2 <span className="text-lg font-semibold">Days</span></p>
          <p className="text-xs text-blue-500 font-semibold flex items-center gap-1">
            ↓ Improved by 0.5 days
          </p>
        </div>
      </div>

      {/* Active Concerns Table */}
      <div className="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden mb-6">
        <div className="px-6 py-5 border-b border-slate-100 flex items-center justify-between">
          <h2 className="text-base font-bold text-slate-900">Active Concerns</h2>
          <div className="relative">
            <Search size={14} className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
            <input
              type="text"
              placeholder="Search ID or Student..."
              className="pl-8 pr-4 py-2 border border-slate-200 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all"
              value={searchTerm}
              onChange={e => setSearchTerm(e.target.value)}
            />
          </div>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full text-sm text-left">
            <thead className="text-[10px] uppercase text-slate-400 font-semibold bg-slate-50/60 border-b border-slate-100">
              <tr>
                <th className="px-6 py-3">Concern ID</th>
                <th className="px-6 py-3">Student</th>
                <th className="px-6 py-3">Email</th>
                <th className="px-6 py-3">Status</th>
                <th className="px-6 py-3 text-right">Actions</th>
              </tr>
            </thead>
            <tbody>
              {loading ? (
                <tr><td colSpan="5" className="px-6 py-10 text-center text-slate-400">Loading...</td></tr>
              ) : filtered.length === 0 ? (
                <tr><td colSpan="5" className="px-6 py-10 text-center text-slate-400">No concerns found.</td></tr>
              ) : (
                filtered.map((c, idx) => {
                  const isEscalated = c.status === 'escalated';
                  const colorClass = avatarColors[idx % avatarColors.length];
                  return (
                    <tr key={c.concern_id} className="border-b border-slate-100 hover:bg-slate-50 transition-colors">
                      <td className="px-6 py-4">
                        <span className="font-bold text-[#1e3a8a] text-xs">#ESC-{c.concern_id.toString().padStart(4, '0')}</span>
                      </td>
                      <td className="px-6 py-4">
                        <div className="flex items-center gap-3">
                          <div className={`h-8 w-8 rounded-full flex-shrink-0 flex items-center justify-center text-xs font-bold ${colorClass}`}>
                            {getInitials(c.student?.name)}
                          </div>
                          <div>
                            <p className="font-semibold text-slate-800 text-sm">{c.student?.name || 'Unknown'}</p>
                            <p className="text-[10px] text-slate-400">ID: {c.student?.student_id_number || '—'}</p>
                          </div>
                        </div>
                      </td>
                      <td className="px-6 py-4 text-slate-500 text-xs">
                        {c.student?.student_id_number ? `${c.student.student_id_number.toString().toLowerCase()}@university.edu` : '—'}
                      </td>
                      <td className="px-6 py-4">
                        <span className="px-2.5 py-1 bg-red-50 text-red-600 text-[10px] font-bold uppercase tracking-wider rounded-full border border-red-100">
                          {isEscalated ? 'Escalated' : 'Pending'}
                        </span>
                      </td>
                      <td className="px-6 py-4 text-right">
                        <button
                          onClick={() => handleAction(isEscalated ? 'resolve' : 'resolve', c.concern_id)}
                          disabled={actionLoading === c.concern_id.toString()}
                          className="inline-flex items-center gap-1.5 px-4 py-2 bg-[#1e3a8a] hover:bg-[#152960] text-white text-xs font-bold rounded-lg transition-colors shadow-sm disabled:opacity-60"
                        >
                          <CheckCircle2 size={13} />
                          {actionLoading === c.concern_id.toString() ? 'Resolving...' : 'Resolve'}
                        </button>
                      </td>
                    </tr>
                  );
                })
              )}
            </tbody>
          </table>
        </div>

        {!loading && filtered.length > 0 && (
          <div className="px-6 py-4 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500 bg-white">
            <span>Showing 1 to {filtered.length} of {totalEscalations} results</span>
            <div className="flex gap-1">
              <button className="h-7 w-7 rounded border border-slate-200 flex items-center justify-center text-slate-400 hover:bg-slate-50">‹</button>
              <button className="h-7 w-7 rounded border border-slate-200 flex items-center justify-center text-slate-400 hover:bg-slate-50">›</button>
            </div>
          </div>
        )}
      </div>

      {/* Recurring Concerns (static visual representation) */}
      <div className="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div className="px-6 py-5 border-b border-slate-100">
          <h2 className="text-base font-bold text-slate-900">Recurring Concerns</h2>
          <p className="text-xs text-slate-500 mt-0.5">Issues reported by multiple students that may require systemic intervention.</p>
        </div>
        <div className="overflow-x-auto">
          <table className="w-full text-sm text-left">
            <thead className="text-[10px] uppercase text-slate-400 font-semibold bg-slate-50/60 border-b border-slate-100">
              <tr>
                <th className="px-6 py-3">Concern Topic</th>
                <th className="px-6 py-3">Number of Students</th>
                <th className="px-6 py-3">Status</th>
                <th className="px-6 py-3 text-right">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr className="border-b border-slate-100 hover:bg-slate-50">
                <td className="px-6 py-4 font-semibold text-slate-800">Air Conditioning Malfunction - Engineering Block</td>
                <td className="px-6 py-4 text-slate-600">12 Students</td>
                <td className="px-6 py-4"><span className="px-2.5 py-1 bg-amber-50 text-amber-600 text-[10px] font-bold uppercase tracking-wider rounded-full border border-amber-100">Investigating</span></td>
                <td className="px-6 py-4 text-right">
                  <div className="flex gap-2 justify-end">
                    <button className="px-3 py-1.5 border border-slate-200 rounded-lg text-xs font-semibold text-slate-600 hover:bg-slate-50">View Details</button>
                    <button className="px-3 py-1.5 bg-[#1e3a8a] text-white rounded-lg text-xs font-semibold hover:bg-[#152960]">Take Action</button>
                  </div>
                </td>
              </tr>
              <tr className="hover:bg-slate-50">
                <td className="px-6 py-4 font-semibold text-slate-800">Library Wi-Fi Connectivity Issues</td>
                <td className="px-6 py-4 text-slate-600">45 Students</td>
                <td className="px-6 py-4"><span className="px-2.5 py-1 bg-blue-50 text-blue-600 text-[10px] font-bold uppercase tracking-wider rounded-full border border-blue-100">In Progress</span></td>
                <td className="px-6 py-4 text-right">
                  <div className="flex gap-2 justify-end">
                    <button className="px-3 py-1.5 border border-slate-200 rounded-lg text-xs font-semibold text-slate-600 hover:bg-slate-50">View Details</button>
                    <button className="px-3 py-1.5 bg-[#1e3a8a] text-white rounded-lg text-xs font-semibold hover:bg-[#152960]">Take Action</button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
};

export default DeanEscalatedConcerns;
