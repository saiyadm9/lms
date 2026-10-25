import { useState } from "react";
import { Link, useNavigate, useLocation } from "react-router";
import { useAuth } from "../features/auth/AuthContext";
import { GraduationCap, AlertCircle, ArrowRight, ShieldCheck, User } from "lucide-react";

export default function LoginPage() {
  const { login } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();

  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState("");
  const [submitting, setSubmitting] = useState(false);

  const from = location.state?.from?.pathname || "/courses";

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError("");
    setSubmitting(true);

    try {
      const user = await login(email, password);
      if (user.role === "admin") {
        navigate("/admin/dashboard", { replace: true });
      } else {
        navigate(from, { replace: true });
      }
    } catch (err) {
      const msg =
        err.response?.data?.errors?.email?.[0] ||
        err.response?.data?.message ||
        "Failed to sign in. Please check your credentials.";
      setError(msg);
    } finally {
      setSubmitting(false);
    }
  };

  const fillCredentials = (demoEmail, demoPassword) => {
    setEmail(demoEmail);
    setPassword(demoPassword);
    setError("");
  };

  return (
    <div className="flex min-h-screen bg-slate-100">
      {/* Left Brand Column */}
      <div className="hidden lg:flex lg:w-1/2 flex-col justify-between relative overflow-hidden bg-gradient-to-br from-slate-900 via-slate-800 to-indigo-950 p-12 text-white">
        {/* Decorative background blobs */}
        <div className="pointer-events-none absolute -top-24 -left-24 h-72 w-72 rounded-full bg-indigo-600/20 blur-3xl" />
        <div className="pointer-events-none absolute bottom-0 right-0 h-96 w-96 rounded-full bg-violet-700/15 blur-3xl" />
        <div className="pointer-events-none absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 h-64 w-64 rounded-full bg-blue-500/10 blur-2xl" />

        {/* Logo */}
        <div className="relative flex items-center gap-3">
          <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-white/10 ring-1 ring-white/20 backdrop-blur-sm">
            <GraduationCap className="h-6 w-6 text-indigo-300" />
          </div>
          <span className="text-2xl font-bold tracking-tight text-white">LearnHub</span>
        </div>

        {/* Hero copy */}
        <div className="relative max-w-md space-y-5">
          <h1 className="text-4xl font-extrabold tracking-tight leading-tight text-white">
            Learn. Grow.<br />
            <span className="text-indigo-200">Achieve.</span>
          </h1>
          <p className="text-sm text-slate-200 leading-relaxed">
            Discover courses, manage your enrollments, and seamlessly join waitlists with instant FIFO promotions.
          </p>

          {/* Feature list */}
          <ul className="mt-2 space-y-2.5 text-sm text-slate-300">
            {[
              "Browse published courses with live seat counts",
              "Enroll instantly or join the waitlist automatically",
              "FIFO promotion when a seat opens up",
              "Manage your enrollments from one place",
            ].map((f) => (
              <li key={f} className="flex items-start gap-2.5">
                <span className="mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-indigo-500/30 text-indigo-200 text-[10px] font-bold">✓</span>
                <span>{f}</span>
              </li>
            ))}
          </ul>
        </div>

        {/* Demo credentials */}
        <div className="relative rounded-xl bg-white/5 ring-1 ring-white/10 p-5 backdrop-blur-sm">
          <p className="text-xs font-semibold uppercase tracking-wider text-slate-300 mb-3">
            Assessment Demo Credentials
          </p>
          <div className="flex flex-wrap gap-2">
            <button
              type="button"
              onClick={() => fillCredentials("admin@example.com", "password")}
              className="inline-flex items-center gap-1.5 rounded-lg bg-white/8 hover:bg-white/15 ring-1 ring-white/10 px-3 py-1.5 text-xs font-medium text-slate-200 transition-colors"
            >
              <ShieldCheck className="h-3.5 w-3.5 text-indigo-400" />
              <span>Admin: admin@example.com</span>
            </button>
            <button
              type="button"
              onClick={() => fillCredentials("student@example.com", "password")}
              className="inline-flex items-center gap-1.5 rounded-lg bg-white/8 hover:bg-white/15 ring-1 ring-white/10 px-3 py-1.5 text-xs font-medium text-slate-200 transition-colors"
            >
              <User className="h-3.5 w-3.5 text-indigo-400" />
              <span>Student: student@example.com</span>
            </button>
          </div>
        </div>
      </div>

      {/* Right Login Form Column */}
      <div className="flex flex-1 items-center justify-center p-8 sm:p-12">
        <div className="w-full max-w-md space-y-8 rounded-2xl bg-white p-8 sm:p-10 shadow-xl shadow-slate-200/50 border border-slate-100">
          <div>
            <div className="flex items-center gap-2 lg:hidden mb-6">
              <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-600 text-white">
                <GraduationCap className="h-5 w-5" />
              </div>
              <span className="text-xl font-bold text-slate-900">LearnHub</span>
            </div>
            <h2 className="text-2xl font-bold tracking-tight text-slate-900">Welcome Back</h2>
            <p className="mt-1.5 text-sm text-slate-500">Sign in to your account to continue</p>
          </div>

          {error && (
            <div className="flex items-start gap-3 rounded-lg bg-red-50 p-3.5 border border-red-200 text-sm text-red-700">
              <AlertCircle className="h-5 w-5 shrink-0 text-red-500 mt-0.5" />
              <span>{error}</span>
            </div>
          )}

          <form onSubmit={handleSubmit} className="space-y-5">
            <div>
              <label className="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
                Email Address
              </label>
              <input
                type="email"
                required
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                placeholder="you@example.com"
                className="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm text-slate-900 shadow-sm focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-600/20"
              />
            </div>

            <div>
              <label className="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
                Password
              </label>
              <input
                type="password"
                required
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                placeholder="••••••••"
                className="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm text-slate-900 shadow-sm focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-600/20"
              />
            </div>

            <button
              type="submit"
              disabled={submitting}
              className="flex w-full items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-2 disabled:opacity-60"
            >
              {submitting ? (
                <>
                  <div className="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent" />
                  <span>Signing in...</span>
                </>
              ) : (
                <>
                  <span>Sign In</span>
                  <ArrowRight className="h-4 w-4" />
                </>
              )}
            </button>
          </form>

          <div className="border-t border-slate-100 pt-5 text-center text-sm text-slate-600">
            Don't have an account?{" "}
            <Link to="/register" className="font-semibold text-blue-600 hover:text-blue-700">
              Create student account
            </Link>
          </div>
        </div>
      </div>
    </div>
  );
}

