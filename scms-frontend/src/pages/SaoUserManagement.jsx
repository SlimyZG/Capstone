import { useState, useEffect, useMemo } from 'react';
import { Link } from 'react-router-dom';
import { 
  Users, 
  GraduationCap, 
  Building2, 
  ShieldCheck, 
  Search, 
  Filter, 
  Plus, 
  Edit3, 
  KeyRound, 
  Ban, 
  CheckCircle2, 
  AlertCircle, 
  X, 
  ArrowLeft,
  RefreshCw,
  Info
} from 'lucide-react';
import api from '../api';

const SaoUserManagement = () => {
  const [users, setUsers] = useState([]);
  const [stats, setStats] = useState({
    total_deans: 0,
    total_dept_staff: 0,
    total_active: 0,
    total_managed: 0,
    schema_supports_deactivation: false
  });
  const [units, setUnits] = useState([]);
  const [departments, setDepartments] = useState([]);
  const [loading, setLoading] = useState(true);

  // Filters & Search
  const [searchQuery, setSearchQuery] = useState('');
  const [roleFilter, setRoleFilter] = useState('');
  const [unitFilter, setUnitFilter] = useState('');
  const [deptFilter, setDeptFilter] = useState('');

  // Notifications
  const [successMessage, setSuccessMessage] = useState('');
  const [errorMessage, setErrorMessage] = useState('');

  // Modals state
  const [showCreateModal, setShowCreateModal] = useState(false);
  const [showEditModal, setShowEditModal] = useState(false);
  const [showDeactivateModal, setShowDeactivateModal] = useState(false);
  const [selectedUser, setSelectedUser] = useState(null);
  const [actionLoading, setActionLoading] = useState(false);

  // Form Data for Create / Edit
  const [formData, setFormData] = useState({
    name: '',
    email: '',
    password: '',
    role: 'dean',
    staff_assignment_type: 'unit', // 'unit' | 'department'
    academic_unit_id: '',
    dept_id: ''
  });

  useEffect(() => {
    fetchInitialData();
  }, []);

  const fetchInitialData = async () => {
    setLoading(true);
    setErrorMessage('');
    try {
      const [userRes, unitRes, deptRes] = await Promise.all([
        api.get('/sao/users'),
        api.get('/academic-units'),
        api.get('/departments')
      ]);

      setUsers(userRes.data.users || []);
      if (userRes.data.stats) {
        setStats(userRes.data.stats);
      }
      setUnits(unitRes.data.academic_units || []);
      setDepartments(deptRes.data.departments || []);
    } catch (err) {
      console.error('Failed to load user management data', err);
      setErrorMessage(err.response?.data?.message || 'Failed to load user records.');
    } finally {
      setLoading(false);
    }
  };

  const handleOpenCreate = () => {
    setFormData({
      name: '',
      email: '',
      password: '',
      role: 'dean',
      staff_assignment_type: 'unit',
      academic_unit_id: units[0]?.id ? String(units[0].id) : '',
      dept_id: ''
    });
    setErrorMessage('');
    setSuccessMessage('');
    setShowCreateModal(true);
  };

  const handleOpenEdit = (user) => {
    setSelectedUser(user);
    const isDept = Boolean(user.dept_id);
    setFormData({
      name: user.name || '',
      email: user.email || '',
      password: '', // blank unless resetting
      role: user.role,
      staff_assignment_type: isDept ? 'department' : 'unit',
      academic_unit_id: user.academic_unit_id ? String(user.academic_unit_id) : '',
      dept_id: user.dept_id ? String(user.dept_id) : ''
    });
    setErrorMessage('');
    setSuccessMessage('');
    setShowEditModal(true);
  };

  const handleOpenDeactivate = (user) => {
    setSelectedUser(user);
    setShowDeactivateModal(true);
  };

  const handleCreateSubmit = async (e) => {
    e.preventDefault();
    setActionLoading(true);
    setErrorMessage('');
    setSuccessMessage('');

    const payload = {
      name: formData.name.trim(),
      email: formData.email.trim(),
      password: formData.password,
      role: formData.role,
      academic_unit_id: formData.role === 'dean' 
        ? formData.academic_unit_id 
        : (formData.staff_assignment_type === 'unit' ? formData.academic_unit_id : null),
      dept_id: formData.role === 'dept_staff' && formData.staff_assignment_type === 'department'
        ? formData.dept_id
        : null
    };

    try {
      const res = await api.post('/sao/users', payload);
      setSuccessMessage(res.data.message || 'Account created successfully.');
      setShowCreateModal(false);
      fetchInitialData();
    } catch (err) {
      console.error('Account creation error', err);
      setErrorMessage(err.response?.data?.message || 'Failed to create account.');
    } finally {
      setActionLoading(false);
    }
  };

  const handleEditSubmit = async (e) => {
    e.preventDefault();
    if (!selectedUser) return;
    setActionLoading(true);
    setErrorMessage('');
    setSuccessMessage('');

    const payload = {
      name: formData.name.trim(),
      email: formData.email.trim(),
      role: formData.role,
      academic_unit_id: formData.role === 'dean' 
        ? formData.academic_unit_id 
        : (formData.staff_assignment_type === 'unit' ? formData.academic_unit_id : null),
      dept_id: formData.role === 'dept_staff' && formData.staff_assignment_type === 'department'
        ? formData.dept_id
        : null
    };

    if (formData.password) {
      payload.password = formData.password;
    }

    try {
      const res = await api.put(`/sao/users/${selectedUser.id}`, payload);
      setSuccessMessage(res.data.message || 'Account updated successfully.');
      setShowEditModal(false);
      fetchInitialData();
    } catch (err) {
      console.error('Account update error', err);
      setErrorMessage(err.response?.data?.message || 'Failed to update account.');
    } finally {
      setActionLoading(false);
    }
  };

  const handleConfirmDeactivate = async () => {
    if (!selectedUser) return;
    setActionLoading(true);
    try {
      const res = await api.post(`/sao/users/${selectedUser.id}/deactivate`);
      if (res.data.supported) {
        setSuccessMessage('Account status updated.');
      }
    } catch (err) {
      // Expected notice if schema does not support deactivation yet
      setErrorMessage(err.response?.data?.message || 'Account deactivation cannot be completed without schema support.');
    } finally {
      setActionLoading(false);
      setShowDeactivateModal(false);
    }
  };

  // Client-side filtering
  const filteredUsers = useMemo(() => {
    return users.filter(u => {
      // Role filter
      if (roleFilter && u.role !== roleFilter) return false;

      // Unit filter
      if (unitFilter && String(u.academic_unit_id) !== String(unitFilter)) return false;

      // Dept filter
      if (deptFilter && String(u.dept_id) !== String(deptFilter)) return false;

      // Search query (name or email)
      if (searchQuery.trim()) {
        const query = searchQuery.toLowerCase();
        const nameMatch = u.name?.toLowerCase().includes(query);
        const emailMatch = u.email?.toLowerCase().includes(query);
        if (!nameMatch && !emailMatch) return false;
      }

      return true;
    });
  }, [users, roleFilter, unitFilter, deptFilter, searchQuery]);

  return (
    <div className="space-y-6">
      {/* Breadcrumb & Header */}
      <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
          <div className="flex items-center gap-2 text-sm text-slate-500 mb-1">
            <Link to="/sao" className="hover:text-blue-600 flex items-center gap-1 transition-colors">
              <ArrowLeft size={14} /> Back to SAO Dashboard
            </Link>
            <span>/</span>
            <span className="text-slate-700 font-medium">User Management</span>
          </div>
          <div className="flex items-center gap-3">
            <h1 className="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">
              User Management
            </h1>
            <span className="badge bg-blue-50 text-blue-700 border border-blue-200">
              SAO Administrative Scope
            </span>
          </div>
          <p className="text-sm text-slate-600 mt-1">
            Provision and manage Academic Deans and Department Staff accounts, assignments, and credentials.
          </p>
        </div>

        <div className="flex items-center gap-3">
          <button
            onClick={fetchInitialData}
            title="Refresh user list"
            className="p-2.5 rounded-lg border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 transition-colors shadow-sm"
          >
            <RefreshCw size={16} className={loading ? 'animate-spin' : ''} />
          </button>
          <button
            onClick={handleOpenCreate}
            className="btn-primary inline-flex items-center gap-2"
          >
            <Plus size={16} />
            Provision Account
          </button>
        </div>
      </div>

      {/* Success & Error Banners */}
      {successMessage && (
        <div className="bg-emerald-50 border border-emerald-200 text-emerald-800 p-4 rounded-xl flex items-center justify-between shadow-sm">
          <div className="flex items-center gap-3">
            <CheckCircle2 size={20} className="text-emerald-600 flex-shrink-0" />
            <span className="text-sm font-medium">{successMessage}</span>
          </div>
          <button onClick={() => setSuccessMessage('')} className="text-emerald-600 hover:text-emerald-800">
            <X size={16} />
          </button>
        </div>
      )}

      {errorMessage && (
        <div className="bg-red-50 border border-red-200 text-red-800 p-4 rounded-xl flex items-center justify-between shadow-sm">
          <div className="flex items-center gap-3">
            <AlertCircle size={20} className="text-red-600 flex-shrink-0" />
            <span className="text-sm font-medium">{errorMessage}</span>
          </div>
          <button onClick={() => setErrorMessage('')} className="text-red-600 hover:text-red-800">
            <X size={16} />
          </button>
        </div>
      )}

      {/* Metric Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div className="card p-5 bg-white border-slate-200 shadow-sm flex items-center gap-4">
          <div className="w-12 h-12 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center font-bold">
            <GraduationCap size={24} />
          </div>
          <div>
            <div className="text-xs font-semibold text-slate-500 uppercase tracking-wider">Total Deans</div>
            <div className="text-2xl font-black text-slate-900">{stats.total_deans}</div>
            <div className="text-[11px] text-slate-500">1 per academic unit</div>
          </div>
        </div>

        <div className="card p-5 bg-white border-slate-200 shadow-sm flex items-center gap-4">
          <div className="w-12 h-12 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center font-bold">
            <Building2 size={24} />
          </div>
          <div>
            <div className="text-xs font-semibold text-slate-500 uppercase tracking-wider">Department Staff</div>
            <div className="text-2xl font-black text-slate-900">{stats.total_dept_staff}</div>
            <div className="text-[11px] text-slate-500">Unit & operational staff</div>
          </div>
        </div>

        <div className="card p-5 bg-white border-slate-200 shadow-sm flex items-center gap-4">
          <div className="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold">
            <CheckCircle2 size={24} />
          </div>
          <div>
            <div className="text-xs font-semibold text-slate-500 uppercase tracking-wider">Active Accounts</div>
            <div className="text-2xl font-black text-slate-900">{stats.total_active}</div>
            <div className="text-[11px] text-emerald-600 font-medium">100% operational</div>
          </div>
        </div>

        <div className="card p-5 bg-slate-50 border-dashed border-2 border-slate-200 shadow-sm flex items-center gap-4">
          <div className="w-12 h-12 rounded-xl bg-slate-100 text-slate-600 flex items-center justify-center font-bold">
            <ShieldCheck size={24} />
          </div>
          <div>
            <div className="text-xs font-semibold text-slate-500 uppercase tracking-wider">Preservation</div>
            <div className="text-xs font-bold text-slate-800 mt-0.5">Historical Safety</div>
            <div className="text-[11px] text-slate-500">Account deletion disabled</div>
          </div>
        </div>
      </div>

      {/* Filter and Search Bar */}
      <div className="card p-4 bg-white border-slate-200 shadow-sm space-y-3">
        <div className="grid grid-cols-1 md:grid-cols-4 gap-3">
          {/* Search */}
          <div className="relative md:col-span-1">
            <Search size={16} className="absolute left-3 top-3 text-slate-400" />
            <input
              type="text"
              placeholder="Search name or email..."
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              className="input-field pl-9 text-sm"
            />
          </div>

          {/* Role Filter */}
          <div>
            <select
              value={roleFilter}
              onChange={(e) => setRoleFilter(e.target.value)}
              className="input-field text-sm"
            >
              <option value="">All Managed Roles</option>
              <option value="dean">Academic Deans</option>
              <option value="dept_staff">Department Staff</option>
            </select>
          </div>

          {/* Academic Unit Filter */}
          <div>
            <select
              value={unitFilter}
              onChange={(e) => {
                setUnitFilter(e.target.value);
                if (e.target.value) setDeptFilter('');
              }}
              className="input-field text-sm"
            >
              <option value="">All Academic Units</option>
              {units.map((u) => (
                <option key={u.id} value={u.id}>{u.name}</option>
              ))}
            </select>
          </div>

          {/* Department Filter */}
          <div>
            <select
              value={deptFilter}
              onChange={(e) => {
                setDeptFilter(e.target.value);
                if (e.target.value) setUnitFilter('');
              }}
              className="input-field text-sm"
            >
              <option value="">All Operational Depts</option>
              {departments.map((d) => (
                <option key={d.id} value={d.id}>{d.name}</option>
              ))}
            </select>
          </div>
        </div>

        {(searchQuery || roleFilter || unitFilter || deptFilter) && (
          <div className="flex items-center justify-between text-xs text-slate-500 pt-2 border-t border-slate-100">
            <span>
              Showing {filteredUsers.length} of {users.length} accounts
            </span>
            <button
              onClick={() => {
                setSearchQuery('');
                setRoleFilter('');
                setUnitFilter('');
                setDeptFilter('');
              }}
              className="text-blue-600 hover:text-blue-800 font-medium"
            >
              Reset Filters
            </button>
          </div>
        )}
      </div>

      {/* Users Table */}
      <div className="card border-slate-200 overflow-hidden shadow-sm">
        <div className="overflow-x-auto">
          <table className="min-w-full divide-y divide-slate-200 text-left text-sm">
            <thead className="bg-slate-50 text-slate-600 font-semibold text-xs uppercase tracking-wider">
              <tr>
                <th scope="col" className="px-6 py-3.5">User</th>
                <th scope="col" className="px-6 py-3.5">Role</th>
                <th scope="col" className="px-6 py-3.5">Academic Unit</th>
                <th scope="col" className="px-6 py-3.5">Department</th>
                <th scope="col" className="px-6 py-3.5">Status</th>
                <th scope="col" className="px-6 py-3.5 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-200 bg-white">
              {loading ? (
                <tr>
                  <td colSpan={6} className="px-6 py-12 text-center text-slate-500">
                    <RefreshCw size={24} className="animate-spin mx-auto mb-2 text-blue-600" />
                    Loading user records...
                  </td>
                </tr>
              ) : filteredUsers.length === 0 ? (
                <tr>
                  <td colSpan={6} className="px-6 py-12 text-center text-slate-500">
                    <Users size={32} className="mx-auto mb-2 text-slate-300" />
                    <p className="font-semibold text-slate-700">No matching user accounts found</p>
                    <p className="text-xs text-slate-400 mt-1">Try adjusting your search query or filters.</p>
                  </td>
                </tr>
              ) : (
                filteredUsers.map((u) => {
                  const isDean = u.role === 'dean';
                  const isStaff = u.role === 'dept_staff';

                  return (
                    <tr key={u.id} className="hover:bg-slate-50/80 transition-colors">
                      {/* Name & Email */}
                      <td className="px-6 py-4">
                        <div className="font-semibold text-slate-900">{u.name}</div>
                        <div className="text-xs text-slate-500">{u.email}</div>
                      </td>

                      {/* Role Badge */}
                      <td className="px-6 py-4">
                        {isDean ? (
                          <span className="badge bg-blue-50 text-blue-700 border border-blue-200 font-semibold">
                            Academic Dean
                          </span>
                        ) : isStaff ? (
                          <span className="badge bg-blue-50 text-blue-700 border border-blue-200 font-semibold">
                            Dept Staff
                          </span>
                        ) : (
                          <span className="badge bg-slate-100 text-slate-700 uppercase">
                            {u.role}
                          </span>
                        )}
                      </td>

                      {/* Academic Unit */}
                      <td className="px-6 py-4 text-slate-700">
                        {u.academic_unit?.name || <span className="text-slate-400 italic">None</span>}
                      </td>

                      {/* Department */}
                      <td className="px-6 py-4 text-slate-700">
                        {u.department?.name || <span className="text-slate-400 italic">None</span>}
                      </td>

                      {/* Status */}
                      <td className="px-6 py-4">
                        <span className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200">
                          <span className="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                          Active
                        </span>
                      </td>

                      {/* Actions */}
                      <td className="px-6 py-4 text-right">
                        <div className="inline-flex items-center gap-2">
                          <button
                            onClick={() => handleOpenEdit(u)}
                            className="p-1.5 rounded text-blue-600 hover:text-blue-800 hover:bg-blue-50 transition-colors"
                            title="Edit account details or reset password"
                          >
                            <Edit3 size={16} />
                          </button>
                          <button
                            onClick={() => handleOpenDeactivate(u)}
                            className="p-1.5 rounded text-slate-400 hover:text-amber-600 hover:bg-amber-50 transition-colors"
                            title="Account deactivation inspection"
                          >
                            <Ban size={16} />
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
      </div>

      {/* ───────────────────────────────────────────────────────────── */}
      {/* CREATE ACCOUNT MODAL */}
      {/* ───────────────────────────────────────────────────────────── */}
      {showCreateModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-xs">
          <div className="bg-white rounded-2xl shadow-xl border border-slate-200 max-w-lg w-full overflow-hidden animate-in fade-in duration-150">
            <div className="p-5 border-b border-slate-100 flex items-center justify-between">
              <div>
                <h3 className="text-lg font-bold text-slate-900">Provision New Account</h3>
                <p className="text-xs text-slate-500">Create an Academic Dean or Department Staff account</p>
              </div>
              <button onClick={() => setShowCreateModal(false)} className="text-slate-400 hover:text-slate-600">
                <X size={18} />
              </button>
            </div>

            <form onSubmit={handleCreateSubmit} className="p-6 space-y-4">
              {/* Role Selection */}
              <div>
                <label className="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
                  Account Role *
                </label>
                <div className="grid grid-cols-2 gap-3">
                  <button
                    type="button"
                    onClick={() => setFormData({ ...formData, role: 'dean' })}
                    className={`p-3 rounded-xl border text-left transition-all ${
                      formData.role === 'dean'
                        ? 'border-blue-600 bg-blue-50/50 ring-2 ring-blue-600/20'
                        : 'border-slate-200 hover:border-slate-300'
                    }`}
                  >
                    <div className="font-bold text-sm text-slate-900">Academic Dean</div>
                    <div className="text-xs text-slate-500 mt-0.5">Assigned to an Academic Unit</div>
                  </button>
                  <button
                    type="button"
                    onClick={() => setFormData({ ...formData, role: 'dept_staff' })}
                    className={`p-3 rounded-xl border text-left transition-all ${
                      formData.role === 'dept_staff'
                        ? 'border-blue-600 bg-blue-50/50 ring-2 ring-blue-600/20'
                        : 'border-slate-200 hover:border-slate-300'
                    }`}
                  >
                    <div className="font-bold text-sm text-slate-900">Department Staff</div>
                    <div className="text-xs text-slate-500 mt-0.5">Assigned to Unit or Dept</div>
                  </button>
                </div>
              </div>

              {/* Name */}
              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1">
                  Full Name *
                </label>
                <input
                  type="text"
                  required
                  placeholder="e.g. Dr. Jane Doe"
                  value={formData.name}
                  onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                  className="input-field text-sm"
                />
              </div>

              {/* Email */}
              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1">
                  Email Address *
                </label>
                <input
                  type="email"
                  required
                  placeholder="name@addu.edu.ph"
                  value={formData.email}
                  onChange={(e) => setFormData({ ...formData, email: e.target.value })}
                  className="input-field text-sm"
                />
              </div>

              {/* Password */}
              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1">
                  Initial Password (minimum 8 characters) *
                </label>
                <input
                  type="password"
                  required
                  minLength={8}
                  placeholder="••••••••"
                  value={formData.password}
                  onChange={(e) => setFormData({ ...formData, password: e.target.value })}
                  className="input-field text-sm"
                />
              </div>

              {/* Role specific assignment */}
              {formData.role === 'dean' ? (
                <div>
                  <label className="block text-xs font-semibold text-slate-700 mb-1">
                    Academic Unit * (1 Dean per Unit Enforced)
                  </label>
                  <select
                    required
                    value={formData.academic_unit_id}
                    onChange={(e) => setFormData({ ...formData, academic_unit_id: e.target.value })}
                    className="input-field text-sm"
                  >
                    <option value="">Select Academic Unit...</option>
                    {units.map((u) => (
                      <option key={u.id} value={u.id}>{u.name}</option>
                    ))}
                  </select>
                </div>
              ) : (
                <div className="p-3 bg-slate-50 rounded-xl border border-slate-200 space-y-3">
                  <label className="block text-xs font-semibold text-slate-700">
                    Staff Assignment Target *
                  </label>
                  <div className="flex gap-4 text-xs font-medium">
                    <label className="flex items-center gap-1.5 cursor-pointer">
                      <input
                        type="radio"
                        checked={formData.staff_assignment_type === 'unit'}
                        onChange={() => setFormData({ ...formData, staff_assignment_type: 'unit', dept_id: '' })}
                        className="text-blue-600"
                      />
                      Academic Unit Staff
                    </label>
                    <label className="flex items-center gap-1.5 cursor-pointer">
                      <input
                        type="radio"
                        checked={formData.staff_assignment_type === 'department'}
                        onChange={() => setFormData({ ...formData, staff_assignment_type: 'department', academic_unit_id: '' })}
                        className="text-blue-600"
                      />
                      Operational Department Staff
                    </label>
                  </div>

                  {formData.staff_assignment_type === 'unit' ? (
                    <div>
                      <select
                        required
                        value={formData.academic_unit_id}
                        onChange={(e) => setFormData({ ...formData, academic_unit_id: e.target.value })}
                        className="input-field text-sm bg-white"
                      >
                        <option value="">Select Academic Unit...</option>
                        {units.map((u) => (
                          <option key={u.id} value={u.id}>{u.name}</option>
                        ))}
                      </select>
                    </div>
                  ) : (
                    <div>
                      <select
                        required
                        value={formData.dept_id}
                        onChange={(e) => setFormData({ ...formData, dept_id: e.target.value })}
                        className="input-field text-sm bg-white"
                      >
                        <option value="">Select Operational Department...</option>
                        {departments.map((d) => (
                          <option key={d.id} value={d.id}>{d.name}</option>
                        ))}
                      </select>
                    </div>
                  )}
                </div>
              )}

              {/* Action Buttons */}
              <div className="pt-3 border-t border-slate-100 flex items-center justify-end gap-3">
                <button
                  type="button"
                  onClick={() => setShowCreateModal(false)}
                  className="btn-secondary text-sm"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={actionLoading}
                  className="btn-primary text-sm inline-flex items-center gap-2"
                >
                  {actionLoading ? (
                    <>
                      <RefreshCw size={14} className="animate-spin" />
                      Provisioning...
                    </>
                  ) : (
                    'Create Account'
                  )}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* ───────────────────────────────────────────────────────────── */}
      {/* EDIT ACCOUNT MODAL */}
      {/* ───────────────────────────────────────────────────────────── */}
      {showEditModal && selectedUser && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-xs">
          <div className="bg-white rounded-2xl shadow-xl border border-slate-200 max-w-lg w-full overflow-hidden animate-in fade-in duration-150">
            <div className="p-5 border-b border-slate-100 flex items-center justify-between">
              <div>
                <h3 className="text-lg font-bold text-slate-900">Edit Account Details</h3>
                <p className="text-xs text-slate-500">Update assignments or reset credentials for {selectedUser.name}</p>
              </div>
              <button onClick={() => setShowEditModal(false)} className="text-slate-400 hover:text-slate-600">
                <X size={18} />
              </button>
            </div>

            <form onSubmit={handleEditSubmit} className="p-6 space-y-4">
              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1">
                  Full Name *
                </label>
                <input
                  type="text"
                  required
                  value={formData.name}
                  onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                  className="input-field text-sm"
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1">
                  Email Address *
                </label>
                <input
                  type="email"
                  required
                  value={formData.email}
                  onChange={(e) => setFormData({ ...formData, email: e.target.value })}
                  className="input-field text-sm"
                />
              </div>

              {/* Password Reset (Optional) */}
              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1 flex items-center gap-1.5">
                  <KeyRound size={14} className="text-amber-500" />
                  Reset Password (leave empty to keep current password)
                </label>
                <input
                  type="password"
                  minLength={8}
                  placeholder="New password (optional)"
                  value={formData.password}
                  onChange={(e) => setFormData({ ...formData, password: e.target.value })}
                  className="input-field text-sm"
                />
              </div>

              {/* Assignments */}
              {formData.role === 'dean' ? (
                <div>
                  <label className="block text-xs font-semibold text-slate-700 mb-1">
                    Academic Unit * (1 Dean per Unit Enforced)
                  </label>
                  <select
                    required
                    value={formData.academic_unit_id}
                    onChange={(e) => setFormData({ ...formData, academic_unit_id: e.target.value })}
                    className="input-field text-sm"
                  >
                    <option value="">Select Academic Unit...</option>
                    {units.map((u) => (
                      <option key={u.id} value={u.id}>{u.name}</option>
                    ))}
                  </select>
                </div>
              ) : (
                <div className="p-3 bg-slate-50 rounded-xl border border-slate-200 space-y-3">
                  <label className="block text-xs font-semibold text-slate-700">
                    Department Staff Assignment Target
                  </label>
                  <div className="flex gap-4 text-xs font-medium">
                    <label className="flex items-center gap-1.5 cursor-pointer">
                      <input
                        type="radio"
                        checked={formData.staff_assignment_type === 'unit'}
                        onChange={() => setFormData({ ...formData, staff_assignment_type: 'unit', dept_id: '' })}
                        className="text-blue-600"
                      />
                      Academic Unit
                    </label>
                    <label className="flex items-center gap-1.5 cursor-pointer">
                      <input
                        type="radio"
                        checked={formData.staff_assignment_type === 'department'}
                        onChange={() => setFormData({ ...formData, staff_assignment_type: 'department', academic_unit_id: '' })}
                        className="text-blue-600"
                      />
                      Operational Department
                    </label>
                  </div>

                  {formData.staff_assignment_type === 'unit' ? (
                    <div>
                      <select
                        required
                        value={formData.academic_unit_id}
                        onChange={(e) => setFormData({ ...formData, academic_unit_id: e.target.value })}
                        className="input-field text-sm bg-white"
                      >
                        <option value="">Select Academic Unit...</option>
                        {units.map((u) => (
                          <option key={u.id} value={u.id}>{u.name}</option>
                        ))}
                      </select>
                    </div>
                  ) : (
                    <div>
                      <select
                        required
                        value={formData.dept_id}
                        onChange={(e) => setFormData({ ...formData, dept_id: e.target.value })}
                        className="input-field text-sm bg-white"
                      >
                        <option value="">Select Operational Department...</option>
                        {departments.map((d) => (
                          <option key={d.id} value={d.id}>{d.name}</option>
                        ))}
                      </select>
                    </div>
                  )}
                </div>
              )}

              {/* Action Buttons */}
              <div className="pt-3 border-t border-slate-100 flex items-center justify-end gap-3">
                <button
                  type="button"
                  onClick={() => setShowEditModal(false)}
                  className="btn-secondary text-sm"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={actionLoading}
                  className="btn-primary text-sm inline-flex items-center gap-2"
                >
                  {actionLoading ? (
                    <>
                      <RefreshCw size={14} className="animate-spin" />
                      Saving Changes...
                    </>
                  ) : (
                    'Save Changes'
                  )}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* ───────────────────────────────────────────────────────────── */}
      {/* DEACTIVATION INSPECTION / NOTICE MODAL */}
      {/* ───────────────────────────────────────────────────────────── */}
      {showDeactivateModal && selectedUser && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-xs">
          <div className="bg-white rounded-2xl shadow-xl border border-slate-200 max-w-md w-full overflow-hidden animate-in fade-in duration-150">
            <div className="p-5 border-b border-slate-100 flex items-center gap-3">
              <div className="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center flex-shrink-0">
                <Info size={20} />
              </div>
              <div>
                <h3 className="text-base font-bold text-slate-900">Account Status Notice</h3>
                <p className="text-xs text-slate-500">{selectedUser.name} ({selectedUser.email})</p>
              </div>
            </div>

            <div className="p-6 space-y-4 text-sm text-slate-600">
              <p>
                In accordance with SCMS Phase 3 security guidelines, <strong>user accounts and their historical concern routing logs cannot be deleted</strong>.
              </p>
              <div className="p-3 bg-amber-50 rounded-xl border border-amber-200 text-xs text-amber-900 space-y-1.5">
                <div className="font-bold flex items-center gap-1.5">
                  <AlertCircle size={14} className="text-amber-700" />
                  Database Schema Status:
                </div>
                <p>
                  The current database table <code>users</code> does not yet include an <code>is_active</code> status column. To prevent irreversible record deletion and ensure historical concern preservation, soft deactivation requires an approved migration.
                </p>
              </div>
              <p className="text-xs text-slate-500">
                No changes will be made to this account. Historical integrity is maintained.
              </p>
            </div>

            <div className="p-4 bg-slate-50 border-t border-slate-100 flex justify-end">
              <button
                type="button"
                onClick={() => setShowDeactivateModal(false)}
                className="btn-secondary text-xs"
              >
                Close Notice
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default SaoUserManagement;
