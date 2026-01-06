'use client';

import Link from 'next/link';
import { useRouter, usePathname } from 'next/navigation';
import { authService } from '@/services/auth';

interface NavigationProps {
  user?: { name: string } | null;
}

export default function Navigation({ user }: NavigationProps) {
  const router = useRouter();
  const pathname = usePathname();

  const handleLogout = async () => {
    await authService.logout();
    router.push('/login');
  };

  const navLinks = [
    { href: '/dashboard', label: 'Dashboard' },
    { href: '/proposals', label: 'Proposals' },
    { href: '/projects', label: 'Projects' },
    { href: '/skills', label: 'Skills' },
    { href: '/user-skills', label: 'My Skills' },
    { href: '/ai-testing', label: 'AI Testing' },
  ];

  return (
    <nav className="bg-white shadow-sm">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="flex justify-between h-16">
          <div className="flex items-center space-x-8">
            <Link href="/dashboard" className="text-xl font-semibold text-gray-900">
              AI Tool
            </Link>
            {navLinks.map(({ href, label }) => (
              <Link 
                key={href}
                href={href} 
                className={pathname === href 
                  ? 'text-indigo-600 font-medium' 
                  : 'text-gray-700 hover:text-gray-900'
                }
              >
                {label}
              </Link>
            ))}
          </div>
          <div className="flex items-center space-x-4">
            {user && (
              <span className="text-gray-700">Welcome, {user.name}</span>
            )}
            <Link href="/profile" className="text-indigo-600 hover:text-indigo-900">
              Profile
            </Link>
            <button
              onClick={handleLogout}
              className="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-md text-sm font-medium"
            >
              Logout
            </button>
          </div>
        </div>
      </div>
    </nav>
  );
}
