// A plain JavaScript module on purpose: the admin's TypeScript configuration emits CommonJS, which turns a
// dynamic import() into a plain require() and keeps the whole assistant in the page bundle. Babel keeps it
// as an import(), so webpack splits it into a chunk fetched on first use.
//
// The two modules below are aliases made by encore/ibexa.config.js: the assistant itself when the npm packages it needs are in the
// project, an empty stand-in (and "not available") when they are not, so that the admin builds without them.
import chatAvailable from 'masilia-ai-chat-status';

export { chatAvailable };
export const loadChatApp = () => import(/* webpackChunkName: "masilia-ai-chat" */ 'masilia-ai-chat-app');
