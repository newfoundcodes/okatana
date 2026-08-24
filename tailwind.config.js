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

/** @type {import('tailwindcss').Config} */
export default {
  content: ['./resources/**/*.blade.php', './resources/**/*.js', './resources/**/*.jsx'],
  theme: {
    fontWeight: {
      normal: '400',
      medium: '500',
      semibold: '600',
    },
    extend: {
      colors: {
        ink: '#111111',
        'ink-muted': '#595959',
        paper: '#FFFFFF',
        surface: '#F4F4F4',
        line: '#DEDEDE',
        'line-strong': '#B0B0B0',
        accent: '#D7263D',
        success: '#2E7D32',
        warning: '#B26A00',
        error: '#C62828',
      },
      fontFamily: {
        // prettier-ignore
        sans: ['"Suisse Intl"', 'Inter', '-apple-system', '"Helvetica Neue"', 'Arial', 'sans-serif'],
      },
      fontSize: {
        xs: ['0.6875rem', { lineHeight: '1.4' }],
        sm: ['0.75rem', { lineHeight: '1.4' }],
        base: ['0.875rem', { lineHeight: '1.5' }],
        md: ['1rem', { lineHeight: '1.5' }],
        lg: ['1.25rem', { lineHeight: '1.2' }],
        xl: ['1.5rem', { lineHeight: '1.2' }],
        '2xl': ['2rem', { lineHeight: '1.1' }],
        '3xl': ['3rem', { lineHeight: '1.1' }],
        '4xl': ['4rem', { lineHeight: '1.05' }],
      },
      borderRadius: {
        none: '0px',
        DEFAULT: '2px',
        sm: '2px',
      },
      boxShadow: {
        none: 'none',
        DEFAULT: '1px 1px 0 rgba(17,17,17,0.22)',
      },
      letterSpacing: {
        display: '-0.02em',
      },
      transitionDuration: {
        150: '150ms',
      },
    },
  },
  plugins: [],
};
