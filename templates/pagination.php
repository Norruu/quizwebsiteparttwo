<?php
/**
 * Pagination Template
 * Usage: include this file and pass $pagination (array) and $baseUrl.
 * - $pagination: expects ['current_page', 'total_pages', 'has_prev', 'has_next'] (see paginate() helper)
 * - $baseUrl: the URL path without ?page=
 * - $queryVars (array, optional): Other query parameters to maintain.
 */
if (!isset($pagination) || $pagination['total_pages'] <= 1) return;

$queryVars = $queryVars ?? [];
function pageUrl($page, $baseUrl, $queryVars) {
    $queryVars['page'] = $page;
    return $baseUrl . '?' . http_build_query($queryVars);
}
?>

<div class="flex items-center justify-center gap-2 my-6">
    <?php if ($pagination['has_prev']): ?>
        <a href="<?= e(pageUrl($pagination['current_page']-1, $baseUrl, $queryVars)) ?>"
            class="px-4 py-2 bg-gray-100 rounded-lg hover:bg-gray-200">← Prev</a>
    <?php endif; ?>

    <span class="px-4 py-2 text-gray-600">
        Page <?= $pagination['current_page'] ?> of <?= $pagination['total_pages'] ?>
    </span>

    <?php if ($pagination['has_next']): ?>
        <a href="<?= e(pageUrl($pagination['current_page']+1, $baseUrl, $queryVars)) ?>"
            class="px-4 py-2 bg-gray-100 rounded-lg hover:bg-gray-200">Next →</a>
    <?php endif; ?>
</div>