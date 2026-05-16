export function AdminFeedback({
  error,
  success,
}: {
  error?: string | null;
  success?: string | null;
}) {
  return (
    <>
      {error ? (
        <div className="alert alert-error" role="alert">
          {error}
        </div>
      ) : null}
      {success ? (
        <div className="alert alert-success" role="status">
          {success}
        </div>
      ) : null}
    </>
  );
}
