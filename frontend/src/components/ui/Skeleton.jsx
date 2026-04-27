import React from 'react';

const Skeleton = ({ 
  className = '',
  width,
  height,
  circle = false,
  lines = 1,
  ...props
}) => {
  const baseClasses = 'animate-pulse bg-gray-200 rounded';
  const circleClasses = circle ? 'rounded-full' : '';
  
  const skeletonClasses = [
    baseClasses,
    circleClasses,
    className
  ].filter(Boolean).join(' ');

  const style = {
    width: width,
    height: height
  };

  if (lines > 1) {
    return (
      <div className="space-y-2">
        {Array.from({ length: lines }).map((_, index) => (
          <div
            key={index}
            className={skeletonClasses}
            style={index === lines - 1 ? { ...style, width: '75%' } : style}
            {...props}
          />
        ))}
      </div>
    );
  }

  return (
    <div
      className={skeletonClasses}
      style={style}
      {...props}
    />
  );
};

export { Skeleton };
export default Skeleton;
