import { useEffect, useState } from 'react';
import { apiRequest, getApiBaseUrl, ApiRequestError } from '../api/client';
import type { HealthData } from '../api/types';

type LoadState = 'idle' | 'loading' | 'ok' | 'error';

export function HealthStatus() {
  const [state, setState] = useState<LoadState>('idle');
  const [health, setHealth] = useState<HealthData | null>(null);
  const [error, setError] = useState<string | null>(null);

  const check = async () => {
    setState('loading');
    setError(null);
    try {
      const data = await apiRequest<HealthData>('/health');
      setHealth(data);
      setState('ok');
    } catch (err) {
      setHealth(null);
      setState('error');
      if (err instanceof ApiRequestError) {
        setError(err.message);
      } else {
        setError('Could not reach the API. Is the backend running?');
      }
    }
  };

  useEffect(() => {
    void check();
  }, []);

  return (
    <section className="card health-card">
      <div className="card-header">
        <h2>API health</h2>
        <button type="button" className="btn btn-ghost btn-sm" onClick={() => void check()} disabled={state === 'loading'}>
          {state === 'loading' ? 'Checking…' : 'Refresh'}
        </button>
      </div>

      <p className="muted">
        Requests go to <code>{getApiBaseUrl() || '(not configured)'}</code>
        {import.meta.env.DEV ? ' via the Vite dev proxy.' : '.'}
      </p>

      {state === 'ok' && health ? (
        <dl className="health-grid">
          <div>
            <dt>Status</dt>
            <dd>
              <span className="badge badge-ok">{health.status}</span>
            </dd>
          </div>
          <div>
            <dt>Application</dt>
            <dd>{health.app}</dd>
          </div>
          <div>
            <dt>Database</dt>
            <dd>{health.database}</dd>
          </div>
          <div>
            <dt>Server time</dt>
            <dd>{new Date(health.time).toLocaleString()}</dd>
          </div>
        </dl>
      ) : null}

      {state === 'error' ? (
        <div className="alert alert-error" role="alert">
          <p>{error}</p>
          <p className="muted">
            Start the API from the <code>backend</code> folder:{' '}
            <code>php -S localhost:8000 -t public</code>
          </p>
        </div>
      ) : null}
    </section>
  );
}
