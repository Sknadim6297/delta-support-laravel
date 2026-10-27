<x-ui.toolbar class="tree-toolbar" label="Genealogy controls">
    <button type="button" data-tree-action="expand">
        <svg class="ds-ico" viewBox="0 0 24 24" aria-hidden="true"><path d="m7 8 5-5 5 5"/><path d="M12 3v10"/><path d="m7 16 5 5 5-5"/><path d="M12 21V11"/></svg>
        <span>Expand All</span>
    </button>
    <button type="button" data-tree-action="collapse">
        <svg class="ds-ico" viewBox="0 0 24 24" aria-hidden="true"><path d="m7 10 5 5 5-5"/><path d="m7 14 5-5 5 5"/></svg>
        <span>Collapse All</span>
    </button>
    <button type="button" data-tree-action="zoom-out" aria-label="Zoom Out">
        <svg class="ds-ico" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14"/></svg>
        <span>Zoom Out</span>
    </button>
    <button type="button" data-tree-action="zoom-in" aria-label="Zoom In">
        <svg class="ds-ico" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
        <span>Zoom In</span>
    </button>
    <button type="button" data-tree-action="fit">
        <svg class="ds-ico" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 3H5a2 2 0 0 0-2 2v3"/><path d="M21 8V5a2 2 0 0 0-2-2h-3"/><path d="M3 16v3a2 2 0 0 0 2 2h3"/><path d="M16 21h3a2 2 0 0 0 2-2v-3"/></svg>
        <span>Fit Tree</span>
    </button>
    <button type="button" data-tree-action="reset">
        <svg class="ds-ico" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
        <span>Reset View</span>
    </button>
    <span class="tree-zoom" data-tree-zoom aria-live="polite">100%</span>
</x-ui.toolbar>
