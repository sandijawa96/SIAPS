import React from 'react';

const getButtonClasses = (variant = 'primary', size = 'md', fullWidth = false, disabled = false, className = '') => {
  const baseClasses = "inline-flex items-center justify-center rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2";
  
  const variantClasses = {
    primary: "bg-blue-600 text-white hover:bg-blue-700 focus:ring-blue-500",
    secondary: "bg-gray-200 text-gray-900 hover:bg-gray-300 focus:ring-gray-500",
    ghost: "hover:bg-blue-600/50 text-white focus:ring-blue-400",
    danger: "bg-red-600 text-white hover:bg-red-700 focus:ring-red-500",
    outline: "border-2 border-blue-600 text-blue-600 hover:bg-blue-50 focus:ring-blue-500",
  };
  
  const sizeClasses = {
    sm: "px-2 py-1 text-sm",
    md: "px-3 py-2",
    lg: "px-4 py-2 text-lg",
    icon: "p-2",
  };
  
  const classes = [
    baseClasses,
    variantClasses[variant],
    sizeClasses[size],
    fullWidth ? "w-full" : "",
    disabled ? "opacity-50 cursor-not-allowed" : "",
    className
  ].filter(Boolean).join(" ");
  
  return classes;
};

const Button = React.forwardRef(({ 
  className,
  variant = 'primary',
  size = 'md',
  fullWidth = false,
  disabled = false,
  children,
  ...props 
}, ref) => {
  return (
    <button
      className={getButtonClasses(variant, size, fullWidth, disabled, className)}
      disabled={disabled}
      ref={ref}
      {...props}
    >
      {children}
    </button>
  );
});

Button.displayName = 'Button';

export { Button };
export default Button;
