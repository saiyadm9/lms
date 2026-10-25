import { useState, useEffect } from "react";
import api from "../../lib/api";
import { X, AlertCircle, Sparkles } from "lucide-react";

export default function CourseModal({ isOpen, onClose, course, onSaved }) {
  const isEditing = Boolean(course);

  const [title, setTitle] = useState("");
  const [capacity, setCapacity] = useState(10);
  const [isPublished, setIsPublished] = useState(true);
  const [error, setError] = useState("");
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    if (course) {
      setTitle(course.title);
      setCapacity(course.capacity);
      setIsPublished(Boolean(course.is_published));
    } else {
      setTitle("");
      setCapacity(10);
      setIsPublished(true);
    }
    setError("");
  }, [course, isOpen]);

  if (!isOpen) return null;

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError("");

    const parsedCapacity = parseInt(capacity, 10);
    if (isNaN(parsedCapacity) || parsedCapacity < 1) {
      setError("Capacity must be a positive integer greater than or equal to 1.");
      return;
    }

    setSubmitting(true);
    try {
      if (isEditing) {
        await api.patch(`/admin/courses/${course.id}`, {
          title,
          capacity: parsedCapacity,
          is_published: isPublished,
        });
      } else {
        await api.post("/admin/courses", {
          title,
          capacity: parsedCapacity,
          is_published: isPublished,
        });
      }

      onSaved();
      onClose();
    } catch (err) {
      const msg =
        err.response?.data?.errors?.capacity?.[0] ||
        err.response?.data?.errors?.title?.[0] ||
        err.response?.data?.message ||
        "Failed to save course.";
      setError(msg);
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4">
      <div className="w-full max-w-lg rounded-2xl bg-white shadow-2xl border border-slate-200 overflow-hidden">
        {/* Modal Header */}
        <div className="flex items-center justify-between border-b border-slate-100 px-6 py-4">
          <div className="flex items-center gap-2">
            <Sparkles className="h-4 w-4 text-blue-600" />
            <h2 className="text-base font-bold text-slate-900">
              {isEditing ? "Edit Course & Capacity" : "Create New Course"}
            </h2>
          </div>
          <button
            onClick={onClose}
            className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
          >
            <X className="h-4 w-4" />
          </button>
        </div>

        {/* Modal Form */}
        <form onSubmit={handleSubmit} className="p-6 space-y-5">
          {error && (
            <div className="flex items-start gap-2.5 rounded-lg bg-red-50 p-3 text-xs text-red-700 border border-red-200">
              <AlertCircle className="h-4 w-4 shrink-0 text-red-500 mt-0.5" />
              <span>{error}</span>
            </div>
          )}

          <div>
            <label className="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
              Course Title *
            </label>
            <input
              type="text"
              required
              value={title}
              onChange={(e) => setTitle(e.target.value)}
              placeholder="e.g. Distributed Systems & Concurrency"
              className="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm text-slate-900 shadow-sm focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-600/20"
            />
          </div>

          <div>
            <label className="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
              Capacity (Positive Integer) *
            </label>
            <input
              type="number"
              min="1"
              step="1"
              required
              value={capacity}
              onChange={(e) => setCapacity(e.target.value)}
              className="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm text-slate-900 shadow-sm focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-600/20"
            />
            {isEditing && (
              <p className="mt-1 text-[11px] text-slate-500">
                Increasing capacity automatically promotes waitlisted students in FIFO order. Decreasing capacity preserves existing enrollments.
              </p>
            )}
          </div>

          <div>
            <label className="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
              Publication Status
            </label>
            <select
              value={isPublished ? "published" : "draft"}
              onChange={(e) => setIsPublished(e.target.value === "published")}
              className="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm text-slate-900 shadow-sm focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-600/20"
            >
              <option value="published">Published (Visible in student catalog)</option>
              <option value="draft">Draft (Unpublished, hidden from student catalog)</option>
            </select>
          </div>

          {/* Modal Actions */}
          <div className="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
            <button
              type="button"
              onClick={onClose}
              disabled={submitting}
              className="rounded-lg border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition-colors"
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={submitting}
              className="flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-blue-700 transition-colors disabled:opacity-50"
            >
              {submitting && (
                <div className="h-3.5 w-3.5 animate-spin rounded-full border-2 border-white border-t-transparent" />
              )}
              <span>{isEditing ? "Save Changes" : "Create Course"}</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
