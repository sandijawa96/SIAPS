// Debug script untuk melihat struktur data user dan permissions
// Jalankan di browser console setelah login

console.log('=== DEBUG USER PERMISSIONS ===');

// Ambil data user dari localStorage
const storedUser = localStorage.getItem('user');
if (storedUser) {
  try {
    const user = JSON.parse(storedUser);
    console.log('User data from localStorage:', user);
    console.log('User role:', user.role);
    console.log('User permissions:', user.permissions);
    console.log('Permissions type:', typeof user.permissions);
    console.log('Is permissions array?', Array.isArray(user.permissions));
    
    if (user.permissions) {
      console.log('Permissions length:', user.permissions.length);
      console.log('First few permissions:', user.permissions.slice(0, 5));
    }
    
    // Test hasPermission function
    const testPermissions = ['manage_pegawai', 'manage_kelas', 'manage_roles'];
    testPermissions.forEach(permission => {
      const hasPermission = user.permissions && user.permissions.includes(permission);
      console.log(`Has permission '${permission}':`, hasPermission);
    });
    
  } catch (error) {
    console.error('Error parsing user data:', error);
  }
} else {
  console.log('No user data found in localStorage');
}

console.log('=== END DEBUG ===');
