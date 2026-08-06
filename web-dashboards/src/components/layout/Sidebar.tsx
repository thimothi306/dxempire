import { useState } from 'react';
import { NavLink, useLocation } from 'react-router-dom';
import {
  LayoutDashboard, Package, ClipboardCheck, Archive, ShoppingCart,
  Users, Wallet, UserCheck, Settings, ScrollText,
  LogOut, Building2, UserPlus, BarChart3, Boxes,
  FileText, Receipt, TrendingUp, BadgeDollarSign, Landmark,
  PersonStanding, CalendarDays, Banknote, ChevronDown, ChevronRight,
  GitBranch, Tag, PackageCheck, ShoppingBag, LifeBuoy, Images, Warehouse, Award, ShieldCheck,
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
  { label: 'Dashboard', to: '/dashboard', icon: <LayoutDashboard size={18} />, roles: ['super_admin', 'sales', 'warehouse_staff', 'qc_engineer', 'accounts', 'hr_manager', 'logistics', 'b2b_partner'] },
  { label: 'My Orders',   to: '/orders',       icon: <ShoppingCart size={18} />,   roles: ['b2b_partner'] },
  { label: 'My Invoices', to: '/invoices',     icon: <FileText size={18} />,       roles: ['b2b_partner'] },
  { label: 'My Dues',     to: '/dues',         icon: <Wallet size={18} />,         roles: ['b2b_partner'] },
  { label: 'Orders',      to: '/orders',       icon: <ShoppingCart size={18} />,   roles: ['super_admin', 'sales', 'warehouse_staff'] },
  { label: 'Inventory',   to: '/inventory',    icon: <Package size={18} />,        roles: ['super_admin', 'warehouse_staff', 'qc_engineer'] },
  { label: 'Catalog Images', to: '/catalog-images', icon: <Images size={18} />,    roles: ['super_admin'] },
  { label: 'QC',          to: '/qc',           icon: <ClipboardCheck size={18} />, roles: ['super_admin', 'warehouse_staff', 'qc_engineer'] },
  { label: 'Bins',        to: '/bins',         icon: <Boxes size={18} />,          roles: ['super_admin', 'warehouse_staff'] },
  { label: 'Warehouses',  to: '/warehouses',   icon: <Warehouse size={18} />,      roles: ['super_admin'] },
  { label: 'Grades',      to: '/grades',       icon: <Award size={18} />,          roles: ['super_admin'] },
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
  { label: 'Permissions', to: '/permissions', icon: <ShieldCheck size={18} />, roles: ['super_admin'] },
  { label: 'Audit Logs',to: '/audit',     icon: <ScrollText size={18} />,roles: ['super_admin'] },
  { label: 'Settings',  to: '/settings',  icon: <Settings size={18} />,  roles: ['super_admin'] },
];

interface SidebarProps {
  isOpen?: boolean;
  onClose?: () => void;
}

export function Sidebar({ isOpen = false, onClose }: SidebarProps) {
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
    <>
      {/* Mobile overlay */}
      {isOpen && (
        <div
          className="fixed inset-0 bg-black/50 z-40 md:hidden"
          onClick={onClose}
        />
      )}

      <aside
        className={`w-60 flex-shrink-0 bg-navy-800 text-blue-100/70 flex flex-col h-screen fixed md:sticky top-0 left-0 z-50 transition-transform duration-200 border-l-4 border-accent ${
          isOpen ? 'translate-x-0' : '-translate-x-full'
        } md:translate-x-0`}
      >
        {/* Logo */}
        <div className="border-b border-navy-600">
          <div className="bg-white py-3 flex items-center justify-center shadow-md shadow-black/20">
            <img src="/DX_Empire_Logo_Admin.png" alt="DXEmpire Admin Panel" className="h-14 w-auto object-contain" />
          </div>
        </div>

        {/* Nav */}
        <nav className="flex-1 overflow-y-auto py-4 px-3 space-y-0.5 scrollbar-thin">
        {visible.map((item) => {
          if (!item.children) {
            return (
              <NavLink
                key={item.to}
                to={item.to}
                onClick={onClose}
                className={({ isActive }) =>
                  `relative flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors ${
                    isActive
                      ? 'bg-primary text-white before:absolute before:left-0 before:top-1.5 before:bottom-1.5 before:w-1 before:rounded-full before:bg-accent before:-ml-3'
                      : 'text-blue-100/60 hover:bg-navy-600 hover:text-white'
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
                  isGroupActive ? 'text-white' : 'text-blue-100/60 hover:bg-navy-600 hover:text-white'
                }`}
              >
                {item.icon}
                <span className="flex-1 text-left">{item.label}</span>
                {isOpen ? <ChevronDown size={14} /> : <ChevronRight size={14} />}
              </button>

              {isOpen && (
                <div className="ml-4 mt-0.5 space-y-0.5 border-l border-navy-600 pl-3">
                  {item.children.map((child) => (
                    <NavLink
                      key={child.to}
                      to={child.to}
                      onClick={onClose}
                      className={({ isActive }) =>
                        `flex items-center gap-2 px-2 py-1.5 rounded-lg text-xs font-medium transition-colors ${
                          isActive ? 'bg-primary text-white' : 'text-blue-100/60 hover:bg-navy-600 hover:text-white'
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
      <div className="px-4 py-4 border-t border-navy-600">
        <div className="flex items-center gap-3 mb-3">
          <div className="w-8 h-8 rounded-full bg-accent flex items-center justify-center text-navy-800 text-sm font-bold">
            {user?.name?.charAt(0).toUpperCase()}
          </div>
          <div className="min-w-0">
            <p className="text-sm font-medium text-white truncate">{user?.name}</p>
            <p className="text-xs text-blue-100/50 truncate capitalize">{user?.role?.replace(/_/g, ' ')}</p>
          </div>
        </div>
        <button
          onClick={logout}
          className="flex items-center gap-2 text-sm text-blue-100/60 hover:text-accent transition-colors w-full"
        >
          <LogOut size={15} /> Logout
        </button>
        </div>
      </aside>
    </>
  );
}
