const path = require('path');

// The npm packages the assistant imports. Composer cannot install npm packages, so a new project does not have them: without them
// the build of the whole admin fails ("contains a reference to the file @assistant-ui/react"). The assistant is then left out of the
// build (no launcher in the admin) and the rest of the admin builds; `yarn add` them and build again to have the assistant.
const CHAT_PACKAGES = ['@ag-ui/client', '@assistant-ui/react', '@assistant-ui/react-ag-ui', '@assistant-ui/react-markdown', 'remark-gfm'];

// Whether the project has a package, resolved from the project (where Encore runs) and not from this bundle.
const projectHas = (name) => {
    try {
        require.resolve(name, { paths: [process.cwd()] });

        return true;
    } catch (error) {
        return false;
    }
};

module.exports = (Encore) => {
    const missing = CHAT_PACKAGES.filter((name) => !projectHas(name));
    const chat = path.resolve(__dirname, '../public/admin/chat');
    Encore.addAliases({
        'masilia-ai-chat-app': path.join(chat, missing.length === 0 ? 'ChatApp.tsx' : 'ChatUnavailable.js'),
        'masilia-ai-chat-status': path.join(chat, missing.length === 0 ? 'available.js' : 'unavailable.js'),
    });
    if (missing.length > 0) {
        // eslint-disable-next-line no-console
        console.warn(`[masilia/ibexa-ai] The AI chat is not built: the project does not have ${missing.join(', ')}. Run "yarn add ${missing.join(' ')}" and build the admin again to have it.`);
    }

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
