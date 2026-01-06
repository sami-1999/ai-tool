// Reusable loading spinner component
interface LoadingSpinnerProps {
  size?: 'sm' | 'md' | 'lg';
  className?: string;
}

export default function LoadingSpinner({ size = 'lg', className = '' }: LoadingSpinnerProps) {
  const sizeClasses = {
    sm: 'h-8 w-8',
    md: 'h-16 w-16', 
    lg: 'h-32 w-32'
  };

  return (
    <div className={`min-h-screen flex items-center justify-center ${className}`}>
      <div className={`animate-spin rounded-full ${sizeClasses[size]} border-b-2 border-indigo-600`}></div>
    </div>
  );
}
