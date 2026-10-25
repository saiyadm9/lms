import { useAuth } from "../features/auth/AuthContext";
import { User as UserIcon, ShieldCheck } from "lucide-react";

export default function Header() {
  const { user, isAdmin } = useAuth();

  return (
    <header className="sticky top-0 z-20 flex h-16 w-full items-center justify-between border-b border-slate-200 bg-white/95 px-8 backdrop-blur-sm">
      <div className="flex items-center gap-3">
        <span className="text-sm font-semibold text-slate-700">
          Learning Management System
        </span>
      </div>

      <div className="flex items-center gap-4">
        <div className="flex items-center gap-2.5">
          <div className="flex h-8 w-8 items-center justify-center rounded-full bg-blue-50 text-blue-600 ring-1 ring-blue-200">
            {isAdmin ? <ShieldCheck className="h-4 w-4" /> : <UserIcon className="h-4 w-4" />}
          </div>
          <div className="text-right">
            <p className="text-xs font-semibold text-slate-800">{user?.name}</p>
            <span className="inline-block text-[10px] font-medium text-slate-500 capitalize">
              {user?.role}
            </span>
          </div>
        </div>
      </div>
    </header>
  );
}
