import { useState, useEffect } from 'react';
import { useSearchParams, useParams, useNavigate, Link } from 'react-router-dom';
import {
  Lock,
  Eye,
  EyeOff,
  CheckCircle2,
  AlertCircle,
  ArrowLeft,
  KeyRound,
  Mail,
} from 'lucide-react';
import api from '../api';
import AuthSplitLayout from '../components/AuthSplitLayout';

const ResetPasswordPage = () => {
  const [searchParams] = useSearchParams();
  const routeParams = useParams();
  const navigate = useNavigate();

  const token = (searchParams.get('token') || routeParams.token || '').trim();
  const initialEmail = (searchParams.get('email') || '').trim();

  const [email, setEmail] = useState(initialEmail);
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [showConfirmPassword, setShowConfirmPassword] = useState(false);

  const [validatingToken, setValidatingToken] = useState(true);
  const [tokenValid, setTokenValid] = useState(false);
  const [tokenError, setTokenError] = useState('');

  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState('');
  const [resetComplete, setResetComplete] = useState(false);

  useEffect(() => {
    let active = true;

    const verifyToken = async () => {
      if (!token || !initialEmail) {
        if (active) {
          setTokenValid(false);
          setTokenError(
            'This password reset link is incomplete or invalid. Please use the full link from your email or request a new password reset link.'
          );
          setValidatingToken(false);
        }
        return;
      }

      try {
        const res = await api.post('/reset-password/validate', {
          token,
          email: initialEmail,
        });
        if (active) {
          setTokenValid(Boolean(res.data?.valid));
          setTokenError('');
        }
      } catch (err) {
        if (active) {
          setTokenValid(false);
          setTokenError(
            err.response?.data?.message ||
              'This password reset link is invalid, expired, or has already been used.'
          );
        }
      } finally {
        if (active) {
          setValidatingToken(false);
        }
      }
    };

    verifyToken();
    return () => {
      active = false;
    };
  }, [token, initialEmail]);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');

    if (password.length < 8) {
      setError('New password must be at least 8 characters long.');
      return;
    }

    if (password !== passwordConfirmation) {
      setError('Password confirmation does not match.');
      return;
    }

    setIsSubmitting(true);
    try {
      await api.post('/reset-password', {
        token,
        email: email.trim(),
        password,
        password_confirmation: passwordConfirmation,
      });
      setResetComplete(true);
      setTimeout(() => {
        navigate('/login', { replace: true });
      }, 2500);
    } catch (err) {
      const apiErrors = err.response?.data?.errors || {};
      const firstError = Object.values(apiErrors)?.[0]?.[0];
      setError(
        firstError ||
          err.response?.data?.message ||
          'Failed to reset password. The reset link may have expired or already been used.'
      );
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <AuthSplitLayout
      badgeText="Password Recovery"
      heading="Reset your password"
      subheading="Choose a strong new password for your UCLM Student Concern Management System account."
    >
      {validatingToken ? (
        <div className="py-10 text-center space-y-3">
          <div className="w-8 h-8 border-3 border-blue-200 border-t-[#1e3a8a] rounded-full animate-spin mx-auto" />
          <p className="text-xs sm:text-sm font-semibold text-slate-600">
            Verifying your password reset link...
          </p>
        </div>
      ) : resetComplete ? (
        <div className="space-y-5">
          <div
            role="status"
            className="p-5 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-900 space-y-2"
          >
            <div className="flex items-center gap-2 font-bold text-emerald-800 text-sm">
              <CheckCircle2 size={18} className="text-emerald-600 shrink-0" />
              <span>Password Changed Successfully</span>
            </div>
            <p className="text-xs sm:text-sm text-emerald-800 leading-relaxed">
              Your password has been updated and your reset token has been invalidated. Redirecting you to Sign In...
            </p>
          </div>
          <Link
            to="/login"
            className="w-full btn-dark py-2.5 text-sm font-bold flex items-center justify-center gap-2"
          >
            <span>Continue to Login</span>
          </Link>
        </div>
      ) : !tokenValid ? (
        <div className="space-y-5">
          <div
            role="alert"
            className="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 space-y-1.5"
          >
            <div className="flex items-center gap-2 font-bold text-rose-900 text-sm">
              <AlertCircle size={18} className="text-rose-600 shrink-0" />
              <span>Invalid or Expired Reset Link</span>
            </div>
            <p className="text-xs sm:text-sm leading-relaxed">{tokenError}</p>
          </div>

          <div className="flex flex-col sm:flex-row gap-2.5">
            <Link
              to="/forgot-password"
              className="flex-1 btn-dark py-2.5 text-xs sm:text-sm font-bold text-center"
            >
              Request a New Reset Link
            </Link>
            <Link
              to="/login"
              className="flex-1 btn-secondary py-2.5 text-xs sm:text-sm font-bold text-center"
            >
              Back to Login
            </Link>
          </div>
        </div>
      ) : (
        <>
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
            {/* Account Email (Read-Only Context) */}
            <div className="space-y-1.5">
              <label
                htmlFor="reset-email"
                className="block text-xs font-bold text-slate-800 uppercase tracking-wider"
              >
                Account Email
              </label>
              <div className="relative">
                <span className="pointer-events-none absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400">
                  <Mail size={16} />
                </span>
                <input
                  id="reset-email"
                  type="email"
                  required
                  readOnly={Boolean(initialEmail)}
                  className="input-field pl-10 py-2.5 bg-slate-50 text-slate-600"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                />
              </div>
            </div>

            {/* New Password */}
            <div className="space-y-1.5">
              <label
                htmlFor="reset-new-password"
                className="block text-xs font-bold text-slate-800 uppercase tracking-wider"
              >
                New Password
              </label>
              <div className="relative">
                <span className="pointer-events-none absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400">
                  <Lock size={16} />
                </span>
                <input
                  id="reset-new-password"
                  type={showPassword ? 'text' : 'password'}
                  required
                  minLength={8}
                  autoComplete="new-password"
                  className="input-field pl-10 pr-10 py-2.5"
                  placeholder="At least 8 characters"
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                />
                <button
                  type="button"
                  onClick={() => setShowPassword(!showPassword)}
                  aria-label={showPassword ? 'Hide new password' : 'Show new password'}
                  className="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-600"
                >
                  {showPassword ? <EyeOff size={16} /> : <Eye size={16} />}
                </button>
              </div>
            </div>

            {/* Confirm New Password */}
            <div className="space-y-1.5">
              <label
                htmlFor="reset-confirm-password"
                className="block text-xs font-bold text-slate-800 uppercase tracking-wider"
              >
                Confirm New Password
              </label>
              <div className="relative">
                <span className="pointer-events-none absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400">
                  <Lock size={16} />
                </span>
                <input
                  id="reset-confirm-password"
                  type={showConfirmPassword ? 'text' : 'password'}
                  required
                  minLength={8}
                  autoComplete="new-password"
                  className="input-field pl-10 pr-10 py-2.5"
                  placeholder="Re-enter your new password"
                  value={passwordConfirmation}
                  onChange={(e) => setPasswordConfirmation(e.target.value)}
                />
                <button
                  type="button"
                  onClick={() => setShowConfirmPassword(!showConfirmPassword)}
                  aria-label={
                    showConfirmPassword ? 'Hide confirm new password' : 'Show confirm new password'
                  }
                  className="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-600"
                >
                  {showConfirmPassword ? <EyeOff size={16} /> : <Eye size={16} />}
                </button>
              </div>
            </div>

            <div className="pt-2">
              <button
                type="submit"
                disabled={isSubmitting}
                className="w-full btn-dark py-2.5 text-sm font-bold flex items-center justify-center gap-2 shadow-sm"
              >
                {isSubmitting ? (
                  <>
                    <span className="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin" />
                    <span>Updating Password...</span>
                  </>
                ) : (
                  <>
                    <KeyRound size={16} />
                    <span>Reset Password</span>
                  </>
                )}
              </button>
            </div>
          </form>

          <div className="mt-6 pt-5 border-t border-slate-200/80 text-center">
            <Link
              to="/login"
              className="inline-flex items-center gap-1.5 text-xs sm:text-sm font-bold text-[#1e3a8a] hover:text-blue-700 hover:underline"
            >
              <ArrowLeft size={15} />
              <span>Back to Login</span>
            </Link>
          </div>
        </>
      )}
    </AuthSplitLayout>
  );
};

export default ResetPasswordPage;
