import React, { useState, useEffect, useCallback, useMemo } from 'react';
import { FixedSizeList as List } from 'react-window';
import { Search, User, CheckSquare, Square, Loader2 } from 'lucide-react';
import { toast } from 'react-hot-toast';
import api from '../../services/api';

const VirtualizedUserList = ({ 
  onSelectionChange, 
  selectedUsers = [], 
  height = 400,
  showSchemaInfo = false 
}) => {
  const [users, setUsers] = useState([]);
  const [loading, setLoading] = useState(false);
  const [searchTerm, setSearchTerm] = useState('');
  const [hasMore, setHasMore] = useState(true);
  const [page, setPage] = useState(1);
  const [totalUsers, setTotalUsers] = useState(0);

  // Debounced search
  const [debouncedSearchTerm, setDebouncedSearchTerm] = useState('');
  
  useEffect(() => {
    const timer = setTimeout(() => {
      setDebouncedSearchTerm(searchTerm);
    }, 300);

    return () => clearTimeout(timer);
  }, [searchTerm]);

  // Reset when search changes
  useEffect(() => {
    setUsers([]);
    setPage(1);
    setHasMore(true);
    loadUsers(1, true);
  }, [debouncedSearchTerm]);

  const loadUsers = useCallback(async (pageNum = 1, reset = false) => {
    if (loading) return;
    
    setLoading(true);
    try {
      const endpoint = showSchemaInfo ? '/bulk-assignment/users-with-schemas' : '/bulk-assignment/users';
      const response = await api.get(endpoint, {
        params: {
          page: pageNum,
          per_page: 50,
          search: debouncedSearchTerm
        }
      });

      if (response.data.success) {
        const newUsers = response.data.data;
        const pagination = response.data.pagination;

        setUsers(prev => reset ? newUsers : [...prev, ...newUsers]);
        setHasMore(pagination.has_more);
        setTotalUsers(pagination.total);
        setPage(pageNum);
      }
    } catch (error) {
      console.error('Error loading users:', error);
      toast.error('Gagal memuat data user');
    } finally {
      setLoading(false);
    }
  }, [loading, debouncedSearchTerm, showSchemaInfo]);

  // Load more when scrolling near bottom
  const handleItemsRendered = useCallback(({ visibleStopIndex }) => {
    if (
      !loading &&
      hasMore &&
      visibleStopIndex >= users.length - 10
    ) {
      loadUsers(page + 1, false);
    }
  }, [loading, hasMore, users.length, page, loadUsers]);

  // Toggle user selection
  const toggleUserSelection = useCallback((user) => {
    const isSelected = selectedUsers.some(u => u.id === user.id);
    let newSelection;
    
    if (isSelected) {
      newSelection = selectedUsers.filter(u => u.id !== user.id);
    } else {
      newSelection = [...selectedUsers, user];
    }
    
    onSelectionChange(newSelection);
  }, [selectedUsers, onSelectionChange]);

  // Select all visible users
  const selectAllVisible = useCallback(() => {
    const visibleUserIds = new Set(selectedUsers.map(u => u.id));
    const newUsers = users.filter(user => !visibleUserIds.has(user.id));
    onSelectionChange([...selectedUsers, ...newUsers]);
  }, [users, selectedUsers, onSelectionChange]);

  // Clear all selections
  const clearSelection = useCallback(() => {
    onSelectionChange([]);
  }, [onSelectionChange]);

  // User item component
  const UserItem = React.memo(({ index, style }) => {
    const user = users[index];
    if (!user) return null;

    const isSelected = selectedUsers.some(u => u.id === user.id);
    const isASN = user.status_kepegawaian === 'ASN';

    return (
      <div style={style} className="px-4 py-2 border-b border-gray-100">
        <div 
          className={`flex items-center gap-3 p-2 rounded cursor-pointer transition-colors ${
            isSelected ? 'bg-blue-50 border border-blue-200' : 'hover:bg-gray-50'
          } ${isASN ? 'opacity-50' : ''}`}
          onClick={() => !isASN && toggleUserSelection(user)}
        >
          <div className="flex-shrink-0">
            {isASN ? (
              <div className="w-5 h-5 bg-yellow-100 rounded border border-yellow-300 flex items-center justify-center">
                <span className="text-xs text-yellow-600">ASN</span>
              </div>
            ) : isSelected ? (
              <CheckSquare className="h-5 w-5 text-blue-600" />
            ) : (
              <Square className="h-5 w-5 text-gray-400" />
            )}
          </div>
          
          <div className="flex-1 min-w-0">
            <div className="flex items-center gap-2">
              <User className="h-4 w-4 text-gray-400 flex-shrink-0" />
              <span className="font-medium text-gray-900 truncate">
                {user.name}
              </span>
              {isASN && (
                <span className="px-2 py-1 text-xs bg-yellow-100 text-yellow-800 rounded">
                  ASN
                </span>
              )}
            </div>
            
            <div className="text-sm text-gray-500 truncate">
              {user.email} • {user.username}
            </div>
            
            {showSchemaInfo && user.schema && (
              <div className="text-xs text-blue-600 mt-1">
                Schema: {user.schema.name} ({user.schema.type})
              </div>
            )}
          </div>
        </div>
      </div>
    );
  });

  const memoizedUsers = useMemo(() => users, [users]);

  return (
    <div className="bg-white rounded-lg shadow">
      {/* Header */}
      <div className="p-4 border-b border-gray-200">
        <div className="flex items-center justify-between mb-4">
          <h3 className="text-lg font-semibold text-gray-900">
            Pilih User ({totalUsers} total)
          </h3>
          <div className="flex items-center gap-2">
            <button
              onClick={selectAllVisible}
              className="px-3 py-1 text-sm bg-blue-100 text-blue-700 rounded hover:bg-blue-200"
              disabled={loading}
            >
              Pilih Semua
            </button>
            <button
              onClick={clearSelection}
              className="px-3 py-1 text-sm bg-gray-100 text-gray-700 rounded hover:bg-gray-200"
              disabled={selectedUsers.length === 0}
            >
              Bersihkan
            </button>
          </div>
        </div>

        {/* Search */}
        <div className="relative">
          <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-gray-400" />
          <input
            type="text"
            placeholder="Cari user..."
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            className="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
          />
        </div>

        {/* Selection info */}
        {selectedUsers.length > 0 && (
          <div className="mt-2 text-sm text-blue-600">
            {selectedUsers.length} user dipilih
          </div>
        )}
      </div>

      {/* Virtualized List */}
      <div className="relative">
        {loading && users.length === 0 ? (
          <div className="flex items-center justify-center py-8">
            <Loader2 className="h-6 w-6 animate-spin text-blue-600" />
            <span className="ml-2 text-gray-600">Memuat data...</span>
          </div>
        ) : users.length === 0 ? (
          <div className="text-center py-8 text-gray-500">
            {debouncedSearchTerm ? 'Tidak ada user yang ditemukan' : 'Tidak ada data user'}
          </div>
        ) : (
          <List
            height={height}
            itemCount={users.length + (hasMore ? 1 : 0)}
            itemSize={80}
            onItemsRendered={handleItemsRendered}
            itemData={memoizedUsers}
          >
            {({ index, style }) => {
              if (index >= users.length) {
                // Loading indicator for more items
                return (
                  <div style={style} className="flex items-center justify-center py-4">
                    <Loader2 className="h-4 w-4 animate-spin text-blue-600" />
                    <span className="ml-2 text-sm text-gray-600">Memuat lebih banyak...</span>
                  </div>
                );
              }
              return <UserItem index={index} style={style} />;
            }}
          </List>
        )}
      </div>
    </div>
  );
};

export default VirtualizedUserList;
