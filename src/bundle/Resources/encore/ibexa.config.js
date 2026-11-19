const path = require('path');

module.exports = (Encore) => {
    // The assistant is loaded on demand (a lazy chunk, chat/loader.js). Its file name carries a hash of its content, so that a browser
    // never pairs a cached copy of an older build with the scripts of the page (a missing module, a launcher that does not change).
    // Entries keep their names: only the on-demand chunks are renamed.
    Encore.addPlugin({
        apply(compiler) {
            compiler.options.output.chunkFilename = '[name].[contenthash:8].js';
        },
    });

    Encore.addEntry('ibexa-admin-ui-ai-settings-react-js', [
        path.resolve(__dirname, '../public/admin/settings/index.tsx'),
    ]);

    Encore.addEntry('ibexa-admin-ui-ai-settings-react-css', [
        path.resolve(__dirname, '../public/admin/settings/settings.scss'),
    ]);

    Encore.addEntry('ibexa-admin-ui-ai-capabilities-react-js', [
        path.resolve(__dirname, '../public/admin/capabilities/index.tsx'),
    ]);

    Encore.addEntry('ibexa-admin-ui-ai-capabilities-react-css', [
        path.resolve(__dirname, '../public/admin/capabilities/capabilities.scss'),
    ]);
};
