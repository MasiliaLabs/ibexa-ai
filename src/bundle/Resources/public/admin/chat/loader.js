// A plain JavaScript module on purpose: the admin's TypeScript configuration emits CommonJS, which turns a
// dynamic import() into a plain require() and keeps the whole assistant in the page bundle. Babel keeps it
// as an import(), so webpack splits it into a chunk fetched on first use.
export const loadChatApp = () => import(/* webpackChunkName: "masilia-ai-chat" */ './ChatApp');
