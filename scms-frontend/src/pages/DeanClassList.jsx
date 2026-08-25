import { useState, useEffect } from 'react';
import { Search, SlidersHorizontal, Upload, UserPlus } from 'lucide-react';
import api from '../api';

const DeanClassList = () => {
  const [students, setStudents] = useState([]);
  const [loading, setLoading] = useState(true);
  const [searchTerm, setSearchTerm] = useState('');

  useEffect(() => {
    // Fetch concerns to infer unique students in the academic unit
    const fetchStudents = async () => {
      try {
        const res = await api.get('/dean/inbox');
        const all = [...(res.data.direct || []), ...(res.data.escalated || [])];
        // Build unique student list from concerns
        const map = new Map();
        all.forEach(c => {
          if (c.student && !map.has(c.student.id)) {
            map.set(c.student.id, c.student);
          }
        });
        setStudents(Array.from(map.values()));
      } catch (err) {
        console.error(err);
      } finally {
        setLoading(false);
      }
    };
    fetchStudents();
  }, []);

  const getInitials = (name) => (name || '?').split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2);

  const avatarColors = [
    'bg-blue-100 text-blue-700',
    'bg-amber-100 text-amber-700',
    'bg-emerald-100 text-emerald-700',
    'bg-purple-100 text-purple-700',
    'bg-red-100 text-red-700',
    'bg-teal-100 text-teal-700',
    'bg-slate-200 text-slate-600',
  ];

  const filtered = students.filter(s =>
    (s.student_id_number || '').toString().includes(searchTerm) ||
    (s.name || '').toLowerCase().includes(searchTerm.toLowerCase())
  );

  return (
    <div>
      <div className="flex justify-between items-start mb-6">
        <h1 className="text-2xl font-bold text-slate-900">Class List</h1>
        <div className="flex gap-2">
          <button className="flex items-center gap-2 px-3 py-2 border border-slate-200 rounded-lg bg-white text-sm font-medium text-slate-600 hover:bg-slate-50 shadow-sm">
            <Upload size={14} /> Upload Class List
          </button>
          <button className="flex items-center gap-2 px-3 py-2 bg-[#1e3a8a] rounded-lg text-sm font-medium text-white hover:bg-[#152960] shadow-sm">
            <UserPlus size={14} /> Add Student
          </button>
        </div>
      </div>

      <div className="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        {/* Search */}
        <div className="px-5 py-4 border-b border-slate-100 flex items-center justify-between gap-4">
          <div className="relative flex-1 max-w-sm">
            <Search size={14} className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
            <input
              type="text"
              placeholder="Search Student ID or Name"
              className="w-full pl-8 pr-4 py-2 border border-slate-200 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all"
              value={searchTerm}
              onChange={e => setSearchTerm(e.target.value)}
            />
          </div>
          <button className="flex items-center gap-1.5 px-3 py-2 border border-slate-200 rounded-lg text-xs font-semibold text-slate-600 hover:bg-slate-50">
            <SlidersHorizontal size={13} /> Filter
          </button>
        </div>

        {/* Table */}
        <div className="overflow-x-auto">
          <table className="w-full text-sm text-left">
            <thead className="text-[10px] uppercase text-slate-400 font-semibold bg-slate-50/60 border-b border-slate-100">
              <tr>
                <th className="px-6 py-3">Student ID</th>
                <th className="px-6 py-3">Student Name</th>
                <th className="px-6 py-3">Email</th>
              </tr>
            </thead>
            <tbody>
              {loading ? (
                <tr><td colSpan="3" className="px-6 py-10 text-center text-slate-400">Loading students...</td></tr>
              ) : filtered.length === 0 ? (
                <tr>
                  <td colSpan="3" className="px-6 py-12 text-center">
                    <p className="text-slate-400 text-sm">No students found in your academic unit.</p>
                    <p className="text-slate-300 text-xs mt-1">Students appear here when they submit concerns.</p>
                  </td>
                </tr>
              ) : (
                filtered.map((s, idx) => {
                  const colorClass = avatarColors[idx % avatarColors.length];
                  return (
                    <tr key={s.id} className="border-b border-slate-100 hover:bg-slate-50 transition-colors">
                      <td className="px-6 py-4 font-semibold text-slate-700">{s.student_id_number || '—'}</td>
                      <td className="px-6 py-4">
                        <div className="flex items-center gap-3">
                          <div className={`h-8 w-8 rounded-full flex-shrink-0 flex items-center justify-center text-xs font-bold ${colorClass}`}>
                            {getInitials(s.name)}
                          </div>
                          <span className="font-medium text-slate-800">{s.name}</span>
                        </div>
                      </td>
                      <td className="px-6 py-4 text-slate-500 text-sm">
                        {s.student_id_number ? `${s.student_id_number.toString().toLowerCase()}@university.edu` : '—'}
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
            <span>Showing 1 to {filtered.length} of {filtered.length} entries</span>
            <div className="flex items-center gap-1">
              <button className="h-7 w-7 rounded border border-slate-200 flex items-center justify-center text-slate-400 hover:bg-slate-50">‹</button>
              <button className="h-7 w-7 rounded border border-slate-200 bg-[#1e3a8a] text-white flex items-center justify-center text-xs font-bold">1</button>
              <button className="h-7 w-7 rounded border border-slate-200 flex items-center justify-center text-slate-400 hover:bg-slate-50">›</button>
            </div>
          </div>
        )}
      </div>
    </div>
  );
};

export default DeanClassList;
