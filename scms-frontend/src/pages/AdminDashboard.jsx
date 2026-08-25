import { useState, useEffect } from 'react';
import api from '../api';

const AdminDashboard = () => {
  const [users, setUsers] = useState([]);
  const [units, setUnits] = useState([]);
  const [departments, setDepartments] = useState([]);
  
  const [formData, setFormData] = useState({
    name: '',
    email: '',
    password: '',
    role: 'dept_staff',
    staff_assignment_type: 'department', // purely for UI toggle
    academic_unit_id: '',
    dept_id: ''
  });
  
  const [editMode, setEditMode] = useState(false);
  const [editingUserId, setEditingUserId] = useState(null);
  
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  
  const [sortField, setSortField] = useState('name');
  const [sortOrder, setSortOrder] = useState('asc');

  useEffect(() => {
    fetchData();
  }, []);

  const fetchData = async () => {
    try {
      const [uRes, unitRes, deptRes] = await Promise.all([
        api.get('/admin/users'),
        api.get('/academic-units'),
        api.get('/departments')
      ]);
      setUsers(uRes.data.users);
      setUnits(unitRes.data.academic_units);
      setDepartments(deptRes.data.departments);
    } catch (err) {
      console.error(err);
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    setSuccess('');

    // Ensure we only send the relevant ID for dept_staff
    const payload = { ...formData };
    if (payload.role === 'dept_staff') {
      if (payload.staff_assignment_type === 'department') {
        payload.academic_unit_id = '';
      } else {
        payload.dept_id = '';
      }
    }
    delete payload.staff_assignment_type;

    try {
      if (editMode) {
        if (!payload.password) delete payload.password; // Don't send empty password
        await api.put(`/admin/users/${editingUserId}`, payload);
        setSuccess('Account updated successfully.');
      } else {
        await api.post('/admin/create-account', payload);
        setSuccess('Account created successfully.');
      }
      
      handleCancelEdit();
      fetchData(); // refresh list
    } catch (err) {
      setError(err.response?.data?.message || (editMode ? 'Failed to update account.' : 'Failed to create account.'));
    }
  };

  const handleEditClick = (user) => {
    setEditMode(true);
    setEditingUserId(user.id);
    
    let assignmentType = 'department';
    if (user.role === 'dept_staff' && user.academic_unit_id && !user.dept_id) {
      assignmentType = 'unit';
    }

    setFormData({
      name: user.name,
      email: user.email,
      password: '',
      role: user.role,
      staff_assignment_type: assignmentType,
      academic_unit_id: user.academic_unit_id || '',
      dept_id: user.dept_id || ''
    });
    
    // Scroll to top
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  const handleCancelEdit = () => {
    setEditMode(false);
    setEditingUserId(null);
    setFormData({ name: '', email: '', password: '', role: 'dept_staff', staff_assignment_type: 'department', academic_unit_id: '', dept_id: '' });
  };

  const handleDelete = async (id) => {
    if (!window.confirm('Are you sure you want to delete this account?')) return;
    
    setError('');
    setSuccess('');
    try {
      await api.delete(`/admin/users/${id}`);
      setSuccess('Account deleted successfully.');
      fetchData();
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to delete account.');
    }
  };

  const getAssignment = (u) => {
    if (u.role === 'student' || u.role === 'dean') return u.academic_unit?.name || '';
    if (u.role === 'dept_staff') return u.department?.name || u.academic_unit?.name || '';
    if (u.role === 'admin') return 'System Wide';
    return '';
  };

  const sortedUsers = [...users].sort((a, b) => {
    let aVal = '';
    let bVal = '';
    
    if (sortField === 'name') {
      aVal = a.name.toLowerCase();
      bVal = b.name.toLowerCase();
    } else if (sortField === 'role') {
      aVal = a.role.toLowerCase();
      bVal = b.role.toLowerCase();
    } else if (sortField === 'assignment') {
      aVal = getAssignment(a).toLowerCase();
      bVal = getAssignment(b).toLowerCase();
    }

    if (aVal < bVal) return sortOrder === 'asc' ? -1 : 1;
    if (aVal > bVal) return sortOrder === 'asc' ? 1 : -1;
    return 0;
  });

  const handleSort = (field) => {
    if (sortField === field) {
      setSortOrder(sortOrder === 'asc' ? 'desc' : 'asc');
    } else {
      setSortField(field);
      setSortOrder('asc');
    }
  };

  return (
    <div className="grid grid-cols-1 xl:grid-cols-4 gap-8">
      <div className="xl:col-span-1">
        <div className="card p-6 sticky top-24">
          <h2 className="text-lg font-bold text-slate-900 mb-4">{editMode ? 'Edit Account' : 'Provision Account'}</h2>
          {error && <div className="bg-red-50 text-red-600 p-3 rounded-lg text-sm mb-4">{error}</div>}
          {success && <div className="bg-emerald-50 text-emerald-600 p-3 rounded-lg text-sm mb-4">{success}</div>}
          
          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <label className="block text-sm font-medium text-slate-700 mb-1">Role</label>
              <select className="input-field" value={formData.role} onChange={e => setFormData({...formData, role: e.target.value, academic_unit_id: '', dept_id: '', staff_assignment_type: 'department'})}>
                <option value="dept_staff">Department Staff</option>
                <option value="dean">Academic Dean</option>
                {editMode && <option value="student">Student</option>}
              </select>
            </div>
            
            <div>
              <label className="block text-sm font-medium text-slate-700 mb-1">Full Name</label>
              <input required type="text" className="input-field" value={formData.name} onChange={e => setFormData({...formData, name: e.target.value})} />
            </div>
            <div>
              <label className="block text-sm font-medium text-slate-700 mb-1">Email</label>
              <input required type="email" className="input-field" value={formData.email} onChange={e => setFormData({...formData, email: e.target.value})} />
            </div>
            <div>
              <label className="block text-sm font-medium text-slate-700 mb-1">{editMode ? 'New Password (Leave blank to keep)' : 'Temporary Password'}</label>
              <input required={!editMode} type="password" minLength="8" className="input-field" value={formData.password} onChange={e => setFormData({...formData, password: e.target.value})} />
            </div>

            {formData.role === 'dept_staff' && (
              <div className="space-y-3 p-3 bg-slate-50 border border-slate-200 rounded-lg">
                <label className="block text-sm font-medium text-slate-700">Assign Staff To:</label>
                <div className="flex space-x-4 mb-2">
                  <label className="flex items-center text-sm">
                    <input type="radio" checked={formData.staff_assignment_type === 'department'} onChange={() => setFormData({...formData, staff_assignment_type: 'department', academic_unit_id: ''})} className="mr-2 text-blue-600" />
                    Operational Dept
                  </label>
                  <label className="flex items-center text-sm">
                    <input type="radio" checked={formData.staff_assignment_type === 'unit'} onChange={() => setFormData({...formData, staff_assignment_type: 'unit', dept_id: ''})} className="mr-2 text-blue-600" />
                    Academic Unit
                  </label>
                </div>

                {formData.staff_assignment_type === 'department' ? (
                  <select required className="input-field bg-white" value={formData.dept_id} onChange={e => setFormData({...formData, dept_id: e.target.value})}>
                    <option value="">Select Operational Department...</option>
                    {departments.map(d => <option key={d.id} value={d.id}>{d.name}</option>)}
                  </select>
                ) : (
                  <select required className="input-field bg-white" value={formData.academic_unit_id} onChange={e => setFormData({...formData, academic_unit_id: e.target.value})}>
                    <option value="">Select Academic Unit...</option>
                    {units.map(u => <option key={u.id} value={u.id}>{u.name}</option>)}
                  </select>
                )}
              </div>
            )}

            {(formData.role === 'dean' || formData.role === 'student') && (
              <div>
                <label className="block text-sm font-medium text-slate-700 mb-1">Assign to Academic Unit</label>
                <select required className="input-field" value={formData.academic_unit_id} onChange={e => setFormData({...formData, academic_unit_id: e.target.value})}>
                  <option value="">Select Academic Unit...</option>
                  {units.map(u => <option key={u.id} value={u.id}>{u.name}</option>)}
                </select>
              </div>
            )}

            <div className="flex space-x-2 mt-2">
              <button type="submit" className="btn-primary flex-1">{editMode ? 'Update Account' : 'Create Account'}</button>
              {editMode && (
                <button type="button" onClick={handleCancelEdit} className="btn-secondary flex-1">Cancel</button>
              )}
            </div>
          </form>
        </div>
      </div>

      <div className="xl:col-span-3">
        <div className="card overflow-hidden">
          <div className="p-4 border-b border-slate-200 bg-slate-50 flex justify-between items-center">
            <h2 className="text-lg font-bold text-slate-900">System Users</h2>
            <span className="text-sm text-slate-500 font-medium">Total: {users.length}</span>
          </div>
          <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-slate-200">
              <thead className="bg-slate-50">
                <tr>
                  <th 
                    className="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase cursor-pointer hover:bg-slate-100"
                    onClick={() => handleSort('name')}
                  >
                    Name / Email {sortField === 'name' && (sortOrder === 'asc' ? '↑' : '↓')}
                  </th>
                  <th 
                    className="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase cursor-pointer hover:bg-slate-100"
                    onClick={() => handleSort('role')}
                  >
                    Role {sortField === 'role' && (sortOrder === 'asc' ? '↑' : '↓')}
                  </th>
                  <th 
                    className="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase cursor-pointer hover:bg-slate-100"
                    onClick={() => handleSort('assignment')}
                  >
                    Assignment {sortField === 'assignment' && (sortOrder === 'asc' ? '↑' : '↓')}
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Student ID</th>
                  <th className="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase">Actions</th>
                </tr>
              </thead>
              <tbody className="bg-white divide-y divide-slate-200">
                {sortedUsers.map(u => (
                  <tr key={u.id}>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <div className="text-sm font-medium text-slate-900">{u.name}</div>
                      <div className="text-sm text-slate-500">{u.email}</div>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <span className={`px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${
                        u.role === 'admin' ? 'bg-red-100 text-red-800' :
                        u.role === 'dean' ? 'bg-purple-100 text-purple-800' :
                        u.role === 'dept_staff' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'
                      }`}>
                        {u.role.replace('_', ' ')}
                      </span>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-slate-500">
                      {getAssignment(u) || '-'}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-slate-500">
                      {u.student_id_number || '-'}
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium flex justify-end space-x-3">
                      {u.role !== 'admin' && (
                        <>
                          <button onClick={() => handleEditClick(u)} className="text-blue-600 hover:text-blue-900">
                            Edit
                          </button>
                          <button onClick={() => handleDelete(u.id)} className="text-red-600 hover:text-red-900">
                            Delete
                          </button>
                        </>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  );
};

export default AdminDashboard;
