import React from 'react';
import AiSettingsDashboard from '../components/ai-settings/AiSettingsDashboard.jsx';
import '../scss/_ai-settings-dashboard.scss';

function mountAiSettings() {
    const container = document.getElementById('ai-settings-react-root');
    // React 19: the root API comes from Ibexa's global ReactDOMClient (react-dom-load), not from react-dom.
    if (container && window.ReactDOMClient) {
        window.ReactDOMClient.createRoot(container).render(<AiSettingsDashboard />);
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mountAiSettings);
} else {
    mountAiSettings();
}
