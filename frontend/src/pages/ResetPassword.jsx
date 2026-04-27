import React, { useMemo, useState } from 'react';
import { Link as RouterLink, useNavigate, useSearchParams } from 'react-router-dom';
import {
  Alert,
  Box,
  Button,
  CircularProgress,
  IconButton,
  InputAdornment,
  Paper,
  Stack,
  TextField,
  Typography,
} from '@mui/material';
import { VisibilityOffRounded, VisibilityRounded } from '@mui/icons-material';
import { alpha } from '@mui/material/styles';
import toast from 'react-hot-toast';
import { authAPI } from '../services/api';

const ResetPassword = () => {
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const tokenFromQuery = (searchParams.get('token') || '').trim();
  const emailFromQuery = (searchParams.get('email') || '').trim();

  const [email, setEmail] = useState(emailFromQuery);
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [showPasswordConfirmation, setShowPasswordConfirmation] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState('');
  const [isSuccess, setIsSuccess] = useState(false);

  const missingRequiredParams = useMemo(() => {
    return tokenFromQuery === '' || emailFromQuery === '';
  }, [emailFromQuery, tokenFromQuery]);

  const extractErrorMessage = (caughtError) => {
    const message = caughtError?.response?.data?.message;
    const validationErrors = caughtError?.response?.data?.errors;

    if (validationErrors && typeof validationErrors === 'object') {
      const firstError = Object.values(validationErrors).flat()[0];
      if (firstError) {
        return String(firstError);
      }
    }

    if (typeof message === 'string' && message.trim() !== '') {
      return message;
    }

    return 'Gagal mereset password. Silakan coba lagi.';
  };

  const handleSubmit = async (event) => {
    event.preventDefault();

    if (isSubmitting || isSuccess) {
      return;
    }

    if (missingRequiredParams) {
      setError('Link reset password tidak valid. Silakan minta link baru.');
      return;
    }

    if (password.length < 8) {
      setError('Password minimal 8 karakter.');
      return;
    }

    if (password !== passwordConfirmation) {
      setError('Konfirmasi password tidak sama.');
      return;
    }

    setIsSubmitting(true);
    setError('');

    try {
      await authAPI.resetPassword({
        token: tokenFromQuery,
        email: email.trim(),
        password,
        password_confirmation: passwordConfirmation,
      });

      setIsSuccess(true);
      toast.success('Password berhasil direset. Silakan login kembali.');
    } catch (caughtError) {
      setError(extractErrorMessage(caughtError));
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <Box
      sx={{
        minHeight: '100dvh',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        p: { xs: 2, md: 3 },
        background:
          'linear-gradient(135deg, #173E78 0%, #2F56D6 44%, #4F7BFF 100%)',
      }}
    >
      <Paper
        elevation={0}
        sx={{
          width: '100%',
          maxWidth: 520,
          p: { xs: 2.2, md: 3.2 },
          borderRadius: '24px',
          backgroundColor: alpha('#FBF9F5', 0.98),
          border: `1px solid ${alpha('#ffffff', 0.66)}`,
          boxShadow: '0 20px 44px rgba(7,20,40,0.18)',
        }}
      >
        <Stack spacing={2}>
          <Box>
            <Typography
              sx={{
                color: '#7f8d9e',
                fontSize: 11,
                fontWeight: 700,
                letterSpacing: '0.16em',
                textTransform: 'uppercase',
              }}
            >
              Akun Pegawai
            </Typography>
            <Typography
              sx={{
                mt: 0.5,
                color: '#10233d',
                fontSize: { xs: 26, md: 34 },
                lineHeight: 1,
                letterSpacing: '-0.04em',
                fontWeight: 800,
              }}
            >
              Reset Password
            </Typography>
          </Box>

          {missingRequiredParams && (
            <Alert severity="error" sx={{ borderRadius: '14px' }}>
              Link reset password tidak valid. Silakan minta link reset baru dari halaman login.
            </Alert>
          )}

          {error && (
            <Alert severity="error" sx={{ borderRadius: '14px' }}>
              {error}
            </Alert>
          )}

          {isSuccess && (
            <Alert severity="success" sx={{ borderRadius: '14px' }}>
              Password berhasil direset. Silakan login dengan password baru Anda.
            </Alert>
          )}

          <Box component="form" onSubmit={handleSubmit}>
            <Stack spacing={1.5}>
              <TextField
                label="Email"
                type="email"
                value={email}
                onChange={(event) => setEmail(event.target.value)}
                fullWidth
                required
                disabled={isSubmitting || isSuccess}
              />

              <TextField
                label="Password Baru"
                type={showPassword ? 'text' : 'password'}
                value={password}
                onChange={(event) => setPassword(event.target.value)}
                fullWidth
                required
                disabled={isSubmitting || isSuccess}
                inputProps={{ minLength: 8 }}
                InputProps={{
                  endAdornment: (
                    <InputAdornment position="end">
                      <IconButton
                        edge="end"
                        onClick={() => setShowPassword((prev) => !prev)}
                        aria-label={showPassword ? 'Sembunyikan password' : 'Tampilkan password'}
                      >
                        {showPassword ? <VisibilityOffRounded /> : <VisibilityRounded />}
                      </IconButton>
                    </InputAdornment>
                  ),
                }}
              />

              <TextField
                label="Konfirmasi Password Baru"
                type={showPasswordConfirmation ? 'text' : 'password'}
                value={passwordConfirmation}
                onChange={(event) => setPasswordConfirmation(event.target.value)}
                fullWidth
                required
                disabled={isSubmitting || isSuccess}
                inputProps={{ minLength: 8 }}
                InputProps={{
                  endAdornment: (
                    <InputAdornment position="end">
                      <IconButton
                        edge="end"
                        onClick={() => setShowPasswordConfirmation((prev) => !prev)}
                        aria-label={showPasswordConfirmation ? 'Sembunyikan konfirmasi password' : 'Tampilkan konfirmasi password'}
                      >
                        {showPasswordConfirmation ? <VisibilityOffRounded /> : <VisibilityRounded />}
                      </IconButton>
                    </InputAdornment>
                  ),
                }}
              />

              <Button
                type="submit"
                variant="contained"
                disabled={isSubmitting || isSuccess || missingRequiredParams}
                sx={{
                  minHeight: 48,
                  borderRadius: '14px',
                  textTransform: 'none',
                  fontWeight: 700,
                  fontSize: 15,
                  mt: 0.5,
                  background: 'linear-gradient(90deg, #173E78 0%, #2F56D6 56%, #4F7BFF 100%)',
                  '&:hover': {
                    background: 'linear-gradient(90deg, #14356A 0%, #294EC7 56%, #456EEA 100%)',
                  },
                }}
              >
                {isSubmitting ? (
                  <Stack direction="row" spacing={1} alignItems="center">
                    <CircularProgress size={16} sx={{ color: '#fff' }} />
                    <span>Memproses...</span>
                  </Stack>
                ) : (
                  'Simpan Password Baru'
                )}
              </Button>
            </Stack>
          </Box>

          <Stack direction="row" justifyContent="space-between" alignItems="center">
            <Button
              component={RouterLink}
              to="/login"
              variant="text"
              sx={{ textTransform: 'none', fontWeight: 700 }}
            >
              Kembali ke Login
            </Button>
            {isSuccess && (
              <Button
                variant="contained"
                onClick={() => navigate('/login')}
                sx={{ textTransform: 'none', fontWeight: 700, borderRadius: '12px' }}
              >
                Login Sekarang
              </Button>
            )}
          </Stack>
        </Stack>
      </Paper>
    </Box>
  );
};

export default ResetPassword;
