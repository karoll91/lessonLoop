/** Two-letter initials for an avatar: "Akmal Karimov" → "AK". */
export function initials(fullName: string): string {
  return fullName
    .trim()
    .split(/\s+/)
    .slice(0, 2)
    .map((part) => part.charAt(0))
    .join('')
    .toUpperCase()
}

/** Seconds as m:ss, e.g. 42 → "0:42", 252 → "4:12". */
export function clock(totalSeconds: number): string {
  const seconds = Math.max(0, Math.floor(totalSeconds))
  return `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`
}

export const difficultyLabel: Record<string, string> = {
  easy: 'Oson',
  medium: "O'rta",
  hard: 'Qiyin',
}
