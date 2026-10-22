import type { SVGProps } from 'react'

function Icon({ size = 18, ...props }: SVGProps<SVGSVGElement> & { size?: number }) {
  return (
    <svg
      width={size}
      height={size}
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth={2}
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
      {...props}
    />
  )
}

export const SearchIcon = () => (
  <Icon size={16}>
    <circle cx="11" cy="11" r="7" />
    <path d="M20 20l-3.5-3.5" />
  </Icon>
)

export const PlusIcon = () => (
  <Icon size={16}>
    <path d="M12 5v14M5 12h14" />
  </Icon>
)

export const UploadIcon = () => (
  <Icon size={16}>
    <path d="M12 16V4M7 9l5-5 5 5M4 20h16" />
  </Icon>
)

export const DownloadIcon = () => (
  <Icon size={16}>
    <path d="M12 4v12M7 11l5 5 5-5M4 20h16" />
  </Icon>
)

export const EditIcon = () => (
  <Icon size={16}>
    <path d="M4 20h4L19 9l-4-4L4 16z" />
  </Icon>
)

export const TrashIcon = () => (
  <Icon size={16}>
    <path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3" />
  </Icon>
)

export const UpIcon = () => (
  <Icon size={16}>
    <path d="M18 15l-6-6-6 6" />
  </Icon>
)

export const DownIcon = () => (
  <Icon size={16}>
    <path d="M6 9l6 6 6-6" />
  </Icon>
)

export const LogoutIcon = () => (
  <Icon size={16}>
    <path d="M15 4h4v16h-4M10 8l-4 4 4 4M6 12h11" />
  </Icon>
)

export const SunIcon = () => (
  <Icon size={16}>
    <circle cx="12" cy="12" r="4" />
    <path d="M12 2v2M12 20v2M2 12h2M20 12h2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4" />
  </Icon>
)

export const MoonIcon = () => (
  <Icon size={16}>
    <path d="M20 14.5A8 8 0 1 1 9.5 4a6.5 6.5 0 0 0 10.5 10.5z" />
  </Icon>
)

export const AlertIcon = ({ size = 28 }: { size?: number }) => (
  <Icon size={size}>
    <circle cx="12" cy="12" r="9" />
    <path d="M12 8v4M12 16h.01" />
  </Icon>
)

export const EmptyIcon = () => (
  <Icon size={28}>
    <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20V3H6.5A2.5 2.5 0 0 0 4 5.5z" />
    <path d="M4 19.5A2.5 2.5 0 0 0 6.5 22H20v-5" />
  </Icon>
)

export const LockIcon = () => (
  <Icon size={28}>
    <rect x="5" y="11" width="14" height="10" rx="2" />
    <path d="M8 11V7a4 4 0 0 1 8 0v4" />
  </Icon>
)

export const CloseIcon = () => (
  <Icon size={18}>
    <path d="M18 6L6 18M6 6l12 12" />
  </Icon>
)
