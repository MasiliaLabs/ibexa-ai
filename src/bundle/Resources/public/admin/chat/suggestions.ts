import { AuiConfig, Suggestions } from '@assistant-ui/react';

// Starting points shown while the conversation is empty. On a content page the first ones are about that page.
const PAGE = [
    { title: 'Summarize this page', label: 'what it says and how it is built', prompt: 'Summarize the page I have open: what it says and which blocks it has.' },
    { title: 'Review this page', label: 'empty or missing fields', prompt: 'Review the page I have open and tell me about empty or missing fields.' },
    { title: 'Check its translations', label: 'which languages exist', prompt: 'Which languages does the page I have open exist in?' },
];

const SITE = [
    { title: 'Site structure', label: 'sites and pages', prompt: 'Show me the structure of the site.' },
    { title: 'Missing translations', label: 'pages without French', prompt: 'Which pages have no French translation?' },
    { title: 'Hidden content', label: 'not visible to visitors', prompt: 'Which content is hidden from visitors?' },
];

export function createSuggestionsConfig(onContentPage: boolean) {
    return AuiConfig({ suggestions: Suggestions(onContentPage ? [...PAGE, SITE[0]] : [...SITE, { title: 'What can you do?', label: 'in this admin', prompt: 'What can you help me with here?' }]) });
}
