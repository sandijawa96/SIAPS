import React, { useMemo, useState } from 'react';
import { useNavigate, useLocation } from 'react-router-dom';
import { useAuth } from '../hooks/useAuth';
import {
  Alert,
  Box,
  Button,
  ButtonBase,
  Checkbox,
  CircularProgress,
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  IconButton,
  InputAdornment,
  Paper,
  Stack,
  TextField,
  Tooltip,
  Typography,
  useMediaQuery,
} from '@mui/material';
import {
  CopyrightRounded,
  FavoriteRounded,
  PersonOutlineRounded,
  SchoolRounded,
  VisibilityOffRounded,
  VisibilityRounded,
} from '@mui/icons-material';
import { alpha, useTheme } from '@mui/material/styles';
import { LocalizationProvider } from '@mui/x-date-pickers/LocalizationProvider';
import { DatePicker } from '@mui/x-date-pickers/DatePicker';
import { AdapterDateFns } from '@mui/x-date-pickers/AdapterDateFnsV2';
import { format as formatDate, isValid, parseISO } from 'date-fns';
import { id as idLocale } from 'date-fns/locale';
import { authAPI } from '../services/api';
import { useServerClock } from '../hooks/useServerClock';
import { toServerCalendarDate } from '../services/serverClock';
import toast from 'react-hot-toast';

const Login = () => {
  const navigate = useNavigate();
  const location = useLocation();
  const theme = useTheme();
  const isMobile = useMediaQuery(theme.breakpoints.down('md'));
  const isShortViewport = useMediaQuery('(max-height: 820px)');
  const isVeryShortViewport = useMediaQuery('(max-height: 720px)');
  const isCompactLayout = isMobile || isShortViewport;
  const { login, loginSiswa } = useAuth();
  const { isSynced: isServerClockSynced, serverDate } = useServerClock();
  const maxTanggalLahirDate = useMemo(
    () => (isServerClockSynced ? toServerCalendarDate(serverDate) : undefined),
    [isServerClockSynced, serverDate]
  );

  const [showPassword, setShowPassword] = useState(false);
  const [rememberMe, setRememberMe] = useState(false);
  const [loginMode, setLoginMode] = useState('pegawai');
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState('');
  const [forgotPasswordOpen, setForgotPasswordOpen] = useState(false);
  const [forgotPasswordEmail, setForgotPasswordEmail] = useState('');
  const [isSubmittingForgotPassword, setIsSubmittingForgotPassword] = useState(false);
  const [formData, setFormData] = useState({
    email: '',
    password: '',
    nis: '',
    tanggal_lahir: '',
  });

  const modes = useMemo(
    () => [
      {
        key: 'pegawai',
        label: 'Pegawai',
        icon: <PersonOutlineRounded sx={{ fontSize: 16 }} />,
      },
      {
        key: 'siswa',
        label: 'Siswa',
        icon: <SchoolRounded sx={{ fontSize: 16 }} />,
      },
    ],
    []
  );

  const fieldRootSx = {
    '& .MuiOutlinedInput-root': {
      minHeight: { xs: 46, sm: 50, md: 56 },
      borderRadius: '18px',
      bgcolor: 'rgba(255,255,255,0.98)',
      boxShadow: 'inset 0 1px 0 rgba(255,255,255,0.88)',
      pl: 1.5,
      pr: 0.8,
      '& fieldset': {
        borderColor: '#d7e0eb',
        transition: 'border-color 0.2s ease, box-shadow 0.2s ease',
      },
      '&:hover fieldset': {
        borderColor: '#c3d2e4',
      },
      '&.Mui-focused': {
        boxShadow: '0 0 0 2px rgba(91, 125, 255, 0.14), inset 0 1px 0 rgba(255,255,255,0.88)',
      },
      '&.Mui-focused fieldset': {
        borderColor: '#5B7DFF',
        borderWidth: '1px',
      },
    },
    '& .MuiInputBase-input, & .MuiOutlinedInput-input, & input.MuiInputBase-input': {
      color: '#22364d',
      width: '100%',
      minWidth: 0,
      border: 'none !important',
      borderWidth: '0 !important',
      borderStyle: 'none !important',
      borderRadius: '0 !important',
      outline: 'none !important',
      boxShadow: 'none !important',
      WebkitBoxShadow: 'none !important',
      backgroundColor: 'transparent !important',
      WebkitTextFillColor: '#22364d',
      caretColor: '#22364d',
      fontSize: { xs: 13, sm: 13.5, md: 14.5 },
      py: { xs: 1.05, md: 1.35 },
      px: 0.2,
      lineHeight: 1.35,
      '&::placeholder': {
        color: '#91a0b3',
        opacity: 1,
      },
    },
    '& .MuiInputBase-input:focus, & .MuiInputBase-input:focus-visible, & input.MuiInputBase-input:focus, & input.MuiInputBase-input:focus-visible': {
      border: 'none !important',
      borderWidth: '0 !important',
      borderStyle: 'none !important',
      outline: 'none !important',
      boxShadow: 'none !important',
      WebkitBoxShadow: 'none !important',
    },
    '& .MuiInputAdornment-root': {
      ml: 1,
      mr: 0.1,
      color: alpha('#22364d', 0.45),
      flexShrink: 0,
      alignSelf: 'center',
    },
    '& .MuiIconButton-root': {
      p: 0.75,
      color: alpha('#22364d', 0.5),
    },
  };

  const handleChange = (event) => {
    const { name, value } = event.target;
    setFormData((prev) => ({
      ...prev,
      [name]: value,
    }));

    if (error) {
      setError('');
    }
  };

  const handleTanggalLahirChange = (value) => {
    setFormData((prev) => ({
      ...prev,
      tanggal_lahir: value && isValid(value) ? formatDate(value, 'yyyy-MM-dd') : '',
    }));

    if (error) {
      setError('');
    }
  };

  const handleSubmit = async (event) => {
    event.preventDefault();
    event.stopPropagation();

    if (isLoading) {
      return;
    }

    setIsLoading(true);
    setError('');

    const loadingToast = toast.loading('Memproses login...', {
      position: 'top-center',
    });

    try {
      if (loginMode === 'siswa') {
        const match = String(formData.tanggal_lahir || '').match(/^(\d{4})-(\d{2})-(\d{2})$/);
        if (!match) {
          throw new Error('Format tanggal lahir tidak valid');
        }

        const formattedDate = `${match[3]}/${match[2]}/${match[1]}`;

        await loginSiswa({
          nis: formData.nis,
          tanggal_lahir: formattedDate,
          remember_me: rememberMe,
        });
      } else {
        await login({
          email: formData.email,
          password: formData.password,
          remember_me: rememberMe,
        });
      }

      toast.dismiss(loadingToast);
      toast.success(`Login berhasil. Selamat datang ${loginMode === 'siswa' ? 'Siswa' : 'Pegawai'}.`, {
        duration: 3000,
        position: 'top-center',
        style: {
          background: '#10B981',
          color: '#fff',
          fontWeight: 'bold',
        },
      });

      setTimeout(() => {
        const from = location.state?.from?.pathname || '/';
        navigate(from, { replace: true });
      }, 1000);
    } catch (caughtError) {
      console.error('Login error:', caughtError);
      toast.dismiss(loadingToast);

      let errorMessage = '';

      if (caughtError.response) {
        const status = caughtError.response.status;
        const message = caughtError.response.data?.message || caughtError.response.data?.error;
        const validationErrors = caughtError.response.data?.errors;

        if (status === 401) {
          errorMessage = 'Email/password atau NIS/tanggal lahir tidak valid';
        } else if (status === 422) {
          if (validationErrors && typeof validationErrors === 'object') {
            const allErrors = Object.values(validationErrors).flat();
            errorMessage = allErrors[0] || 'Data yang dimasukkan tidak valid';
          } else {
            errorMessage = message || 'Data yang dimasukkan tidak valid';
          }
        } else if (status === 404) {
          errorMessage = 'Endpoint tidak ditemukan. Periksa konfigurasi server.';
        } else if (status >= 500) {
          errorMessage = 'Terjadi kesalahan server. Silakan coba lagi.';
        } else {
          errorMessage = message || `Error ${status}: Terjadi kesalahan saat login`;
        }
      } else if (caughtError.request) {
        errorMessage = 'Tidak dapat terhubung ke server. Periksa koneksi internet Anda.';
      } else {
        errorMessage = caughtError.message || 'Terjadi kesalahan yang tidak diketahui';
      }

      setError(errorMessage);
      toast.error(errorMessage, {
        duration: 6000,
        position: 'top-center',
        style: {
          background: '#EF4444',
          color: '#fff',
          fontWeight: 'bold',
          maxWidth: '500px',
        },
      });
    } finally {
      setIsLoading(false);
    }
  };

  const openForgotPasswordDialog = () => {
    if (loginMode !== 'pegawai') {
      toast.error('Reset password siswa dilakukan melalui administrator.');
      return;
    }

    setForgotPasswordEmail(formData.email || '');
    setForgotPasswordOpen(true);
  };

  const closeForgotPasswordDialog = () => {
    if (isSubmittingForgotPassword) {
      return;
    }

    setForgotPasswordOpen(false);
    setForgotPasswordEmail('');
  };

  const handleForgotPasswordSubmit = async () => {
    const email = forgotPasswordEmail.trim();
    if (!email) {
      toast.error('Email wajib diisi.');
      return;
    }

    setIsSubmittingForgotPassword(true);
    try {
      await authAPI.forgotPassword(email);
      toast.success('Link reset password berhasil dikirim ke email Anda.');
      setForgotPasswordOpen(false);
      setForgotPasswordEmail('');
    } catch (caughtError) {
      const message =
        caughtError?.response?.data?.message ||
        caughtError?.response?.data?.errors?.email?.[0] ||
        'Gagal mengirim link reset password.';
      toast.error(message);
    } finally {
      setIsSubmittingForgotPassword(false);
    }
  };

  const siswaDateValue = formData.tanggal_lahir ? parseISO(formData.tanggal_lahir) : null;

  return (
    <LocalizationProvider dateAdapter={AdapterDateFns} adapterLocale={idLocale}>
      <Box
        sx={{
          height: '100dvh',
          minHeight: '100svh',
          maxHeight: '100dvh',
          overflow: 'hidden',
          position: 'relative',
          background: `
            radial-gradient(circle at 15% 12%, rgba(255,255,255,0.18), transparent 18%),
            radial-gradient(circle at 82% 82%, rgba(255,255,255,0.13), transparent 18%),
            linear-gradient(135deg, #173E78 0%, #2F56D6 44%, #4F7BFF 100%)
          `,
        }}
      >
        <Box
          sx={{
            position: 'absolute',
            inset: 'auto auto 7% -6%',
            width: 420,
            height: 420,
            borderRadius: '50%',
            background: 'radial-gradient(circle, rgba(255,255,255,0.12), transparent 72%)',
            pointerEvents: 'none',
            display: { xs: 'none', md: 'block' },
          }}
        />
        <Box
          sx={{
            position: 'absolute',
            inset: '8% -5% auto auto',
            width: 360,
            height: 360,
            borderRadius: '50%',
            background: 'radial-gradient(circle, rgba(255,255,255,0.11), transparent 72%)',
            pointerEvents: 'none',
            display: { xs: 'none', md: 'block' },
          }}
        />
        <Box
          sx={{
            position: 'absolute',
            top: 108,
            left: '18%',
            width: 500,
            height: 210,
            borderRadius: '44px',
            background: 'linear-gradient(135deg, rgba(255,255,255,0.16), rgba(255,255,255,0.04))',
            transform: 'rotate(-24deg)',
            pointerEvents: 'none',
            display: { xs: 'none', md: 'block' },
          }}
        />

        <Box
          sx={{
            position: 'relative',
            zIndex: 1,
            width: 'min(1480px, 100%)',
            height: '100%',
            mx: 'auto',
            px: { xs: 1.5, sm: 2, md: 3.5 },
            py: { xs: 0.75, sm: 1, md: 2.25 },
          }}
        >
          {!isMobile && (
            <Paper
              elevation={0}
              sx={{
                position: 'absolute',
                top: 24,
                left: 28,
                zIndex: 3,
                display: 'inline-flex',
                alignItems: 'center',
                gap: 1.75,
                px: 2,
                py: 1.5,
                borderRadius: '20px',
                bgcolor: alpha('#ffffff', 0.12),
                border: `1px solid ${alpha('#ffffff', 0.14)}`,
                backdropFilter: 'blur(18px)',
              }}
            >
              <Box
                component="img"
                src="/icon.png"
                alt="Logo SIAP Absensi"
                sx={{
                  width: 56,
                  height: 56,
                  objectFit: 'contain',
                  p: 1,
                  borderRadius: '16px',
                  bgcolor: alpha('#ffffff', 0.96),
                }}
              />
              <Box>
                <Typography
                  sx={{
                    mb: 0.5,
                    fontSize: 11,
                    fontWeight: 700,
                    letterSpacing: '0.16em',
                    textTransform: 'uppercase',
                    color: alpha('#ffffff', 0.62),
                  }}
                >
                  Sistem Akademik
                </Typography>
                <Typography sx={{ fontSize: 30, fontWeight: 800, lineHeight: 1, color: '#fff' }}>
                  SIAP Absensi
                </Typography>
              </Box>
            </Paper>
          )}

          <Box
            sx={{
              height: '100%',
              display: 'grid',
              alignItems: 'center',
              pt: { xs: 0, md: 0 },
            }}
          >
            <Box
              sx={{
                height: { xs: '100%', md: 'min(760px, calc(100dvh - 52px))' },
                display: 'grid',
                gridTemplateColumns: { xs: '1fr', md: 'minmax(0, 1fr) minmax(400px, 500px)' },
                gap: { xs: 0, md: 4 },
                alignItems: 'center',
                borderRadius: { xs: 0, md: '34px' },
                overflow: 'hidden',
                bgcolor: { xs: 'transparent', md: alpha('#ffffff', 0.07) },
                border: { xs: 'none', md: `1px solid ${alpha('#ffffff', 0.14)}` },
                backdropFilter: { xs: 'none', md: 'blur(22px)' },
                boxShadow: { xs: 'none', md: '0 24px 64px rgba(7,20,40,0.18)' },
              }}
            >
              <Box
                sx={{
                  display: { xs: 'none', md: 'flex' },
                  position: 'relative',
                  px: 5,
                  py: 4,
                  minHeight: '100%',
                  alignItems: 'flex-end',
                  overflow: 'hidden',
                }}
              >
                <Box
                  sx={{
                    position: 'absolute',
                    left: -130,
                    bottom: -180,
                    width: 380,
                    height: 380,
                    borderRadius: '50%',
                    bgcolor: alpha('#ffffff', 0.08),
                  }}
                />
                <Box
                  sx={{
                    position: 'absolute',
                    right: 28,
                    bottom: 48,
                    width: 290,
                    height: 290,
                    borderRadius: '50%',
                    border: `1px solid ${alpha('#ffffff', 0.14)}`,
                  }}
                />
                <Box
                  sx={{
                    position: 'relative',
                    zIndex: 1,
                    display: 'grid',
                    gap: 2,
                  }}
                >
                  <Stack direction="row" spacing={1.1}>
                    {[1, 2, 3].map((item) => (
                      <Box
                        key={item}
                        sx={{
                          width: 14,
                          height: 14,
                          borderRadius: '50%',
                          bgcolor: alpha('#ffffff', item === 1 ? 0.84 : item === 2 ? 0.7 : 0.55),
                        }}
                      />
                    ))}
                  </Stack>
                <Typography
                  sx={{
                    maxWidth: 720,
                    fontSize: { md: 58, lg: 64, xl: 68 },
                    lineHeight: 0.96,
                    letterSpacing: '-0.06em',
                    fontWeight: 800,
                    color: '#fff',
                    whiteSpace: 'nowrap',
                  }}
                >
                  SMAN 1 Sumber
                </Typography>
                  <Typography
                    sx={{
                      maxWidth: 380,
                      color: alpha('#ffffff', 0.82),
                      fontSize: { md: 16, lg: 17 },
                      lineHeight: 1.45,
                      fontWeight: 500,
                    }}
                  >
                    Sistem Informasi Absensi Pembelajaran Digital Terintegrasi
                  </Typography>
                  <Box sx={{ mt: 0.5 }}>
                    {[180, 126, 82].map((width, index) => (
                      <Box
                        key={width}
                        sx={{
                          width,
                          height: 14,
                          mt: index === 0 ? 0 : 1.2,
                          borderRadius: '999px',
                          bgcolor: alpha('#ffffff', index === 0 ? 0.16 : index === 1 ? 0.12 : 0.09),
                        }}
                      />
                    ))}
                  </Box>
                </Box>
              </Box>

              <Box
                sx={{
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                  minHeight: '100%',
                  px: { xs: 0.5, sm: 1.1, md: 3.5 },
                  py: { xs: 0, md: 2 },
                }}
              >
                <Paper
                  elevation={0}
                  sx={{
                    width: '100%',
                    maxWidth: { xs: 440, md: 500 },
                    maxHeight: { xs: 'calc(100dvh - 16px)', sm: 'calc(100dvh - 20px)', md: 'calc(100dvh - 64px)' },
                    overflow: 'hidden',
                    p: { xs: 1.6, sm: 2.1, md: 3.1 },
                    borderRadius: { xs: '26px', md: '32px' },
                    bgcolor: alpha('#FBF9F5', 0.96),
                    border: `1px solid ${alpha('#ffffff', 0.64)}`,
                    boxShadow: '0 18px 42px rgba(9,25,47,0.12)',
                    color: '#10233d',
                    display: 'flex',
                    flexDirection: 'column',
                  }}
                >
                  <Stack direction="row" alignItems="flex-start" justifyContent="space-between" spacing={1.5}>
                    <Box>
                      <Typography
                        sx={{
                          mb: 0.5,
                          color: '#7f8d9e',
                          fontSize: 11,
                          fontWeight: 700,
                          letterSpacing: '0.16em',
                          textTransform: 'uppercase',
                        }}
                      >
                        Selamat Datang
                      </Typography>
                      <Typography
                        sx={{
                          color: '#10233d',
                          fontSize: { xs: 24, sm: 30, md: 44 },
                          lineHeight: 0.94,
                          letterSpacing: '-0.05em',
                          fontWeight: 800,
                        }}
                      >
                        Masuk
                      </Typography>
                    </Box>
                    <Box
                      component="img"
                      src="/icon.png"
                      alt="Logo SIAP Absensi"
                      sx={{
                        width: { xs: 44, sm: 50, md: 64 },
                        height: { xs: 44, sm: 50, md: 64 },
                        objectFit: 'contain',
                        p: { xs: 0.75, md: 1 },
                        borderRadius: '18px',
                        bgcolor: alpha('#ffffff', 0.98),
                        border: `1px solid ${alpha('#10233d', 0.08)}`,
                      }}
                    />
                  </Stack>

                  <Stack
                    direction="row"
                    spacing={1}
                    sx={{
                      mt: { xs: 1.4, md: 2.6 },
                      p: 0.6,
                      borderRadius: '20px',
                      bgcolor: alpha('#E8EEF9', 0.9),
                    }}
                  >
                    {modes.map((mode) => {
                      const active = loginMode === mode.key;
                      return (
                        <ButtonBase
                          key={mode.key}
                          onClick={() => setLoginMode(mode.key)}
                          sx={{
                            flex: 1,
                            minHeight: { xs: 40, md: 48 },
                            px: { xs: 1, md: 1.25 },
                            py: 0.5,
                            borderRadius: '16px',
                            justifyContent: 'flex-start',
                            bgcolor: active ? alpha('#ffffff', 0.98) : 'transparent',
                            boxShadow: active ? '0 10px 20px rgba(16,35,61,0.08)' : 'none',
                          }}
                        >
                          <Stack direction="row" alignItems="center" spacing={0.9} sx={{ minWidth: 0 }}>
                            <Box
                              sx={{
                                width: 22,
                                height: 22,
                                borderRadius: '8px',
                                display: 'inline-flex',
                                alignItems: 'center',
                                justifyContent: 'center',
                                bgcolor: alpha('#14326A', 0.08),
                                color: active ? '#10233d' : '#5f718b',
                                backgroundImage: active
                                  ? 'linear-gradient(135deg, rgba(14,39,72,0.12), rgba(57,93,255,0.16))'
                                  : 'none',
                                flexShrink: 0,
                              }}
                            >
                              {mode.icon}
                            </Box>
                            <Typography
                              sx={{
                                fontSize: { xs: 12.5, md: 14 },
                                fontWeight: 700,
                                color: active ? '#10233d' : '#6e7f96',
                                whiteSpace: 'nowrap',
                              }}
                            >
                              {mode.label}
                            </Typography>
                          </Stack>
                        </ButtonBase>
                      );
                    })}
                  </Stack>

                  <Box component="form" onSubmit={handleSubmit} sx={{ mt: { xs: 1.2, md: 2.3 } }}>
                    <Stack spacing={{ xs: 1.2, md: 1.75 }}>
                      {loginMode === 'pegawai' ? (
                        <>
                          <Stack>
                            <TextField
                              id="email"
                              name="email"
                              type="email"
                              autoComplete="email"
                              required
                              fullWidth
                              value={formData.email}
                              onChange={handleChange}
                              placeholder="nama@sekolah.sch.id"
                              sx={fieldRootSx}
                              InputProps={{
                                endAdornment: (
                                  <InputAdornment position="end">
                                    <Typography sx={{ fontSize: 14, fontWeight: 700, color: alpha('#22364d', 0.48) }}>
                                      @
                                    </Typography>
                                  </InputAdornment>
                                ),
                              }}
                            />
                          </Stack>

                          <Stack>
                            <TextField
                              id="password"
                              name="password"
                              type={showPassword ? 'text' : 'password'}
                              autoComplete="current-password"
                              required
                              fullWidth
                              value={formData.password}
                              onChange={handleChange}
                              placeholder="Masukkan password"
                              sx={fieldRootSx}
                              InputProps={{
                                endAdornment: (
                                  <InputAdornment position="end">
                                    <IconButton
                                      edge="end"
                                      onClick={() => setShowPassword((prev) => !prev)}
                                      aria-label={showPassword ? 'Sembunyikan password' : 'Tampilkan password'}
                                      sx={{ color: alpha('#22364d', 0.45) }}
                                    >
                                      {showPassword ? <VisibilityOffRounded /> : <VisibilityRounded />}
                                    </IconButton>
                                  </InputAdornment>
                                ),
                              }}
                            />
                          </Stack>
                        </>
                      ) : (
                        <>
                          <Stack>
                            <TextField
                              id="nis"
                              name="nis"
                              type="text"
                              autoComplete="off"
                              required
                              fullWidth
                              value={formData.nis}
                              onChange={handleChange}
                              placeholder="Masukkan NIS"
                              sx={fieldRootSx}
                            />
                          </Stack>

                          <Stack>
                            <DatePicker
                              value={siswaDateValue}
                              onChange={handleTanggalLahirChange}
                              format="dd/MM/yyyy"
                              views={['year', 'month', 'day']}
                              openTo="year"
                              disableFuture={isServerClockSynced}
                              minDate={new Date(1900, 0, 1)}
                              maxDate={maxTanggalLahirDate}
                              yearsOrder="desc"
                              yearsPerRow={4}
                              slotProps={{
                                textField: {
                                  required: true,
                                  fullWidth: true,
                                  placeholder: 'Pilih tanggal lahir',
                                  sx: fieldRootSx,
                                },
                                openPickerButton: {
                                  size: 'small',
                                  sx: {
                                    color: alpha('#22364d', 0.52),
                                  },
                                },
                                popper: {
                                  sx: {
                                    '& .MuiPaper-root': {
                                      borderRadius: '20px',
                                      boxShadow: '0 18px 44px rgba(9,25,47,0.16)',
                                    },
                                  },
                                },
                                mobilePaper: {
                                  sx: {
                                    borderRadius: '18px',
                                    '& .MuiPickersLayout-root': {
                                      minWidth: 280,
                                    },
                                  },
                                },
                              }}
                            />
                          </Stack>
                        </>
                      )}
                    </Stack>

                    <Stack
                      direction="row"
                      alignItems="center"
                      justifyContent="space-between"
                      spacing={1}
                      sx={{ mt: { xs: 1.25, md: 1.9 } }}
                    >
                      <Stack direction="row" alignItems="center" spacing={0.5} sx={{ minWidth: 0 }}>
                        <Checkbox
                          checked={rememberMe}
                          onChange={(event) => setRememberMe(event.target.checked)}
                          sx={{
                            p: 0.2,
                            color: '#c1cdde',
                            '&.Mui-checked': {
                              color: '#3154da',
                            },
                          }}
                        />
                        <Typography sx={{ color: '#7f8c9e', fontSize: { xs: 12, md: 13 } }}>Ingat saya</Typography>
                      </Stack>
                      {loginMode === 'pegawai' ? (
                        <Typography
                          onClick={openForgotPasswordDialog}
                          sx={{
                            color: '#35507f',
                            fontSize: { xs: 12, md: 13 },
                            whiteSpace: 'nowrap',
                            fontWeight: 700,
                            cursor: 'pointer',
                          }}
                        >
                          Lupa password?
                        </Typography>
                      ) : (
                        <Box sx={{ width: 1 }} />
                      )}
                    </Stack>

                    {error && (
                      <Alert severity="error" sx={{ mt: 2, borderRadius: '16px', py: 0.5 }}>
                        {error}
                      </Alert>
                    )}

                    <Button
                      type="submit"
                      disabled={isLoading}
                      fullWidth
                      sx={{
                        mt: { xs: 1.6, md: 2.4 },
                        minHeight: { xs: 48, md: 58 },
                        borderRadius: '20px',
                        textTransform: 'none',
                        fontSize: { xs: 15, md: 16 },
                        fontWeight: 800,
                        letterSpacing: '0.01em',
                        color: '#fff',
                        background: 'linear-gradient(90deg, #173E78 0%, #2F56D6 56%, #4F7BFF 100%)',
                        boxShadow: '0 16px 32px rgba(24,58,114,0.18)',
                        '&:hover': {
                          background: 'linear-gradient(90deg, #14356A 0%, #294EC7 56%, #456EEA 100%)',
                        },
                        '&.Mui-disabled': {
                          color: '#ffffff',
                          opacity: 0.72,
                        },
                      }}
                    >
                      {isLoading ? (
                        <Stack direction="row" alignItems="center" spacing={1.25}>
                          <CircularProgress size={18} sx={{ color: '#fff' }} />
                          <span>Memproses...</span>
                        </Stack>
                      ) : (
                        'Masuk'
                      )}
                    </Button>

                    <Typography
                      sx={{
                        mt: 1.2,
                        color: '#7b8797',
                        fontSize: { xs: 10.8, md: 13 },
                        textAlign: 'center',
                        lineHeight: 1.45,
                      }}
                    >
                      Belum punya akun?{' '}
                      <Tooltip
                        title="Silahkan hubungi administrator smanis / Wakasek Kesiswaan."
                        arrow
                        placement="top"
                      >
                        <Typography
                          component="span"
                          sx={{
                            color: '#233A57',
                            fontWeight: 700,
                            cursor: 'pointer',
                            '&:hover': {
                              textDecoration: 'underline',
                            },
                          }}
                        >
                          Hubungi administrator.
                        </Typography>
                      </Tooltip>
                    </Typography>

                    <Typography
                      noWrap
                      sx={{
                        mt: 1.45,
                        textAlign: 'center',
                        color: '#6f7f95',
                        fontSize: { xs: 10.4, md: 12 },
                        fontWeight: 700,
                        letterSpacing: '0.01em',
                      }}
                    >
                      SIAP Absensi - 2026 <CopyrightRounded sx={{ fontSize: { xs: 12, md: 14 }, mb: '-2px' }} /> ICTSmanis.
                    </Typography>

                    <Stack
                      direction="row"
                      alignItems="center"
                      justifyContent="center"
                      spacing={0.8}
                      flexWrap="wrap"
                      sx={{
                        mt: isCompactLayout ? 0.85 : 1.15,
                        pt: isCompactLayout ? 0.95 : 1.25,
                        borderTop: `1px solid ${alpha('#10233d', 0.08)}`,
                        color: '#7b8797',
                        textAlign: 'center',
                        display: isMobile && isVeryShortViewport ? 'none' : 'flex',
                      }}
                    >
                      <Typography sx={{ fontSize: { xs: 10.5, md: 12 }, fontWeight: 600 }}>
                        Dibuat dan Design Oleh Sandi & Edi Dengan
                      </Typography>
                      <Box
                        sx={{
                          width: 16,
                          height: 16,
                          borderRadius: '999px',
                          display: 'inline-flex',
                          alignItems: 'center',
                          justifyContent: 'center',
                          bgcolor: alpha('#14326A', 0.08),
                          backgroundImage: 'linear-gradient(135deg, rgba(20,50,106,0.12), rgba(57,93,255,0.14))',
                          color: '#3052D7',
                        }}
                      >
                        <FavoriteRounded sx={{ fontSize: 10 }} />
                      </Box>
                    </Stack>
                  </Box>

                  <Dialog open={forgotPasswordOpen} onClose={closeForgotPasswordDialog} fullWidth maxWidth="xs">
                    <DialogTitle sx={{ pb: 1.5 }}>Lupa Password</DialogTitle>
                    <DialogContent>
                      <Typography sx={{ mb: 1.5, color: '#5d6f84', fontSize: 14 }}>
                        Masukkan email akun pegawai. Link reset akan dikirim ke email tersebut.
                      </Typography>
                      <TextField
                        autoFocus
                        fullWidth
                        type="email"
                        label="Email"
                        value={forgotPasswordEmail}
                        onChange={(event) => setForgotPasswordEmail(event.target.value)}
                        onKeyDown={(event) => {
                          if (event.key === 'Enter') {
                            event.preventDefault();
                            handleForgotPasswordSubmit();
                          }
                        }}
                      />
                    </DialogContent>
                    <DialogActions sx={{ px: 3, pb: 2.5 }}>
                      <Button onClick={closeForgotPasswordDialog} disabled={isSubmittingForgotPassword}>
                        Batal
                      </Button>
                      <Button
                        onClick={handleForgotPasswordSubmit}
                        variant="contained"
                        disabled={isSubmittingForgotPassword}
                      >
                        {isSubmittingForgotPassword ? 'Mengirim...' : 'Kirim Link'}
                      </Button>
                    </DialogActions>
                  </Dialog>
                </Paper>
              </Box>
            </Box>
          </Box>
        </Box>
      </Box>
    </LocalizationProvider>
  );
};

export default Login;
