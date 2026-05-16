import { FormEvent, useCallback, useEffect, useState } from 'react';
import { apiRequest, ApiRequestError } from '../../api/client';
import type { AdminSetting, SettingsGrouped } from '../../api/types';
import { AdminFeedback } from '../../components/admin/AdminFeedback';
import { AdminPageHeader } from '../../components/admin/AdminPageHeader';

export function AdminSettingsPage() {
  const [groups, setGroups] = useState<SettingsGrouped>({});
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);
  const [draft, setDraft] = useState<Record<string, string>>({});

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const data = await apiRequest<SettingsGrouped>('/settings');
      setGroups(data);
      const initial: Record<string, string> = {};
      Object.values(data)
        .flat()
        .forEach((s) => {
          if (s.key !== 'smtp_password' && s.key !== 'store_logo') {
            initial[s.key] = s.value ?? '';
          }
        });
      setDraft(initial);
    } catch (err) {
      setGroups({});
      setError(err instanceof ApiRequestError ? err.message : 'Failed to load settings');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  const onSubmit = async (event: FormEvent) => {
    event.preventDefault();
    setSaving(true);
    setError(null);
    setSuccess(null);
    const payload: Record<string, string> = {};
    for (const [key, value] of Object.entries(draft)) {
      payload[key] = value;
    }
    try {
      await apiRequest('/settings', {
        method: 'PUT',
        body: JSON.stringify(payload),
      });
      setSuccess('Settings saved.');
      await load();
    } catch (err) {
      setError(err instanceof ApiRequestError ? err.message : 'Save failed');
    } finally {
      setSaving(false);
    }
  };

  const renderField = (setting: AdminSetting) => {
    if (setting.key === 'store_logo' || setting.type === 'image') {
      return <span className="muted">Image setting — use API upload</span>;
    }
    if (setting.key === 'smtp_password') {
      return <span className="muted">Hidden for security</span>;
    }
    return (
      <input
        value={draft[setting.key] ?? ''}
        onChange={(e) => setDraft({ ...draft, [setting.key]: e.target.value })}
      />
    );
  };

  return (
    <div className="page">
      <AdminPageHeader
        eyebrow="Configuration"
        title="Settings"
        description="Site settings from GET /settings. Admin-only updates."
      />
      <AdminFeedback error={error} success={success} />
      {loading ? <p className="muted">Loading settings…</p> : null}
      {!loading ? (
        <form onSubmit={onSubmit}>
          {Object.entries(groups).map(([groupName, settings]) => (
            <section key={groupName} className="card">
              <h2>{groupName}</h2>
              <div className="admin-form admin-form-settings">
                {settings.map((s) => (
                  <label key={s.id}>
                    {s.label ?? s.key}
                    {renderField(s)}
                  </label>
                ))}
              </div>
            </section>
          ))}
          <button type="submit" className="btn btn-primary" disabled={saving}>
            {saving ? 'Saving…' : 'Save all'}
          </button>
        </form>
      ) : null}
    </div>
  );
}
