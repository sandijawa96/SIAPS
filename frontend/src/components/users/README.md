# User Management Components

Komponen-komponen untuk halaman Manajemen Pengguna yang telah direfactor menggunakan MUI, Tailwind CSS, dan Lucide icons dengan arsitektur yang clean dan modular.

## 🏗️ Struktur Komponen

### 1. UserManagementHeader.jsx

**Fungsi**: Header halaman dengan title dan deskripsi
**Props**: Tidak ada
**Features**:

- Icon Users dari Lucide
- Typography menggunakan MUI
- Styling dengan Tailwind CSS

### 2. UserTabs.jsx

**Fungsi**: Tab navigation untuk switch antara Pegawai dan Siswa
**Props**:

- `activeTab`: Tab yang sedang aktif ('pegawai' | 'siswa')
- `onTabChange`: Function untuk handle perubahan tab
- `userCounts`: Object berisi jumlah user per tab

**Features**:

- MUI Tabs component
- Badge untuk menampilkan jumlah user
- Icons yang berbeda untuk setiap tab
- Smooth transition effects

### 3. UserFiltersNew.jsx

**Fungsi**: Filter dan action buttons
**Props**:

- `activeTab`: Tab yang sedang aktif
- `filters`: Object berisi filter values
- `onFilterChange`: Function untuk handle perubahan filter
- `onAddUser`, `onExport`, `onImport`: Action handlers
- `selectedUsers`: Array user yang dipilih
- `onBulkDelete`: Function untuk bulk delete
- `availableRoles`, `availableSubRoles`: Data role dari database

**Features**:

- Search field dengan icon
- Filter dropdown untuk role dan status
- Action buttons dengan icons
- Responsive design
- Chip untuk menampilkan selected items

### 4. UserTableNew.jsx

**Fungsi**: Tabel data user dengan sorting dan actions
**Props**:

- `users`: Array data user
- `loading`: Loading state
- `activeTab`: Tab yang sedang aktif
- `selectedUsers`: Array user yang dipilih
- `onSelectUser`, `onSelectAll`: Selection handlers
- `onEdit`, `onDelete`, `onResetPassword`, `onToggleStatus`: Action handlers
- `sortConfig`, `onSort`: Sorting configuration

**Features**:

- MUI Table components
- Skeleton loading states
- Avatar dengan fallback
- Action menu dengan dropdown
- Sortable columns
- Checkbox selection
- Status chips
- Responsive design

### 5. UserPaginationNew.jsx

**Fungsi**: Pagination controls
**Props**:

- `pagination`: Object berisi pagination data
- `onPageChange`: Function untuk handle page change
- `onPerPageChange`: Function untuk handle per page change

**Features**:

- MUI Pagination component
- Per page selector
- Info text showing current range
- Responsive layout

## 🎣 Custom Hooks

### 1. useUserManagementNew.jsx

**Fungsi**: State management untuk user data dan operations
**Returns**:

- State: `users`, `loading`, `pagination`, `filters`, `selectedUsers`, `sortConfig`
- Actions: `loadUsers`, `handleFilterChange`, `handlePageChange`, `handleSort`, `handleSelectUser`, `handleSelectAll`, `handleDeleteUser`, `toggleUserStatus`, `handleBulkDelete`

**Features**:

- Centralized state management
- Error handling dengan snackbar
- Optimistic updates
- Clean API integration

### 2. useRoleManagementNew.jsx

**Fungsi**: State management untuk role data
**Returns**:

- State: `primaryRoles`, `allSubRoles`, `availableSubRoles`, `loading`, `error`
- Actions: `loadRoles`, `fetchSubRoles`, `updateAvailableSubRoles`
- Utilities: `getRoleById`, `getRoleByName`, `isPrimaryRole`, `isSubRole`, `getFormattedRoles`

**Features**:

- Auto-load roles on mount
- Dynamic sub-role fetching
- Role validation utilities
- Error handling

### 3. useUserModalManagement.jsx

**Fungsi**: State management untuk modal operations
**Returns**:

- State: Modal visibility states, `selectedUser`, `importProgress`, `exportProgress`
- Actions: `openModal`, `closeModal`, `handleImport`, `handleExport`

**Features**:

- Centralized modal state
- Progress tracking untuk import/export
- File handling
- Success/error callbacks

## 📱 Modal Components

### 1. ImportModalNew.jsx

**Fungsi**: Modal untuk import data dengan progress bar
**Props**:

- `isOpen`, `onClose`, `onSuccess`: Modal controls
- `userType`: Type user ('pegawai' | 'siswa')
- `onImport`: Import handler
- `progress`: Import progress (0-100)

**Features**:

- Drag & drop file upload
- File validation
- Import options (mode, update type)
- Progress bar dengan animasi
- Error handling dengan detail
- Template download
- MUI Dialog dengan custom styling

### 2. ExportModalNew.jsx

**Fungsi**: Modal untuk export data dengan options
**Props**:

- `isOpen`, `onClose`: Modal controls
- `onExport`: Export handler
- `userType`: Type user
- `progress`: Export progress

**Features**:

- Export format selection
- Field selection dengan checkbox
- Date range options
- Progress tracking
- Custom export options
- MUI Dialog dengan styling

## 🎨 Design System

### Color Palette

- **Primary**: Blue (#3B82F6)
- **Success**: Green (#10B981)
- **Error**: Red (#EF4444)
- **Warning**: Yellow (#F59E0B)
- **Gray**: Various shades untuk neutral elements

### Typography

- **Headers**: MUI Typography variant h4-h6
- **Body**: MUI Typography variant body1-body2
- **Captions**: MUI Typography variant caption

### Spacing

- **Container**: MUI Container maxWidth="xl"
- **Padding**: Tailwind classes (p-4, p-6, py-6)
- **Margins**: Tailwind classes (mb-4, mb-6, mt-4)
- **Gaps**: Tailwind classes (gap-2, gap-3, gap-4)

### Icons

- **Library**: Lucide React
- **Size**: Consistent 4x4 (w-4 h-4) dan 5x5 (w-5 h-5)
- **Usage**: Semantic icons untuk setiap action

## 🔧 State Management Pattern

### 1. Centralized State

Setiap hook mengelola state yang terkait dengan domain-nya:

- `useUserManagementNew`: User data dan operations
- `useRoleManagementNew`: Role data
- `useUserModalManagement`: Modal states

### 2. State Updates

Menggunakan pattern `updateState` helper untuk immutable updates:

```javascript
const updateState = useCallback((updates) => {
  setState((prev) => ({ ...prev, ...updates }));
}, []);
```

### 3. Error Handling

Consistent error handling dengan snackbar notifications:

```javascript
enqueueSnackbar(message, { variant: "error" });
```

## 📋 Props Interface

### Common Props Pattern

```typescript
interface BaseProps {
  className?: string;
  children?: React.ReactNode;
}

interface UserManagementProps {
  activeTab: "pegawai" | "siswa";
  loading?: boolean;
  onSuccess?: () => void;
  onError?: (error: Error) => void;
}
```

## 🚀 Performance Optimizations

### 1. Memoization

- `useCallback` untuk event handlers
- `useMemo` untuk computed values
- `React.memo` untuk komponen yang tidak sering berubah

### 2. Lazy Loading

- Skeleton components untuk loading states
- Progressive data loading
- Image lazy loading untuk avatars

### 3. Efficient Re-renders

- Proper dependency arrays
- State normalization
- Minimal prop drilling

## 🧪 Testing Strategy

### 1. Unit Tests

- Hook testing dengan React Testing Library
- Component testing dengan user interactions
- Utility function testing

### 2. Integration Tests

- Modal workflows
- Data flow testing
- API integration testing

### 3. E2E Tests

- Complete user workflows
- Cross-browser testing
- Accessibility testing

## 📚 Usage Examples

### Basic Usage

```jsx
import ManajemenPengguna from "./pages/ManajemenPenggunaNew";

function App() {
  return <ManajemenPengguna />;
}
```

### Custom Hook Usage

```jsx
import useUserManagementNew from "./hooks/useUserManagementNew";

function CustomComponent() {
  const { users, loading, loadUsers } = useUserManagementNew();

  useEffect(() => {
    loadUsers("pegawai");
  }, []);

  return <div>{loading ? "Loading..." : `${users.length} users`}</div>;
}
```

## 🔄 Migration Guide

### From Old Components

1. Replace import paths
2. Update prop names sesuai interface baru
3. Update event handlers
4. Test functionality

### Breaking Changes

- Props interface berubah
- Event handler signatures berubah
- CSS classes menggunakan Tailwind
- Icons menggunakan Lucide

## 🎯 Best Practices

### 1. Component Design

- Single responsibility principle
- Proper prop typing
- Consistent naming conventions
- Reusable components

### 2. State Management

- Centralized state per domain
- Immutable updates
- Proper error boundaries
- Loading states

### 3. Styling

- Consistent design tokens
- Responsive design
- Accessibility considerations
- Performance optimizations

### 4. Code Organization

- Logical file structure
- Clear separation of concerns
- Proper imports/exports
- Documentation
