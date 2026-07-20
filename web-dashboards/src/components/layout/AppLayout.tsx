import { useState, useRef, useEffect } from 'react';
import { Outlet, Navigate } from 'react-router-dom';
import { Bell } from 'lucide-react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useAuthStore } from '../../stores/authStore';
import { notificationService } from '../../services';
import { Sidebar } from './Sidebar';
import type { AppNotification } from '../../types';

function fmtRelative(dt: string) {
  const diff = (Date.now() - new Date(dt).getTime()) / 1000;
  if (diff < 60) return 'just now';
  if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
  if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
  return `${Math.floor(diff / 86400)}d ago`;
}

function NotificationBell() {
  const [open, setOpen] = useState(false);
  const ref = useRef<HTMLDivElement>(null);
  const qc = useQueryClient();
  const token = useAuthStore((s) => s.token);

  // Only fire real API calls when there's a proper Sanctum token (not test/fake tokens)
  const isRealToken = !!token && token.length > 20 && token !== 'test-token-bypass';

  const { data: countData } = useQuery({
    queryKey: ['notifications-unread'],
    queryFn: notificationService.unreadCount,
    refetchInterval: isRealToken ? 30_000 : false,
    enabled: isRealToken,
  });

  const { data: notifsData, isLoading } = useQuery({
    queryKey: ['notifications-list'],
    queryFn: () => notificationService.list({ per_page: '20' }),
    enabled: open && isRealToken,
    staleTime: 10_000,
  });

  const markReadMut = useMutation({
    mutationFn: (id: number) => notificationService.markRead(id),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['notifications-unread'] });
      qc.invalidateQueries({ queryKey: ['notifications-list'] });
    },
  });

  const markAllMut = useMutation({
    mutationFn: notificationService.markAllRead,
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['notifications-unread'] });
      qc.invalidateQueries({ queryKey: ['notifications-list'] });
    },
  });

  // Close on outside click
  useEffect(() => {
    function handler(e: MouseEvent) {
      if (ref.current && !ref.current.contains(e.target as Node)) setOpen(false);
    }
    document.addEventListener('mousedown', handler);
    return () => document.removeEventListener('mousedown', handler);
  }, []);

  const unreadCount: number = countData?.count ?? 0;
  const notifications: AppNotification[] = notifsData?.data ?? [];

  return (
    <div ref={ref} className="relative">
      <button
        onClick={() => setOpen((o) => !o)}
        className="relative p-2 rounded-lg text-gray-500 hover:bg-gray-100 transition-colors"
      >
        <Bell size={20} />
        {unreadCount > 0 && (
          <span className="absolute top-1 right-1 min-w-[16px] h-4 px-1 bg-red-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center leading-none">
            {unreadCount > 99 ? '99+' : unreadCount}
          </span>
        )}
      </button>

      {open && (
        <div className="absolute right-0 top-full mt-2 w-80 bg-white rounded-xl shadow-xl border border-gray-100 z-50 overflow-hidden">
          <div className="flex items-center justify-between px-4 py-3 border-b border-gray-100">
            <span className="text-sm font-semibold text-gray-800">Notifications</span>
            {unreadCount > 0 && (
              <button
                onClick={() => markAllMut.mutate()}
                className="text-xs text-primary hover:underline"
                disabled={markAllMut.isPending}
              >
                Mark all read
              </button>
            )}
          </div>

          <div className="max-h-96 overflow-y-auto divide-y divide-gray-50">
            {isLoading && (
              <div className="py-8 text-center text-sm text-gray-400">Loading…</div>
            )}
            {!isLoading && notifications.length === 0 && (
              <div className="py-8 text-center text-sm text-gray-400">No notifications</div>
            )}
            {notifications.map((n) => (
              <div
                key={n.id}
                onClick={() => !n.is_read && markReadMut.mutate(n.id)}
                className={`px-4 py-3 hover:bg-gray-50 cursor-pointer transition-colors ${!n.is_read ? 'bg-blue-50/40' : ''}`}
              >
                <div className="flex items-start gap-2">
                  {!n.is_read && <span className="mt-1.5 w-2 h-2 rounded-full bg-primary flex-shrink-0" />}
                  {n.is_read && <span className="mt-1.5 w-2 h-2 flex-shrink-0" />}
                  <div className="min-w-0">
                    <div className="text-xs font-semibold text-gray-800 truncate">{n.title}</div>
                    <div className="text-xs text-gray-500 mt-0.5 line-clamp-2">{n.body}</div>
                    <div className="text-[10px] text-gray-400 mt-1">{fmtRelative(n.created_at)}</div>
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}

export function AppLayout() {
  const { token } = useAuthStore();
  if (!token) return <Navigate to="/login" replace />;
  return (
    <div className="flex min-h-screen bg-gray-50">
      <Sidebar />
      <div className="flex-1 flex flex-col overflow-hidden">
        <header className="bg-white border-b border-gray-100 px-6 h-12 flex items-center justify-end">
          <NotificationBell />
        </header>
        <main className="flex-1 overflow-auto">
          <div className="max-w-7xl mx-auto px-6 py-6">
            <Outlet />
          </div>
        </main>
      </div>
    </div>
  );
}
