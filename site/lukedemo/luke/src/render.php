<?php
function project_render_board(array $board, array $query = []): string {
    $board = project_normalise_board($board);
    project_use_settings($board);
    $boardVersion = project_browser_version($board, $query);
    $projects = $board['projects'];
    $sections = $board['sections'];
    $view = (string)($query['view'] ?? 'tasks');
    $sectionFilter = (string)($query['section_filter'] ?? '');
    if ($sectionFilter === '__add_section') {
        $sectionFilter = '';
    }
    $knownSections = project_section_slugs($board);
    if ($sectionFilter !== '' && !in_array($sectionFilter, $knownSections, true)) {
        $sectionFilter = '';
    }
    $projectFilter = (string)($query['project_filter'] ?? ($sectionFilter !== '' ? project_first_project_slug($board, $sectionFilter) : ''));
    if ($projectFilter === '__add_project') {
        $projectFilter = '';
    }
    if ($projectFilter !== '' && $projectFilter !== '__none') {
        $allowedProjects = $sectionFilter === ''
            ? project_project_slugs($board)
            : project_project_slugs(['projects' => array_values(array_filter($projects, fn($project) => ($project['section'] ?? '') === $sectionFilter))]);
        if (!in_array($projectFilter, $allowedProjects, true)) {
            $projectFilter = '';
        }
    }
    $taskQ = trim((string)($query['item_q'] ?? $query['task_q'] ?? ''));
    $taskStatus = $query['item_status'] ?? $query['task_status'] ?? 'active';
    $taskStatusQuery = project_item_status_filter_query_value($taskStatus);
    $taskPriority = (string)($query['item_priority'] ?? $query['task_priority'] ?? '');
    $taskSort = (string)($query['item_sort'] ?? $query['task_sort'] ?? 'order');
    $activeItemId = project_active_item_id($query);
    $justEditedItemId = trim((string)($query['just_edited_item_id'] ?? ''));
    $selectedSection = null;
    foreach ($sections as $section) {
        if (($section['slug'] ?? '') === $sectionFilter) {
            $selectedSection = $section;
            break;
        }
    }
    $selectedProject = null;
    foreach ($projects as $project) {
        if (($project['slug'] ?? '') === $projectFilter) {
            $selectedProject = $project;
            break;
        }
    }
    $projectLines = [];
    if ($selectedProject) {
        $projectLines[] = '<p><strong>' . h((string)($selectedProject['status'] ?? 'Active')) . '</strong></p>';
        foreach (['now', 'next'] as $field) {
            $value = trim((string)($selectedProject[$field] ?? ''));
            if ($value !== '') {
                $projectLines[] = '<p>' . h($value) . '</p>';
            }
        }
        $blocked = trim((string)($selectedProject['blocked'] ?? ''));
        $projectLines[] = '<p>' . h($blocked !== '' ? $blocked : 'Nothing blocked') . '</p>';
        $projectLinks = project_render_found_links([
            'title' => (string)($selectedProject['name'] ?? ''),
            'notes' => implode("
", [
                (string)($selectedProject['now'] ?? ''),
                (string)($selectedProject['next'] ?? ''),
                (string)($selectedProject['blocked'] ?? ''),
            ]),
        ]);
        if ($projectLinks !== '') {
            $projectLines[] = $projectLinks;
        }
    }
    if (!$selectedSection) {
        $selectedSection = ['summary' => 'Showing all areas.'];
    }
    if (!$selectedProject) {
        $projectLines = [$projectFilter === '__none' ? '<p>Showing items without a focus.</p>' : '<p>Showing all focuses.</p>'];
    }
    $sectionNames = [];
    foreach ($sections as $section) { $sectionNames[(string)($section['slug'] ?? '')] = (string)($section['name'] ?? ''); }
    $projectNames = [];
    foreach ($projects as $project) { $projectNames[(string)($project['slug'] ?? '')] = (string)($project['name'] ?? ''); }
    $contextProjectLabel = $projectFilter === '__none' ? 'No focus' : ($projectNames[$projectFilter] ?? $projectFilter);
    $contextLabel = $sectionFilter === '' && $projectFilter === '' ? 'Everything' : trim(($sectionNames[$sectionFilter] ?? 'Everything') . ($projectFilter !== '' ? ' · ' . $contextProjectLabel : ''));
    $contextQuery = array_filter(['section_filter' => $sectionFilter, 'project_filter' => $projectFilter], fn($value) => $value !== '');
    $scopeContextQuery = array_filter([
        'section_filter' => $sectionFilter,
        'project_filter' => $projectFilter,
        'task_q' => $taskQ,
        'task_priority' => $taskPriority,
        'task_status' => $taskStatusQuery,
        'task_sort' => $taskSort !== 'order' ? $taskSort : '',
    ], fn($value) => $value !== '');
    $fullContextQuery = array_filter([
        'section_filter' => $sectionFilter,
        'project_filter' => $projectFilter,
        'task_q' => $taskQ,
        'task_priority' => $taskPriority,
        'task_status' => $taskStatusQuery,
        'task_sort' => $taskSort !== 'order' ? $taskSort : '',
    ], fn($value) => $value !== '');
    $contextUrl = $fullContextQuery ? '?' . http_build_query($fullContextQuery) : '';
    $addItemSectionFilter = $sectionFilter !== '' ? $sectionFilter : project_first_section_slug($board);
    $addItemProjectFilter = '';
    if ($projectFilter !== '' && $projectFilter !== '__none') {
        foreach ($projects as $project) {
            if (($project['slug'] ?? '') === $projectFilter && ($project['section'] ?? '') === $addItemSectionFilter) {
                $addItemProjectFilter = $projectFilter;
                break;
            }
        }
    }
    if ($addItemProjectFilter === '') {
        $addItemProjectFilter = project_first_project_slug($board, $addItemSectionFilter);
    }
    $returnContextInputs = '<input type="hidden" name="return_section" value="' . h($sectionFilter) . '"><input type="hidden" name="return_project" value="' . h($projectFilter) . '"><input type="hidden" name="task_q" value="' . h($taskQ) . '"><input type="hidden" name="task_priority" value="' . h($taskPriority) . '"><input type="hidden" name="task_status" value="' . h($taskStatusQuery) . '"><input type="hidden" name="task_sort" value="' . h($taskSort) . '">' . ($justEditedItemId !== '' ? '<input type="hidden" name="just_edited_item_id" value="' . h($justEditedItemId) . '">' : '');
    $sectionAddUrl = '?' . http_build_query(['view' => 'add_section'] + $fullContextQuery);
    $sectionEditUrl = '?' . http_build_query(['view' => 'edit_section'] + $fullContextQuery);
    $projectAddUrl = '?' . http_build_query(['view' => 'add_project'] + $fullContextQuery);
    $projectEditUrl = '?' . http_build_query(['view' => 'edit_project'] + $fullContextQuery);
    $cancelUrl = './' . $contextUrl;
    $visibleTodos = project_filter_and_sort_items($board['items'], $sectionFilter, $projectFilter, $taskQ, $taskStatus, $taskPriority, $taskSort, $activeItemId, $justEditedItemId);
    $statusLabels = [
        'active' => 'Active items',
        'inactive' => 'Inactive items',
        'recent' => 'Recent changes',
        'agent-active' => 'Agent active',
    ];
    foreach (PROJECT_TODO_STATUSES as $status) {
        $statusLabels[$status] = project_item_status_label($status);
    }
    $statusLabels['Bin'] = 'Bin';
    $statusFilterValues = project_effective_item_status_filters($taskStatus);
    $recentFilterActive = in_array('recent', $statusFilterValues, true);
    $recentItemCounts = project_recent_item_counts();
    $recentDefaultCount = project_recent_item_default_count();
    if ($statusFilterValues === []) {
        $itemsFilterParts = ['All items'];
    } elseif (count(array_diff($statusFilterValues, ['Open', 'Do', 'Ready', 'agent-active'])) === 0 && count(array_intersect(['Open', 'Do', 'Ready'], $statusFilterValues)) === 3 && in_array('agent-active', $statusFilterValues, true)) {
        $itemsFilterParts = ['Active items + Agent active'];
    } elseif (count($statusFilterValues) === 1) {
        $statusLabel = $statusLabels[$statusFilterValues[0]] ?? $statusFilterValues[0];
        $itemsFilterParts = [$statusFilterValues[0] === 'recent' || $statusFilterValues[0] === 'agent-active' || str_ends_with($statusLabel, 'items') ? $statusLabel : $statusLabel . ' items'];
    } else {
        $itemsFilterParts = [implode(' + ', array_map(fn($status) => $statusLabels[$status] ?? $status, $statusFilterValues)) . ' items'];
    }
    if ($taskPriority !== '') {
        $itemsFilterParts[] = $taskPriority === '__blank' ? 'blank priority' : $taskPriority;
    }
    if ($taskQ !== '') {
        $itemsFilterParts[] = 'matching “' . $taskQ . '”';
    }
    if ($taskSort !== 'order') {
        $sortLabels = [
            'item_number' => 'item number',
            'updated' => 'recently updated first',
            'priority' => 'priority order',
        ];
        $itemsFilterParts[] = 'sorted by ' . ($sortLabels[$taskSort] ?? $taskSort);
    }
    $itemsFilterLabel = implode(' · ', $itemsFilterParts);
    $itemsFilterQueryKeys = ['task_q', 'task_priority', 'task_status', 'task_sort', 'item_q', 'item_priority', 'item_status', 'item_sort'];
    $itemsFilterJustApplied = false;
    foreach ($itemsFilterQueryKeys as $filterQueryKey) {
        if (array_key_exists($filterQueryKey, $query)) {
            $itemsFilterJustApplied = true;
            break;
        }
    }
    $allItemsParams = array_filter([
        'section_filter' => $sectionFilter,
        'project_filter' => $projectFilter,
    ], fn($value) => $value !== '');
    $allItemsParams['task_status'] = '';
    $allItemsUrl = '?' . http_build_query($allItemsParams);
    $chipUrl = function (string $status) use ($sectionFilter, $projectFilter, $taskQ, $taskPriority, $taskSort): string {
        $params = array_filter([
            'section_filter' => $sectionFilter,
            'project_filter' => $projectFilter,
            'task_q' => $taskQ,
            'task_priority' => $taskPriority,
            'task_sort' => $taskSort !== 'order' ? $taskSort : '',
        ], fn($value) => $value !== '');
        $params['task_status'] = $status;
        return '?' . http_build_query($params);
    };

    ob_start();
    include __DIR__ . '/views/page-shell.php';
    return ob_get_clean();
}
