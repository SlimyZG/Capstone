import { useState, useEffect } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import { Eye, EyeOff } from 'lucide-react';
import api from '../api';

const LoginPage = () => {
  const [loginValue, setLoginValue] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [isForgotPassword, setIsForgotPassword] = useState(false);
  const [forgotEmail, setForgotEmail] = useState('');
  const [isResetting, setIsResetting] = useState(false);
  const { user, login } = useAuth();
  const navigate = useNavigate();

  useEffect(() => {
    const token = localStorage.getItem('token');
    if (user && token) {
      if (user.role === 'student') navigate('/student', { replace: true });
      else if (user.role === 'admin') navigate('/admin', { replace: true });
      else if (user.role === 'sao') navigate('/sao', { replace: true });
      else if (user.role === 'dean') navigate('/dean', { replace: true });
      else if (user.role === 'dept_staff') navigate('/dept-staff', { replace: true });
      else navigate('/', { replace: true });
    }
  }, [user, navigate]);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    setSuccess('');
    try {
      const data = await login({ login: loginValue, password });
      if (data.user.role === 'student') navigate('/student');
      else if (data.user.role === 'admin') navigate('/admin');
      else if (data.user.role === 'sao') navigate('/sao');
      else if (data.user.role === 'dean') navigate('/dean');
      else if (data.user.role === 'dept_staff') navigate('/dept-staff');
      else navigate('/');
    } catch (err) {
      setError(err.response?.data?.errors?.login?.[0] || 'Login failed. Please check your credentials.');
    }
  };

  const handleForgotPassword = async (e) => {
    e.preventDefault();
    setError('');
    setSuccess('');
    setIsResetting(true);
    try {
      const response = await api.post('/forgot-password', { email: forgotEmail });
      setSuccess(response.data.message);
      setForgotEmail('');
    } catch (err) {
      setError(err.response?.data?.message || 'Failed to request password reset.');
    } finally {
      setIsResetting(false);
    }
  };

  return (
    <div className="min-h-screen bg-[#f4f5f6] flex flex-col items-center justify-center p-4 font-sans">
      {/* Main Login Card */}
      <div className="w-full max-w-[420px] bg-white rounded-md shadow-sm border border-slate-200 p-8">

        {/* Top Branding inside Card */}
        <div className="flex flex-col items-center mb-6">
          <div className="mb-4 h-16 w-16">
            <img src="/logo.png" alt="UCLM SCMS Logo" className="h-full w-full object-contain" />
          </div>
          <h1 className="text-[22px] font-bold text-[#1e3a8a] tracking-tight">UCLM SCMS</h1>
          <p className="text-sm text-slate-500 mt-1 font-normal">Student Portal Login</p>
        </div>

        {error && (
          <div className="bg-red-50 border border-red-100 text-red-600 p-3 rounded-lg text-sm mb-6 text-center">
            {error}
          </div>
        )}
        {success && (
          <div className="bg-emerald-50 border border-emerald-100 text-emerald-600 p-3 rounded-lg text-sm mb-6 text-center">
            {success}
          </div>
        )}

        {isForgotPassword ? (
          <form onSubmit={handleForgotPassword} className="space-y-4">
            <div className="text-center mb-6">
              <h2 className="text-lg font-bold text-slate-900">Reset Password</h2>
              <p className="text-sm text-slate-500 mt-1">Enter your registered email address.</p>
            </div>
            <div className="space-y-1.5">
              <label className="block text-xs font-bold text-slate-800">Email Address</label>
              <input
                type="email"
                required
                className="w-full px-3 py-2 bg-white border border-slate-300 rounded-md text-sm text-slate-900 focus:bg-white focus:ring-1 focus:ring-[#1e40af] focus:border-[#1e40af] transition-all placeholder:text-slate-400 font-normal outline-none"
                value={forgotEmail}
                onChange={(e) => setForgotEmail(e.target.value)}
                placeholder="Enter your email"
              />
            </div>
            
            <button 
              type="submit" 
              disabled={isResetting}
              className="w-full bg-[#1e40af] hover:bg-[#1e3a8a] text-white font-medium text-sm py-2.5 rounded-md flex justify-center items-center gap-2 transition-colors shadow-sm mt-2 disabled:opacity-70"
            >
              {isResetting ? 'Sending...' : 'Send Reset Link'}
            </button>
            <button 
              type="button" 
              onClick={() => { setIsForgotPassword(false); setError(''); setSuccess(''); }}
              className="w-full text-sm font-semibold text-slate-500 hover:text-slate-700 mt-4"
            >
              Back to Sign In
            </button>
          </form>
        ) : (
          <form onSubmit={handleSubmit} className="space-y-4">
            {/* Identifier Input */}
            <div className="space-y-1.5">
              <label className="block text-xs font-bold text-slate-800">Student ID or Email</label>
              <input
                type="text"
                required
                className="w-full px-3 py-2 bg-white border border-slate-300 rounded-md text-sm text-slate-900 focus:bg-white focus:ring-1 focus:ring-[#1e40af] focus:border-[#1e40af] transition-all placeholder:text-slate-400 font-normal outline-none"
                value={loginValue}
                onChange={(e) => setLoginValue(e.target.value)}
                placeholder="Enter your ID or email"
              />
            </div>

            {/* Password Input */}
            <div className="space-y-1.5">
              <div className="flex justify-between items-center">
                <label className="block text-xs font-bold text-slate-800">Password</label>
                <button 
                  type="button" 
                  onClick={() => { setIsForgotPassword(true); setError(''); setSuccess(''); }}
                  className="text-xs font-semibold text-[#1e40af] hover:text-[#1e3a8a]"
                >
                  Forgot?
                </button>
              </div>
              <div className="relative">
                <input
                  type={showPassword ? "text" : "password"}
                  required
                  className="w-full pl-3 pr-10 py-2 bg-white border border-slate-300 rounded-md text-sm text-slate-900 focus:bg-white focus:ring-1 focus:ring-[#1e40af] focus:border-[#1e40af] transition-all placeholder:text-slate-400 font-normal outline-none"
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  placeholder="Enter your password"
                />
                <button
                  type="button"
                  onClick={() => setShowPassword(!showPassword)}
                  className="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600 focus:outline-none"
                >
                  {showPassword ? <EyeOff size={16} /> : <Eye size={16} />}
                </button>
              </div>
            </div>

            {/* Submit Button */}
            <button
              type="submit"
              className="w-full bg-[#20429f] hover:bg-[#1a3680] text-white font-medium text-sm py-2.5 rounded-md flex justify-center items-center transition-colors mt-6"
            >
              Login
            </button>
          </form>
        )}

        {/* Register Section */}
        {!isForgotPassword && (
          <div className="text-center mt-6">
            <p className="text-xs text-slate-600 font-normal">
              Don't have an account?{' '}
              <Link to="/register" className="text-[#1e40af] font-semibold hover:underline">
                Register
              </Link>
            </p>
          </div>
        )}
      </div>
    </div>
  );
};

export default LoginPage;
