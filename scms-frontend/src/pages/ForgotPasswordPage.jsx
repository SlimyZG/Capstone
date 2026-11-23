import { useState } from 'react';
import { Link } from 'react-router-dom';
import { Mail, ArrowLeft, Send, CheckCircle2, AlertCircle } from 'lucide-react';
import api from '../api';
import AuthSplitLayout from '../components/AuthSplitLayout';

const ForgotPasswordPage = () => {
  const [email, setEmail] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState('');
  const [successMessage, setSuccessMessage] = useState('');

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError('');
    setSuccessMessage('');

    const trimmedEmail = email.trim();
    if (!trimmedEmail) {
      setError('Please enter your registered email address.');
      return;
    }

    setIsSubmitting(true);
    try {
      const response = await api.post('/forgot-password', { email: trimmedEmail });
      setSuccessMessage(
        response.data?.message ||
          'If your email is registered in our system, you will receive a password reset link shortly.'
      );
      setEmail('');
    } catch (err) {
      const fieldError = err.response?.data?.errors?.email?.[0];
      setError(
        fieldError ||
          err.response?.data?.message ||
          'Unable to process your password reset request right now. Please try again.'
      );
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <AuthSplitLayout
      badgeText="Account Recovery"
      heading="Forgot your password?"
      subheading="Enter the email address registered to your UCLM SCMS account and we will send you a password reset link."
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

      {successMessage && (
        <div
          role="status"
          className="mb-5 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-900 text-xs sm:text-sm space-y-1.5"
        >
          <div className="flex items-center gap-2 font-bold text-emerald-800">
            <CheckCircle2 size={17} className="text-emerald-600 shrink-0" />
            <span>Check your email inbox</span>
          </div>
          <p className="text-emerald-800 leading-relaxed">{successMessage}</p>
        </div>
      )}

      <form onSubmit={handleSubmit} className="space-y-4">
        <div className="space-y-1.5">
          <label
            htmlFor="forgot-email"
            className="block text-xs font-bold text-slate-800 uppercase tracking-wider"
          >
            Registered Email Address
          </label>
          <div className="relative">
            <span className="pointer-events-none absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400">
              <Mail size={16} />
            </span>
            <input
              id="forgot-email"
              type="email"
              required
              autoComplete="email"
              className="input-field pl-10 py-2.5"
              placeholder="Enter your registered email (e.g. student@uclm.edu.ph)"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
            />
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
                <span>Sending Reset Link...</span>
              </>
            ) : (
              <>
                <Send size={15} />
                <span>Send Reset Link</span>
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
    </AuthSplitLayout>
  );
};

export default ForgotPasswordPage;
