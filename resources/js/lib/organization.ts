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

export const organizationTabs = [
  'projects',
  'members',
  'audit',
  'integrations',
  'data',
  'settings',
];

export const projectMatches = (project: any, query: string) =>
  [project.name, project.key, project.id, project.description].some((value) =>
    String(value || '')
      .toLowerCase()
      .includes(query),
  );

export const memberMatches = (member: any, query: string) =>
  [member.name, member.email, member.role].some((value) =>
    String(value || '')
      .toLowerCase()
      .includes(query),
  );

export const webhookMatches = (hook: any, query: string) =>
  [
    hook.name,
    hook.url,
    hook.project?.name,
    hook.is_active ? 'active' : 'revoked',
    ...(hook.events || []),
  ].some((value) =>
    String(value || '')
      .toLowerCase()
      .includes(query),
  );

export const credentialMatches = (credential: any, query: string) => {
  const status = credential.revoked_at
    ? 'revoked'
    : credential.expires_at && new Date(credential.expires_at) < new Date()
      ? 'expired'
      : 'active';
  return [credential.name, credential.public_id, status, ...(credential.scopes || [])].some(
    (value) =>
      String(value || '')
        .toLowerCase()
        .includes(query),
  );
};

export function requestedOrganizationTab() {
  const value = new URLSearchParams(location.search).get('tab');
  return value && organizationTabs.includes(value) ? value : 'projects';
}
