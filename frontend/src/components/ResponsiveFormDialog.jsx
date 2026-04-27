import React from 'react';
import {
  Dialog,
  DialogTitle,
  DialogContent,
  DialogActions,
  Button,
  IconButton,
  useMediaQuery,
  useTheme,
  AppBar,
  Toolbar,
  Typography,
  Slide
} from '@mui/material';
import { X, ArrowLeft } from 'lucide-react';

const Transition = React.forwardRef(function Transition(props, ref) {
  return <Slide direction="up" ref={ref} {...props} />;
});

const ResponsiveFormDialog = ({
  open,
  onClose,
  title,
  children,
  actions,
  maxWidth = "sm",
  fullWidth = true,
  loading = false
}) => {
  const theme = useTheme();
  const isMobile = useMediaQuery(theme.breakpoints.down('md'));

  if (isMobile) {
    return (
      <Dialog
        fullScreen
        open={open}
        onClose={onClose}
        TransitionComponent={Transition}
      >
        {/* Mobile Header */}
        <AppBar sx={{ position: 'relative' }} elevation={1}>
          <Toolbar>
            <IconButton
              edge="start"
              color="inherit"
              onClick={onClose}
              aria-label="close"
            >
              <ArrowLeft size={20} />
            </IconButton>
            <Typography sx={{ ml: 2, flex: 1 }} variant="h6" component="div">
              {title}
            </Typography>
          </Toolbar>
        </AppBar>

        {/* Mobile Content */}
        <DialogContent className="flex-1 p-4 pb-20">
          {children}
        </DialogContent>

        {/* Mobile Actions - Fixed Bottom */}
        <div className="fixed bottom-0 left-0 right-0 bg-white border-t p-4 space-y-2">
          {actions}
        </div>
      </Dialog>
    );
  }

  // Desktop Dialog
  return (
    <Dialog
      open={open}
      onClose={onClose}
      maxWidth={maxWidth}
      fullWidth={fullWidth}
      PaperProps={{
        sx: {
          borderRadius: 2,
          maxHeight: '90vh'
        }
      }}
    >
      <DialogTitle className="flex items-center justify-between pb-2">
        <Typography variant="h6" component="div">
          {title}
        </Typography>
        <IconButton
          aria-label="close"
          onClick={onClose}
          size="small"
        >
            <X size={20} />
        </IconButton>
      </DialogTitle>

      <DialogContent dividers className="p-6">
        {children}
      </DialogContent>

      <DialogActions className="p-4 gap-2">
        {actions}
      </DialogActions>
    </Dialog>
  );
};

export default ResponsiveFormDialog;
