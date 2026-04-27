# Frontend Structure Documentation

## Refactored ManajemenPengguna Module

Struktur kode telah direfactor untuk memisahkan antara halaman, komponen, hook, dan state management agar lebih modular dan mudah dipelihara.

### 📁 Struktur Direktori

```
src/
├── hooks/                          # Custom hooks untuk logic dan state management
│   ├── index.js                   # Export semua hooks
│   ├── useAuth.jsx                # Hook untuk autentikasi
│   ├── usePerformance.jsx         # Hook untuk performance monitoring
│   ├── useUserManagement.jsx      # Hook untuk manajemen pengguna
│   ├── useRoleManagement.jsx      # Hook untuk manajemen role
│   ├── usePegawaiForm.jsx         # Hook untuk form pegawai
│   └── usePasswordManagement.jsx  # Hook untuk manajemen password
│
├── components/
│   ├── users/                     # Komponen khusus untuk manajemen pengguna
│   │   ├── index.js              # Export semua komponen users
│   │   ├── UserTable.jsx         # Komponen tabel pengguna
│   │   ├── UserFilters.jsx       # Komponen filter dan search
│   │   ├── UserPagination.jsx    # Komponen pagination
│   │   ├── UserTabs.jsx          # Komponen tab navigation
│   │   └── UserTableHeader.jsx   # Komponen header tabel
│   │
│   └── [komponen lainnya...]
│
└── pages/
    ├── ManajemenPengguna.jsx      # Halaman utama (refactored)
    └── [halaman lainnya...]
```

### 🔧 Custom Hooks

#### 1. `useUserManagement`
Mengelola state dan logic untuk operasi CRUD pengguna:
- Loading data pengguna (pegawai/siswa)
- Filter dan pagination
- Delete dan toggle status pengguna

#### 2. `useRoleManagement`
Mengelola data role dan sub-role:
- Loading primary roles dan sub-roles
- Update available sub-roles berdasarkan parent role

#### 3. `usePasswordManagement`
Mengelola operasi reset password:
- Reset password pegawai dengan validasi
- Reset password siswa ke tanggal lahir

#### 4. `usePegawaiForm`
Mengelola state form pegawai:
- Form data management
- Image upload dan preview
- Form validation

### 🧩 Komponen Users

#### 1. `UserTable`
Komponen tabel yang menampilkan data pengguna dengan:
- Responsive design
- Action buttons (edit, delete, reset password, toggle status)
- Loading dan empty states

#### 2. `UserFilters`
Komponen filter dan search dengan:
- Search input
- Status filter
- Add user button

#### 3. `UserPagination`
Komponen pagination dengan:
- Page navigation
- Results count
- Responsive design

#### 4. `UserTabs`
Komponen tab navigation untuk:
- Switch antara pegawai dan siswa
- Active state styling

#### 5. `UserTableHeader`
Komponen header tabel dengan:
- Dynamic columns berdasarkan tab aktif
- Responsive column visibility

### 📄 Halaman ManajemenPengguna

Halaman utama yang telah direfactor untuk:
- Menggunakan custom hooks untuk logic
- Menggunakan komponen modular
- Clean import structure
- Separation of concerns

### 🎯 Keuntungan Refactoring

1. **Modularity**: Setiap komponen memiliki tanggung jawab yang jelas
2. **Reusability**: Hooks dan komponen dapat digunakan kembali
3. **Maintainability**: Kode lebih mudah dipelihara dan di-debug
4. **Testability**: Setiap unit dapat ditest secara terpisah
5. **Readability**: Kode lebih mudah dibaca dan dipahami
6. **Scalability**: Mudah untuk menambah fitur baru

### 🚀 Cara Penggunaan

```jsx
// Import hooks
import { 
  useUserManagement, 
  useRoleManagement, 
  usePasswordManagement 
} from '../hooks';

// Import komponen
import { 
  UserTable, 
  UserFilters, 
  UserPagination,
  UserTabs,
  UserTableHeader 
} from '../components/users';

// Gunakan dalam komponen
const MyComponent = () => {
  const { users, loading, loadUsers } = useUserManagement();
  
  return (
    <div>
      <UserTabs activeTab="pegawai" onTabChange={setActiveTab} />
      <UserFilters filters={filters} onFilterChange={handleFilterChange} />
      {/* ... */}
    </div>
  );
};
```

### 📝 Best Practices

1. **Single Responsibility**: Setiap hook/komponen memiliki satu tanggung jawab
2. **Props Interface**: Gunakan props yang jelas dan terdokumentasi
3. **Error Handling**: Implementasi error handling di setiap hook
4. **Loading States**: Berikan feedback loading yang jelas
5. **Responsive Design**: Semua komponen responsive
6. **Clean Code**: Kode yang bersih dan mudah dibaca

### 🔄 Migration Guide

Jika ingin mengaplikasikan pola yang sama ke halaman lain:

1. Identifikasi logic yang bisa diextract ke hooks
2. Pisahkan komponen UI yang reusable
3. Buat struktur direktori yang konsisten
4. Update import statements
5. Test functionality

### 🧪 Testing

Setiap hook dan komponen dapat ditest secara terpisah:

```jsx
// Test hook
import { renderHook } from '@testing-library/react-hooks';
import { useUserManagement } from '../hooks/useUserManagement';

// Test komponen
import { render } from '@testing-library/react';
import UserTable from '../components/users/UserTable';
```

Struktur ini memberikan foundation yang solid untuk pengembangan aplikasi yang scalable dan maintainable.
