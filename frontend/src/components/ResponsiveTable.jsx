import React, { memo, useCallback, useMemo } from 'react';
import {
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Paper,
  Card,
  CardContent,
  Typography,
  Box,
  IconButton,
  useMediaQuery,
  useTheme,
  Skeleton
} from '@mui/material';
import { Edit, Trash2 } from 'lucide-react';

// Memoized mobile card component
const MobileCard = memo(({ item, index, columns, onEdit, onDelete }) => (
  <Card key={index} className="mb-3 shadow-sm hover:shadow-md transition-shadow">
    <CardContent className="p-4">
      {columns.slice(0, -1).map((column, colIndex) => (
        <Box key={colIndex} className="mb-2 last:mb-0">
          <Typography variant="caption" className="text-gray-500 font-medium">
            {column.label}
          </Typography>
          <Typography variant="body2" className="mt-1">
            {column.render ? column.render(item) : item[column.field] || '-'}
          </Typography>
        </Box>
      ))}
      
      {/* Action buttons */}
      <Box className="flex justify-end mt-3 pt-3 border-t border-gray-200">
        {onEdit && (
          <IconButton
            size="small"
            onClick={() => onEdit(item)}
            className="mr-2"
            aria-label="Edit"
          >
                      <Edit size={16} />
          </IconButton>
        )}
        {onDelete && (
          <IconButton
            size="small"
            onClick={() => onDelete(item.id)}
            color="error"
            aria-label="Delete"
          >
                      <Trash2 size={16} />
          </IconButton>
        )}
      </Box>
    </CardContent>
  </Card>
));

MobileCard.displayName = 'MobileCard';

// Memoized table row component
const TableRowComponent = memo(({ item, index, columns }) => (
  <TableRow hover className="cursor-pointer">
    {columns.map((column, colIndex) => (
      <TableCell key={colIndex}>
        {column.render ? column.render(item) : item[column.field] || '-'}
      </TableCell>
    ))}
  </TableRow>
));

TableRowComponent.displayName = 'TableRowComponent';

// Loading skeleton component
const LoadingSkeleton = memo(({ isMobile, columns }) => {
  if (isMobile) {
    return (
      <Box className="space-y-3">
        {[...Array(3)].map((_, index) => (
          <Card key={index}>
            <CardContent className="p-4">
              <Skeleton variant="text" width="40%" height={20} />
              <Skeleton variant="text" width="80%" height={16} className="mt-2" />
              <Skeleton variant="text" width="60%" height={16} className="mt-1" />
            </CardContent>
          </Card>
        ))}
      </Box>
    );
  }

  return (
    <TableContainer component={Paper}>
      <Table>
        <TableHead>
          <TableRow>
            {columns.map((_, index) => (
              <TableCell key={index}>
                <Skeleton variant="text" width="80%" />
              </TableCell>
            ))}
          </TableRow>
        </TableHead>
        <TableBody>
          {[...Array(5)].map((_, rowIndex) => (
            <TableRow key={rowIndex}>
              {columns.map((_, colIndex) => (
                <TableCell key={colIndex}>
                  <Skeleton variant="text" width="70%" />
                </TableCell>
              ))}
            </TableRow>
          ))}
        </TableBody>
      </Table>
    </TableContainer>
  );
});

LoadingSkeleton.displayName = 'LoadingSkeleton';

const ResponsiveTable = memo(({ 
  data = [], 
  columns = [], 
  onEdit, 
  onDelete, 
  loading = false,
  emptyMessage = "Tidak ada data",
  mobileCardRenderer,
  maxHeight = 600
}) => {
  const theme = useTheme();
  const isMobile = useMediaQuery(theme.breakpoints.down('md'));

  // Memoized callbacks
  const handleEdit = useCallback((item) => {
    if (onEdit) onEdit(item);
  }, [onEdit]);

  const handleDelete = useCallback((id) => {
    if (onDelete) onDelete(id);
  }, [onDelete]);

  // Default mobile card renderer jika tidak disediakan
  const defaultMobileCardRenderer = useCallback((item, index) => (
    <MobileCard
      key={item.id || index}
      item={item}
      index={index}
      columns={columns}
      onEdit={handleEdit}
      onDelete={handleDelete}
    />
  ), [columns, handleEdit, handleDelete]);

  // Memoized empty state
  const emptyState = useMemo(() => {
    if (isMobile) {
      return (
        <Card>
          <CardContent className="text-center py-8">
            <Typography color="textSecondary">{emptyMessage}</Typography>
          </CardContent>
        </Card>
      );
    }
    
    return (
      <TableRow>
        <TableCell colSpan={columns.length} align="center" className="py-8">
          <Typography color="textSecondary">{emptyMessage}</Typography>
        </TableCell>
      </TableRow>
    );
  }, [isMobile, emptyMessage, columns.length]);

  // Show loading skeleton
  if (loading) {
    return <LoadingSkeleton isMobile={isMobile} columns={columns} />;
  }

  // Render mobile view
  if (isMobile) {
    return (
      <Box className="space-y-3" style={{ maxHeight, overflowY: 'auto' }}>
        {data.length === 0 ? (
          emptyState
        ) : (
          data.map((item, index) => 
            mobileCardRenderer ? 
              mobileCardRenderer(item, index) : 
              defaultMobileCardRenderer(item, index)
          )
        )}
      </Box>
    );
  }

  // Render desktop table view
  return (
    <TableContainer 
      component={Paper} 
      className="overflow-x-auto"
      style={{ maxHeight }}
    >
      <Table stickyHeader>
        <TableHead>
          <TableRow>
            {columns.map((column, index) => (
              <TableCell 
                key={index}
                className="font-semibold bg-gray-50"
                style={{ minWidth: column.minWidth || 'auto' }}
              >
                {column.label}
              </TableCell>
            ))}
          </TableRow>
        </TableHead>
        <TableBody>
          {data.length === 0 ? (
            emptyState
          ) : (
            data.map((item, index) => (
              <TableRowComponent
                key={item.id || index}
                item={item}
                index={index}
                columns={columns}
              />
            ))
          )}
        </TableBody>
      </Table>
    </TableContainer>
  );
});

ResponsiveTable.displayName = 'ResponsiveTable';

export default ResponsiveTable;
