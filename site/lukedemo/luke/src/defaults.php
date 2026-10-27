<?php
function project_slug(string $text): string {
    $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $text), '-'));
    return $slug !== '' ? $slug : 'item';
}

function project_default_board(): array {
    if (project_demo_mode()) {
        return project_demo_seed_board();
    }
    $now = project_now();
    return [
        'updated_at' => $now,
        'sections' => [
            [
                'slug' => 'bsm-section',
                'name' => 'BSM section',
                'summary' => 'BibleStudyMan family work: sites, translation source, tooling, apps, and sync jobs.',
                'updated_at' => $now,
            ],
            [
                'slug' => 'real-father-xmas',
                'name' => 'Real Father Xmas',
                'summary' => 'Real Father Christmas family work.',
                'updated_at' => $now,
            ],
            [
                'slug' => 'the-umbrella',
                'name' => 'The Umbrella',
                'summary' => 'Shared communications, notes, recordings, and general capture.',
                'updated_at' => $now,
            ],
        ],
        'projects' => [
            ['slug' => 'bsm', 'section' => 'bsm-section', 'name' => 'BSM', 'summary' => 'Hope/develop preview work and public-site alignment. Keep search, rendering, and deployment behaviour aligned with BSMT.', 'updated_at' => $now],
            ['slug' => 'bsmt', 'section' => 'bsm-section', 'name' => 'BSMT', 'summary' => 'Universal target truth for decisions and public-surface expectations. Record decisions before wider implementation.', 'updated_at' => $now],
            ['slug' => 'bl', 'section' => 'bsm-section', 'name' => 'BL', 'summary' => 'Legacy/source comparison data. Use carefully as source evidence, not as universal target truth.', 'updated_at' => $now],
            ['slug' => 'bhu', 'section' => 'bsm-section', 'name' => 'BHU', 'summary' => 'Raw USFM editing and queue workflow. Continue small verified workflow improvements.', 'updated_at' => $now],
            ['slug' => 'rfx-invoicing', 'section' => 'real-father-xmas', 'name' => 'RFX Invoicing', 'summary' => '', 'updated_at' => $now],
            ['slug' => 'communication', 'section' => 'the-umbrella', 'name' => 'Communication', 'summary' => 'Voice notes and shared communications.', 'updated_at' => $now],
        ],
        'items' => [
            ['id' => 1, 'section' => 'bsm-section', 'project' => 'bsm', 'priority' => 'Must', 'status' => 'Done', 'title' => 'Make large search limits optional on BSM Hope preview', 'notes' => 'Default is limited; users can disable it for full broad searches.', 'updated_at' => $now],
            ['id' => 2, 'section' => 'bsm-section', 'project' => 'bsmt', 'priority' => 'Must', 'status' => 'Done', 'title' => 'Record shared public-surface expectations', 'notes' => 'Search history, highlight style, rendering, and search-limit rules captured as target truth.', 'updated_at' => $now],
            ['id' => 3, 'section' => 'bsm-section', 'project' => 'bhu', 'priority' => 'Should', 'status' => 'Done', 'title' => 'Load completed-verses SQL as a queue', 'notes' => 'Completed-verses SQL can be loaded directly as a queue.', 'updated_at' => $now],
        ],
    ];
}

function project_section_slugs(array $board): array {
    return array_map(fn($section) => (string)($section['slug'] ?? ''), $board['sections'] ?? []);
}

function project_project_slugs(array $board): array {
    return array_map(fn($project) => (string)($project['slug'] ?? ''), $board['projects'] ?? []);
}

function project_first_section_slug(array $board): string {
    return (string)($board['sections'][0]['slug'] ?? 'bsm-section');
}

function project_first_project_slug(array $board, string $section = ''): string {
    foreach ($board['projects'] ?? [] as $project) {
        if ($section === '' || ($project['section'] ?? '') === $section) {
            return (string)($project['slug'] ?? '');
        }
    }
    return (string)($board['projects'][0]['slug'] ?? '');
}

function project_default_project_for_section(string $sectionSlug, string $now = ''): array {
    $now = $now !== '' ? $now : project_now();
    return [
        'slug' => project_slug($sectionSlug . '-general'),
        'section' => $sectionSlug,
        'name' => 'General',
        'summary' => '',
        'updated_at' => $now,
    ];
}

function project_merge_default_entries(array $board, array $default): array {
    if (project_demo_mode()) {
        return $board; // demo boards never gain the real default areas
    }
    $sectionSlugs = project_section_slugs($board);
    foreach ($default['sections'] as $section) {
        if (!in_array((string)$section['slug'], $sectionSlugs, true)) {
            $board['sections'][] = $section;
            $sectionSlugs[] = (string)$section['slug'];
        }
    }

    $projectSlugs = project_project_slugs($board);
    foreach ($default['projects'] as $project) {
        if (!in_array((string)$project['slug'], $projectSlugs, true)) {
            $board['projects'][] = $project;
            $projectSlugs[] = (string)$project['slug'];
        }
    }
    return $board;
}

function project_normalise_board(array $board): array {
    $default = project_default_board();
    $board['sections'] = array_values($board['sections'] ?? $default['sections']);
    $board['projects'] = array_values($board['projects'] ?? $default['projects']);
    $items = $board['items'] ?? $board['todos'] ?? [];
    $board['items'] = array_values($items);
    // Keep a mirrored legacy key for old scripts/forms while items is the primary app key.
    $board['todos'] = $board['items'];
    $board['updated_at'] = $board['updated_at'] ?? project_now();

    if (!$board['sections']) {
        $board['sections'] = $default['sections'];
    }
    if (!$board['projects']) {
        $board['projects'] = $default['projects'];
    }
    $board = project_merge_default_entries($board, $default);

    foreach ($board['sections'] as &$section) {
        if (($section['slug'] ?? '') === 'rfx-section') {
            $section['slug'] = 'real-father-xmas';
            $section['name'] = 'Real Father Xmas';
        }
    }
    unset($section);
    foreach ($board['projects'] as &$project) {
        // Focus status, Now, Next and Blocked were folded into one Description.
        if (array_intersect(['status', 'now', 'next', 'blocked'], array_keys($project))) {
            $parts = [];
            foreach (['summary', 'now', 'next', 'blocked'] as $field) {
                $text = trim((string)($project[$field] ?? ''));
                if ($text !== '' && $text !== 'Nothing blocked') { $parts[] = $text; }
            }
            $project['summary'] = implode("\n", $parts);
            unset($project['status'], $project['now'], $project['next'], $project['blocked']);
        }
        if (($project['section'] ?? '') === 'rfx-section') {
            $project['section'] = 'real-father-xmas';
        }
        if (($project['slug'] ?? '') === 'rfx-general') {
            $project['slug'] = 'rfx-invoicing';
            $project['name'] = 'RFX Invoicing';
            $project['section'] = 'real-father-xmas';
        }
        if (($project['slug'] ?? '') === 'the-umbrella-general' && ($project['section'] ?? '') === 'the-umbrella' && ($project['name'] ?? '') === 'Communication') {
            $project['slug'] = 'communication';
        }
    }
    unset($project);
    foreach ($board['items'] as &$todo) {
        if (($todo['section'] ?? '') === 'rfx-section') {
            $todo['section'] = 'real-father-xmas';
        }
        if (($todo['project'] ?? '') === 'rfx-general') {
            $todo['project'] = 'rfx-invoicing';
        }
        if (($todo['project'] ?? '') === 'the-umbrella-general') {
            $todo['project'] = 'communication';
        }
        if (($todo['status'] ?? '') === 'Doing') {
            $todo['status'] = 'Do';
        }
    }
    unset($todo);

    $dedupedSections = [];
    $seenSections = [];
    foreach ($board['sections'] as $section) {
        $slug = (string)($section['slug'] ?? '');
        if ($slug !== '' && !in_array($slug, $seenSections, true)) {
            $dedupedSections[] = $section;
            $seenSections[] = $slug;
        }
    }
    $board['sections'] = $dedupedSections;

    $dedupedProjects = [];
    $seenProjects = [];
    $removedGeneralProjects = [];
    foreach ($board['projects'] as $project) {
        $slug = (string)($project['slug'] ?? '');
        $name = trim((string)($project['name'] ?? ''));
        if ($slug !== '' && strcasecmp($name, 'General') === 0) {
            $removedGeneralProjects[] = $slug;
            continue;
        }
        if ($slug !== '' && !in_array($slug, $seenProjects, true)) {
            $dedupedProjects[] = $project;
            $seenProjects[] = $slug;
        }
    }
    $board['projects'] = $dedupedProjects;

    $validSections = project_section_slugs($board);
    $fallbackSection = project_first_section_slug($board);
    foreach ($board['projects'] as &$project) {
        if (!in_array((string)($project['section'] ?? ''), $validSections, true)) {
            $project['section'] = $fallbackSection;
        }
    }
    unset($project);

    $validProjects = project_project_slugs($board);
    $projectSections = [];
    foreach ($board['projects'] as $project) {
        $projectSections[(string)($project['slug'] ?? '')] = (string)($project['section'] ?? '');
    }
    $nextOrderByProject = [];
    foreach ($board['items'] as &$todo) {
        if (!in_array((string)($todo['section'] ?? ''), $validSections, true)) {
            $todo['section'] = $fallbackSection;
        }
        $itemProject = (string)($todo['project'] ?? '');
        if ($itemProject !== '' && (in_array($itemProject, $removedGeneralProjects, true) || !in_array($itemProject, $validProjects, true))) {
            $todo['project'] = '';
            $itemProject = '';
        }
        $projectSection = $projectSections[$itemProject] ?? '';
        if ($projectSection !== '' && $projectSection !== (string)($todo['section'] ?? '')) {
            $todo['project'] = '';
            $itemProject = '';
        }
        $projectKey = (string)($todo['section'] ?? '') . '|' . $itemProject;
        $nextOrderByProject[$projectKey] = $nextOrderByProject[$projectKey] ?? 1;
        $order = (int)($todo['order'] ?? 0);
        if ($order < 1) {
            $order = $nextOrderByProject[$projectKey];
        }
        $todo['order'] = max(1, min(50, $order));
        $nextOrderByProject[$projectKey] = max($nextOrderByProject[$projectKey], $todo['order'] + 1);
    }
    unset($todo);

    // Re-sync after normalisation/migration so legacy readers see the repaired items too.
    $board['todos'] = $board['items'];

    return $board;
}

function project_load_board(): array {
    $path = project_data_path();
    if (!file_exists($path)) {
        $board = project_demo_mode() ? project_demo_seed_board() : project_default_board();
        project_save_board($board);
        return $board;
    }
    if (!is_readable($path)) {
        throw new RuntimeException('LUKE state exists but is not readable. Refusing to overwrite it with defaults.');
    }
    $json = file_get_contents($path);
    if ($json === false) {
        throw new RuntimeException('LUKE state could not be read. Refusing to overwrite it with defaults.');
    }
    $board = json_decode($json, true);
    if (!is_array($board)) {
        throw new RuntimeException('LUKE state is invalid JSON. Refusing to overwrite it with defaults.');
    }
    return project_normalise_board($board);
}

function project_board_version(array $board): string {
    return sha1(json_encode(project_normalise_board($board), JSON_UNESCAPED_SLASHES));
}

function project_browser_version(array $board, array $query = []): string {
    return sha1(json_encode([
        'board' => project_normalise_board($board),
        'active_item_id' => project_active_item_id($query),
    ], JSON_UNESCAPED_SLASHES));
}

function project_save_board(array $board): void {
    $path = project_data_path();
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0770, true);
    }
    $existingMode = is_file($path) ? (@fileperms($path) & 0777) : 0660;
    $existingOwner = is_file($path) ? @fileowner($path) : false;
    $existingGroup = is_file($path) ? @filegroup($path) : false;
    $previous = is_file($path) ? json_decode((string)@file_get_contents($path), true) : null;
    $previousItems = is_array($previous) ? ($previous['items'] ?? $previous['todos'] ?? []) : [];
    project_create_backup('', project_change_summary($previousItems, $board['items'] ?? []));
    $board = project_purge_expired_binned_items($board);
    $board['updated_at'] = project_now();
    $tmp = $path . '.tmp.' . getmypid();
    file_put_contents($tmp, json_encode(project_normalise_board($board), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n", LOCK_EX);
    @chmod($tmp, $existingMode ?: 0660);
    if ($existingOwner !== false) {
        @chown($tmp, $existingOwner);
    }
    if ($existingGroup !== false) {
        @chgrp($tmp, $existingGroup);
    }
    rename($tmp, $path);
    @chmod($path, $existingMode ?: 0660);
}
