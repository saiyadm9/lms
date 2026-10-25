import { useState, useEffect, useCallback } from "react";
import { Link } from "react-router";
import api from "../lib/api";
import {
  BookmarkCheck,
  Clock,
  AlertCircle,
  CheckCircle2,
  BookOpen,
  Calendar,
} from "lucide-react";

export default function MyEnrollmentsPage() {
  const [enrollments, setEnrollments] = useState([]);
  const [loading, setLoading] = useState(true);
  const [activeTab, setActiveTab] = useState("enrolled"); // 'enrolled' or 'waitlisted'
  const [actionMessage, setActionMessage] = useState(null);
  const [cancellingId, setCancellingId] = useState(null);

  const fetchEnrollments = useCallback(async () => {
    try {
      const res = await api.get("/me/enrollments");
      setEnrollments(res.data.enrollments || []);
    } catch {
      setActionMessage({ type: "error", text: "Failed to load enrollments." });
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchEnrollments();
  }, [fetchEnrollments]);

  const activeEnrolled = enrollments.filter((e) => e.status === "enrolled");
  const activeWaitlisted = enrollments.filter((e) => e.status === "waitlisted");

  const handleCancel = async (courseId, courseTitle, isWaitlist) => {
    const actionLabel = isWaitlist ? "cancel your waitlist position for" : "drop your enrollment from";
    if (!window.confirm(`Are you sure you want to ${actionLabel} "${courseTitle}"?`)) {
      return;
    }

    setCancellingId(courseId);
    setActionMessage(null);

    try {
      await api.delete(`/courses/${courseId}/enrollment`);
      setActionMessage({
        type: "success",
        text: `Successfully cancelled ${isWaitlist ? "waitlist position" : "enrollment"} for "${courseTitle}".`,
      });
      await fetchEnrollments();
    } catch (err) {
      const msg =
        err.response?.data?.errors?.enrollment?.[0] ||
        err.response?.data?.message ||
        "Failed to cancel enrollment.";
      setActionMessage({ type: "error", text: msg });
    } finally {
      setCancellingId(null);
    }
  };

  const currentList = activeTab === "enrolled" ? activeEnrolled : activeWaitlisted;

  return (
    <div className="space-y-8">
      {/* Header */}
      <div>
        <h1 className="text-2xl font-bold tracking-tight text-slate-900">My Enrollments</h1>
        <p className="mt-1 text-sm text-slate-500">
          Manage your active course enrollments and track your waitlist status.
        </p>
      </div>

      {/* Action Notification */}
      {actionMessage && (
        <div
          className={`flex items-center justify-between rounded-xl p-4 border text-sm transition-all shadow-sm ${
            actionMessage.type === "success"
              ? "bg-emerald-50 border-emerald-200 text-emerald-800"
              : "bg-red-50 border-red-200 text-red-800"
          }`}
        >
          <div className="flex items-center gap-3">
            {actionMessage.type === "success" ? (
              <CheckCircle2 className="h-5 w-5 text-emerald-600 shrink-0" />
            ) : (
              <AlertCircle className="h-5 w-5 text-red-600 shrink-0" />
            )}
            <span className="font-medium">{actionMessage.text}</span>
          </div>
          <button
            onClick={() => setActionMessage(null)}
            className="text-xs font-semibold hover:underline opacity-80"
          >
            Dismiss
          </button>
        </div>
      )}

      {/* Tabs */}
      <div className="flex border-b border-slate-200">
        <button
          onClick={() => setActiveTab("enrolled")}
          className={`flex items-center gap-2 border-b-2 px-5 py-3 text-sm font-semibold transition-colors ${
            activeTab === "enrolled"
              ? "border-blue-600 text-blue-600"
              : "border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700"
          }`}
        >
          <BookmarkCheck className="h-4 w-4" />
          <span>Current Enrollments</span>
          <span className="ml-1 rounded-full bg-blue-100 px-2 py-0.5 text-xs text-blue-700">
            {activeEnrolled.length}
          </span>
        </button>

        <button
          onClick={() => setActiveTab("waitlisted")}
          className={`flex items-center gap-2 border-b-2 px-5 py-3 text-sm font-semibold transition-colors ${
            activeTab === "waitlisted"
              ? "border-blue-600 text-blue-600"
              : "border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700"
          }`}
        >
          <Clock className="h-4 w-4" />
          <span>Waitlist</span>
          <span className="ml-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-700">
            {activeWaitlisted.length}
          </span>
        </button>
      </div>

      {/* Content Cards */}
      {loading ? (
        <div className="flex h-48 items-center justify-center">
          <div className="h-8 w-8 animate-spin rounded-full border-4 border-blue-600 border-t-transparent" />
        </div>
      ) : currentList.length === 0 ? (
        <div className="rounded-2xl border border-dashed border-slate-300 bg-white p-12 text-center">
          <BookOpen className="mx-auto h-10 w-10 text-slate-300" />
          <h3 className="mt-3 text-sm font-semibold text-slate-800">
            {activeTab === "enrolled" ? "No active enrollments" : "No waitlisted courses"}
          </h3>
          <p className="mt-1 text-xs text-slate-500">
            {activeTab === "enrolled"
              ? "You are not enrolled in any courses yet."
              : "You are not currently waiting for a seat in any courses."}
          </p>
          <Link
            to="/courses"
            className="mt-4 inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-blue-700 transition-colors"
          >
            Browse Available Courses
          </Link>
        </div>
      ) : (
        <div className="space-y-4">
          {currentList.map((item) => {
            const course = item.course;
            const isWaitlist = item.status === "waitlisted";
            const isCancelling = cancellingId === course.id;

            return (
              <div
                key={item.id}
                className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition-all hover:border-slate-300"
              >
                <div className="flex items-start gap-4">
                  <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-slate-900 text-white font-bold text-sm">
                    {course.title.slice(0, 2).toUpperCase()}
                  </div>
                  <div className="space-y-1">
                    <div className="flex items-center gap-2.5">
                      <h3 className="text-base font-bold text-slate-900">{course.title}</h3>
                      {isWaitlist ? (
                        <span className="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-800 border border-amber-200">
                          <Clock className="h-3 w-3" />
                          Waitlisted
                        </span>
                      ) : (
                        <span className="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 border border-emerald-200">
                          <CheckCircle2 className="h-3 w-3" />
                          Enrolled
                        </span>
                      )}
                    </div>

                    <div className="flex flex-wrap items-center gap-4 text-xs text-slate-500">
                      <span className="flex items-center gap-1">
                        <Calendar className="h-3.5 w-3.5 text-slate-400" />
                        {isWaitlist
                          ? `Joined waitlist: ${new Date(item.waitlisted_at).toLocaleDateString()}`
                          : `Enrolled: ${new Date(item.enrolled_at || item.created_at).toLocaleDateString()}`}
                      </span>
                      <span>Course capacity: {course.capacity}</span>
                    </div>
                  </div>
                </div>

                <div className="flex items-center gap-2 self-end sm:self-center">
                  <button
                    onClick={() => handleCancel(course.id, course.title, isWaitlist)}
                    disabled={isCancelling}
                    className="flex items-center gap-1.5 rounded-lg border border-red-200 bg-red-50 px-3.5 py-2 text-xs font-semibold text-red-700 transition-colors hover:bg-red-100 hover:border-red-300 focus:outline-none focus:ring-2 focus:ring-red-500/20 disabled:opacity-50"
                  >
                    {isCancelling ? (
                      <div className="h-3.5 w-3.5 animate-spin rounded-full border-2 border-red-600 border-t-transparent" />
                    ) : null}
                    <span>{isWaitlist ? "Cancel Waitlist" : "Cancel Enrollment"}</span>
                  </button>
                </div>
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
}
