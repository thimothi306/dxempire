import { useState } from 'react';
import { Sparkles, Loader2 } from 'lucide-react';
import toast from 'react-hot-toast';
import { inventoryService } from '../services';

export interface AiSearchFilters {
  search?: string;
  category?: string;
  grade?: string;
  status?: string;
}

export function AiSearchBox({
  onResult,
  placeholder = 'Try: "S2 iPhones in stock" or "MacBooks pending QC"',
}: {
  onResult: (filters: AiSearchFilters) => void;
  placeholder?: string;
}) {
  const [query, setQuery] = useState('');
  const [loading, setLoading] = useState(false);

  const submit = async () => {
    if (!query.trim()) return;
    setLoading(true);
    try {
      const filters: AiSearchFilters = await inventoryService.aiSearch(query.trim());
      if (Object.keys(filters).length === 0) {
        toast.error("Couldn't understand that — try being more specific about grade, category, or status.");
        return;
      }
      onResult(filters);
    } catch {
      toast.error('AI search failed — try the filters below instead.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="relative">
      <Sparkles size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-primary" />
      <input
        value={query}
        onChange={(e) => setQuery(e.target.value)}
        onKeyDown={(e) => { if (e.key === 'Enter') submit(); }}
        placeholder={placeholder}
        disabled={loading}
        className="w-full pl-9 pr-10 py-2.5 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary bg-white"
      />
      {loading && <Loader2 size={15} className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 animate-spin" />}
    </div>
  );
}
