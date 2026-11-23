import { useState, useEffect } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import {
  ArrowRight,
  Eye,
  EyeOff,
  User,
  Hash,
  Building2,
  Mail,
  Lock,
  ShieldCheck,
  AlertCircle,
  CheckCircle2,
} from 'lucide-react';
import api from '../api';
import AuthSplitLayout from '../components/AuthSplitLayout';

const RegisterPage = () => {
  const [formData, setFormData] = useState({
    name: '',
    student_id_number: '',
    email: '',
    password: '',
    password_confirmation: '',
    academic_unit_id: '',
  });
  const [units, setUnits] = useState([]);
  const [loadingUnits, setLoadingUnits] = useState(true);
  const [showPassword, setShowPassword] = useState(false);
  const [showConfirmPassword, setShowConfirmPassword] = useState(false);
  const [error, setError] = useState('');
  const [fieldErrors, setFieldErrors] = useState({});
  const [success, setSuccess] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const navigate = useNavigate();

  useEffect(() => {
    let active = true;
    api
      .get('/academic-units')
      .then((res) => {
        if (active) {
          setUnits(res.data?.academic_units || []);
        }
      })
      .catch(() => {
        if (active) {
          setError('Unable to load Academic Units. Please refresh the page.');
        }
      })
      .finally(() => {
        if (active) setLoadingUnits(false);
      });
    return () => {
      active = false;
    };
  }, []);

  const handleChange = (field, value) => {
    setFormData((prev) => ({ ...prev, [field]: value }));
    if (fieldErrors[field]) {
      setFieldErrors((prev) => ({ ...prev, [field]: null }));
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    setFieldErrors({});
    setSuccess('');

    const trimmedStudentId = formData.student_id_number.trim();
    if (!/^\d{8}$/.test(trimmedStudentId)) {
      setError('Student ID must be exactly 8 numeric digits.');
      setFieldErrors({ student_id_number: ['Student ID must be exactly 8 numeric digits.'] });
      return;
    }

    if (!formData.academic_unit_id) {
      setError('Please select your registered Academic Unit.');
      setFieldErrors({ academic_unit_id: ['Please select your registered Academic Unit.'] });
      return;
    }

    if (formData.password.length < 8) {
      setError('Password must be at least 8 characters long.');
      setFieldErrors({ password: ['Password must be at least 8 characters long.'] });
      return;
    }

    if (formData.password !== formData.password_confirmation) {
      setError('Password confirmation does not match.');
      setFieldErrors({ password_confirmation: ['Password confirmation does not match.'] });
      return;
    }

    setIsSubmitting(true);
    try {
      await api.post('/register', {
        ...formData,
        name: formData.name.trim(),
        student_id_number: trimmedStudentId,
        email: formData.email.trim(),
      });
      setSuccess('Registration successful! Redirecting you to Sign In...');
      setTimeout(() => navigate('/login'), 1800);
    } catch (err) {
      const apiErrors = err.response?.data?.errors || {};
      setFieldErrors(apiErrors);
      const firstFieldError = Object.values(apiErrors)?.[0]?.[0];
      setError(
        firstFieldError ||
          err.response?.data?.message ||
          'Registration failed. Please verify your Student ID and Academic Unit.'
      );
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <AuthSplitLayout
      badgeText="Verified Student Registration"
      heading="Create your account"
      subheading="Register with your official 8-digit UCLM Student ID to submit and track academic and campus concerns."
      maxWidthClass="max-w-5xl"
    >
      {/* Active-Term Class List Verification Notice */}
      <div className="mb-5 p-3.5 rounded-xl bg-amber-50/90 border border-amber-200 text-amber-950 text-xs flex items-start gap-2.5">
        <ShieldCheck size={17} className="text-amber-700 shrink-0 mt-0.5" />
        <div className="leading-relaxed">
          <span className="font-bold">Student ID Verification Required: </span>
          Your 8-digit Student ID is verified against the official active-term class list uploaded by your Academic
          Unit Dean.
        </div>
      </div>

      {error && (
        <div
          role="alert"
          className="mb-5 p-3.5 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs sm:text-sm flex items-start gap-2.5"
        >
          <AlertCircle size={17} className="text-rose-600 shrink-0 mt-0.5" />
          <span className="font-medium leading-relaxed">{error}</span>
        </div>
      )}

      {success && (
        <div
          role="status"
          className="mb-5 p-3.5 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs sm:text-sm flex items-start gap-2.5"
        >
          <CheckCircle2 size={17} className="text-emerald-600 shrink-0 mt-0.5" />
          <span className="font-semibold leading-relaxed">{success}</span>
        </div>
      )}

      <form onSubmit={handleSubmit} className="space-y-4">
        {/* Full Name */}
        <div className="space-y-1.5">
          <label
            htmlFor="register-name"
            className="block text-xs font-bold text-slate-800 uppercase tracking-wider"
          >
            Full Name
          </label>
          <div className="relative">
            <span className="pointer-events-none absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400">
              <User size={16} />
            </span>
            <input
              id="register-name"
              type="text"
              required
              autoComplete="name"
              className="input-field pl-10 py-2.5"
              placeholder="e.g. Juan Dela Cruz"
              value={formData.name}
              onChange={(e) => handleChange('name', e.target.value)}
            />
          </div>
          {fieldErrors.name && (
            <p className="text-[11px] font-medium text-rose-600">{fieldErrors.name[0]}</p>
          )}
        </div>

        {/* Student ID & Academic Unit */}
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
          <div className="space-y-1.5">
            <div className="flex items-center justify-between">
              <label
                htmlFor="register-student-id"
                className="block text-xs font-bold text-slate-800 uppercase tracking-wider"
              >
                Student ID
              </label>
              <span className="text-[10px] font-semibold text-slate-400">
                {formData.student_id_number.length}/8 digits
              </span>
            </div>
            <div className="relative">
              <span className="pointer-events-none absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400">
                <Hash size={15} />
              </span>
              <input
                id="register-student-id"
                type="text"
                inputMode="numeric"
                required
                maxLength={8}
                pattern="\d{8}"
                className="input-field pl-9 py-2.5 font-mono"
                placeholder="8-digit ID"
                value={formData.student_id_number}
                onChange={(e) =>
                  handleChange('student_id_number', e.target.value.replace(/\D/g, '').slice(0, 8))
                }
              />
            </div>
            {fieldErrors.student_id_number && (
              <p className="text-[11px] font-medium text-rose-600">
                {fieldErrors.student_id_number[0]}
              </p>
            )}
          </div>

          <div className="space-y-1.5">
            <label
              htmlFor="register-academic-unit"
              className="block text-xs font-bold text-slate-800 uppercase tracking-wider"
            >
              Academic Unit
            </label>
            <div className="relative">
              <span className="pointer-events-none absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400">
                <Building2 size={15} />
              </span>
              <select
                id="register-academic-unit"
                required
                disabled={loadingUnits}
                className="input-field pl-9 py-2.5"
                value={formData.academic_unit_id}
                onChange={(e) => handleChange('academic_unit_id', e.target.value)}
              >
                <option value="" disabled hidden>
                  {loadingUnits ? 'Loading units...' : 'Select Academic Unit'}
                </option>
                {units.map((u) => (
                  <option key={u.id} value={u.id}>
                    {u.name}
                  </option>
                ))}
              </select>
            </div>
            {fieldErrors.academic_unit_id && (
              <p className="text-[11px] font-medium text-rose-600">
                {fieldErrors.academic_unit_id[0]}
              </p>
            )}
          </div>
        </div>

        {/* Email Address */}
        <div className="space-y-1.5">
          <label
            htmlFor="register-email"
            className="block text-xs font-bold text-slate-800 uppercase tracking-wider"
          >
            Email Address
          </label>
          <div className="relative">
            <span className="pointer-events-none absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400">
              <Mail size={16} />
            </span>
            <input
              id="register-email"
              type="email"
              required
              autoComplete="email"
              className="input-field pl-10 py-2.5"
              placeholder="student@uclm.edu.ph"
              value={formData.email}
              onChange={(e) => handleChange('email', e.target.value)}
            />
          </div>
          {fieldErrors.email && (
            <p className="text-[11px] font-medium text-rose-600">{fieldErrors.email[0]}</p>
          )}
        </div>

        {/* Password & Confirm Password */}
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
          <div className="space-y-1.5">
            <label
              htmlFor="register-password"
              className="block text-xs font-bold text-slate-800 uppercase tracking-wider"
            >
              Password
            </label>
            <div className="relative">
              <span className="pointer-events-none absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400">
                <Lock size={15} />
              </span>
              <input
                id="register-password"
                type={showPassword ? 'text' : 'password'}
                required
                minLength={8}
                autoComplete="new-password"
                className="input-field pl-9 pr-9 py-2.5"
                placeholder="Min. 8 characters"
                value={formData.password}
                onChange={(e) => handleChange('password', e.target.value)}
              />
              <button
                type="button"
                onClick={() => setShowPassword(!showPassword)}
                aria-label={showPassword ? 'Hide password' : 'Show password'}
                className="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600"
              >
                {showPassword ? <EyeOff size={15} /> : <Eye size={15} />}
              </button>
            </div>
            {fieldErrors.password && (
              <p className="text-[11px] font-medium text-rose-600">{fieldErrors.password[0]}</p>
            )}
          </div>

          <div className="space-y-1.5">
            <label
              htmlFor="register-password-confirm"
              className="block text-xs font-bold text-slate-800 uppercase tracking-wider"
            >
              Confirm Password
            </label>
            <div className="relative">
              <span className="pointer-events-none absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400">
                <Lock size={15} />
              </span>
              <input
                id="register-password-confirm"
                type={showConfirmPassword ? 'text' : 'password'}
                required
                minLength={8}
                autoComplete="new-password"
                className="input-field pl-9 pr-9 py-2.5"
                placeholder="Re-enter password"
                value={formData.password_confirmation}
                onChange={(e) => handleChange('password_confirmation', e.target.value)}
              />
              <button
                type="button"
                onClick={() => setShowConfirmPassword(!showConfirmPassword)}
                aria-label={showConfirmPassword ? 'Hide confirm password' : 'Show confirm password'}
                className="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600"
              >
                {showConfirmPassword ? <EyeOff size={15} /> : <Eye size={15} />}
              </button>
            </div>
            {fieldErrors.password_confirmation && (
              <p className="text-[11px] font-medium text-rose-600">
                {fieldErrors.password_confirmation[0]}
              </p>
            )}
          </div>
        </div>

        {/* Submit Button */}
        <div className="pt-2">
          <button
            type="submit"
            disabled={isSubmitting || Boolean(success)}
            className="w-full btn-dark py-2.5 text-sm font-bold flex items-center justify-center gap-2 shadow-sm"
          >
            {isSubmitting ? (
              <>
                <span className="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin" />
                <span>Verifying Student ID &amp; Creating Account...</span>
              </>
            ) : (
              <>
                <span>Create Student Account</span>
                <ArrowRight size={16} />
              </>
            )}
          </button>
        </div>
      </form>

      <div className="mt-6 pt-5 border-t border-slate-200/80 text-center">
        <p className="text-xs sm:text-sm text-slate-600">
          Already have an account?{' '}
          <Link
            to="/login"
            className="text-[#1e3a8a] font-bold hover:text-blue-700 hover:underline"
          >
            Back to Login
          </Link>
        </p>
      </div>
    </AuthSplitLayout>
  );
};

export default RegisterPage;
