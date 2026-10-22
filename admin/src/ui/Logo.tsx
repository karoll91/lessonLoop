/** LessonLoop logo (public/logo.png, made by deploy/make-icons.php). */
export function Logo({ size = 32, className }: { size?: number; className?: string }) {
  return <img src={`${import.meta.env.BASE_URL}logo.png`} width={size} height={size} alt="LessonLoop" className={className} />
}
