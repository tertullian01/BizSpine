import { useEffect, useState } from 'react';
import { apiRequest, ApiRequestError } from '../api/client';
import type { Store } from '../api/types';

export function StoresPage() {
  const [stores, setStores] = useState<Store[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const load = async () => {
      setLoading(true);
      setError(null);
      try {
        const data = await apiRequest<Store[]>('/stores');
        setStores(data);
      } catch (err) {
        setStores([]);
        setError(err instanceof ApiRequestError ? err.message : 'Failed to load stores');
      } finally {
        setLoading(false);
      }
    };
    void load();
  }, []);

  return (
    <div className="page">
      <header className="page-header">
        <h1>Stores</h1>
        <p className="muted">Physical locations served by the BizSpine API.</p>
      </header>

      {loading ? <p className="muted">Loading stores…</p> : null}
      {error ? (
        <div className="alert alert-error" role="alert">
          {error}
        </div>
      ) : null}

      {!loading && !error && stores.length === 0 ? (
        <p className="muted">No stores configured yet.</p>
      ) : null}

      <ul className="store-list">
        {stores.map((store) => (
          <li key={store.id} className="card store-card">
            <h2>{store.name}</h2>
            {store.description ? <p>{store.description}</p> : null}
            {store.currency_symbol ? (
              <p className="muted">Currency: {store.currency_symbol}</p>
            ) : null}
          </li>
        ))}
      </ul>
    </div>
  );
}
