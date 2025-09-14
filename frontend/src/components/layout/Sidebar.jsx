import { NavLink, useLocation } from 'react-router-dom';
import { motion, AnimatePresence } from 'framer-motion';
import {
  Home,
  Calendar,
  FileText,
  CheckSquare,
  MessageSquare,
  Building,
  Users,
  Settings,
  BarChart3,
  FolderOpen,
  ChevronDown,
  LogOut,
  X
} from 'lucide-react';
import { useState } from 'react';
import { useAuthStore } from '../../stores/authStore';
import clsx from 'clsx';

/**
 * Sidebar con navigazione gerarchica
 * Supporta menu collassabili e responsive
 */
const Sidebar = ({ isOpen, isMobileOpen, onClose }) => {
  const location = useLocation();
  const { user, logout } = useAuthStore();
  const [expandedMenus, setExpandedMenus] = useState(['']);

  // Configurazione menu di navigazione
  const navigation = [
    {
      name: 'Dashboard',
      href: '/dashboard',
      icon: Home,
      current: location.pathname === '/dashboard'
    },
    {
      name: 'Calendario',
      href: '/calendar',
      icon: Calendar,
      current: location.pathname === '/calendar'
    },
    {
      name: 'File Manager',
      href: '/files',
      icon: FolderOpen,
      current: location.pathname.startsWith('/files'),
      children: [
        { name: 'I miei file', href: '/files/my-files' },
        { name: 'Condivisi', href: '/files/shared' },
        { name: 'Workflow', href: '/files/workflows' }
      ]
    },
    {
      name: 'Task',
      href: '/tasks',
      icon: CheckSquare,
      current: location.pathname.startsWith('/tasks'),
      children: [
        { name: 'Kanban Board', href: '/tasks/kanban' },
        { name: 'Lista Task', href: '/tasks/list' },
        { name: 'Timeline', href: '/tasks/timeline' }
      ]
    },
    {
      name: 'Chat',
      href: '/chat',
      icon: MessageSquare,
      current: location.pathname === '/chat',
      badge: 3 // Numero messaggi non letti
    },
    {
      name: 'Aziende',
      href: '/companies',
      icon: Building,
      current: location.pathname.startsWith('/companies'),
      requiredRole: ['admin', 'manager']
    },
    {
      name: 'Utenti',
      href: '/users',
      icon: Users,
      current: location.pathname === '/users',
      requiredRole: ['admin']
    },
    {
      name: 'Report',
      href: '/reports',
      icon: BarChart3,
      current: location.pathname === '/reports'
    }
  ];

  const adminNavigation = [
    {
      name: 'Impostazioni',
      href: '/settings',
      icon: Settings,
      current: location.pathname.startsWith('/settings'),
      children: [
        { name: 'Generali', href: '/settings/general' },
        { name: 'Tenant', href: '/settings/tenant' },
        { name: 'Sicurezza', href: '/settings/security' },
        { name: 'Audit Log', href: '/settings/audit' }
      ]
    }
  ];

  const toggleMenu = (menuName) => {
    setExpandedMenus(prev =>
      prev.includes(menuName)
        ? prev.filter(m => m !== menuName)
        : [...prev, menuName]
    );
  };

  const checkPermission = (item) => {
    if (!item.requiredRole) return true;
    return item.requiredRole.includes(user?.role);
  };

  const renderNavItem = (item) => {
    const hasPermission = checkPermission(item);
    if (!hasPermission) return null;

    const isExpanded = expandedMenus.includes(item.name);

    return (
      <li key={item.name}>
        {item.children ? (
          <>
            <button
              onClick={() => toggleMenu(item.name)}
              className={clsx(
                'sidebar-link w-full',
                item.current ? 'sidebar-link-active' : 'sidebar-link-inactive'
              )}
            >
              <item.icon className="h-5 w-5 flex-shrink-0" />
              <span className="flex-1 text-left">{item.name}</span>
              <ChevronDown
                className={clsx(
                  'h-4 w-4 transition-transform',
                  isExpanded && 'rotate-180'
                )}
              />
            </button>
            <AnimatePresence>
              {isExpanded && (
                <motion.ul
                  initial={{ height: 0, opacity: 0 }}
                  animate={{ height: 'auto', opacity: 1 }}
                  exit={{ height: 0, opacity: 0 }}
                  transition={{ duration: 0.2 }}
                  className="mt-1 space-y-1 overflow-hidden"
                >
                  {item.children.map(child => (
                    <li key={child.href}>
                      <NavLink
                        to={child.href}
                        className={({ isActive }) =>
                          clsx(
                            'sidebar-link pl-11',
                            isActive ? 'sidebar-link-active' : 'sidebar-link-inactive'
                          )
                        }
                      >
                        {child.name}
                      </NavLink>
                    </li>
                  ))}
                </motion.ul>
              )}
            </AnimatePresence>
          </>
        ) : (
          <NavLink
            to={item.href}
            className={({ isActive }) =>
              clsx(
                'sidebar-link',
                isActive ? 'sidebar-link-active' : 'sidebar-link-inactive'
              )
            }
          >
            <item.icon className="h-5 w-5 flex-shrink-0" />
            <span>{item.name}</span>
            {item.badge && (
              <span className="ml-auto inline-flex items-center justify-center px-2 py-0.5 text-xs font-medium rounded-full bg-primary text-white">
                {item.badge}
              </span>
            )}
          </NavLink>
        )}
      </li>
    );
  };

  const sidebarContent = (
    <>
      {/* Logo e branding */}
      <div className="flex h-16 items-center justify-between px-4 border-b border-gray-800">
        <div className="flex items-center gap-3">
          <div className="w-8 h-8 bg-primary rounded-lg flex items-center justify-center">
            <span className="text-white font-bold text-lg">N</span>
          </div>
          <span className="text-xl font-bold text-white">NexioSolution</span>
        </div>
        {isMobileOpen && (
          <button
            onClick={onClose}
            className="lg:hidden text-gray-400 hover:text-white"
          >
            <X className="h-6 w-6" />
          </button>
        )}
      </div>

      {/* Menu principale */}
      <nav className="flex-1 space-y-1 px-3 py-4">
        <ul className="space-y-1">
          {navigation.map(renderNavItem)}
        </ul>

        {/* Sezione admin */}
        {user?.role === 'admin' && (
          <>
            <div className="my-4 border-t border-gray-800" />
            <div className="px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">
              Amministrazione
            </div>
            <ul className="mt-2 space-y-1">
              {adminNavigation.map(renderNavItem)}
            </ul>
          </>
        )}
      </nav>

      {/* Footer sidebar con profilo utente */}
      <div className="border-t border-gray-800 p-4">
        <div className="flex items-center gap-3">
          <div className="flex-shrink-0">
            <div className="h-8 w-8 rounded-full bg-gray-600 flex items-center justify-center">
              <span className="text-white text-sm font-medium">
                {user?.name?.charAt(0).toUpperCase()}
              </span>
            </div>
          </div>
          <div className="flex-1 min-w-0">
            <p className="text-sm font-medium text-white truncate">
              {user?.name}
            </p>
            <p className="text-xs text-gray-400 truncate">
              {user?.email}
            </p>
          </div>
          <button
            onClick={logout}
            className="text-gray-400 hover:text-white transition-colors"
            title="Logout"
          >
            <LogOut className="h-5 w-5" />
          </button>
        </div>
      </div>
    </>
  );

  return (
    <>
      {/* Sidebar desktop */}
      <AnimatePresence>
        {isOpen && (
          <motion.aside
            initial={{ x: -256 }}
            animate={{ x: 0 }}
            exit={{ x: -256 }}
            transition={{ duration: 0.3 }}
            className="hidden lg:flex lg:flex-col lg:fixed lg:inset-y-0 lg:w-64 lg:bg-dark-sidebar"
          >
            {sidebarContent}
          </motion.aside>
        )}
      </AnimatePresence>

      {/* Sidebar mobile */}
      <AnimatePresence>
        {isMobileOpen && (
          <motion.aside
            initial={{ x: -256 }}
            animate={{ x: 0 }}
            exit={{ x: -256 }}
            transition={{ duration: 0.3 }}
            className="fixed inset-y-0 left-0 z-50 flex flex-col w-64 bg-dark-sidebar lg:hidden"
          >
            {sidebarContent}
          </motion.aside>
        )}
      </AnimatePresence>
    </>
  );
};

export default Sidebar;