import React from 'react';

const variants = {
  info: 'bg-blue-100 text-blue-700',
  success: 'bg-green-100 text-green-700',
  warning: 'bg-yellow-100 text-yellow-700',
  error: 'bg-red-100 text-red-700',
  primary: 'bg-blue-100 text-blue-700',
  secondary: 'bg-gray-100 text-gray-700'
};

const sizes = {
  sm: 'px-1.5 py-0.5 text-xs',
  md: 'px-2 py-1 text-sm',
  lg: 'px-2.5 py-1.5 text-base'
};

const Badge = ({ 
  children, 
  variant = 'primary',
  size = 'sm',
  className = '',
  ...props 
}) => {
  const variantClass = variants[variant] || variants.primary;
  const sizeClass = sizes[size] || sizes.sm;

  return (
    <span
      className={`inline-flex items-center justify-center font-medium rounded-full ${variantClass} ${sizeClass} ${className}`}
      {...props}
    >
      {children}
    </span>
  );
};

export { Badge };
export default Badge;
