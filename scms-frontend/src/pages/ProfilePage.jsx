import { useState } from 'react';
import { useAuth } from '../contexts/AuthContext';
import api from '../api';

const ProfilePage = () => {
  const { user, setUser } = useAuth();
  
  const [formData, setFormData] = useState({
    name: user?.name || '',
    email: user?.email || '',
    password: '',
    password_confirmation: ''
  });
  
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');

  const handleChange = (e) => {
    setFormData({ ...formData, [e.target.name]: e.target.value });
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);
    setError('');
    setSuccess('');

    try {
      const res = await api.put('/profile', formData);
      setSuccess('Profile updated successfully.');
      setFormData(prev => ({ ...prev, password: '', password_confirmation: '' }));
      if (res.data?.user) {
        setUser(res.data.user);
      }
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to update profile.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="max-w-2xl mx-auto mt-8">
      <div className="bg-white p-8 rounded-xl shadow-sm border border-slate-200">
        <h1 className="text-2xl font-bold text-slate-900 mb-6">My Profile</h1>
        
        {error && <div className="mb-4 p-3 bg-red-50 text-red-700 rounded-md text-sm">{error}</div>}
        {success && <div className="mb-4 p-3 bg-green-50 text-green-700 rounded-md text-sm">{success}</div>}

        <div className="mb-8 p-4 bg-slate-50 rounded-lg border border-slate-100">
          <h2 className="text-sm font-semibold text-slate-700 mb-2">Account Details</h2>
          <div className="grid grid-cols-2 gap-4 text-sm">
            <div>
              <span className="block text-slate-500">Role</span>
              <span className="font-medium text-slate-900 uppercase">{user?.role}</span>
            </div>
            {user?.student_id_number && (
              <div>
                <span className="block text-slate-500">Student ID</span>
                <span className="font-medium text-slate-900">{user.student_id_number}</span>
              </div>
            )}
            {user?.academic_unit && (
              <div>
                <span className="block text-slate-500">Academic Unit</span>
                <span className="font-medium text-slate-900">{user.academic_unit.name}</span>
              </div>
            )}
            {user?.department && (
              <div>
                <span className="block text-slate-500">Department</span>
                <span className="font-medium text-slate-900">{user.department.name}</span>
              </div>
            )}
          </div>
        </div>

        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className="block text-sm font-medium text-slate-700">Full Name</label>
            <input 
              type="text" 
              name="name" 
              value={formData.name} 
              onChange={handleChange}
              className="input-field mt-1" 
              required
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-slate-700">Email Address</label>
            <input 
              type="email" 
              name="email" 
              value={formData.email} 
              onChange={handleChange}
              className="input-field mt-1" 
              required
            />
          </div>
          
          <div className="pt-4 border-t border-slate-200">
            <h2 className="text-sm font-semibold text-slate-700 mb-4">Change Password (Optional)</h2>
            <div className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-slate-700">New Password</label>
                <input 
                  type="password" 
                  name="password" 
                  value={formData.password} 
                  onChange={handleChange}
                  className="input-field mt-1" 
                  minLength="8"
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-slate-700">Confirm New Password</label>
                <input 
                  type="password" 
                  name="password_confirmation" 
                  value={formData.password_confirmation} 
                  onChange={handleChange}
                  className="input-field mt-1" 
                />
              </div>
            </div>
          </div>

          <button 
            type="submit" 
            disabled={loading} 
            className="btn-primary w-full mt-6"
          >
            {loading ? 'Saving...' : 'Update Profile'}
          </button>
        </form>
      </div>
    </div>
  );
};

export default ProfilePage;
