import React, { forwardRef } from 'react';
import { ChevronDown } from 'lucide-react';

const Select = forwardRef(({ 
  options = [],
  placeholder = 'Pilih...',
  className = '',
  error,
  value,
  onChange,
  ...props 
}, ref) => {
  const baseClasses = 'block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm appearance-none';
  const focusClasses = 'focus:border-blue-500 focus:ring-1 focus:ring-blue-500';
  const errorClasses = error ? 'border-red-500 focus:border-red-500 focus:ring-red-500' : '';
  const disabledClasses = props.disabled ? 'bg-gray-50 text-gray-500 cursor-not-allowed' : '';
  
  const selectClasses = [
    baseClasses,
    focusClasses,
    errorClasses,
    disabledClasses,
    className
  ].filter(Boolean).join(' ');

  const handleChange = (e) => {
    if (onChange) {
      onChange(e.target.value);
    }
  };

  return (
    <div className="relative">
      <select
        ref={ref}
        value={value || ''}
        onChange={handleChange}
        className={selectClasses}
        {...props}
      >
        {placeholder && (
          <option value="" disabled>
            {placeholder}
          </option>
        )}
        {options.map((option) => (
          <option key={option.value} value={option.value}>
            {option.label}
          </option>
        ))}
      </select>
      <div className="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
        <ChevronDown className="w-4 h-4 text-gray-400" />
      </div>
      {error && (
        <p className="mt-1 text-xs text-red-500">{error}</p>
      )}
    </div>
  );
});

Select.displayName = 'Select';

export { Select };
export default Select;
