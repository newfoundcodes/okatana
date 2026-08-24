<?php

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

declare(strict_types=1);

$publicPath = getcwd();
$requestUri = $_SERVER['REQUEST_URI'];
$uri = urldecode(parse_url($requestUri, PHP_URL_PATH) ?? '');

if ($uri === '/docs') {
    $query = parse_url($requestUri, PHP_URL_QUERY);
    $location = '/docs/'.(is_string($query) && $query !== '' ? '?'.$query : '');

    header('Location: '.$location, true, 308);

    return true;
}

if ($uri !== '/' && file_exists($publicPath.$uri)) {
    return false;
}

$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $publicPath.'/index.php';
$_SERVER['PHP_SELF'] = '/index.php';

require_once $publicPath.'/index.php';
