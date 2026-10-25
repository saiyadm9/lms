import { useState, useEffect, useCallback } from "react";
import { Link } from "react-router";
import api from "../lib/api";
import { useAuth } from "../features/auth/AuthContext";
import {
  Search,
  BookOpen,
  Users,
  CheckCircle2,
  Clock,
  AlertCircle,
  Sparkles,
} from "lucide-react";

export default function CourseCatalogPage() {
  const { user } = useAuth();
  const [courses, setCourses] = useState([]);
  const [myEnrollments, setMyEnrollments] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [actionMessage, setActionMessage] = useState(null);
  const [actionLoadingId, setActionLoadingId] = useState(null);
  const [searchQuery, setSearchQuery] = useState("");
  const [selectedCategory, setSelectedCategory] = useState("All");

  const categories = ["All", "Full-Stack", "Architecture", "Database", "Algorithms"];

  const loadData = useCallback(async () => {
    try {
      const [coursesRes, enrollmentsRes] = await Promise.all([
        api.get("/courses"),
        user ? api.get("/me/enrollments").catch(() => ({ data: { enrollments: [] } })) : Promise.resolve({ data: { enrollments: [] } }),
      ]);
      setCourses(coursesRes.data.courses || []);
      setMyEnrollments(enrollmentsRes.data.enrollments || []);
      setError("");
    } catch {
      setError("Failed to load courses. Please refresh.");
    } finally {
      setLoading(false);
    }
  }, [user]);

  useEffect(() => {
    loadData();
  }, [loadData]);

  // Map of course_id -> active enrollment status for current user
  const enrollmentStatusMap = myEnrollments.reduce((acc, item) => {
    acc[item.course_id] = item.status;
    return acc;
  }, {});

  const handleEnrollOrWaitlist = async (courseId) => {
    setActionLoadingId(courseId);
    setActionMessage(null);
    setError("");

    try {
      const res = await api.post(`/courses/${courseId}/enrollment`);
      setActionMessage({
        type: "success",
        text: res.data.message || "Enrollment request processed.",
      });
      await loadData();
    } catch (err) {
      const msg =
        err.response?.data?.errors?.course?.[0] ||
        err.response?.data?.message ||
        "Failed to submit enrollment request.";
      setActionMessage({ type: "error", text: msg });
    } finally {
      setActionLoadingId(null);
    }
  };

  const filteredCourses = courses.filter((course) => {
    const matchesSearch = course.title.toLowerCase().includes(searchQuery.toLowerCase());
    if (selectedCategory === "All") return matchesSearch;
    return matchesSearch && course.title.toLowerCase().includes(selectedCategory.toLowerCase());
  });

  return (
    <div className="space-y-8">
      {/* Page Header */}
      <div>
        <h1 className="text-2xl font-bold tracking-tight text-slate-900">Browse Courses</h1>
        <p className="mt-1 text-sm text-slate-500">
          Explore our published courses and enroll when seats are available or join the waitlist.
        </p>
      </div>

      {/* Action Notification Alert */}
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

      {error && (
        <div className="flex items-center gap-3 rounded-xl bg-red-50 border border-red-200 p-4 text-sm text-red-700">
          <AlertCircle className="h-5 w-5 shrink-0 text-red-500" />
          <span>{error}</span>
        </div>
      )}

      {/* Filter Tabs & Search Bar */}
      <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div className="flex flex-wrap gap-2">
          {categories.map((category) => (
            <button
              key={category}
              onClick={() => setSelectedCategory(category)}
              className={`rounded-lg px-3.5 py-1.5 text-xs font-medium transition-colors ${
                selectedCategory === category
                  ? "bg-blue-600 text-white shadow-sm"
                  : "bg-white text-slate-600 hover:bg-slate-100 border border-slate-200"
              }`}
            >
              {category}
            </button>
          ))}
        </div>

        <div className="relative w-full sm:w-72">
          <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
          <input
            type="text"
            placeholder="Search courses..."
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            className="w-full rounded-lg border border-slate-200 bg-white py-2 pl-9 pr-3 text-xs text-slate-900 shadow-sm focus:border-blue-600 focus:outline-none focus:ring-1 focus:ring-blue-600"
          />
        </div>
      </div>

      {/* Course Cards Grid */}
      {loading ? (
        <div className="flex h-64 items-center justify-center">
          <div className="flex flex-col items-center gap-3">
            <div className="h-8 w-8 animate-spin rounded-full border-4 border-blue-600 border-t-transparent" />
            <p className="text-xs font-medium text-slate-500">Loading published courses...</p>
          </div>
        </div>
      ) : filteredCourses.length === 0 ? (
        <div className="rounded-2xl border border-dashed border-slate-300 bg-white p-12 text-center">
          <BookOpen className="mx-auto h-10 w-10 text-slate-300" />
          <h3 className="mt-3 text-sm font-semibold text-slate-800">No courses found</h3>
          <p className="mt-1 text-xs text-slate-500">
            {searchQuery
              ? "Try adjusting your search criteria or category filter."
              : "No published courses are available at this moment."}
          </p>
        </div>
      ) : (
        <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
          {filteredCourses.map((course) => {
            const userStatus = enrollmentStatusMap[course.id];
            const isFull = course.available_seats === 0;
            const isProcessing = actionLoadingId === course.id;

            return (
              <div
                key={course.id}
                className="group flex flex-col justify-between overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm transition-all hover:shadow-md hover:border-slate-300"
              >
                {/* Course Card Banner */}
                <div className="h-32 bg-gradient-to-br from-slate-800 via-blue-900 to-slate-900 p-5 flex flex-col justify-between text-white relative">
                  <div className="flex items-center justify-between">
                    <span className="rounded-full bg-white/20 backdrop-blur-sm px-2.5 py-0.5 text-[11px] font-medium text-white flex items-center gap-1">
                      <Sparkles className="h-3 w-3 text-amber-300" />
                      Course
                    </span>
                    {isFull ? (
                      <span className="rounded-full bg-red-500/90 backdrop-blur-sm px-2.5 py-0.5 text-[11px] font-semibold text-white">
                        Full ({course.waitlisted_count} waitlisted)
                      </span>
                    ) : (
                      <span className="rounded-full bg-emerald-500/90 backdrop-blur-sm px-2.5 py-0.5 text-[11px] font-semibold text-white">
                        Available {course.available_seats} {course.available_seats === 1 ? "seat" : "seats"}
                      </span>
                    )}
                  </div>
                  <h3 className="text-base font-bold text-white line-clamp-1">{course.title}</h3>
                </div>

                {/* Course Details Content */}
                <div className="p-5 flex-1 flex flex-col justify-between">
                  <div>
                    <div className="flex items-center justify-between text-xs text-slate-500 mb-4 pb-3 border-b border-slate-100">
                      <span className="flex items-center gap-1.5 font-medium">
                        <Users className="h-3.5 w-3.5 text-slate-400" />
                        {course.enrolled_count} / {course.capacity} enrolled
                      </span>
                      <span className="text-slate-400">Total capacity: {course.capacity}</span>
                    </div>

                    <p className="text-xs text-slate-600 line-clamp-2 mb-4 leading-relaxed">
                      Comprehensive hands-on curriculum covering key software engineering patterns, live tests, and real-world system architecture.
                    </p>
                  </div>

                  {/* Actions */}
                  <div className="pt-2">
                    {userStatus === "enrolled" ? (
                      <div className="flex items-center justify-between gap-2">
                        <span className="inline-flex flex-1 items-center justify-center gap-1.5 rounded-lg bg-emerald-50 py-2 text-xs font-semibold text-emerald-700 border border-emerald-200">
                          <CheckCircle2 className="h-3.5 w-3.5" />
                          Enrolled
                        </span>
                        <Link
                          to="/my-enrollments"
                          className="rounded-lg border border-slate-200 px-3 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50 transition-colors"
                        >
                          View
                        </Link>
                      </div>
                    ) : userStatus === "waitlisted" ? (
                      <div className="flex items-center justify-between gap-2">
                        <span className="inline-flex flex-1 items-center justify-center gap-1.5 rounded-lg bg-amber-50 py-2 text-xs font-semibold text-amber-800 border border-amber-200">
                          <Clock className="h-3.5 w-3.5" />
                          On Waitlist
                        </span>
                        <Link
                          to="/my-enrollments"
                          className="rounded-lg border border-slate-200 px-3 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50 transition-colors"
                        >
                          View
                        </Link>
                      </div>
                    ) : isFull ? (
                      <button
                        onClick={() => handleEnrollOrWaitlist(course.id)}
                        disabled={isProcessing}
                        className="flex w-full items-center justify-center gap-2 rounded-lg border-2 border-blue-600 bg-white py-2 px-4 text-xs font-semibold text-blue-600 transition-colors hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-600/30 disabled:opacity-50"
                      >
                        {isProcessing ? (
                          <div className="h-3.5 w-3.5 animate-spin rounded-full border-2 border-blue-600 border-t-transparent" />
                        ) : (
                          <Clock className="h-3.5 w-3.5" />
                        )}
                        <span>Join Waitlist</span>
                      </button>
                    ) : (
                      <button
                        onClick={() => handleEnrollOrWaitlist(course.id)}
                        disabled={isProcessing}
                        className="flex w-full items-center justify-center gap-2 rounded-lg bg-blue-600 py-2 px-4 text-xs font-semibold text-white shadow-sm transition-colors hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-600/30 disabled:opacity-50"
                      >
                        {isProcessing ? (
                          <div className="h-3.5 w-3.5 animate-spin rounded-full border-2 border-white border-t-transparent" />
                        ) : (
                          <BookOpen className="h-3.5 w-3.5" />
                        )}
                        <span>Enroll Now</span>
                      </button>
                    )}
                  </div>
                </div>
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
}
