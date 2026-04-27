import React from 'react';

const Textarea = React.forwardRef(({ className = '', error, ...props }, ref) => {
  return (
    <div className="relative">
      <textarea
        className={`
          w-full px-3 py-2 text-gray-700 border rounded-lg focus:outline-none
          ${error ? 'border-red-500' : 'border-gray-300'}
          ${error ? 'focus:border-red-500' : 'focus:border-blue-500'}
          ${className}
        `}
        ref={ref}
        {...props}
      />
      {error && (
        <p className="mt-1 text-sm text-red-500">
          {error}
        </p>
      )}
    </div>
  );
});

Textarea.displayName = 'Textarea';

export { Textarea };
export default Textarea;
