import { Link } from 'react-router-dom';
import { ShieldCheck, MessageSquareText, BookOpen, CheckCircle2 } from 'lucide-react';

/**
 * Shared UCLM SCMS two-column authentication layout for Login, Registration,
 * Forgot Password, and Reset Password pages.
 * - Full-page background: /images/uclm-campus-banner.jpg covering the browser viewport behind the card
 *   with a subtle light overlay (responsive scroll on mobile, fixed on md+ viewports).
 * - Left panel: Solid UCLM Navy Blue (#0f2557 / #1e3a8a) and Gold (#f59e0b / #fbbf24) branding with /logo.png.
 * - Right panel: Solid opaque white authentication form surface.
 * - Responsive: two-column split on desktop (lg), stacked card on tablet/mobile without horizontal scrolling.
 */
const AuthSplitLayout = ({
  children,
  badgeText = 'Student & Campus Portal',
  heading = 'Welcome back',
  subheading = 'Sign in to access your UCLM Student Concern Management System account.',
  maxWidthClass = 'max-w-5xl',
}) => {
  const highlights = [
    {
      icon: MessageSquareText,
      title: 'Submit & Track Concerns',
      description:
        'Route academic and operational concerns directly to responsible UCLM offices with real-time updates.',
    },
    {
      icon: BookOpen,
      title: 'Digital UCLM Student Guide',
      description:
        'Browse official university procedures, handbook checklists, and step-by-step campus guidance.',
    },
    {
      icon: ShieldCheck,
      title: 'Verified Campus Security',
      description:
        'Protected by active-term class list verification and role-based confidentiality safeguards.',
    },
  ];

  return (
    <div
      className="min-h-screen flex flex-col justify-center items-center px-4 py-10 sm:px-6 lg:px-8 font-sans relative overflow-x-hidden bg-slate-100 bg-cover bg-center bg-no-repeat bg-scroll md:bg-fixed"
      style={{
        backgroundImage: "url('/images/uclm-campus-banner.jpg')",
        backgroundSize: 'cover',
        backgroundPosition: 'center center',
        backgroundRepeat: 'no-repeat',
      }}
    >
      {/* Subtle Light Overlay so Campus Photo Remains Visible Outside Card Without Distracting */}
      <div
        aria-hidden="true"
        className="pointer-events-none absolute inset-0 z-0 bg-white/50"
      />

      {/* Centered Split Authentication Card */}
      <div
        className={`relative z-10 w-full ${maxWidthClass} bg-white rounded-2xl shadow-2xl border border-slate-200/90 overflow-hidden grid grid-cols-1 lg:grid-cols-12`}
      >
        {/* Left Branding Panel (Solid UCLM Navy & Gold) */}
        <div className="lg:col-span-5 bg-gradient-to-br from-[#0f2557] via-[#1e3a8a] to-[#152c69] text-white p-7 sm:p-9 lg:p-10 flex flex-col justify-between relative overflow-hidden">
          {/* Decorative Navy & Gold Geometric Elements */}
          <div
            aria-hidden="true"
            className="pointer-events-none absolute -top-20 -right-20 w-64 h-64 rounded-full border border-amber-400/20 bg-amber-400/5"
          />
          <div
            aria-hidden="true"
            className="pointer-events-none absolute -bottom-24 -left-16 w-72 h-72 rounded-full border border-blue-300/15 bg-blue-400/5"
          />
          <div
            aria-hidden="true"
            className="pointer-events-none absolute top-0 left-0 right-0 h-1.5 bg-gradient-to-r from-amber-400 via-amber-300 to-amber-500"
          />

          {/* Top University Header */}
          <div className="relative z-10">
            <Link to="/login" className="inline-flex items-center gap-3.5 group">
              <div className="h-14 w-14 sm:h-16 sm:w-16 rounded-2xl bg-white/95 p-2 shadow-lg border border-amber-300/50 flex items-center justify-center shrink-0">
                <img
                  src="/logo.png"
                  alt="University of Cebu Lapu-Lapu and Mandaue Official Logo"
                  className="h-full w-full object-contain"
                />
              </div>
              <div>
                <span className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-widest bg-amber-400/25 text-amber-300 border border-amber-400/40 shadow-2xs">
                  UCLM • Official Portal
                </span>
                <h1 className="text-xl sm:text-2xl font-extrabold tracking-tight text-white mt-1 drop-shadow-xs">
                  UCLM SCMS
                </h1>
              </div>
            </Link>

            <div className="mt-6 sm:mt-8">
              <p className="text-xs font-bold uppercase tracking-widest text-amber-300 drop-shadow-xs">
                Student Concern Management System
              </p>
              <h2 className="text-xl sm:text-2xl font-bold text-white mt-1.5 leading-snug drop-shadow-xs">
                A direct, transparent bridge between UCLM students and campus offices.
              </h2>
              <p className="text-xs sm:text-sm text-blue-50/95 mt-3 leading-relaxed">
                Submit academic or operational concerns, consult the Digital UCLM Student Guide, and track official
                resolutions from your department, Chairperson, Dean, or administrative office.
              </p>
            </div>

            {/* Feature Highlights (Visible on Tablet & Desktop) */}
            <div className="mt-7 space-y-3 hidden sm:block">
              {highlights.map((item, idx) => {
                const Icon = item.icon;
                return (
                  <div
                    key={idx}
                    className="flex items-start gap-3 p-3 rounded-xl bg-white/7 border border-white/10 backdrop-blur-xs shadow-xs"
                  >
                    <div className="p-2 rounded-lg bg-amber-400/20 text-amber-300 border border-amber-400/25 shrink-0 mt-0.5">
                      <Icon size={16} />
                    </div>
                    <div>
                      <h3 className="text-xs font-bold text-white">{item.title}</h3>
                      <p className="text-[11px] text-blue-100/90 mt-0.5 leading-relaxed">
                        {item.description}
                      </p>
                    </div>
                  </div>
                );
              })}
            </div>
          </div>

          {/* Bottom Institutional Footer */}
          <div className="relative z-10 mt-6 pt-4 border-t border-white/20 flex flex-wrap items-center justify-between gap-2 text-[11px] text-blue-100/95">
            <span className="font-medium">University of Cebu Lapu-Lapu and Mandaue</span>
            <span className="inline-flex items-center gap-1 text-amber-300 font-semibold">
              <CheckCircle2 size={12} />
              Official Campus Portal
            </span>
          </div>
        </div>

        {/* Right Form Panel (Solid Opaque White Surface) */}
        <div className="lg:col-span-7 bg-white p-6 sm:p-9 lg:p-11 flex flex-col justify-center">
          <div className="w-full max-w-md mx-auto">
            {/* Form Heading */}
            <div className="mb-6">
              <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold uppercase tracking-wider bg-blue-50 text-[#1e3a8a] border border-blue-200/80">
                {badgeText}
              </span>
              <h2 className="text-2xl sm:text-[26px] font-extrabold text-slate-900 tracking-tight mt-2">
                {heading}
              </h2>
              {subheading && (
                <p className="text-xs sm:text-sm text-slate-500 mt-1.5 leading-relaxed">
                  {subheading}
                </p>
              )}
            </div>

            {children}
          </div>
        </div>
      </div>
    </div>
  );
};

export default AuthSplitLayout;
