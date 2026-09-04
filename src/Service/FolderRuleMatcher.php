<?php

/**
 * WT Image Resize and Convert folder-rule matcher.
 *
 * @package       WT Image Resize and Convert
 * @subpackage    plg_media-action_wtimageresizeconvert
 * @author     WebTolk
 * @copyright  Copyright (c) 2026 WebTolk. All rights reserved.
 * @license       GNU General Public License version 3 or later.
 * @link          https://web-tolk.ru
 */

namespace Webtolk\Plugin\MediaAction\WtImageResizeConvert\Service;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Selects a processing profile for a local Joomla Media folder.
 *
 * @since  1.0.0
 */
final class FolderRuleMatcher
{
    /**
     * Resolves a site-root-relative folder from a Joomla Media save item.
     *
     * @param   object  $item  Media save item.
     *
     * @return  string|null
     *
     * @since   1.0.0
     */
    public function resolveLocalFolder(object $item): ?string
    {
        $adapter = $item->adapter ?? null;
        $path    = $item->path ?? '';

        if (!\is_string($adapter) || !str_starts_with($adapter, 'local-') || !\is_string($path)) {
            return null;
        }

        $account = substr($adapter, \strlen('local-'));

        if ($account === '') {
            return null;
        }

        return $this->normalizePath($account . '/' . $path);
    }

    /**
     * Selects a profile identifier from ordered folder rules.
     *
     * @param   mixed        $rules       Folder-rule subform values.
     * @param   string|null  $folderPath  Local site-root-relative folder.
     *
     * @return  string|null
     *
     * @since   1.0.0
     */
    public function selectProfileId(mixed $rules, ?string $folderPath): ?string
    {
        $folderPath = \is_string($folderPath) ? $this->normalizePath($folderPath) : null;

        if ($folderPath === null) {
            return null;
        }

        $winnerPath      = null;
        $winnerProfileId = null;
        $seenPaths       = [];

        foreach ($this->normalizeRows($rules) as $rule) {
            $profileId = isset($rule['profile_id']) && \is_scalar($rule['profile_id'])
                ? trim((string) $rule['profile_id'])
                : '';

            if ($profileId === '') {
                continue;
            }

            $paths = isset($rule['paths']) && \is_scalar($rule['paths']) ? (string) $rule['paths'] : '';

            foreach (preg_split('/\R/u', $paths) ?: [] as $path) {
                $rulePath = $this->normalizePath($path);

                if ($rulePath === null || isset($seenPaths[$rulePath])) {
                    continue;
                }

                $seenPaths[$rulePath] = true;

                if (
                    ($folderPath !== $rulePath && !str_starts_with($folderPath, $rulePath . '/'))
                    || ($winnerPath !== null && \strlen($rulePath) <= \strlen($winnerPath))
                ) {
                    continue;
                }

                $winnerPath      = $rulePath;
                $winnerProfileId = $profileId;
            }
        }

        return $winnerProfileId;
    }

    /**
     * Normalizes a site-root-relative folder path or rejects an unsafe one.
     *
     * @param   string  $path  Candidate path.
     *
     * @return  string|null
     *
     * @since   1.0.0
     */
    public function normalizePath(string $path): ?string
    {
        if (
            str_contains($path, "\x00")
            || preg_match('/^[a-z][a-z0-9+.-]*:/i', $path)
            || preg_match('/^[a-z]:/i', $path)
        ) {
            return null;
        }

        $segments = [];

        foreach (preg_split('#/+#', trim(str_replace('\\', '/', $path), " \t\n\r\x00\x0B/")) ?: [] as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                return null;
            }

            $segments[] = $segment;
        }

        return $segments === [] ? null : implode('/', $segments);
    }

    /**
     * Normalizes Joomla subform values into ordered rows.
     *
     * @param   mixed  $rows  Subform values.
     *
     * @return  array<int, array<string, mixed>>
     *
     * @since   1.0.0
     */
    private function normalizeRows(mixed $rows): array
    {
        if (\is_object($rows) && method_exists($rows, 'toArray')) {
            $rows = $rows->toArray();
        }

        if (\is_object($rows)) {
            $rows = (array) $rows;
        }

        if (\is_string($rows)) {
            $rows = json_decode($rows, true);
        }

        if (!\is_array($rows)) {
            return [];
        }

        $normalized = [];

        foreach ($rows as $row) {
            if (\is_object($row) && method_exists($row, 'toArray')) {
                $row = $row->toArray();
            }

            if (\is_object($row)) {
                $row = (array) $row;
            }

            if (\is_array($row)) {
                $normalized[] = $row;
            }
        }

        return $normalized;
    }
}
