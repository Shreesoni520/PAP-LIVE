export function formatDisplayName(raw: string | null | undefined): string {
  const nome = (raw || "").trim();
  if (!nome) return "Utilizador";

  const parts = nome.split(/\s+/);
  const first = parts[0]
    ? parts[0].charAt(0).toUpperCase() + parts[0].slice(1).toLowerCase()
    : "Utilizador";

  if (parts.length > 1) {
    const last = parts[parts.length - 1];
    const lastFormatted =
      last.charAt(0).toUpperCase() + last.slice(1).toLowerCase();
    return `${first} ${lastFormatted}`;
  }

  return first;
}

export function initialsFromName(raw: string | null | undefined): string {
  const nome = formatDisplayName(raw);
  const parts = nome.split(/\s+/);
  if (parts.length >= 2) {
    return `${parts[0][0] ?? ""}${parts[1][0] ?? ""}`.toUpperCase();
  }
  return (parts[0]?.slice(0, 2) || "U").toUpperCase();
}
