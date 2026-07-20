import { useState } from 'react';
import { NavLink, useLocation } from 'react-router-dom';
import {
  LayoutDashboard, Package, ClipboardCheck, Archive, ShoppingCart,
  Users, Wallet, UserCheck, Settings, ScrollText,
  LogOut, Building2, UserPlus, BarChart3, Boxes,
  FileText, Receipt, TrendingUp, BadgeDollarSign, Landmark,
  PersonStanding, CalendarDays, Banknote, ChevronDown, ChevronRight,
  GitBranch, Tag, PackageCheck, ShoppingBag, LifeBuoy,
} from 'lucide-react';
import { useAuthStore } from '../../stores/authStore';
import type { Role } from '../../types';

interface NavItem {
  label: string;
  to: string;
  icon: React.ReactNode;
  roles: Role[];
  children?: { label: string; to: string; icon: React.ReactNode }[];
}

const NAV: NavItem[] = [
  { label: 'Dashboard', to: '/dashboard', icon: <LayoutDashboard size={18} />, roles: ['super_admin', 'sales', 'warehouse_staff', 'qc_engineer', 'accounts', 'hr_manager', 'logistics'] },
  { label: 'Orders',      to: '/orders',       icon: <ShoppingCart size={18} />,   roles: ['super_admin', 'sales', 'warehouse_staff'] },
  { label: 'Inventory',   to: '/inventory',    icon: <Package size={18} />,        roles: ['super_admin', 'warehouse_staff', 'qc_engineer'] },
  { label: 'QC',          to: '/qc',           icon: <ClipboardCheck size={18} />, roles: ['super_admin', 'warehouse_staff', 'qc_engineer'] },
  { label: 'Bins',        to: '/bins',         icon: <Boxes size={18} />,          roles: ['super_admin', 'warehouse_staff'] },
  { label: 'Procurement', to: '/procurement',  icon: <Archive size={18} />,        roles: ['super_admin', 'warehouse_staff'] },
  { label: 'Business Partners', to: '/dealers', icon: <Building2 size={18} />, roles: ['super_admin', 'sales'] },
  { label: 'Leads',       to: '/leads',        icon: <UserPlus size={18} />,       roles: ['super_admin', 'sales'] },
  { label: 'Hierarchy',   to: '/hierarchy',    icon: <GitBranch size={18} />,      roles: ['super_admin', 'sales'] },
  { label: 'Offers',           to: '/offers',           icon: <Tag size={18} />,        roles: ['super_admin', 'sales'] },
  { label: 'Retail Customers', to: '/retail-customers', icon: <ShoppingBag size={18} />, roles: ['super_admin', 'sales', 'accounts'] },
  { label: 'Support Tickets', to: '/support', icon: <LifeBuoy size={18} />, roles: ['super_admin', 'sales', 'accounts', 'warehouse_staff'] },
  { label: 'Peti to Peti',to: '/peti',         icon: <PackageCheck size={18} />,   roles: ['super_admin', 'warehouse_staff'] },
  {
    label: 'Finance', to: '/finance', icon: <Wallet size={18} />, roles: ['super_admin', 'accounts'],
    children: [
      { label: 'Invoices',     to: '/invoices',     icon: <FileText size={15} /> },
      { label: 'Expenses',     to: '/expenses',     icon: <Receipt size={15} /> },
      { label: 'P & L',        to: '/pl',           icon: <TrendingUp size={15} /> },
      { label: 'GST',          to: '/gst',          icon: <BadgeDollarSign size={15} /> },
      { label: 'Receivables',  to: '/receivables',  icon: <Landmark size={15} /> },
    ],
  },
  {
    label: 'HR', to: '/hr', icon: <UserCheck size={18} />, roles: ['super_admin', 'hr_manager'],
    children: [
      { label: 'Employees',  to: '/employees',  icon: <PersonStanding size={15} /> },
      { label: 'Attendance', to: '/attendance', icon: <CalendarDays size={15} /> },
      { label: 'Payroll',    to: '/payroll',    icon: <Banknote size={15} /> },
    ],
  },
  { label: 'Analytics', to: '/analytics', icon: <BarChart3 size={18} />, roles: ['super_admin', 'sales', 'accounts'] },
  { label: 'Users',     to: '/users',     icon: <Users size={18} />,     roles: ['super_admin'] },
  { label: 'Audit Logs',to: '/audit',     icon: <ScrollText size={18} />,roles: ['super_admin'] },
  { label: 'Settings',  to: '/settings',  icon: <Settings size={18} />,  roles: ['super_admin'] },
];

export function Sidebar() {
  const { user, logout } = useAuthStore();
  const role = user?.role;
  const location = useLocation();

  const financeRoutes = ['/invoices', '/expenses', '/pl', '/gst', '/receivables'];
  const hrRoutes     = ['/employees', '/attendance', '/payroll'];

  const [openMenus, setOpenMenus] = useState<Record<string, boolean>>(() => ({
    Finance: financeRoutes.some((r) => location.pathname.startsWith(r)),
    HR: hrRoutes.some((r) => location.pathname.startsWith(r)),
  }));

  const toggleMenu = (label: string) =>
    setOpenMenus((prev) => ({ ...prev, [label]: !prev[label] }));

  const visible = NAV.filter((n) => role && n.roles.includes(role as Role));

  return (
    <aside className="w-60 flex-shrink-0 bg-gray-900 text-gray-300 flex flex-col h-screen sticky top-0">
      {/* Logo */}
      <div className="px-5 py-5 border-b border-gray-800">
        <span className="text-xl font-bold text-white tracking-tight">DX<span className="text-primary">EMPIRE</span></span>
        <p className="text-xs text-gray-500 mt-0.5">Admin Panel</p>
      </div>

      {/* Nav */}
      <nav className="flex-1 overflow-y-auto py-4 px-3 space-y-0.5 scrollbar-thin">
        {visible.map((item) => {
          if (!item.children) {
            return (
              <NavLink
                key={item.to}
                to={item.to}
                className={({ isActive }) =>
                  `flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors ${
                    isActive ? 'bg-primary text-white' : 'text-gray-400 hover:bg-gray-800 hover:text-white'
                  }`
                }
              >
                {item.icon}
                {item.label}
              </NavLink>
            );
          }

          // Group with children (Finance, HR)
          const isGroupActive = item.children.some((c) => location.pathname === c.to);
          const isOpen = openMenus[item.label];

          return (
            <div key={item.label}>
              <button
                onClick={() => toggleMenu(item.label)}
                className={`w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors ${
                  isGroupActive ? 'text-white' : 'text-gray-400 hover:bg-gray-800 hover:text-white'
                }`}
              >
                {item.icon}
                <span className="flex-1 text-left">{item.label}</span>
                {isOpen ? <ChevronDown size={14} /> : <ChevronRight size={14} />}
              </button>

              {isOpen && (
                <div className="ml-4 mt-0.5 space-y-0.5 border-l border-gray-700 pl-3">
                  {item.children.map((child) => (
                    <NavLink
                      key={child.to}
                      to={child.to}
                      className={({ isActive }) =>
                        `flex items-center gap-2 px-2 py-1.5 rounded-lg text-xs font-medium transition-colors ${
                          isActive ? 'bg-primary text-white' : 'text-gray-400 hover:bg-gray-800 hover:text-white'
                        }`
                      }
                    >
                      {child.icon}
                      {child.label}
                    </NavLink>
                  ))}
                </div>
              )}
            </div>
          );
        })}
      </nav>

      {/* User footer */}
      <div className="px-4 py-4 border-t border-gray-800">
        <div className="flex items-center gap-3 mb-3">
          <div className="w-8 h-8 rounded-full bg-primary flex items-center justify-center text-white text-sm font-bold">
            {user?.name?.charAt(0).toUpperCase()}
          </div>
          <div className="min-w-0">
            <p className="text-sm font-medium text-white truncate">{user?.name}</p>
            <p className="text-xs text-gray-500 truncate capitalize">{user?.role?.replace(/_/g, ' ')}</p>
          </div>
        </div>
        <button
          onClick={logout}
          className="flex items-center gap-2 text-sm text-gray-400 hover:text-red-400 transition-colors w-full"
        >
          <LogOut size={15} /> Logout
        </button>
      </div>
    </aside>
  );
}
