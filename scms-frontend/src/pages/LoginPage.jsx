import { useState, useEffect } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import { Eye, EyeOff, UserCheck, Lock, LogIn, AlertCircle } from 'lucide-react';
import AuthSplitLayout from '../components/AuthSplitLayout';

const LoginPage = () => {
  const [loginValue, setLoginValue] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [error, setError] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);
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

    if (!loginValue.trim() || !password) {
      setError('Please enter your Student ID or Email and your password.');
      return;
    }

    setIsSubmitting(true);
    try {
      const data = await login({ login: loginValue.trim(), password });
      if (data.user.role === 'student') navigate('/student');
      else if (data.user.role === 'admin') navigate('/admin');
      else if (data.user.role === 'sao') navigate('/sao');
      else if (data.user.role === 'dean') navigate('/dean');
      else if (data.user.role === 'dept_staff') navigate('/dept-staff');
      else navigate('/');
    } catch (err) {
      setError(
        err.response?.data?.errors?.login?.[0] ||
          err.response?.data?.message ||
          'Login failed. Please verify your Student ID/Email and password.'
      );
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <AuthSplitLayout
      badgeText="UCLM Portal Sign In"
      heading="Welcome back"
      subheading="Sign in with your 8-digit Student ID or registered institutional email address to submit and track concerns."
    >
      {error && (
        <div
          role="alert"
          className="mb-5 p-3.5 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs sm:text-sm flex items-start gap-2.5"
        >
          <AlertCircle size={17} className="text-rose-600 shrink-0 mt-0.5" />
          <span className="font-medium leading-relaxed">{error}</span>
        </div>
      )}

      <form onSubmit={handleSubmit} className="space-y-4">
        {/* Student ID or Email Field */}
        <div className="space-y-1.5">
          <label
            htmlFor="login-identifier"
            className="block text-xs font-bold text-slate-800 uppercase tracking-wider"
          >
            Student ID or Email
          </label>
          <div className="relative">
            <span className="pointer-events-none absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400">
              <UserCheck size={16} />
            </span>
            <input
              id="login-identifier"
              type="text"
              required
              autoComplete="username"
              className="input-field pl-10 py-2.5"
              value={loginValue}
              onChange={(e) => setLoginValue(e.target.value)}
              placeholder="e.g. 20240001 or student@uclm.edu.ph"
            />
          </div>
        </div>

        {/* Password Field */}
        <div className="space-y-1.5">
          <div className="flex items-center justify-between">
            <label
              htmlFor="login-password"
              className="block text-xs font-bold text-slate-800 uppercase tracking-wider"
            >
              Password
            </label>
            <Link
              to="/forgot-password"
              className="text-xs font-bold text-[#1e3a8a] hover:text-blue-700 hover:underline transition-colors"
            >
              Forgot Password?
            </Link>
          </div>
          <div className="relative">
            <span className="pointer-events-none absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400">
              <Lock size={16} />
            </span>
            <input
              id="login-password"
              type={showPassword ? 'text' : 'password'}
              required
              autoComplete="current-password"
              className="input-field pl-10 pr-10 py-2.5"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              placeholder="Enter your password"
            />
            <button
              type="button"
              onClick={() => setShowPassword(!showPassword)}
              aria-label={showPassword ? 'Hide password' : 'Show password'}
              className="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-600 focus:outline-none"
            >
              {showPassword ? <EyeOff size={16} /> : <Eye size={16} />}
            </button>
          </div>
        </div>

        {/* Prominent Login Button */}
        <div className="pt-2">
          <button
            type="submit"
            disabled={isSubmitting}
            className="w-full btn-dark py-2.5 text-sm font-bold flex items-center justify-center gap-2 shadow-sm"
          >
            {isSubmitting ? (
              <>
                <span className="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin" />
                <span>Signing In...</span>
              </>
            ) : (
              <>
                <LogIn size={16} />
                <span>Login to SCMS</span>
              </>
            )}
          </button>
        </div>
      </form>

      {/* Register Link */}
      <div className="mt-6 pt-5 border-t border-slate-200/80 text-center">
        <p className="text-xs sm:text-sm text-slate-600">
          Don&apos;t have a student account yet?{' '}
          <Link
            to="/register"
            className="text-[#1e3a8a] font-bold hover:text-blue-700 hover:underline"
          >
            Register with your Student ID
          </Link>
        </p>
      </div>
    </AuthSplitLayout>
  );
};

export default LoginPage;
