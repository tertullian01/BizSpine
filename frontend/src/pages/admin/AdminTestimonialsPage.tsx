import { useCallback, useEffect, useState } from 'react';
import { apiRequest, ApiRequestError } from '../../api/client';
import type { AdminTestimonial, PaginatedTestimonials } from '../../api/types';
import { AdminFeedback } from '../../components/admin/AdminFeedback';
import { AdminPageHeader } from '../../components/admin/AdminPageHeader';

export function AdminTestimonialsPage() {
  const [items, setItems] = useState<AdminTestimonial[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [busyId, setBusyId] = useState<number | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const result = await apiRequest<PaginatedTestimonials>('/testimonials/admin?limit=100');
      setItems(result.data);
    } catch (err) {
      setItems([]);
      setError(err instanceof ApiRequestError ? err.message : 'Failed to load testimonials');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  const setPublished = async (id: number, publish: boolean) => {
    setBusyId(id);
    try {
      await apiRequest(`/testimonials/${id}/${publish ? 'publish' : 'unpublish'}`, { method: 'POST' });
      await load();
    } catch (err) {
      setError(err instanceof ApiRequestError ? err.message : 'Update failed');
    } finally {
      setBusyId(null);
    }
  };

  const onDelete = async (id: number) => {
    if (!window.confirm('Delete this testimonial?')) return;
    try {
      await apiRequest(`/testimonials/${id}`, { method: 'DELETE' });
      await load();
    } catch (err) {
      setError(err instanceof ApiRequestError ? err.message : 'Delete failed');
    }
  };

  return (
    <div className="page">
      <AdminPageHeader eyebrow="Marketing" title="Testimonials" description="Moderate customer testimonials." />
      <AdminFeedback error={error} />
      {loading ? <p className="muted">Loading…</p> : null}
      {!loading ? (
        <section className="card">
          {items.length === 0 ? (
            <p className="muted">No testimonials yet.</p>
          ) : (
            <ul className="admin-list">
              {items.map((t) => (
                <li key={t.id} className="admin-list-item">
                  <div className="admin-list-main">
                    <strong>{t.customer_name ?? 'Anonymous'}</strong>
                    {t.rating != null ? <span className="badge">{t.rating}★</span> : null}
                    <span className={`badge${t.published ? ' badge-ok' : ''}`}>
                      {t.published ? 'Published' : 'Draft'}
                    </span>
                    <p className="muted">{t.content}</p>
                  </div>
                  <div className="admin-row-actions">
                    <button
                      type="button"
                      className="btn btn-secondary btn-sm"
                      disabled={busyId === t.id}
                      onClick={() => void setPublished(t.id, !t.published)}
                    >
                      {t.published ? 'Unpublish' : 'Publish'}
                    </button>
                    <button type="button" className="btn btn-ghost btn-sm btn-danger-text" onClick={() => void onDelete(t.id)}>
                      Delete
                    </button>
                  </div>
                </li>
              ))}
            </ul>
          )}
        </section>
      ) : null}
    </div>
  );
}
