import { Link, useLocation } from "react-router";
import { useAuth } from "../features/auth/AuthContext";
import {
  BookOpen,
  LayoutDashboard,
  BookmarkCheck,
  LogOut,
  GraduationCap,
  ShieldCheck,
  User as UserIcon,
} from "lucide-react";

export default function Sidebar() {
  const { user, isAdmin, logout } = useAuth();
  const location = useLocation();

  const studentLinks = [
    { label: "Browse Courses", to: "/courses", icon: BookOpen },
    { label: "My Enrollments", to: "/my-enrollments", icon: BookmarkCheck },
  ];

  const adminLinks = [
    { label: "Dashboard", to: "/admin/dashboard", icon: LayoutDashboard },
    { label: "Course Management", to: "/admin/courses", icon: BookOpen },
  ];

  const links = isAdmin ? adminLinks : studentLinks;

  return (
    <aside className="fixed inset-y-0 left-0 z-30 flex w-64 flex-col bg-slate-900 text-white shadow-xl">
      {/* Brand Header */}
      <div className="flex h-16 items-center gap-3 border-b border-slate-800 px-6">
        <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-600 text-white shadow-sm">
          <GraduationCap className="h-5 w-5" />
        </div>
        <div>
          <span className="text-lg font-bold tracking-tight text-white">LearnHub</span>
          <span className="ml-1 text-[10px] font-semibold tracking-wider text-blue-400 uppercase">
            {isAdmin ? "Admin" : "Student"}
          </span>
        </div>
      </div>

      {/* Nav Links */}
      <div className="flex-1 space-y-1.5 overflow-y-auto px-4 py-6">
        <div className="px-3 pb-2 text-[11px] font-semibold tracking-wider text-slate-400 uppercase">
          Navigation
        </div>
        {links.map((link) => {
          const Icon = link.icon;
          const isActive = location.pathname === link.to;
          return (
            <Link
              key={link.to}
              to={link.to}
              className={`flex items-center gap-3 rounded-lg px-3.5 py-2.5 text-sm font-medium transition-colors ${
                isActive
                  ? "bg-blue-600 text-white shadow-sm"
                  : "text-slate-300 hover:bg-slate-800 hover:text-white"
              }`}
            >
              <Icon className="h-4 w-4 shrink-0" />
              <span>{link.label}</span>
            </Link>
          );
        })}
      </div>

      {/* User Section & Logout */}
      <div className="border-t border-slate-800 p-4">
        <div className="mb-3 flex items-center gap-3 rounded-lg bg-slate-800/60 p-2.5">
          <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-slate-700 text-slate-200">
            {isAdmin ? <ShieldCheck className="h-4 w-4 text-blue-400" /> : <UserIcon className="h-4 w-4" />}
          </div>
          <div className="min-w-0 flex-1">
            <p className="truncate text-xs font-semibold text-white">{user?.name || "User"}</p>
            <p className="truncate text-[11px] text-slate-400">{user?.email}</p>
          </div>
        </div>

        <button
          onClick={logout}
          className="flex w-full items-center justify-center gap-2 rounded-lg border border-slate-700 bg-transparent px-3 py-2 text-xs font-medium text-slate-300 transition-colors hover:bg-red-950/40 hover:border-red-800/60 hover:text-red-300"
        >
          <LogOut className="h-3.5 w-3.5" />
          <span>Log out</span>
        </button>
      </div>
    </aside>
  );
}
