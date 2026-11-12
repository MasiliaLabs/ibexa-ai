const path = require('path');

module.exports = (Encore) => {
    Encore.addEntry('ibexa-admin-ui-ai-settings-react-js', [
        path.resolve(__dirname, '../public/admin/settings/index.tsx'),
    ]);

    Encore.addEntry('ibexa-admin-ui-ai-settings-react-css', [
        path.resolve(__dirname, '../public/admin/settings/settings.scss'),
    ]);
};
