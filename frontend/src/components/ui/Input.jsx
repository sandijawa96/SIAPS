import React, { forwardRef } from 'react';

const Input = forwardRef(({ 
  type = 'text',
  className = '',
  error,
  icon,
  ...props 
}, ref) => {
  const baseClasses = 'block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm';
  const focusClasses = 'focus:border-blue-500 focus:ring-1 focus:ring-blue-500';
  const errorClasses = error ? 'border-red-500 focus:border-red-500 focus:ring-red-500' : '';
  const disabledClasses = props.disabled ? 'bg-gray-50 text-gray-500 cursor-not-allowed' : '';
  const iconClasses = icon ? 'pl-10' : '';
  
  const inputClasses = [
    baseClasses,
    focusClasses,
    errorClasses,
    disabledClasses,
    iconClasses,
    className
  ].filter(Boolean).join(' ');

  return (
    <div className="relative">
      {icon && (
        <div className="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
          {icon}
        </div>
      )}
      <input
        ref={ref}
        type={type}
        className={inputClasses}
        {...props}
      />
      {error && (
        <p className="mt-1 text-xs text-red-500">{error}</p>
      )}
    </div>
  );
});

Input.displayName = 'Input';

export { Input };
export default Input;
