/*
 * Okatana - A Newfoundcodes project.
 *
 * Copyright (C) 2026 Jonathan Eldy Baldivicio
 *
 * Author: Jonathan Eldy Baldivicio
 * Contact: jonathaneldy.baldivicio@newfoundcodes.com
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

import { useEffect, useState } from 'react';
import { createRoot } from 'react-dom/client';
import { get, navigate, post } from './lib/api';
import NotificationsMenu from './components/NotificationsMenu';
import AccountSecuritySetup from './components/AccountSecuritySetup';
import ToastViewport from './components/ToastViewport';
import { Avatar, GridGuides } from './components/Primitives';
import AuthPage from './pages/AuthPage';
import InvitePage from './pages/InvitePage';
import DashboardPage from './pages/DashboardPage';
import DocumentsPage from './pages/DocumentsPage';
import DocumentEditorPage from './pages/DocumentEditorPage';
import DocumentViewPage from './pages/DocumentViewPage';
import OrganizationPage from './pages/OrganizationPage';
import ProjectPage from './pages/ProjectPage';
import ProjectsPage from './pages/ProjectsPage';
import UserProfilePage from './pages/UserProfilePage';
import UserSettingsPage from './pages/UserSettingsPage';

function spaNavigate(event, href) {
  if (
    event.defaultPrevented ||
    event.button !== 0 ||
    event.metaKey ||
    event.ctrlKey ||
    event.shiftKey ||
    event.altKey
  ) {
    return;
  }

  event.preventDefault();
  navigate(href);
}

function SidebarLink({ href, active, children, target, rel }: any) {
  return (
    <a
      href={href}
      onClick={target ? undefined : (event) => spaNavigate(event, href)}
      target={target}
      rel={rel || (target === '_blank' ? 'noopener noreferrer' : undefined)}
      aria-current={active ? 'page' : undefined}
      className={`flex min-h-10 items-center border-l-2 px-4 py-2 text-sm transition-colors duration-150 ease-out motion-reduce:transition-none ${
        active
          ? 'border-accent font-semibold text-ink'
          : 'border-transparent font-normal text-ink-muted hover:border-line-strong hover:text-ink'
      }`}
    >
      {children}
    </a>
  );
}

function WorkspaceSidebar({ user, path, onLogout }) {
  const organizationsActive = path === '/app' || path.startsWith('/app/organizations/');
  const projectsActive = path === '/app/projects' || path.startsWith('/app/projects/');
  const documentsActive = path === '/app/documents' || path.startsWith('/app/documents/');

  return (
    <aside className="relative z-20 col-span-4 flex flex-col border-b border-line bg-paper px-8 py-8 md:sticky md:top-0 md:col-span-2 md:h-screen md:border-b-0 md:border-r md:px-6 xl:col-span-2">
      <a
        href="/app"
        onClick={(event) => spaNavigate(event, '/app')}
        className="mb-8 flex items-center text-xl font-semibold tracking-display text-ink"
      >
        <img src="/logo-256.png" alt="Okatana" className="mr-2 h-8 w-8" />
        Okatana
      </a>

      <nav aria-label="Workspace navigation" className="grid gap-2">
        <SidebarLink href="/app" active={organizationsActive}>
          Organizations
        </SidebarLink>
        <SidebarLink href="/app/projects" active={projectsActive}>
          Projects
        </SidebarLink>
        <SidebarLink href="/app/documents" active={documentsActive}>
          Documents
        </SidebarLink>
        <SidebarLink href="/docs" target="_blank">
          Help Center
        </SidebarLink>
      </nav>

      <div className="mt-8 border-t border-line pt-4 md:mt-auto">
        <div className="mb-4 flex min-w-0 items-center gap-2 px-4">
          <Avatar user={user} size="sm" />
          <div className="min-w-0">
            <div className="truncate text-sm font-medium text-ink">{user.name}</div>
            <div className="truncate text-xs text-ink-muted">{user.email}</div>
          </div>
        </div>
        <nav aria-label="Account navigation" className="grid gap-2">
          <SidebarLink href="/app/profile" active={path === '/app/profile'}>
            Profile
          </SidebarLink>
          <SidebarLink href="/app/settings" active={path === '/app/settings'}>
            Settings
          </SidebarLink>
          <button
            type="button"
            onClick={onLogout}
            className="flex min-h-10 items-center border-l-2 border-transparent px-4 py-2 text-left text-sm font-normal text-ink-muted transition-colors duration-150 ease-out hover:border-error hover:text-error motion-reduce:transition-none"
          >
            Log-out
          </button>
        </nav>
      </div>
    </aside>
  );
}

function WorkspaceTopbar() {
  return (
    <header className="sticky top-0 z-20 border-b border-line bg-paper">
      <div className="flex min-h-16 items-center justify-end px-8 md:px-12">
        <NotificationsMenu />
      </div>
    </header>
  );
}

function WorkspaceShell({ user, path, onLogout, children }) {
  return (
    <div className="relative z-10 grid min-h-screen grid-cols-4 bg-paper text-ink md:grid-cols-8 xl:grid-cols-12">
      <WorkspaceSidebar user={user} path={path} onLogout={onLogout} />
      <div className="col-span-4 min-w-0 md:col-span-6 xl:col-span-10">
        <WorkspaceTopbar />
        <main id="workspace-main" className="relative z-10">
          {children}
        </main>
      </div>
    </div>
  );
}

function App() {
  const [user, setUser] = useState(undefined);
  const [security, setSecurity] = useState(undefined);
  const [path, setPath] = useState(location.pathname);

  useEffect(() => {
    const onPopState = () => setPath(location.pathname);

    addEventListener('popstate', onPopState);
    get('/app-api/auth/me')
      .then((data) => {
        setUser(data.user);
        setSecurity(data.security);
      })
      .catch(() => {
        setUser(null);
        setSecurity(null);
      });

    return () => removeEventListener('popstate', onPopState);
  }, []);

  const join = path.match(/^\/join\/([^/]+)$/);
  if (join) {
    return (
      <InvitePage
        token={join[1]}
        onAuthenticated={(authenticated) => {
          setUser(authenticated);
          setSecurity({
            email_verified: true,
            two_factor_confirmed: true,
            complete: true,
          });
        }}
      />
    );
  }

  if (user === undefined) {
    return (
      <main className="relative min-h-screen bg-paper px-8 py-12 md:px-12">
        <GridGuides />
        <div className="relative z-10 grid grid-cols-4 gap-6 md:grid-cols-8 xl:grid-cols-12">
          <div className="col-span-4 border-l-2 border-ink pl-4 md:col-span-4 xl:col-span-4">
            <div className="text-xl font-semibold tracking-display">Okatana</div>
            <div className="mt-2 text-sm text-ink-muted" role="status">
              Loading workspace…
            </div>
          </div>
        </div>
      </main>
    );
  }

  if (!user) {
    return (
      <AuthPage
        onAuthenticated={(authenticated) => {
          setUser(authenticated);
          setSecurity({
            email_verified: true,
            two_factor_confirmed: true,
            complete: true,
          });
          navigate('/app');
        }}
      />
    );
  }

  if (security && !security.complete) {
    return (
      <main className="relative min-h-screen bg-paper">
        <GridGuides />
        <div className="relative z-10 px-8 py-8 md:px-12 md:py-12">
          <div className="grid grid-cols-4 gap-6 md:grid-cols-8 xl:grid-cols-12">
            <section className="col-span-4 border-t-2 border-ink pt-6 md:col-span-6 xl:col-span-7">
              <AccountSecuritySetup
                initialSecurity={security}
                email={user.email}
                heading="Finish securing your account"
                onComplete={(data) => {
                  setUser(data.user);
                  setSecurity(data.security);
                  navigate('/app');
                }}
              />
            </section>
          </div>
        </div>
      </main>
    );
  }

  const organization = path.match(/^\/app\/organizations\/([^/]+)$/);
  const project = path.match(/^\/app\/projects\/([^/]+)(?:\/(\d+))?$/);
  const documentEdit = path.match(/^\/app\/documents\/([^/]+)\/edit$/);
  const documentView = path.match(/^\/app\/documents\/([^/]+)$/);

  const logout = async () => {
    await post('/app-api/auth/logout', {});
    setUser(null);
    setSecurity(null);
    navigate('/');
  };

  let content;
  if (path === '/app') {
    content = <DashboardPage user={user} />;
  } else if (path === '/app/projects') {
    content = <ProjectsPage />;
  } else if (path === '/app/documents' || path === '/app/documents/') {
    content = <DocumentsPage />;
  } else if (path === '/app/documents/new') {
    content = <DocumentEditorPage />;
  } else if (documentEdit) {
    content = <DocumentEditorPage id={documentEdit[1]} />;
  } else if (documentView) {
    content = <DocumentViewPage id={documentView[1]} />;
  } else if (path === '/app/profile') {
    content = <UserProfilePage user={user} />;
  } else if (path === '/app/settings') {
    content = <UserSettingsPage user={user} onUserUpdated={setUser} />;
  } else if (organization) {
    content = <OrganizationPage id={organization[1]} />;
  } else if (project) {
    content = (
      <ProjectPage id={project[1]} initialTicketNumber={project[2] ? Number(project[2]) : null} />
    );
  } else {
    content = <DashboardPage user={user} />;
  }

  return (
    <div className="min-h-screen bg-paper text-ink">
      <GridGuides />
      <WorkspaceShell user={user} path={path} onLogout={logout}>
        {content}
      </WorkspaceShell>
    </div>
  );
}

createRoot(document.getElementById('app')).render(
  <>
    <App />
    <ToastViewport />
  </>,
);
