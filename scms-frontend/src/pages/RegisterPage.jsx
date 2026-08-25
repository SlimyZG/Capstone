import { useState, useEffect } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { ArrowRight } from 'lucide-react';
import api from '../api';

const RegisterPage = () => {
  const [formData, setFormData] = useState({
    name: '',
    student_id_number: '',
    email: '',
    password: '',
    password_confirmation: '',
    academic_unit_id: ''
  });
  const [units, setUnits] = useState([]);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const navigate = useNavigate();

  useEffect(() => {
    api.get('/academic-units').then(res => setUnits(res.data.academic_units));
  }, []);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    
    if (formData.student_id_number.length !== 8) {
      return setError('Student ID must be exactly 8 digits.');
    }

    try {
      await api.post('/register', formData);
      setSuccess('Registration successful! Redirecting to login...');
      setTimeout(() => navigate('/login'), 2000);
    } catch (err) {
      setError(err.response?.data?.message || 'Registration failed. Please check the form.');
    }
  };

  return (
    <div className="min-h-screen bg-[#f4f5f6] flex flex-col items-center justify-center p-4 font-sans">
      <div className="w-full max-w-[500px] bg-white p-8 sm:p-10 rounded-md shadow-sm border border-slate-200">
        
        {/* Top Branding */}
        <div className="flex flex-col items-center mb-8">
          <div className="mb-4 h-16 w-16">
            <img src="/logo.png" alt="UCLM SCMS Logo" className="h-full w-full object-contain" />
          </div>
          <h2 className="text-[24px] font-bold text-slate-900 tracking-tight">Create your account</h2>
          <p className="text-sm text-slate-600 mt-2 font-normal">Student ID is verified before registration.</p>
        </div>

        {error && <div className="bg-red-50 text-red-600 p-3 rounded-md text-sm mb-6 text-center">{error}</div>}
        {success && <div className="bg-emerald-50 text-emerald-600 p-3 rounded-md text-sm mb-6 text-center">{success}</div>}

        <form onSubmit={handleSubmit} className="space-y-4">
          <div className="space-y-1.5">
            <label className="block text-xs font-bold text-slate-800">Full Name</label>
            <input 
              type="text" 
              required 
              className="w-full px-3 py-2 bg-white border border-slate-300 rounded-md text-sm text-slate-900 focus:bg-white focus:ring-1 focus:ring-[#1e40af] focus:border-[#1e40af] transition-all placeholder:text-slate-400 font-normal outline-none" 
              value={formData.name} 
              onChange={e => setFormData({...formData, name: e.target.value})} 
            />
          </div>
          
          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-1.5">
              <label className="block text-xs font-bold text-slate-800">Student ID</label>
              <input 
                type="text" 
                required 
                maxLength="8" 
                pattern="\d{8}" 
                className="w-full px-3 py-2 bg-white border border-slate-300 rounded-md text-sm text-slate-900 focus:bg-white focus:ring-1 focus:ring-[#1e40af] focus:border-[#1e40af] transition-all placeholder:text-slate-400 font-normal outline-none" 
                value={formData.student_id_number} 
                onChange={e => setFormData({...formData, student_id_number: e.target.value})} 
              />
            </div>
            <div className="space-y-1.5">
              <label className="block text-xs font-bold text-slate-800">Academic Unit</label>
              <select 
                required 
                className="w-full px-3 py-2 bg-white border border-slate-300 rounded-md text-sm text-slate-900 focus:bg-white focus:ring-1 focus:ring-[#1e40af] focus:border-[#1e40af] transition-all font-normal outline-none appearance-none" 
                value={formData.academic_unit_id} 
                onChange={e => setFormData({...formData, academic_unit_id: e.target.value})}
              >
                <option value="" disabled hidden className="text-slate-400">e.g. College of Engineering</option>
                {units.map(u => <option key={u.id} value={u.id} className="text-slate-900">{u.name}</option>)}
              </select>
            </div>
          </div>

          <div className="space-y-1.5">
            <label className="block text-xs font-bold text-slate-800">Email Address</label>
            <input 
              type="email" 
              required 
              className="w-full px-3 py-2 bg-white border border-slate-300 rounded-md text-sm text-slate-900 focus:bg-white focus:ring-1 focus:ring-[#1e40af] focus:border-[#1e40af] transition-all placeholder:text-slate-400 font-normal outline-none" 
              value={formData.email} 
              onChange={e => setFormData({...formData, email: e.target.value})} 
            />
          </div>

          <div className="space-y-1.5">
            <label className="block text-xs font-bold text-slate-800">Password</label>
            <input 
              type="password" 
              required 
              className="w-full px-3 py-2 bg-white border border-slate-300 rounded-md text-sm text-slate-900 focus:bg-white focus:ring-1 focus:ring-[#1e40af] focus:border-[#1e40af] transition-all placeholder:text-slate-400 font-normal outline-none" 
              value={formData.password} 
              onChange={e => setFormData({...formData, password: e.target.value})} 
            />
          </div>

          <div className="space-y-1.5">
            <label className="block text-xs font-bold text-slate-800">Confirm Password</label>
            <input 
              type="password" 
              required 
              className="w-full px-3 py-2 bg-white border border-slate-300 rounded-md text-sm text-slate-900 focus:bg-white focus:ring-1 focus:ring-[#1e40af] focus:border-[#1e40af] transition-all placeholder:text-slate-400 font-normal outline-none" 
              value={formData.password_confirmation} 
              onChange={e => setFormData({...formData, password_confirmation: e.target.value})} 
            />
          </div>
          
          <button 
            type="submit" 
            className="w-full bg-[#20429f] hover:bg-[#1a3680] text-white font-medium text-sm py-2.5 rounded-md flex justify-center items-center gap-2 transition-colors mt-8"
          >
            Register <ArrowRight size={16} />
          </button>
        </form>

        <div className="text-center mt-8">
          <p className="text-xs text-slate-600 font-normal">
            Already have an account?{' '}
            <Link to="/login" className="text-[#1e40af] font-semibold hover:underline">
              Login
            </Link>
          </p>
        </div>
      </div>
    </div>
  );
};

export default RegisterPage;
