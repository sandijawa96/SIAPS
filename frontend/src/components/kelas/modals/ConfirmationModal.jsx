import React from 'react';
import {
  Dialog,
  DialogTitle,
  DialogContent,
  DialogActions,
  Button,
  Typography,
  Box,
  Avatar,
  useTheme,
  useMediaQuery
} from '@mui/material';
import {
  AlertTriangle,
  Trash2,
  Users,
  BookOpen
} from 'lucide-react';

const ConfirmationModal = ({
  open,
  onClose,
  title,
  message,
  onConfirm,
  confirmText = 'Hapus',
  cancelText = 'Batal',
  type = 'delete'
}) => {
  const theme = useTheme();
  const isMobile = useMediaQuery(theme.breakpoints.down('sm'));

  const getIcon = () => {
    switch (type) {
      case 'delete':
        return <Trash2 size={24} />;
      case 'delete-siswa':
        return <Users size={24} />;
      case 'delete-kelas':
        return <BookOpen size={24} />;
      default:
        return <AlertTriangle size={24} />;
    }
  };

  const getColor = () => {
    switch (type) {
      case 'delete':
      case 'delete-siswa':
      case 'delete-kelas':
        return 'error.main';
      default:
        return 'warning.main';
    }
  };

  return (
    <Dialog
      open={open}
      onClose={onClose}
      maxWidth="sm"
      fullWidth
      fullScreen={isMobile}
      PaperProps={{
        sx: {
          borderRadius: isMobile ? 0 : 2,
          margin: isMobile ? 0 : 2
        }
      }}
    >
      <DialogTitle sx={{ pb: 2 }}>
        <Box display="flex" alignItems="center" gap={2}>
          <Avatar
            sx={{
              bgcolor: `${getColor()}15`,
              color: getColor(),
              width: 48,
              height: 48
            }}
          >
            {getIcon()}
          </Avatar>
          <Typography variant="h6" fontWeight="bold">
            {title}
          </Typography>
        </Box>
      </DialogTitle>

      <DialogContent>
        <Typography variant="body1" color="textSecondary">
          {message}
        </Typography>
      </DialogContent>

      <DialogActions sx={{ p: 2.5, gap: 1 }}>
        <Button
          onClick={onClose}
          variant="outlined"
          fullWidth={isMobile}
        >
          {cancelText}
        </Button>
        <Button
          onClick={onConfirm}
          variant="contained"
          color="error"
          fullWidth={isMobile}
          startIcon={getIcon()}
        >
          {confirmText}
        </Button>
      </DialogActions>
    </Dialog>
  );
};

export default ConfirmationModal;
