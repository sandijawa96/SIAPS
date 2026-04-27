import React from 'react';
import { TextField, InputAdornment } from '@mui/material';
import { Search, Filter } from 'lucide-react';

const KelasSearch = ({
  searchTerm,
  setSearchTerm,
  activeTab,
  placeholder
}) => {
  const getPlaceholder = () => {
    if (placeholder) return placeholder;
    return activeTab === 'kelas' 
      ? 'Cari kelas atau wali kelas...' 
      : 'Cari tingkat...';
  };

  return (
    <div className="mb-6">
      <div className="flex flex-col md:flex-row gap-4">
        <div className="flex-1">
          <TextField
            fullWidth
            variant="outlined"
            placeholder={getPlaceholder()}
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            InputProps={{
              startAdornment: (
                <InputAdornment position="start">
                  <Search className="w-5 h-5 text-gray-400" />
                </InputAdornment>
              ),
            }}
            className="bg-white"
          />
        </div>
        
        {/* Additional filters can be added here */}
        <div className="flex items-center gap-2">
          {/* Filter button for future enhancement */}
          {/* <Button
            variant="outlined"
            startIcon={<Filter className="w-4 h-4" />}
            className="whitespace-nowrap"
          >
            Filter
          </Button> */}
        </div>
      </div>
    </div>
  );
};

export default KelasSearch;
