import { createTranslator } from '@masilia-admin-kit/i18n';

/** The labels inside the design system's components on this page (the page's own texts are written in English where they are used). */
export const DEFAULTS: Readonly<Record<string, string>> = {
    'ids.dropdown.placeholder': 'Select',
    'ids.dropdown.search.placeholder': 'Search…',
    'ids.clear_btn.label': 'Clear',
    'ibexa.expander.label.collapse': 'Hide',
    'ibexa.expander.label.expand': 'Show',
    'ibexa.chip.delete-btn.label': 'Remove',
};

export const t = createTranslator(DEFAULTS, 'masilia_ibexa_ai');
