import { useCallback, useEffect, useState } from 'react';
import { apiRequest, ApiRequestError } from '../../api/client';
import type { AdminReview } from '../../api/types';
import { AdminFeedback } from '../../components/admin/AdminFeedback';
import { AdminPageHeader } from '../../components/admin/AdminPageHeader';

export function AdminReviewsPage() {
  const [reviews, setReviews] = useState<AdminReview[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [busyId, setBusyId] = useState<number | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const data = await apiRequest<AdminReview[]>('/reviews/pending');
      setReviews(data);
    } catch (err) {
      setReviews([]);
      setError(err instanceof ApiRequestError ? err.message : 'Failed to load reviews');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  const publish = async (id: number) => {
    setBusyId(id);
    try {
      await apiRequest(`/reviews/${id}/publish`, { method: 'POST' });
      await load();
    } catch (err) {
      setError(err instanceof ApiRequestError ? err.message : 'Publish failed');
    } finally {
      setBusyId(null);
    }
  };

  return (
    <div className="page">
      <AdminPageHeader eyebrow="Moderation" title="Pending reviews" description="Unpublished product reviews awaiting approval." />
      <AdminFeedback error={error} />
      {loading ? <p className="muted">Loading…</p> : null}
      {!loading ? (
        <section className="card">
          {reviews.length === 0 ? (
            <p className="muted">No pending reviews.</p>
          ) : (
            <ul className="admin-list">
              {reviews.map((r) => (
                <li key={r.id} className="admin-list-item">
                  <div className="admin-list-main">
                    <strong>{r.product_name ?? `Product #${r.product_id}`}</strong>
                    <span className="badge">{r.rating}★</span>
                    <span className="muted">{r.user_email ?? 'Guest'}</span>
                    {r.review_text ? <p>{r.review_text}</p> : null}
                  </div>
                  <button
                    type="button"
                    className="btn btn-primary btn-sm"
                    disabled={busyId === r.id}
                    onClick={() => void publish(r.id)}
                  >
                    Publish
                  </button>
                </li>
              ))}
            </ul>
          )}
        </section>
      ) : null}
    </div>
  );
}
