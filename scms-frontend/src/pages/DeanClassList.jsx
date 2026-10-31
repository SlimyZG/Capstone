import { useState, useEffect, useMemo } from 'react';
import { Search, Upload, UserPlus, X, FolderOpen, AlertCircle } from 'lucide-react';
import api from '../api';
import PageHeader from '../components/PageHeader';
import EmptyState from '../components/EmptyState';
import PaginationControls from '../components/PaginationControls';
import LoadingSpinner from '../components/LoadingSpinner';

const DeanClassList = () => {
  const [students, setStudents] = useState([]);
  const [loading, setLoading] = useState(true);
  const [searchTerm, setSearchTerm] = useState('');

  const [showAddModal, setShowAddModal] = useState(false);
  const [showUploadModal, setShowUploadModal] = useState(false);
  
  const [newStudentId, setNewStudentId] = useState('');
  const [uploadFile, setUploadFile] = useState(null);
  const [actionLoading, setActionLoading] = useState(false);
  const [errorMsg, setErrorMsg] = useState('');

  const [currentPage, setCurrentPage] = useState(1);
  const pageSize = 12;

  const fetchStudents = async () => {
    setLoading(true);
    try {
      const res = await api.get('/dean/class-list');
      setStudents(res.data.class_list || []);
    } catch (err) {
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchStudents();
  }, []);

  const handleAddStudent = async (e) => {
    e.preventDefault();
    setErrorMsg('');
    setActionLoading(true);
    try {
      await api.post('/dean/class-list', { student_id_number: newStudentId });
      setNewStudentId('');
      setShowAddModal(false);
      fetchStudents();
    } catch (err) {
      setErrorMsg(err.response?.data?.message || 'Failed to add student. Ensure ID is 8 digits and not duplicate.');
    } finally {
      setActionLoading(false);
    }
  };

  const handleUploadFile = async (e) => {
    e.preventDefault();
    if (!uploadFile) return;
    setErrorMsg('');
    setActionLoading(true);

    const formData = new FormData();
    formData.append('file', uploadFile);

    try {
      await api.post('/dean/class-list/upload', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      setUploadFile(null);
      setShowUploadModal(false);
      fetchStudents();
    } catch (err) {
      setErrorMsg(err.response?.data?.message || 'Failed to upload file.');
    } finally {
      setActionLoading(false);
    }
  };

  const getInitials = (name) => {
    if (!name || name === 'Pending Registration') return '?';
    return name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2);
  };

  const filtered = useMemo(() => {
    return students.filter(s =>
      (s.student_id_number || '').toString().includes(searchTerm) ||
      (s.name || '').toLowerCase().includes(searchTerm.toLowerCase())
    );
  }, [students, searchTerm]);

  const totalPages = Math.ceil(filtered.length / pageSize);
  const paginated = useMemo(() => {
    const start = (currentPage - 1) * pageSize;
    return filtered.slice(start, start + pageSize);
  }, [filtered, currentPage, pageSize]);

  const headerActions = (
    <div className="flex items-center gap-2">
      <button 
        onClick={() => setShowUploadModal(true)}
        className="btn-secondary text-xs py-2 px-3"
      >
        <Upload size={14} className="mr-1.5" /> Upload CSV
      </button>
      <button 
        onClick={() => setShowAddModal(true)}
        className="btn-dark text-xs py-2 px-3.5"
      >
        <UserPlus size={14} className="mr-1.5" /> Add Student
      </button>
    </div>
  );

  return (
    <div className="space-y-6">
      <PageHeader
        category="Academic Affairs"
        title="College Class List"
        subtitle="Roster of authorized students enrolled in your academic unit with registration status."
        breadcrumbs={[
          { label: 'Dashboard', path: '/dean' },
          { label: 'Class List' }
        ]}
        actions={headerActions}
      />

      <div className="card overflow-hidden">
        {/* Search */}
        <div className="p-4 sm:p-5 border-b border-slate-200 flex flex-col sm:flex-row items-center justify-between gap-3 bg-slate-50/50">
          <div>
            <h2 className="text-base font-bold text-slate-900">Enrolled Students ({students.length})</h2>
            <p className="text-xs text-slate-500 mt-0.5">Filter by 8-digit Student ID Number or Student Name</p>
          </div>
          <div className="relative w-full sm:w-72">
            <Search size={15} className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
            <input
              type="text"
              placeholder="Search Student ID or Name..."
              className="input-field pl-9"
              value={searchTerm}
              onChange={e => {
                setSearchTerm(e.target.value);
                setCurrentPage(1);
              }}
            />
          </div>
        </div>

        {/* Table */}
        <div className="overflow-x-auto">
          <table className="w-full text-sm text-left">
            <thead className="text-[11px] uppercase text-slate-500 font-bold tracking-wider bg-slate-50 border-b border-slate-200">
              <tr>
                <th className="px-5 py-3.5">Student ID</th>
                <th className="px-5 py-3.5">Full Name</th>
                <th className="px-5 py-3.5">Account Status</th>
                <th className="px-5 py-3.5">Date Enrolled</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {loading ? (
                <tr>
                  <td colSpan="4" className="px-6 py-12 text-center text-slate-400 text-sm">
                    Loading student roster...
                  </td>
                </tr>
              ) : filtered.length === 0 ? (
                <tr>
                  <td colSpan="4" className="p-0">
                    <EmptyState
                      icon={FolderOpen}
                      title="No students found"
                      description={searchTerm ? "No students match your search criteria." : "No students currently enrolled in the class list."}
                      className="border-0 shadow-none py-12"
                    />
                  </td>
                </tr>
              ) : (
                paginated.map((s) => (
                  <tr key={s.id} className="hover:bg-slate-50/80 transition-colors">
                    <td className="px-5 py-4 font-mono font-bold text-xs text-slate-700 whitespace-nowrap">
                      {s.student_id_number}
                    </td>
                    <td className="px-5 py-4">
                      <div className="flex items-center gap-2.5">
                        <div className="h-7 w-7 rounded-full bg-blue-100 text-[#1e3a8a] flex items-center justify-center text-xs font-bold shrink-0">
                          {getInitials(s.name)}
                        </div>
                        <span className="font-semibold text-slate-900 text-xs">
                          {s.name || 'Pending Registration'}
                        </span>
                      </div>
                    </td>
                    <td className="px-5 py-4 whitespace-nowrap">
                      <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold ${
                        s.is_registered 
                          ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' 
                          : 'bg-amber-50 text-amber-700 border border-amber-200'
                      }`}>
                        {s.is_registered ? 'Registered Account' : 'Pending First Login'}
                      </span>
                    </td>
                    <td className="px-5 py-4 text-xs text-slate-500 whitespace-nowrap">
                      {s.created_at ? new Date(s.created_at).toLocaleDateString() : '—'}
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
          totalItems={filtered.length}
          pageSize={pageSize}
          onPageChange={setCurrentPage}
        />
      </div>

      {/* Add Student Modal */}
      {showAddModal && (
        <div className="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white rounded-xl shadow-lg border border-slate-200 max-w-sm w-full p-6 animate-in fade-in zoom-in-95 duration-150">
            <div className="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
              <h3 className="text-base font-bold text-slate-900">Add Student to Roster</h3>
              <button onClick={() => setShowAddModal(false)} className="text-slate-400 hover:text-slate-600">
                <X size={18} />
              </button>
            </div>

            {errorMsg && (
              <div className="p-3 bg-red-50 border border-red-200 text-red-700 text-xs rounded-lg mb-4 flex items-center gap-1.5">
                <AlertCircle size={14} className="shrink-0" />
                <span>{errorMsg}</span>
              </div>
            )}

            <form onSubmit={handleAddStudent} className="space-y-4">
              <div>
                <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                  Student ID Number (8 Digits) <span className="text-red-500">*</span>
                </label>
                <input
                  type="text"
                  maxLength={8}
                  placeholder="e.g. 20231234"
                  required
                  className="input-field"
                  value={newStudentId}
                  onChange={e => setNewStudentId(e.target.value)}
                />
              </div>

              <div className="flex justify-end gap-2 pt-2">
                <button
                  type="button"
                  onClick={() => setShowAddModal(false)}
                  className="btn-secondary text-xs"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={actionLoading}
                  className="btn-dark text-xs"
                >
                  {actionLoading ? 'Adding...' : 'Add Student'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Upload CSV Modal */}
      {showUploadModal && (
        <div className="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white rounded-xl shadow-lg border border-slate-200 max-w-sm w-full p-6 animate-in fade-in zoom-in-95 duration-150">
            <div className="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
              <h3 className="text-base font-bold text-slate-900">Upload Class List CSV</h3>
              <button onClick={() => setShowUploadModal(false)} className="text-slate-400 hover:text-slate-600">
                <X size={18} />
              </button>
            </div>

            {errorMsg && (
              <div className="p-3 bg-red-50 border border-red-200 text-red-700 text-xs rounded-lg mb-4 flex items-center gap-1.5">
                <AlertCircle size={14} className="shrink-0" />
                <span>{errorMsg}</span>
              </div>
            )}

            <form onSubmit={handleUploadFile} className="space-y-4">
              <div>
                <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                  Select CSV File <span className="text-red-500">*</span>
                </label>
                <input
                  type="file"
                  accept=".csv,.txt"
                  required
                  className="input-field text-xs py-2"
                  onChange={e => setUploadFile(e.target.files[0])}
                />
                <p className="text-[11px] text-slate-500 mt-1">File must contain student ID numbers in the first column.</p>
              </div>

              <div className="flex justify-end gap-2 pt-2">
                <button
                  type="button"
                  onClick={() => setShowUploadModal(false)}
                  className="btn-secondary text-xs"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={actionLoading || !uploadFile}
                  className="btn-dark text-xs"
                >
                  {actionLoading ? 'Uploading...' : 'Upload & Import'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};

export default DeanClassList;
